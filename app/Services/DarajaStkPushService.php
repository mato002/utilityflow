<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Organization;
use App\Models\PaymentTransaction;
use App\Models\Tenant;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class DarajaStkPushService
{
    public function __construct(private readonly PaymentService $payments, private readonly AuditLogger $audit) {}

    public function isConfigured(?Organization $organization = null): bool
    {
        return $organization !== null
            && filled($organization->daraja_consumer_key)
            && filled($organization->daraja_consumer_secret)
            && filled($organization->daraja_shortcode)
            && filled($organization->daraja_passkey)
            && filled($organization->daraja_environment)
            && filled(config('services.daraja.callback_url'))
            && strlen((string) config('services.daraja.callback_token')) >= 32
            && str_starts_with((string) config('services.daraja.callback_url'), 'https://');
    }

    public function initiate(Tenant $tenant, Invoice $invoice, string $phone, int $amount): PaymentTransaction
    {
        $organization = Organization::find($tenant->organization_id);
        if (! $this->isConfigured($organization)) {
            throw ValidationException::withMessages(['payment' => 'Online M-Pesa is not configured yet. Contact your property manager.']);
        }

        if (PaymentTransaction::query()->where('organization_id', $tenant->organization_id)->where('tenant_id', $tenant->id)->where('provider', 'daraja')->where('status', 'pending')->where('created_at', '>=', now()->subMinutes(5))->exists()) {
            throw ValidationException::withMessages(['payment' => 'An M-Pesa prompt is already pending for your account. Wait a few minutes before starting another.']);
        }
        $accessToken = $this->accessToken($organization);
        $reference = 'DARAJA-'.Str::upper(Str::random(24));
        $payment = PaymentTransaction::create([
            'organization_id' => $tenant->organization_id,
            'tenant_id' => $tenant->id,
            'reference' => $reference,
            'method' => 'mpesa',
            'provider' => 'daraja',
            'amount' => number_format($amount, 2, '.', ''),
            'unallocated_amount' => number_format($amount, 2, '.', ''),
            'currency' => 'KES',
            'status' => 'pending',
            'provider_payload' => ['invoice_id' => $invoice->id],
        ]);
        $this->audit->record($tenant->organization_id, null, 'payment.daraja_initiated', $payment, null, ['invoice_id' => $invoice->id, 'amount' => number_format($amount, 2, '.', '')]);

        try {
            $timestamp = now()->format('YmdHis');
            $shortcode = (string) $organization->daraja_shortcode;
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(20)
                ->post($this->baseUrl($organization).'/mpesa/stkpush/v1/processrequest', [
                    'BusinessShortCode' => $shortcode,
                    'Password' => base64_encode($shortcode.$organization->daraja_passkey.$timestamp),
                    'Timestamp' => $timestamp,
                    'TransactionType' => $organization->daraja_transaction_type ?: 'CustomerPayBillOnline',
                    'Amount' => $amount,
                    'PartyA' => $phone,
                    'PartyB' => $shortcode,
                    'PhoneNumber' => $phone,
                    'CallBackURL' => rtrim((string) config('services.daraja.callback_url'), '/').'/'.config('services.daraja.callback_token'),
                    'AccountReference' => Str::limit($invoice->invoice_number, 12, ''),
                    'TransactionDesc' => 'Utility bill',
                ])->throw();

            $body = $response->json();
            if (($body['ResponseCode'] ?? null) !== '0' || empty($body['CheckoutRequestID'])) {
                $payment->update(['status' => 'failed', 'provider_payload' => ['invoice_id' => $invoice->id, 'error' => Str::limit($body['ResponseDescription'] ?? $body['errorMessage'] ?? 'Safaricom did not accept the payment request.', 500)]]);

                throw ValidationException::withMessages(['payment' => $body['ResponseDescription'] ?? $body['errorMessage'] ?? 'Safaricom did not accept the payment request.']);
            }

            $payment->update([
                'provider_reference' => $body['CheckoutRequestID'],
                'provider_payload' => [
                    'invoice_id' => $invoice->id,
                    'merchant_request_id' => $body['MerchantRequestID'] ?? null,
                    'checkout_request_id' => $body['CheckoutRequestID'],
                    'customer_message' => $body['CustomerMessage'] ?? null,
                ],
            ]);

            return $payment->refresh();
        } catch (ConnectionException $exception) {
            $payment->update(['provider_payload' => ['invoice_id' => $invoice->id, 'initiation_status' => 'unknown']]);
            report($exception);

            throw ValidationException::withMessages(['payment' => 'We could not confirm whether Safaricom received the request. Check your phone and payment history before trying again.']);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $payment->update(['status' => 'failed', 'provider_payload' => ['invoice_id' => $invoice->id, 'error' => Str::limit($exception->getMessage(), 500)]]);
            report($exception);

            throw ValidationException::withMessages(['payment' => 'M-Pesa could not start this payment. Check the phone number and try again.']);
        }
    }

    /** @param array<string, mixed> $callback */
    public function handleCallback(array $callback): void
    {
        $result = data_get($callback, 'Body.stkCallback');
        if (! is_array($result) || empty($result['CheckoutRequestID'])) {
            return;
        }

        $payment = PaymentTransaction::query()->where('provider', 'daraja')->where('provider_reference', $result['CheckoutRequestID'])->first();
        if (! $payment || $payment->status !== 'pending') {
            return;
        }

        if ((int) ($result['ResultCode'] ?? -1) !== 0) {
            $payment->update(['status' => 'failed', 'provider_payload' => array_merge($payment->provider_payload ?? [], ['result_description' => $result['ResultDesc'] ?? 'Payment was not completed.'])]);
            $this->audit->record($payment->organization_id, null, 'payment.daraja_failed', $payment, ['status' => 'pending'], ['status' => 'failed']);

            return;
        }

        $metadata = collect(data_get($result, 'CallbackMetadata.Item', []))
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item['Name']))
            ->mapWithKeys(fn (array $item): array => [$item['Name'] => $item['Value'] ?? null]);
        $amount = (int) $metadata->get('Amount', 0);
        $receipt = (string) $metadata->get('MpesaReceiptNumber', '');
        if ($amount !== (int) round((float) $payment->amount) || $receipt === '') {
            $payment->update(['status' => 'review', 'provider_payload' => array_merge($payment->provider_payload ?? [], ['review_reason' => 'Safaricom callback amount or receipt did not match the requested payment.', 'callback_amount' => $amount, 'callback_receipt' => $receipt ?: null])]);

            return;
        }

        $this->payments->confirmDaraja($payment, $receipt);
    }

    private function accessToken(Organization $organization): string
    {
        $key = (string) $organization->daraja_consumer_key;
        $secret = (string) $organization->daraja_consumer_secret;
        $cacheKey = 'daraja-token-'.$organization->id.'-'.hash('sha256', $key);

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($key, $secret, $organization): string {
            $response = Http::withBasicAuth($key, $secret)
                ->acceptJson()->timeout(15)
                ->get($this->baseUrl($organization).'/oauth/v1/generate', ['grant_type' => 'client_credentials'])
                ->throw();
            $token = $response->json('access_token');
            if (! is_string($token) || $token === '') {
                throw new \RuntimeException('Safaricom did not return an access token.');
            }

            return $token;
        });
    }

    private function baseUrl(Organization $organization): string
    {
        return $organization->daraja_environment === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }
}
