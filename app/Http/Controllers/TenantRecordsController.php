<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\PaymentTransaction;
use App\Models\Tenant;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantRecordsController extends Controller
{
    public function usage(Request $request): View
    {
        $tenantIds = Tenant::query()->where('user_id', $request->user()->id)->where('status', 'active')->pluck('id');
        abort_if($tenantIds->isEmpty(), 403, 'No active tenant portal is linked to this account.');
        $readings = Invoice::query()->whereIn('tenant_id', $tenantIds)->whereIn('status', ['issued', 'partial', 'overdue', 'paid', 'credited'])
            ->with('readings.meter')->get()->flatMap(fn (Invoice $invoice) => $invoice->readings)
            ->unique('id')->sortBy('reading_at')->values();
        $months = [];
        foreach (CarbonPeriod::create(now()->startOfMonth()->subMonths(11), '1 month', now()->startOfMonth()) as $month) {
            $key = $month->format('Y-m');
            $months[$key] = ['label' => $month->format('M Y'), 'water' => 0.0, 'electricity' => 0.0];
        }
        foreach ($readings as $reading) {
            $key = $reading->reading_at->format('Y-m');
            if (isset($months[$key], $months[$key][$reading->meter->utility_type])) {
                $months[$key][$reading->meter->utility_type] += (float) $reading->pivot->consumption;
            }
        }

        return view('tenant.usage', ['months' => array_values($months), 'tenants' => Tenant::with('organization')->whereIn('id', $tenantIds)->get()]);
    }

    public function invoice(Request $request, Invoice $invoice): View
    {
        $tenantIds = Tenant::query()->where('user_id', $request->user()->id)->where('status', 'active')->pluck('id');
        abort_unless($tenantIds->contains($invoice->tenant_id), 404);
        $invoice->load(['organization', 'tenant', 'unit.property', 'items', 'readings.meter', 'creditNotes']);

        return view('tenant.invoice', compact('invoice'));
    }

    public function receipt(Request $request, PaymentTransaction $payment): View
    {
        $tenantIds = Tenant::query()->where('user_id', $request->user()->id)->where('status', 'active')->pluck('id');
        abort_unless($payment->status === 'received' && $tenantIds->contains($payment->tenant_id), 404);
        $payment->load(['organization', 'tenant', 'invoices.unit', 'invoices.cycle']);

        return view('tenant.receipt', compact('payment'));
    }
}
