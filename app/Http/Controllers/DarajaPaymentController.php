<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\DarajaStkPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DarajaPaymentController extends Controller
{
    public function initiate(Request $request, DarajaStkPushService $daraja): RedirectResponse
    {
        $tenantIds = Tenant::query()->where('user_id', $request->user()->id)->where('status', 'active')->pluck('id');
        abort_if($tenantIds->isEmpty(), 403);
        $data = $request->validate([
            'invoice_id' => ['required', 'integer'],
            'amount' => ['required', 'integer', 'min:1'],
            'phone' => ['required', 'string', 'max:20'],
        ]);
        $invoice = Invoice::query()->whereIn('tenant_id', $tenantIds)->whereKey($data['invoice_id'])->whereIn('status', ['issued', 'partial', 'overdue'])->firstOrFail();
        $balance = (int) floor((float) $invoice->total - (float) $invoice->amount_paid - (float) $invoice->credit_total);
        if ($data['amount'] > $balance) {
            throw ValidationException::withMessages(['amount' => 'The payment cannot exceed the outstanding invoice balance.']);
        }

        $phone = preg_replace('/[\s()+-]/', '', $data['phone']);
        if (preg_match('/^0([17]\d{8})$/', $phone, $matches) === 1) {
            $phone = '254'.$matches[1];
        } elseif (preg_match('/^([17]\d{8})$/', $phone, $matches) === 1) {
            $phone = '254'.$matches[1];
        }
        if (preg_match('/^254[17]\d{8}$/', $phone) !== 1) {
            throw ValidationException::withMessages(['phone' => 'Enter a valid Kenyan Safaricom number, such as 0712345678.']);
        }

        $tenant = Tenant::query()->whereIn('id', $tenantIds)->whereKey($invoice->tenant_id)->firstOrFail();
        $payment = $daraja->initiate($tenant, $invoice, $phone, (int) $data['amount']);

        return to_route('tenant.dashboard')->with('success', 'M-Pesa prompt sent. Complete it on your phone; the invoice updates after Safaricom confirms payment.')->with('pending_payment_id', $payment->id);
    }

    public function callback(Request $request, string $token, DarajaStkPushService $daraja): JsonResponse
    {
        $expected = (string) config('services.daraja.callback_token');
        abort_unless($expected !== '' && hash_equals($expected, $token), 404);
        $payload = $request->json()->all();
        DB::transaction(fn () => $daraja->handleCallback($payload));

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }
}
