@extends('layouts.workspace')
@php($title = 'Shared meter allocation')
@php($pageTitle = 'Shared meter allocation')

@section('workspace-content')
    @if(session('success'))<div class="flash flash-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="flash flash-error" role="alert">{{ $errors->first() }}</div>@endif
    <div class="module-heading-row"><div class="module-title-wrap"><span class="module-icon">◫</span><h1>Shared meter allocation</h1></div></div>
    <article class="panel workflow-table-panel">
        <form method="GET" action="{{ route('workspace.allocations') }}" class="report-filter"><label>Billing cycle <select name="cycle" onchange="this.form.submit()">@foreach($cycles as $cycle)<option value="{{ $cycle->id }}" @selected($selectedCycle?->id === $cycle->id)>{{ $cycle->name }} · {{ $cycle->starts_on->format('j M') }}–{{ $cycle->ends_on->format('j M Y') }}</option>@endforeach</select></label></form>
        <div class="panel-heading records-heading"><h2>Readings awaiting allocation</h2></div>
        @if(! $selectedCycle)<div class="records-empty compact-empty"><h3>No billing cycle selected</h3><a class="button button-dark" href="{{ route('workspace.module', 'billing') }}">Billing cycles</a></div>
        @elseif($readings->isEmpty())<div class="records-empty compact-empty"><span class="records-empty-art"><span class="empty-orbit"></span><span class="empty-module-icon">◫</span></span><h3>No readings to allocate</h3></div>
        @else
            <div class="table-scroll"><table class="data-table module-data-table"><thead><tr><th>MAIN METER</th><th>PROPERTY</th><th>READ DATE</th><th>MAIN USAGE</th><th>UNITS</th><th>ACTION</th></tr></thead><tbody>
            @foreach($readings as $reading)
                @php($assignment = $reading->meter->assignments->first(fn ($item) => $item->starts_at->lte($reading->reading_at) && (! $item->ends_at || $item->ends_at->gte($reading->reading_at))))
                @php($property = $assignment?->property)
                <tr><td><span class="table-org-name">{{ $reading->meter->meter_number }}<small>{{ ucfirst($reading->meter->utility_type) }} · {{ ucfirst($reading->meter->category) }}</small></span></td><td>{{ $property?->name ?? 'Unassigned property' }}</td><td>{{ $reading->reading_at->timezone($organization->timezone)->format('j M Y') }}</td><td><strong>{{ number_format((float) $reading->consumption, 4) }}</strong></td><td>{{ $property?->units->where('occupancy_status', 'occupied')->count() ?? 0 }} occupied</td><td><button class="row-action" type="button" data-modal-open="allocation-{{ $reading->id }}">Allocate usage</button></td></tr>
                <dialog class="record-dialog" id="allocation-{{ $reading->id }}"><form class="record-form" method="POST" action="{{ route('workspace.allocations.store') }}">@csrf<input type="hidden" name="billing_cycle_id" value="{{ $selectedCycle->id }}"><input type="hidden" name="meter_reading_id" value="{{ $reading->id }}"><div class="dialog-header"><div><h2>Allocate {{ number_format((float) $reading->consumption, 4) }} units</h2></div><button class="dialog-close" type="button" data-modal-close aria-label="Close">×</button></div>
                    <div class="dialog-fields"><label class="dialog-field dialog-field-wide"><span>Allocation policy <b>*</b></span><select name="policy" required><option value="equal">Equal share of the variance</option><option value="proportional">Proportional to submeter usage</option><option value="occupancy">By occupied unit count</option><option value="floor_area">By recorded floor area</option><option value="custom">Custom weights</option><option value="landlord_absorbs">Landlord absorbs the variance</option></select></label>
                    @if($property)
                        @foreach($property->units->where('occupancy_status', 'occupied') as $unit)
                            <label class="dialog-field"><span>{{ $unit->unit_number }} · custom weight</span><input type="number" min="0" step="0.01" name="weights[{{ $unit->id }}]" value="1"></label>
                        @endforeach
                    @endif
                    </div><div class="dialog-actions"><button class="button button-muted" type="button" data-modal-close>Cancel</button><button class="button button-primary" type="submit">Calculate and save allocation</button></div></form></dialog>
            @endforeach
            </tbody></table></div>
        @endif
    </article>
    <article class="panel workflow-table-panel"><div class="panel-heading records-heading"><h2>Allocation history</h2></div>@if($completedAllocations->isEmpty())<div class="small-empty-state">No allocations</div>@else<div class="table-scroll"><table class="data-table module-data-table"><thead><tr><th>MAIN METER</th><th>UNIT</th><th>TENANT</th><th>POLICY</th><th>SUBMETER</th><th>SHARED VARIANCE</th><th>ALLOCATED</th><th>LANDLORD</th></tr></thead><tbody>@foreach($completedAllocations as $allocation)<tr><td>{{ $allocation->reading->meter->meter_number }}<small>{{ $allocation->reading->reading_at->format('j M Y') }}</small></td><td>{{ $allocation->unit->unit_number }}</td><td>{{ $allocation->tenant?->name ?? 'Vacant' }}</td><td>{{ str_replace('_', ' ', ucfirst($allocation->policy)) }}</td><td>{{ number_format((float) $allocation->submeter_consumption, 4) }}</td><td>{{ number_format((float) $allocation->shared_variance, 4) }}</td><td><strong>{{ number_format((float) $allocation->allocated_consumption, 4) }}</strong></td><td>{{ number_format((float) $allocation->landlord_absorbed, 4) }}</td></tr>@endforeach</tbody></table></div>@endif</article>
@endsection
