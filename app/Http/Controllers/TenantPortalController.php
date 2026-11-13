<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\MaintenanceTicket;
use App\Models\PaymentTransaction;
use App\Models\Tenant;
use App\Services\DarajaStkPushService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TenantPortalController extends Controller
{
    public function index(Request $request): View
    {
        $tenants = Tenant::query()->with(['organization', 'occupancies.unit.property'])
            ->where('user_id', $request->user()->id)->where('status', 'active')->get();
        abort_if($tenants->isEmpty(), 403, 'No active tenant portal is linked to this account.');

        $tenantIds = $tenants->pluck('id');
        $openInvoices = Invoice::query()->with('organization')
            ->whereIn('tenant_id', $tenantIds)->whereIn('status', ['issued', 'partial', 'overdue'])
            ->whereRaw('total - amount_paid - credit_total > 0')->orderBy('due_on')->get();
        $daraja = app(DarajaStkPushService::class);
        $darajaInvoices = $openInvoices->filter(fn (Invoice $invoice): bool => $daraja->isConfigured($invoice->organization));

        return view('tenant.dashboard', [
            'tenants' => $tenants,
            'invoices' => Invoice::with('organization')->whereIn('tenant_id', $tenantIds)->whereIn('status', ['issued', 'partial', 'overdue', 'paid', 'credited'])->latest('issued_on')->paginate(10),
            'openInvoices' => $openInvoices,
            'payments' => PaymentTransaction::with('organization')->whereIn('tenant_id', $tenantIds)->whereIn('status', ['pending', 'received', 'failed', 'review'])->latest()->limit(15)->get(),
            'darajaInvoices' => $darajaInvoices,
            'darajaConfigured' => $darajaInvoices->isNotEmpty(),
        ]);
    }

    public function reportIssue(Request $request): RedirectResponse
    {
        $tenantIds = Tenant::where('user_id', $request->user()->id)->where('status', 'active')->pluck('id');
        abort_if($tenantIds->isEmpty(), 403);
        $data = $request->validate([
            'tenant_id' => ['required', 'integer', Rule::in($tenantIds->all())],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'max:3000'],
            'category' => ['required', Rule::in(['leak', 'meter_fault', 'electricity_fault', 'other'])],
        ]);
        $tenant = Tenant::with('occupancies.unit')->findOrFail($data['tenant_id']);
        $occupancy = $tenant->occupancies()->where('status', 'active')->latest('move_in_date')->first();
        $unit = $occupancy?->unit;

        MaintenanceTicket::create([
            'organization_id' => $tenant->organization_id,
            'property_id' => $unit?->property_id,
            'unit_id' => $unit?->id,
            'tenant_id' => $tenant->id,
            'title' => $data['title'],
            'description' => $data['description'],
            'category' => $data['category'],
            'priority' => 'normal',
            'status' => 'open',
            'created_by' => $request->user()->id,
        ]);

        return to_route('tenant.dashboard')->with('success', 'Your issue was sent to the property team.');
    }
}
