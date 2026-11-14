@extends('layouts.workspace')
@php($title = 'Water readings')
@php($pageTitle = 'Water readings')

@section('workspace-content')
    <div class="water-app" data-water-app>

        <section class="water-app-intro">
            <div><p class="eyebrow">METER COLLECTION</p><h1>Water readings</h1><p>Choose a property, enter each meter’s new value, and review the calculated usage before submitting.</p></div>
            <button type="button" class="water-install-button" data-install-water-app hidden>Install app</button>
        </section>

        @if(session('success'))<div class="flash flash-success" role="status">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="flash flash-error" role="alert">{{ $errors->first() }}</div>@endif
        <div id="offline-reading-status" class="flash flash-info" role="status" aria-live="polite" hidden></div>

        <section class="water-property-picker panel">
            <form method="GET" action="{{ route('water.readings') }}" class="water-property-form">
                <label for="water-property"><span>1. Choose property</span><select id="water-property" name="property_id" required><option value="">Select a property</option>@foreach($properties as $property)<option value="{{ $property->id }}" @selected($selectedProperty?->id === $property->id)>{{ $property->name }}</option>@endforeach</select></label>
                <button type="submit" class="button button-primary">Show units</button>
            </form>
            <p>Only active water meters assigned to this property are available here.</p>
        </section>

        @if($selectedProperty)
            @php($activeMeterCount = $unitAssignments->sum(fn ($metersForUnit): int => $metersForUnit->count()) + $sharedAssignments->count())
            <section class="water-collection-summary" aria-live="polite">
                <article><span>PROPERTY</span><strong>{{ $selectedProperty->name }}</strong></article>
                <article><span>UNITS</span><strong>{{ $propertyUnits->count() }}</strong></article>
                <article><span>WATER METERS</span><strong>{{ $activeMeterCount }}</strong></article>
                <article><span>ENTERED</span><strong data-water-entered>0 / {{ $activeMeterCount }}</strong></article>
                <article><span>CALCULATED USAGE</span><strong><span data-water-total>0.0000</span> m³</strong></article>
            </section>

            @if($canCreateReadings)
            <form method="POST" enctype="multipart/form-data" action="{{ route('readings.store') }}" class="water-entry-form" data-reading-form data-water-entry-form>
                @csrf
                <input type="hidden" name="water_reader" value="1">
                <input type="hidden" name="property_id" value="{{ $selectedProperty->id }}">
                <section class="panel water-units-panel">
                    <div class="water-panel-heading"><div><p class="eyebrow">2. ENTER CURRENT VALUES</p><h2>{{ $selectedProperty->name }} · Units</h2></div><label class="water-date-field"><span>Reading date &amp; time</span><input type="datetime-local" name="reading_at" value="{{ old('reading_at', now()->format('Y-m-d\TH:i')) }}" required></label></div>
                    @if($propertyUnits->isEmpty())
                        <div class="water-empty-state"><h3>No units have been added</h3><p>Add this property's units before collecting individual readings.</p><a href="{{ route('workspace.module', 'spaces') }}">Manage units</a></div>
                    @else
                        <div class="water-unit-list">
                            @foreach($propertyUnits as $unit)
                                @php($assignmentsForUnit = $unitAssignments->get($unit->id, collect()))
                                <article class="water-unit-card">
                                    <div class="water-unit-heading"><div><h3>{{ $unit->unit_number }}</h3><p>{{ $unit->unit_type ?: 'Unit' }}@if($unit->block) · {{ $unit->block->name }}@endif</p></div><span class="water-unit-count">{{ $assignmentsForUnit->count() }} {{ \Illuminate\Support\Str::plural('water meter', $assignmentsForUnit->count()) }}</span></div>
                                    @forelse($assignmentsForUnit as $assignment)
                                        @php($meter = $assignment->meter)
                                        @php($previous = (float) ($meter->last_approved_reading ?? $meter->initial_reading))
                                        @php($fieldKey = 'meter_'.$meter->id)
                                        <div class="water-meter-row" data-water-reading-row data-previous="{{ number_format($previous, 4, '.', '') }}" data-multiplier="{{ (float) $meter->multiplier }}" data-rollover="{{ $meter->rollover_value ?? '' }}">
                                            <input type="hidden" name="readings[{{ $fieldKey }}][meter_id]" value="{{ $meter->id }}">
                                            <input type="hidden" name="readings[{{ $fieldKey }}][submission_uuid]" value="{{ old("readings.$fieldKey.submission_uuid", (string) \Illuminate\Support\Str::uuid()) }}">
                                            <div class="water-meter-id"><strong>{{ $meter->meter_number }}</strong><span>Previous <b>{{ number_format($previous, 4) }}</b></span></div>
                                            <label class="water-current-field"><span class="visually-hidden">Current reading for {{ $unit->unit_number }}, meter {{ $meter->meter_number }}</span><input type="number" name="readings[{{ $fieldKey }}][current_reading]" min="0" step="0.0001" inputmode="decimal" placeholder="Current reading" value="{{ old("readings.$fieldKey.current_reading") }}" autocomplete="off" data-water-current></label>
                                            <div class="water-usage-value"><span>Usage</span><strong data-water-usage>—</strong></div>
                                        </div>
                                        @empty
                                            <p class="water-no-meter">No active water meter is linked to this unit. <a href="{{ route('workspace.module', 'meters') }}">Link a meter</a></p>
                                    @endforelse
                                </article>
                            @endforeach
                        </div>
                    @endif

                    @if($sharedAssignments->isNotEmpty())
                        <div class="water-shared-section"><div class="water-panel-heading"><div><p class="eyebrow">PROPERTY METERS</p><h2>Shared water meters</h2></div></div>
                            @foreach($sharedAssignments as $assignment)
                                @php($meter = $assignment->meter)
                                @php($previous = (float) ($meter->last_approved_reading ?? $meter->initial_reading))
                                @php($fieldKey = 'meter_'.$meter->id)
                                <div class="water-meter-row" data-water-reading-row data-previous="{{ number_format($previous, 4, '.', '') }}" data-multiplier="{{ (float) $meter->multiplier }}" data-rollover="{{ $meter->rollover_value ?? '' }}">
                                    <input type="hidden" name="readings[{{ $fieldKey }}][meter_id]" value="{{ $meter->id }}"><input type="hidden" name="readings[{{ $fieldKey }}][submission_uuid]" value="{{ old("readings.$fieldKey.submission_uuid", (string) \Illuminate\Support\Str::uuid()) }}">
                                    <div class="water-meter-id"><strong>{{ $meter->meter_number }}</strong><span>Shared / property meter · previous <b>{{ number_format($previous, 4) }}</b></span></div>
                                    <label class="water-current-field"><span class="visually-hidden">Current reading for shared meter {{ $meter->meter_number }}</span><input type="number" name="readings[{{ $fieldKey }}][current_reading]" min="0" step="0.0001" inputmode="decimal" placeholder="Current reading" value="{{ old("readings.$fieldKey.current_reading") }}" autocomplete="off" data-water-current></label>
                                    <div class="water-usage-value"><span>Usage</span><strong data-water-usage>—</strong></div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>

                @if($activeMeterCount > 0)
                    <div class="water-submit-bar"><span>Blank meter fields will be skipped. The server checks previous values again when you submit.</span><button type="submit" class="button button-primary" data-water-submit>Submit readings for review</button></div>
                @endif
            </form>
            @else
                <div class="flash flash-info">You can view the property's water meters, but your account does not have permission to submit readings.</div>
            @endif

            @if($activeMeterCount === 0)
                <div class="water-empty-state panel"><h3>No active water meters linked to this property</h3><p>Link water meters to this property and its units before collecting readings.</p><a class="button button-primary" href="{{ route('workspace.module', 'meters') }}">Manage meters</a></div>
            @endif
        @endif

        @if($canViewReadings && $readings->isNotEmpty())
            <section class="panel water-recent-panel"><div class="water-panel-heading"><div><p class="eyebrow">REVIEW QUEUE</p><h2>Recent readings</h2></div><a href="{{ route('workspace.module', 'readings') }}">Full review list</a></div>
                <div class="table-scroll"><table class="data-table"><thead><tr><th>METER / UNIT</th><th>DATE</th><th>PREVIOUS</th><th>CURRENT</th><th>USAGE</th><th>STATUS</th><th>REVIEW</th></tr></thead><tbody>@foreach($readings as $reading)<tr><td><strong>{{ $reading->meter->meter_number }}</strong><small>{{ $reading->meter->activeAssignment->first()?->unit?->unit_number ?? $reading->meter->activeAssignment->first()?->property?->name ?? 'Meter' }}</small></td><td>{{ $reading->reading_at->timezone($organization->timezone)->format('j M, H:i') }}</td><td>{{ number_format((float) $reading->previous_reading, 4) }}</td><td>{{ number_format((float) $reading->current_reading, 4) }}</td><td>{{ number_format((float) $reading->consumption, 4) }} m³</td><td><span class="status-pill {{ $reading->status === 'approved' ? 'status-active' : ($reading->status === 'rejected' ? 'status-paused' : 'status-neutral') }}"><i></i>{{ str_replace('_', ' ', ucfirst($reading->status)) }}</span></td><td>@if($reading->status === 'pending_approval' && auth()->user()->hasOrganizationPermission($organization, 'readings.approve'))<div class="water-review-actions"><form method="POST" action="{{ route('readings.approve', $reading) }}">@csrf<button class="row-action" type="submit">Approve</button></form><form method="POST" action="{{ route('readings.reject', $reading) }}">@csrf<input type="hidden" name="reason" value="Rejected from water reading review"><button class="row-action row-action-danger" type="submit" data-confirm="Reject this reading?">Reject</button></form></div>@else—@endif</td></tr>@endforeach</tbody></table></div>
                <div class="records-footer"><span>Showing {{ $readings->firstItem() ?? 0 }}–{{ $readings->lastItem() ?? 0 }} of {{ $readings->total() }} readings</span>{{ $readings->links('pagination::simple-tailwind') }}</div>
            </section>
        @endif

    </div>
@endsection
