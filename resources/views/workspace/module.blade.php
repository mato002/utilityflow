@extends('layouts.workspace')
@php($title = $module['title'])
@php($pageTitle = $module['title'])

@section('workspace-content')
    @if(session('success'))<div class="flash flash-success" role="status">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="flash flash-error" role="alert">{{ session('error') }}</div>@endif
    <div class="module-heading-row">
        <div class="module-title-wrap"><span class="module-icon">{{ $module['icon'] }}</span><h1>{{ $module['title'] }}</h1></div>
        <div class="heading-actions">
            @if($module['key'] === 'meters')
                <a class="button button-muted" href="{{ route('workspace.module', 'readings') }}">Record reading <span>→</span></a>
            @endif
            <button class="button button-primary" type="button" data-modal-open="record-modal">＋ <span>Add {{ $module['record'] }}</span></button>
        </div>
    </div>
    @if(in_array($module['key'], ['spaces', 'occupants'], true))<div class="unit-hierarchy-note"><strong>Property</strong> → <strong>Unit label</strong> → <strong>Tenant</strong><span> · Example: Classic Residence → Unit A1 → Jane Doe</span></div>@endif
    <section class="module-workspace-grid module-workspace-grid-single">
        <article class="panel records-panel">
            <div class="panel-heading records-heading"><div><h2>{{ $module['title'] }}</h2></div><span class="records-count">{{ $records->total() }}</span></div>
            <form class="records-toolbar" method="GET" action="{{ route('workspace.module', $module['key']) }}"><label class="search-placeholder"><span>⌕</span><input name="search" value="{{ request('search') }}" placeholder="Search {{ $module['plural'] }}" aria-label="Search {{ $module['plural'] }}"></label><button class="filter-button" type="submit">Search</button></form>
            @if($records->isEmpty())
                <div class="records-empty"><span class="records-empty-art"><span class="empty-orbit"></span><span class="empty-module-icon">{{ $module['icon'] }}</span><span class="empty-plus">＋</span></span><h3>{{ $module['empty_title'] }}</h3><button class="button button-dark" type="button" data-modal-open="record-modal">Add {{ $module['record'] }} <span>→</span></button></div>
            @else
                <div class="table-scroll module-table-scroll"><table class="data-table module-data-table"><thead><tr>@if($module['key'] === 'spaces')<th>UNIT LABEL</th><th>PROPERTY</th><th>TYPE</th><th>BLOCK / FLOOR</th><th>OCCUPANCY</th>@elseif($module['key'] === 'occupants')<th>TENANT</th><th>PROPERTY → UNIT</th><th>EMAIL</th><th>PHONE</th><th>STATUS</th>@else<th>RECORD</th><th>REFERENCE</th><th>DETAILS</th><th>STATUS</th><th>AMOUNT</th><th>DATE</th>@endif<th><span class="sr-only">ACTIONS</span></th></tr></thead><tbody>
                    @foreach($records as $record)
                        <tr>
                            @if($module['key'] === 'spaces')
                                <td><span class="table-org-icon">{{ $module['icon'] }}</span><span class="table-org-name">{{ $record->title }}</span></td>
                                <td><strong>{{ $record->reference ?: '—' }}</strong></td>
                                <td>{{ data_get($record->values, 'unit_type') ?: '—' }}</td>
                                <td>{{ $record->summary ?: '—' }}</td>
                                <td><span class="status-pill status-neutral"><i></i>{{ $record->status }}</span></td>
                            @elseif($module['key'] === 'occupants')
                                <td><span class="table-org-icon">{{ $module['icon'] }}</span><span class="table-org-name">{{ $record->title }}</span></td>
                                <td><strong>{{ $record->summary ?: 'Unit not assigned' }}</strong></td>
                                <td>{{ $record->reference ?: '—' }}</td>
                                <td>{{ data_get($record->values, 'phone') ?: '—' }}</td>
                                <td><span class="status-pill status-neutral"><i></i>{{ $record->status }}</span></td>
                            @else
                                <td><span class="table-org-icon">{{ $module['icon'] }}</span><span class="table-org-name">{{ $record->title }}<small>Added {{ $record->created_at->format('j M Y') }}</small></span></td>
                                <td>{{ $record->reference ?: '—' }}</td>
                                <td class="record-summary">{{ $record->summary ?: collect($record->values ?? [])->map(fn ($value, $key) => str_replace('_', ' ', ucfirst($key)).': '.$value)->filter()->take(2)->implode(' · ') ?: '—' }}</td>
                                <td><span class="status-pill status-neutral"><i></i>{{ $record->status }}</span></td>
                                <td>{{ $record->amount !== null ? number_format((float) $record->amount, 2) : '—' }}</td>
                                <td>{{ $record->recorded_on?->format('j M Y') ?? '—' }}</td>
                            @endif
                            <td class="record-actions">
                                @if(($module['key'] !== 'billing' || strtolower($record->status) === 'draft') && ($module['key'] !== 'payments' || strtolower($record->status) === 'pending reconciliation') && ($module['key'] !== 'readings' || in_array(strtolower($record->status), ['draft', 'rejected'], true)))
                                    <button class="row-action" type="button" aria-label="Edit {{ $record->title }}" data-modal-open="record-modal" data-record='{{ $record->toJson() }}' data-update-url="{{ route('workspace.records.update', [$module['key'], $record]) }}">Edit</button>
                                @endif
                                @if($module['key'] === 'meters' && strtolower($record->status) === 'active')<button class="row-action row-action-danger" type="button" data-modal-open="replace-meter-{{ $record->id }}">Replace</button>@endif
                                @if($module['key'] === 'occupants' && !empty(data_get($record->values, 'email')) && auth()->user()->hasOrganizationPermission($organization, 'tenants.manage'))<form method="POST" action="{{ route('tenants.invite', $record->id) }}">@csrf<button class="row-action" type="submit">Invite portal</button></form>@endif
                                @if($module['key'] !== 'payments' && ($module['key'] !== 'billing' || strtolower($record->status) === 'draft') && ($module['key'] !== 'readings' || in_array(strtolower($record->status), ['draft', 'rejected'], true)))
                                    <form method="POST" action="{{ route('workspace.records.destroy', [$module['key'], $record]) }}" data-confirm="Delete this record? This cannot be undone.">@csrf @method('DELETE')<button class="row-action row-action-danger" type="submit">Delete</button></form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody></table></div>
            @endif
            <div class="records-footer"><span>Showing {{ $records->firstItem() ?? 0 }}–{{ $records->lastItem() ?? 0 }} of {{ $records->total() }} {{ $module['plural'] }}</span>{{ $records->links('pagination::simple-tailwind') }}</div>
        </article>

    </section>

    @if($module['key'] === 'meters')
        @if(session('device_token'))<section class="flash flash-success device-token-panel"><strong>Copy this device token now.</strong><code>{{ session('device_token') }}</code><small>It is stored only as a hash and cannot be retrieved again.</small></section>@endif
        <section class="panel org-table-panel device-panel"><div class="panel-heading"><div><h3>Devices</h3></div><button class="button button-muted" type="button" data-modal-open="device-modal">＋ Register device</button></div>
            @if($devices->isEmpty())<div class="small-empty-state">No smart meter devices registered.</div>@else<div class="table-scroll"><table class="data-table module-data-table"><thead><tr><th>DEVICE</th><th>METER</th><th>PROVIDER</th><th>LAST SEEN</th><th>STATUS</th></tr></thead><tbody>@foreach($devices as $device)<tr><td>{{ $device->external_id }}</td><td>{{ $device->meter->meter_number }}</td><td>{{ $device->provider }}</td><td>{{ $device->last_seen_at?->timezone($organization->timezone)->format('j M Y H:i') ?? 'Never' }}</td><td>{{ ucfirst($device->status) }}</td></tr>@endforeach</tbody></table></div>@endif
        </section>
        <section class="panel org-table-panel device-panel"><div class="panel-heading"><div><h3>Pending device readings</h3></div></div>
            @if($deviceReadings->isEmpty())<div class="small-empty-state">No device readings are waiting for review.</div>@else<div class="table-scroll"><table class="data-table module-data-table"><thead><tr><th>METER</th><th>EVENT</th><th>VALUE</th><th>MEASURED AT</th><th>STATE</th><th>DECISION</th></tr></thead><tbody>@foreach($deviceReadings as $deviceReading)<tr><td>{{ $deviceReading->device->meter->meter_number }}</td><td>{{ $deviceReading->event_id }}</td><td>{{ number_format((float) $deviceReading->value, 2) }}</td><td>{{ $deviceReading->measured_at->timezone($organization->timezone)->format('j M Y H:i') }}</td><td>{{ str_replace('_', ' ', ucfirst($deviceReading->status)) }}</td><td><form class="device-review-form" method="POST" action="{{ route('device-readings.reconcile', $deviceReading) }}">@csrf <input type="hidden" name="decision" value="accept"><input name="notes" placeholder="Review note"><button class="row-action" type="submit">Reconcile</button></form><form method="POST" action="{{ route('device-readings.reconcile', $deviceReading) }}">@csrf<input type="hidden" name="decision" value="reject"><button class="row-action row-action-danger" type="submit">Reject</button></form></td></tr>@endforeach</tbody></table></div>@endif
        </section>
        <dialog class="record-dialog" id="device-modal"><form class="record-form" method="POST" action="{{ route('devices.store') }}">@csrf<div class="dialog-header"><div><span class="eyebrow">DEVICE PROVISIONING</span><h2>Register smart meter device</h2><p>A unique bearer token will be generated. Store it securely on the device.</p></div><button class="dialog-close" type="button" data-modal-close>×</button></div><div class="dialog-fields"><label class="dialog-field dialog-field-wide"><span>Active meter</span><select name="meter_id" required>@foreach($meters as $meter)<option value="{{ $meter->id }}">{{ $meter->meter_number }} · {{ ucfirst($meter->utility_type) }}</option>@endforeach</select></label><label class="dialog-field"><span>Provider</span><input name="provider" required maxlength="80" placeholder="Provider code"></label><label class="dialog-field"><span>Device ID</span><input name="external_id" required maxlength="120"></label></div><div class="dialog-actions"><button class="button button-muted" type="button" data-modal-close>Cancel</button><button class="button button-primary" type="submit">Register device</button></div></form></dialog>
        @foreach($records as $record)@if(strtolower($record->status) === 'active')<dialog class="record-dialog" id="replace-meter-{{ $record->id }}"><form class="record-form" method="POST" action="{{ route('meters.replace', $record->id) }}">@csrf<div class="dialog-header"><div><span class="eyebrow">METER REPLACEMENT</span><h2>Replace {{ $record->title }}</h2><p>The old meter and its readings remain in the history.</p></div><button class="dialog-close" type="button" data-modal-close>×</button></div><div class="dialog-fields"><label class="dialog-field"><span>New meter number <b>*</b></span><input name="meter_number" required maxlength="80"></label><label class="dialog-field"><span>New serial number</span><input name="serial_number" maxlength="100"></label><label class="dialog-field"><span>Initial reading <b>*</b></span><input name="initial_reading" type="number" step="0.0001" min="0" required></label><label class="dialog-field"><span>Installed on <b>*</b></span><input name="installed_at" type="date" required></label><label class="dialog-field dialog-field-wide"><span>Replacement reason <b>*</b></span><textarea name="reason" rows="2" required maxlength="1000"></textarea></label></div><div class="dialog-actions"><button class="button button-muted" type="button" data-modal-close>Cancel</button><button class="button button-primary" type="submit">Replace meter</button></div></form></dialog>@endif @endforeach
    @endif

    <dialog class="record-dialog" id="record-modal" aria-labelledby="record-modal-title" data-record-label="{{ $module['record'] }}" data-open-on-error="{{ $errors->any() ? 'true' : 'false' }}" @if($module['key'] === 'spaces') data-old-unit-groups="{{ json_encode(old('unit_groups', [])) }}" @endif>
        @php($propertyOptions = collect($formFields)->first(fn ($field) => $field[0] === 'property')[3] ?? [])
        @php($unitOptions = collect($formFields)->first(fn ($field) => $field[0] === 'unit')[3] ?? [])
        <form class="record-form" method="POST" action="{{ route('workspace.records.store', $module['key']) }}" data-create-url="{{ route('workspace.records.store', $module['key']) }}" data-record-form>
            @csrf
            <input type="hidden" name="_method" value="POST" data-method-field>
            @if($module['key'] === 'spaces')<input type="hidden" name="bulk_mode" value="{{ old('bulk_mode', 'single') }}" data-bulk-mode-field>@endif
            <div class="dialog-header"><div><h2 id="record-modal-title" data-modal-title>Add {{ $module['record'] }}</h2></div><button class="dialog-close" type="button" data-modal-close aria-label="Close form">×</button></div>
            <div class="dialog-fields">
                @if($module['key'] === 'spaces')
                    <section class="dialog-field dialog-field-wide unit-bulk-mode" data-unit-bulk-mode>
                        <span>Choose how to add units</span>
                        <div class="unit-bulk-tabs"><button type="button" class="is-active" data-unit-mode="single">Single unit</button><button type="button" data-unit-mode="bulk">Similar units</button><button type="button" data-unit-mode="mixed">Mixed groups</button></div>
                        <small data-unit-mode-help>Enter one unit label.</small>
                    </section>
                    <label class="dialog-field dialog-field-wide unit-property-field"><span>Select the parent property <b>*</b></span><select name="values[property]" data-value-field="property" required><option value="">Choose the property this unit belongs to</option>@foreach($propertyOptions as $option)@php($optionValue = is_array($option) ? $option['value'] : $option)@php($optionLabel = is_array($option) ? $option['label'] : $option)<option value="{{ $optionValue }}" @selected((string) old('values.property') === (string) $optionValue)>{{ $optionLabel }}</option>@endforeach</select><small>First choose the property, then enter its unit label below.</small></label>
                    <label class="dialog-field" data-single-unit-field><span>Unit label <b>*</b></span><input name="title" value="{{ old('title') }}" required maxlength="180" placeholder="For example, A, B, A1 or 4B" data-field="title">@error('title')<small class="field-error">{{ $message }}</small>@enderror</label>
                    <label class="dialog-field" data-bulk-unit-field hidden><span>How many similar units? <b>*</b></span><input name="bulk_count" type="number" min="2" max="500" value="{{ old('bulk_count', 10) }}" disabled data-bulk-count><small>Choose from 2 to 500 units.</small></label>
                    <label class="dialog-field" data-bulk-unit-field hidden><span>Label prefix (optional)</span><input name="bulk_prefix" maxlength="40" placeholder="e.g. A" disabled data-bulk-prefix><small>Labels will be A1, A2, A3…</small></label>
                    <label class="dialog-field" data-bulk-unit-field hidden><span>Label sequence</span><select name="bulk_sequence" disabled data-bulk-sequence><option value="numeric" @selected(old('bulk_sequence', 'numeric') === 'numeric')>Numbers (1, 2, 3…)</option><option value="letters" @selected(old('bulk_sequence') === 'letters')>Letters (A, B, C…)</option></select></label>
                    <label class="dialog-field" data-bulk-unit-field hidden><span data-bulk-start-label>Start number</span><input name="bulk_start" type="text" value="{{ old('bulk_start', '1') }}" disabled data-bulk-start><small data-bulk-start-help>First generated label will use this number.</small></label>
                    <div class="dialog-field dialog-field-wide bulk-unit-preview" data-bulk-preview hidden></div>
                    <section class="dialog-field dialog-field-wide mixed-unit-groups" data-mixed-groups hidden>
                        <div class="mixed-groups-heading"><div><strong>Unit type groups</strong><small>Each group gets its own unit type and label sequence.</small></div><button type="button" class="button button-muted" data-add-unit-group>＋ Add group</button></div>
                        <div data-unit-group-list></div>
                        <div class="bulk-unit-preview" data-mixed-preview></div>
                    </section>
                    <template data-unit-group-template>
                        <div class="unit-group-card" data-unit-group>
                            <div class="unit-group-heading" data-unit-group-heading><strong>Unit group</strong><button type="button" class="row-action row-action-danger" data-remove-unit-group>Remove</button></div>
                            <div class="unit-group-fields">
                                <label class="dialog-field"><span>Unit type <b>*</b></span><input data-group-field="unit_type" maxlength="40" placeholder="Bedsitter or 1 bedroom" required></label>
                                <label class="dialog-field"><span>Quantity <b>*</b></span><input data-group-field="count" type="number" min="1" max="500" value="1" required></label>
                                <label class="dialog-field"><span>Label prefix (optional)</span><input data-group-field="prefix" maxlength="40" placeholder="e.g. A-"></label>
                                <label class="dialog-field"><span>Sequence</span><select data-group-field="sequence"><option value="numeric">Numbers</option><option value="letters">Letters</option></select></label>
                                <label class="dialog-field"><span data-group-start-label>Start number</span><input data-group-field="start" value="1" required></label>
                            </div>
                        </div>
                    </template>
                    <label class="dialog-field"><span>Occupancy <b>*</b></span><select name="status" required data-field="status">@foreach($statuses as $status)<option value="{{ $status }}" @selected(old('status', $statuses[0]) === $status)>{{ $status }}</option>@endforeach</select></label>
                @elseif($module['key'] === 'occupants')
                    <label class="dialog-field dialog-field-wide unit-property-field"><span>Assign tenant to property unit <b>*</b></span><select name="values[unit]" data-value-field="unit" required><option value="">Choose property → unit label</option>@foreach($unitOptions as $option)@php($optionValue = is_array($option) ? $option['value'] : $option)@php($optionLabel = is_array($option) ? $option['label'] : $option)<option value="{{ $optionValue }}" @selected((string) old('values.unit') === (string) $optionValue)>{{ $optionLabel }}</option>@endforeach</select><small>Selecting the unit also identifies its parent property.</small></label>
                    <label class="dialog-field"><span>Tenant full name <b>*</b></span><input name="title" value="{{ old('title') }}" required maxlength="180" placeholder="Tenant's full name" data-field="title">@error('title')<small class="field-error">{{ $message }}</small>@enderror</label>
                    <label class="dialog-field"><span>Tenant status <b>*</b></span><select name="status" required data-field="status">@foreach($statuses as $status)<option value="{{ $status }}" @selected(old('status', $statuses[0]) === $status)>{{ $status }}</option>@endforeach</select></label>
                @else
                    <label class="dialog-field"><span>Name <b>*</b></span><input name="title" value="{{ old('title') }}" required maxlength="180" data-field="title">@error('title')<small class="field-error">{{ $message }}</small>@enderror</label>
                    <label class="dialog-field"><span>Reference</span><input name="reference" value="{{ old('reference') }}" maxlength="100" data-field="reference">@error('reference')<small class="field-error">{{ $message }}</small>@enderror</label>
                    <label class="dialog-field"><span>Status <b>*</b></span><select name="status" required data-field="status">@foreach($statuses as $status)<option value="{{ $status }}" @selected(old('status', $statuses[0]) === $status)>{{ $status }}</option>@endforeach</select></label>
                @endif
                @if(in_array($module['key'], ['billing', 'payments', 'tariffs']))
                    <label class="dialog-field"><span>Amount / rate</span><input name="amount" value="{{ old('amount') }}" type="number" min="0" step="0.01" placeholder="0.00" data-field="amount"></label>
                @endif
                @if(in_array($module['key'], ['readings', 'billing', 'payments', 'maintenance', 'reports', 'notifications', 'tariffs']))
                    <label class="dialog-field"><span>Date</span><input name="recorded_on" value="{{ old('recorded_on') }}" type="date" data-field="recorded_on"></label>
                @endif
                <label class="dialog-field dialog-field-wide"><span>{{ $module['key'] === 'spaces' ? 'Notes (optional)' : 'Notes' }}</span><textarea name="summary" rows="2" maxlength="3000" placeholder="Add helpful context" data-field="summary">{{ old('summary') }}</textarea></label>
                @foreach($formFields as [$key, $label, $type, $options])
                    @if(! (in_array($module['key'], ['spaces', 'occupants'], true) && in_array($key, ['property', 'unit'], true)))
                    <label class="dialog-field {{ $type === 'textarea' ? 'dialog-field-wide' : '' }}"><span>{{ $label }}</span>
                        @if($type === 'select')<select name="values[{{ $key }}]" data-value-field="{{ $key }}" @if(in_array($key, ['property', 'utility', 'rate_type']) && in_array($module['key'], ['spaces', 'meters', 'tariffs', 'maintenance'])) required @endif><option value="">Choose {{ strtolower($label) }}</option>@foreach($options as $option)@php($optionValue = is_array($option) ? $option['value'] : $option)@php($optionLabel = is_array($option) ? $option['label'] : $option)<option value="{{ $optionValue }}" @selected((string) old('values.'.$key) === (string) $optionValue)>{{ $optionLabel }}</option>@endforeach</select>
                        @elseif($type === 'textarea')<textarea name="values[{{ $key }}]" rows="2" maxlength="3000" data-value-field="{{ $key }}">{{ old('values.'.$key) }}</textarea>
                        @else<input name="values[{{ $key }}]" value="{{ old('values.'.$key) }}" type="{{ $type }}" @if($type === 'number') min="0" step="0.01" @endif data-value-field="{{ $key }}">@endif
                    </label>
                    @endif
                @endforeach
            </div>
            @if($errors->any())<div class="form-errors" role="alert">{{ $errors->first() }}</div>@endif
            <div class="dialog-actions"><button class="button button-muted" type="button" data-modal-close>Cancel</button><button class="button button-primary" type="submit" data-submit-label>Save {{ $module['record'] }}</button></div>
        </form>
    </dialog>
    <footer class="dashboard-footer"><span>© {{ now()->year }} UtilityFlow</span><span>{{ $organization->timezone }} workspace</span><a href="mailto:support@utilityflow.app">Help &amp; support</a></footer>
@endsection
