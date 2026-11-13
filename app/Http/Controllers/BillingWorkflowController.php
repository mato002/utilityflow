<?php

namespace App\Http\Controllers;

use App\Models\BillingCycle;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\Role;
use App\Services\BillingService;
use App\Services\CreditNoteService;
use App\Services\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BillingWorkflowController extends Controller
{
    public function index(Request $request, CurrentOrganization $organizations): View
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization, 403);
        abort_unless($request->user()->hasOrganizationPermission($organization, 'billing.view'), 403);

        return view('workspace.billing', [
            'organization' => $organization,
            'organizations' => $request->user()->organizations()->where('organizations.status', 'active')->orderBy('name')->get(),
            'roleName' => $organization->users()->where('users.id', $request->user()->id)->first()?->pivot?->role_id
                ? Role::find($organization->users()->where('users.id', $request->user()->id)->first()->pivot->role_id)?->name : null,
            'cycles' => BillingCycle::query()->with('property')->where('organization_id', $organization->id)->latest()->paginate(8, ['*'], 'cycles'),
            'invoices' => Invoice::query()->with(['tenant', 'unit', 'items', 'readings.meter', 'creditNotes'])->where('organization_id', $organization->id)->latest()->paginate(12, ['*'], 'invoices'),
            'properties' => Property::query()->where('organization_id', $organization->id)->where('status', 'active')->orderBy('name')->get(),
            'currentModule' => 'billing',
        ]);
    }

    public function storeCycle(Request $request, CurrentOrganization $organizations): RedirectResponse
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization, 403);
        abort_unless($request->user()->hasOrganizationPermission($organization, 'billing.manage'), 403);

        $data = $request->validate([
            'property_id' => ['nullable', 'integer', Rule::exists('properties', 'id')->where('organization_id', $organization->id)],
            'name' => ['required', 'string', 'max:100'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'due_on' => ['nullable', 'date', 'after_or_equal:ends_on'],
        ]);

        BillingCycle::create($data + ['organization_id' => $organization->id, 'created_by' => $request->user()->id, 'status' => 'draft']);

        return to_route('workspace.module', 'billing')->with('success', 'Billing cycle created.');
    }

    public function generate(Request $request, BillingCycle $cycle, CurrentOrganization $organizations, BillingService $billing): RedirectResponse
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization && $cycle->organization_id === $organization->id, 404);
        abort_unless($request->user()->hasOrganizationPermission($organization, 'billing.manage'), 403);

        $invoices = $billing->generate($cycle, $request->user());

        return to_route('workspace.module', 'billing')->with('success', count($invoices).' draft invoices generated. Review each invoice before issuing.');
    }

    public function issue(Request $request, Invoice $invoice, CurrentOrganization $organizations, BillingService $billing): RedirectResponse
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization && $invoice->organization_id === $organization->id, 404);
        abort_unless($request->user()->hasOrganizationPermission($organization, 'billing.issue'), 403);

        $billing->issue($invoice, $request->user());

        return to_route('workspace.module', 'billing')->with('success', 'Invoice issued. Its financial values are now locked.');
    }

    public function credit(Request $request, Invoice $invoice, CurrentOrganization $organizations, CreditNoteService $credits): RedirectResponse
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization && $invoice->organization_id === $organization->id, 404);
        abort_unless($request->user()->hasOrganizationPermission($organization, 'billing.issue'), 403);
        $data = $request->validate(['amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'], 'reason' => ['required', 'string', 'max:500']]);
        $credit = $credits->issue($invoice, (string) $data['amount'], $data['reason'], $request->user());

        return to_route('workspace.module', 'billing')->with('success', 'Credit note '.$credit->credit_note_number.' issued and retained in the invoice history.');
    }
}
