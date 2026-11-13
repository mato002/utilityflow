@if(auth()->user()->hasOrganizationPermission($organization, 'settings.manage') && $plans->isNotEmpty())
    @if($pendingPlanRequest)
        @php($requestDetails = json_decode($pendingPlanRequest->payload ?? '{}', true) ?: [])
        <div class="flash flash-info subscription-pending-request" role="status"><strong>Plan request awaiting payment confirmation</strong><br>{{ $requestDetails['plan_name'] ?? 'Selected plan' }} · {{ ucfirst($requestDetails['interval'] ?? 'monthly') }} · {{ $requestDetails['currency'] ?? '' }} {{ number_format((float) ($requestDetails['amount'] ?? 0), 2) }}<br>Reference: {{ $requestDetails['payment_reference'] ?? 'Not provided' }}</div>
    @else
    <form class="subscription-select-form" method="POST" action="{{ route('subscription.change') }}">
        @csrf
        <label class="dialog-field"><span>Plan</span><select name="plan_id" required>@foreach($plans as $plan)<option value="{{ $plan->id }}" @selected(($subscription?->subscription_plan_id ?? null) === $plan->id)>{{ $plan->name }} · {{ $plan->currency }} {{ number_format((float) $plan->monthly_price, 2) }}/month · {{ $plan->currency }} {{ number_format((float) $plan->annual_price, 2) }}/year</option>@endforeach</select></label>
        <label class="dialog-field"><span>Billing interval</span><select name="interval" required><option value="monthly" @selected(($subscription?->billing_interval ?? 'monthly') === 'monthly')>Monthly</option><option value="annual" @selected(($subscription?->billing_interval ?? 'monthly') === 'annual')>Annual</option></select></label>
        <label class="dialog-field subscription-payment-reference"><span>Payment reference (for paid plans)</span><input name="payment_reference" maxlength="120" placeholder="Bank or mobile money receipt reference"></label>
        <button class="button button-primary" type="submit">Continue with plan <span>→</span></button>
        <p class="subscription-flow-note">Free plans activate immediately. For a paid plan, submit your payment reference and the billing team can confirm it. </p>
    </form>
    @endif
@endif
