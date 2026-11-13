<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\MeterReading;
use App\Models\PaymentTransaction;
use App\Models\Role;
use App\Services\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsWorkspaceController extends Controller
{
    public function index(Request $request, CurrentOrganization $organizations): View
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization, 403);
        abort_unless($request->user()->hasOrganizationPermission($organization, 'reports.view'), 403);
        $start = $request->date('from')?->startOfDay() ?? now()->startOfMonth();
        $end = $request->date('to')?->endOfDay() ?? now()->endOfMonth();

        $consumption = MeterReading::query()->join('meters', 'meters.id', '=', 'meter_readings.meter_id')
            ->where('meter_readings.organization_id', $organization->id)->where('meter_readings.status', 'approved')
            ->whereBetween('meter_readings.reading_at', [$start, $end])
            ->select('meters.utility_type', DB::raw('SUM(meter_readings.consumption) as total_consumption'))
            ->groupBy('meters.utility_type')->pluck('total_consumption', 'meters.utility_type');

        $invoiceTotals = Invoice::where('organization_id', $organization->id)->whereBetween('created_at', [$start, $end])
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total),0) as billed, COALESCE(SUM(amount_paid),0) as collected')->first();
        $paymentTotalsByInvoice = DB::table('payment_allocations')->join('payment_transactions', 'payment_transactions.id', '=', 'payment_allocations.payment_transaction_id')
            ->where('payment_transactions.organization_id', $organization->id)->where('payment_transactions.status', 'received')
            ->select('payment_allocations.invoice_id')->selectRaw('SUM(payment_allocations.amount) as collected')->groupBy('payment_allocations.invoice_id');
        $collectionByPropertyAndCycle = DB::table('invoices')
            ->join('billing_cycles', 'billing_cycles.id', '=', 'invoices.billing_cycle_id')
            ->join('units', 'units.id', '=', 'invoices.unit_id')->join('properties', 'properties.id', '=', 'units.property_id')
            ->leftJoinSub($paymentTotalsByInvoice, 'payment_totals', 'payment_totals.invoice_id', '=', 'invoices.id')
            ->where('invoices.organization_id', $organization->id)->whereBetween('invoices.created_at', [$start, $end])
            ->select('billing_cycles.id as cycle_id', 'billing_cycles.name as cycle_name', 'properties.id as property_id', 'properties.name as property_name')
            ->selectRaw('COUNT(invoices.id) as invoice_count, SUM(invoices.total) as billed, SUM(COALESCE(payment_totals.collected, 0)) as collected, SUM(CASE WHEN invoices.total - invoices.credit_total - COALESCE(payment_totals.collected, 0) > 0 THEN invoices.total - invoices.credit_total - COALESCE(payment_totals.collected, 0) ELSE 0 END) as outstanding')
            ->groupBy('billing_cycles.id', 'billing_cycles.name', 'properties.id', 'properties.name')
            ->orderBy('billing_cycles.name')->orderBy('properties.name')->get();

        return view('workspace.reports', [
            'organization' => $organization,
            'organizations' => $request->user()->organizations()->where('organizations.status', 'active')->orderBy('name')->get(),
            'roleName' => Role::find($organization->users()->where('users.id', $request->user()->id)->first()?->pivot?->role_id)?->name,
            'currentModule' => 'reports',
            'start' => $start,
            'end' => $end,
            'consumption' => $consumption,
            'invoiceTotals' => $invoiceTotals,
            'paymentTotal' => PaymentTransaction::where('organization_id', $organization->id)->where('status', 'received')->whereBetween('received_at', [$start, $end])->sum('amount'),
            'collectionByPropertyAndCycle' => $collectionByPropertyAndCycle,
            'recentActivity' => AuditLog::with('actor')->where('organization_id', $organization->id)->latest()->limit(12)->get(),
        ]);
    }

    public function export(Request $request, CurrentOrganization $organizations): StreamedResponse
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization && $request->user()->hasOrganizationPermission($organization, 'reports.view'), 403);
        $filters = $request->validate([
            'type' => ['required', Rule::in(['readings', 'invoices', 'payments', 'overdue', 'collection_summary'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
        $start = $request->date('from')?->startOfDay() ?? now()->startOfMonth();
        $end = $request->date('to')?->endOfDay() ?? now()->endOfMonth();
        if ($end->lt($start)) {
            throw ValidationException::withMessages(['to' => 'The end date must be on or after the start date.']);
        }
        $type = $filters['type'];
        $filename = 'utilityflow-'.$type.'-'.$start->toDateString().'-'.$end->toDateString().'.csv';

        return response()->streamDownload(function () use ($organization, $start, $end, $type): void {
            $out = fopen('php://output', 'w');
            if ($type === 'readings') {
                fputcsv($out, ['date', 'utility', 'meter', 'previous_reading', 'current_reading', 'consumption', 'status']);
                MeterReading::query()->with('meter')->where('organization_id', $organization->id)->whereBetween('reading_at', [$start, $end])->orderBy('id')->chunk(500, function ($readings) use ($out): void {
                    foreach ($readings as $reading) {
                        $this->writeCsvRow($out, [$reading->reading_at->toDateTimeString(), $reading->meter->utility_type, $reading->meter->meter_number, $reading->previous_reading, $reading->current_reading, $reading->consumption, $reading->status]);
                    }
                });
            } elseif ($type === 'collection_summary') {
                fputcsv($out, ['billing_cycle', 'property', 'invoice_count', 'billed', 'collected', 'outstanding']);
                $paymentTotalsByInvoice = DB::table('payment_allocations')->join('payment_transactions', 'payment_transactions.id', '=', 'payment_allocations.payment_transaction_id')
                    ->where('payment_transactions.organization_id', $organization->id)->where('payment_transactions.status', 'received')
                    ->select('payment_allocations.invoice_id')->selectRaw('SUM(payment_allocations.amount) as collected')->groupBy('payment_allocations.invoice_id');
                DB::table('invoices')->join('billing_cycles', 'billing_cycles.id', '=', 'invoices.billing_cycle_id')
                    ->join('units', 'units.id', '=', 'invoices.unit_id')->join('properties', 'properties.id', '=', 'units.property_id')
                    ->leftJoinSub($paymentTotalsByInvoice, 'payment_totals', 'payment_totals.invoice_id', '=', 'invoices.id')
                    ->where('invoices.organization_id', $organization->id)->whereBetween('invoices.created_at', [$start, $end])
                    ->select('billing_cycles.name as cycle_name', 'properties.name as property_name')
                    ->selectRaw('COUNT(invoices.id) as invoice_count, SUM(invoices.total) as billed, SUM(COALESCE(payment_totals.collected, 0)) as collected, SUM(CASE WHEN invoices.total - invoices.credit_total - COALESCE(payment_totals.collected, 0) > 0 THEN invoices.total - invoices.credit_total - COALESCE(payment_totals.collected, 0) ELSE 0 END) as outstanding')
                    ->groupBy('billing_cycles.id', 'billing_cycles.name', 'properties.id', 'properties.name')->orderBy('billing_cycles.name')->orderBy('properties.name')
                    ->chunk(500, function ($rows) use ($out): void {
                        foreach ($rows as $row) {
                            $billed = (float) $row->billed;
                            $collected = (float) $row->collected;
                            $this->writeCsvRow($out, [$row->cycle_name, $row->property_name, $row->invoice_count, number_format($billed, 2, '.', ''), number_format($collected, 2, '.', ''), number_format((float) $row->outstanding, 2, '.', '')]);
                        }
                    });
            } elseif ($type === 'invoices' || $type === 'overdue') {
                fputcsv($out, ['invoice_number', 'tenant', 'unit', 'issued_on', 'due_on', 'total', 'amount_paid', 'credit_total', 'outstanding', 'status']);
                $query = Invoice::query()->with(['tenant', 'unit'])->where('organization_id', $organization->id);
                if ($type === 'overdue') {
                    $query->whereIn('status', ['issued', 'partial', 'overdue'])->whereNotNull('due_on')->whereDate('due_on', '<', today())
                        ->whereBetween('due_on', [$start->toDateString(), $end->toDateString()]);
                } else {
                    $query->whereBetween('created_at', [$start, $end]);
                }
                $query->orderBy('id')->chunk(500, function ($invoices) use ($out): void {
                    foreach ($invoices as $invoice) {
                        $outstanding = max(0, (float) $invoice->total - (float) $invoice->amount_paid - (float) $invoice->credit_total);
                        $this->writeCsvRow($out, [$invoice->invoice_number, $invoice->tenant?->name, $invoice->unit?->unit_number, $invoice->issued_on?->toDateString(), $invoice->due_on?->toDateString(), $invoice->total, $invoice->amount_paid, $invoice->credit_total, number_format($outstanding, 2, '.', ''), $invoice->status]);
                    }
                });
            } else {
                fputcsv($out, ['reference', 'tenant', 'method', 'provider_reference', 'received_at', 'amount', 'unallocated_credit', 'currency', 'status', 'invoices']);
                PaymentTransaction::query()->with(['tenant', 'invoices'])->where('organization_id', $organization->id)
                    ->whereBetween('received_at', [$start, $end])->orderBy('id')->chunk(250, function ($payments) use ($out): void {
                        foreach ($payments as $payment) {
                            $this->writeCsvRow($out, [$payment->reference, $payment->tenant?->name, $payment->method, $payment->mpesa_receipt ?? $payment->provider_reference, $payment->received_at?->toDateTimeString(), $payment->amount, $payment->unallocated_amount, $payment->currency, $payment->status, $payment->invoices->pluck('invoice_number')->join(', ')]);
                        }
                    });
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @param resource $stream
     * @param  array<int, mixed>  $values
     */
    private function writeCsvRow($stream, array $values): void
    {
        $values = array_map(function (mixed $value): mixed {
            if (! is_string($value) || $value === '') {
                return $value;
            }

            return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
        }, $values);

        fputcsv($stream, $values);
    }
}
