<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\PaymentTransaction;
use App\Models\Role;
use App\Models\Tenant;
use App\Services\CurrentOrganization;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentWorkflowController extends Controller
{
    public function index(Request $request, CurrentOrganization $organizations): View
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization, 403);
        abort_unless($request->user()->hasOrganizationPermission($organization, 'payments.view'), 403);

        return view('workspace.payments', [
            'organization' => $organization,
            'organizations' => $request->user()->organizations()->where('organizations.status', 'active')->orderBy('name')->get(),
            'roleName' => $organization->users()->where('users.id', $request->user()->id)->first()?->pivot?->role_id
                ? Role::find($organization->users()->where('users.id', $request->user()->id)->first()->pivot->role_id)?->name : null,
            'payments' => PaymentTransaction::query()->with(['tenant', 'invoices'])->where('organization_id', $organization->id)->latest('received_at')->paginate(12),
            'tenants' => Tenant::query()->where('organization_id', $organization->id)->where('status', 'active')->orderBy('name')->get(),
            'invoices' => Invoice::query()->where('organization_id', $organization->id)->whereIn('status', ['issued', 'partial', 'overdue'])->whereRaw('amount_paid + credit_total < total')->orderBy('due_on')->get(),
            'currentModule' => 'payments',
        ]);
    }

    public function store(Request $request, CurrentOrganization $organizations, PaymentService $payments): RedirectResponse
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization, 403);
        abort_unless($request->user()->hasOrganizationPermission($organization, 'payments.record'), 403);

        $data = $request->validate([
            'submission_uuid' => ['required', 'uuid'],
            'tenant_id' => ['required', 'integer', Rule::exists('tenants', 'id')->where('organization_id', $organization->id)],
            'reference' => ['required', 'string', 'max:100'],
            'method' => ['required', Rule::in(['bank_transfer', 'cash', 'card', 'mpesa', 'other'])],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'allocations' => ['nullable', 'array'],
            'allocations.*.invoice_id' => ['nullable', 'required_with:allocations.*.amount', 'integer', Rule::exists('invoices', 'id')->where('organization_id', $organization->id)],
            'allocations.*.amount' => ['nullable', 'required_with:allocations.*.invoice_id', 'numeric', 'gt:0', 'decimal:0,2'],
        ]);

        $tenant = Tenant::findOrFail($data['tenant_id']);
        $allocations = array_values(array_filter($data['allocations'] ?? [], fn (array $allocation): bool => filled($allocation['invoice_id'] ?? null) && filled($allocation['amount'] ?? null)));
        $payments->record($organization->id, $tenant, $data['reference'], $data['method'], (string) $data['amount'], $allocations, $request->user(), submissionUuid: $data['submission_uuid']);

        return to_route('workspace.module', 'payments')->with('success', 'Payment recorded. Any unallocated balance is available as tenant credit.');
    }

    public function applyCredit(Request $request, PaymentTransaction $payment, CurrentOrganization $organizations, PaymentService $payments): RedirectResponse
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization && $payment->organization_id === $organization->id, 404);
        abort_unless($request->user()->hasOrganizationPermission($organization, 'payments.record'), 403);
        $data = $request->validate([
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.invoice_id' => ['required', 'integer', Rule::exists('invoices', 'id')->where('organization_id', $organization->id)],
            'allocations.*.amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
        ]);
        $payments->applyUnallocated($payment, $data['allocations'], $request->user());

        return to_route('workspace.module', 'payments')->with('success', 'Tenant credit applied to the selected invoice(s).');
    }

    public function reverse(Request $request, PaymentTransaction $payment, CurrentOrganization $organizations, PaymentService $payments): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $organization = $organizations->resolve($request->user());
        abort_unless($organization && $payment->organization_id === $organization->id, 404);
        abort_unless($request->user()->hasOrganizationPermission($organization, 'payments.reverse'), 403);

        $payments->reverse($payment, $request->user(), $data['reason']);

        return to_route('workspace.module', 'payments')->with('success', 'Payment reversed and invoice balances restored.');
    }

    public function reconcileDaraja(Request $request, PaymentTransaction $payment, CurrentOrganization $organizations, PaymentService $payments): RedirectResponse
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization && $payment->organization_id === $organization->id, 404);
        abort_unless($request->user()->hasOrganizationPermission($organization, 'payments.record'), 403);
        $data = $request->validate([
            'mpesa_receipt' => ['required', 'string', 'max:32'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
        ]);

        $payments->resolveDarajaReview($payment, $data['mpesa_receipt'], (string) $data['amount'], $request->user());

        return to_route('workspace.module', 'payments')->with('success', 'M-Pesa receipt reconciled and applied to the outstanding utility balance.');
    }
}
