<?php

namespace App\Services;

use App\Models\MaintenanceTicket;
use App\Models\Meter;
use App\Models\MeterAssignment;
use App\Models\Occupancy;
use App\Models\Organization;
use App\Models\Property;
use App\Models\PropertyBlock;
use App\Models\PropertyFloor;
use App\Models\Tariff;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkspaceRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DomainWorkspaceService
{
    private const TYPES = [
        'properties' => Property::class,
        'spaces' => Unit::class,
        'occupants' => Tenant::class,
        'meters' => Meter::class,
        'tariffs' => Tariff::class,
        'maintenance' => MaintenanceTicket::class,
    ];

    public function supports(string $module): bool
    {
        return isset(self::TYPES[$module]);
    }

    public function records(string $module, int $organizationId, ?string $search)
    {
        $model = self::TYPES[$module];
        $query = $model::query()->where('organization_id', $organizationId);
        if ($module === 'spaces') {
            $query->with(['property:id,name', 'block:id,name', 'floor:id,name']);
        }
        if ($module === 'occupants') {
            $query->with(['occupancies' => fn ($occupancies) => $occupancies->where('status', 'active')->with('unit.property')]);
        }
        if ($search) {
            $query->where(function ($query) use ($search, $module): void {
                $columns = match ($module) {
                    'properties' => ['name', 'code', 'city'], 'spaces' => ['unit_number', 'unit_type'],
                    'occupants' => ['name', 'email', 'phone'], 'meters' => ['meter_number', 'serial_number', 'utility_type'],
                    'tariffs' => ['name', 'utility_type'], default => ['title', 'category', 'status'],
                };
                foreach ($columns as $column) {
                    $query->orWhere($column, 'like', '%'.$search.'%');
                }
            });
        }

        return $query->latest()->paginate(10)->withQueryString()->through(fn (Model $item) => $this->present($module, $item));
    }

    public function fields(string $module, int $organizationId): array
    {
        $fields = match ($module) {
            'properties' => [['type', 'Property type', 'select', ['Residential', 'Commercial', 'Mixed use', 'Estate', 'Other']], ['address', 'Street address', 'text'], ['city', 'City or town', 'text']],
            'spaces' => [['property', 'Property', 'select', $this->options(Property::where('organization_id', $organizationId)->orderBy('name')->get(), 'id', fn ($item) => $item->name)], ['block', 'Block (optional)', 'select', $this->options(PropertyBlock::with('property')->where('organization_id', $organizationId)->orderBy('name')->get(), 'id', fn ($item) => $item->property->name.' · '.$item->name)], ['floor', 'Floor (optional)', 'select', $this->options(PropertyFloor::with('property')->where('organization_id', $organizationId)->orderBy('name')->get(), 'id', fn ($item) => $item->property->name.' · '.$item->name)], ['unit_type', 'Unit type', 'select', ['Apartment', 'Office', 'Shop', 'House', 'Other']], ['floor_area', 'Floor area (m²)', 'number'], ['occupancy', 'Occupancy', 'select', ['Vacant', 'Occupied', 'Reserved', 'Maintenance']]],
            'occupants' => [['unit', 'Unit in property', 'select', $this->options(Unit::with('property')->where('organization_id', $organizationId)->orderBy('property_id')->orderBy('unit_number')->get(), 'id', fn ($item) => $item->property->name.' · Unit '.$item->unit_number)], ['phone', 'Phone number', 'tel'], ['email', 'Email address', 'email'], ['move_in', 'Move-in date', 'date']],
            'meters' => [['utility', 'Utility', 'select', ['water', 'electricity']], ['property', 'Property', 'select', $this->options(Property::where('organization_id', $organizationId)->orderBy('name')->get(), 'id', fn ($item) => $item->name)], ['unit', 'Unit (optional)', 'select', $this->options(Unit::where('organization_id', $organizationId)->orderBy('unit_number')->get(), 'id', fn ($item) => $item->property->name.' · '.$item->unit_number)], ['serial', 'Serial number', 'text'], ['category', 'Meter category', 'select', ['individual', 'main', 'submeter', 'shared']], ['manufacturer', 'Manufacturer', 'text'], ['installed_at', 'Installation date', 'date'], ['initial_reading', 'Initial reading', 'number'], ['multiplier', 'Multiplier', 'number'], ['rollover_value', 'Rollover value', 'number']],
            'tariffs' => [['utility', 'Utility', 'select', ['water', 'electricity']], ['property', 'Property (optional)', 'select', $this->options(Property::where('organization_id', $organizationId)->orderBy('name')->get(), 'id', fn ($item) => $item->name)], ['meter', 'Meter (optional)', 'select', $this->options(Meter::where('organization_id', $organizationId)->orderBy('meter_number')->get(), 'id', fn ($item) => $item->meter_number.' · '.$item->utility_type)], ['rate_type', 'Rate type', 'select', ['per_unit', 'fixed', 'tiered']], ['unit_rate', 'Rate per unit', 'number'], ['fixed_charge', 'Fixed charge', 'number'], ['tier_1_from', 'Tier 1 from units', 'number'], ['tier_1_to', 'Tier 1 up to units', 'number'], ['tier_1_rate', 'Tier 1 rate', 'number'], ['tier_2_from', 'Tier 2 from units', 'number'], ['tier_2_to', 'Tier 2 up to units', 'number'], ['tier_2_rate', 'Tier 2 rate', 'number'], ['tier_3_from', 'Tier 3 from units', 'number'], ['tier_3_to', 'Tier 3 up to units', 'number'], ['tier_3_rate', 'Tier 3 rate', 'number']],
            'maintenance' => [['property', 'Property', 'select', $this->options(Property::where('organization_id', $organizationId)->orderBy('name')->get(), 'id', fn ($item) => $item->name)], ['unit', 'Unit (optional)', 'select', $this->options(Unit::where('organization_id', $organizationId)->orderBy('unit_number')->get(), 'id', fn ($item) => $item->property->name.' · '.$item->unit_number)], ['category', 'Issue type', 'select', ['leak', 'meter_fault', 'electricity_fault', 'other']], ['priority', 'Priority', 'select', ['low', 'normal', 'high', 'urgent']]],
            default => [],
        };

        return array_map(fn (array $field): array => array_pad($field, 4, []), $fields);
    }

    public function statuses(string $module): array
    {
        return match ($module) {
            'spaces' => ['Vacant', 'Occupied', 'Reserved', 'Maintenance'],
            'meters' => ['Active', 'Inactive', 'Faulty', 'Maintenance', 'Removed', 'Replaced'],
            'maintenance' => ['Open', 'Assigned', 'In progress', 'Resolved', 'Closed'],
            default => ['Active', 'Inactive'],
        };
    }

    public function save(Request $request, string $module, int $organizationId, User $actor, ?int $id = null): Model
    {
        if ($module === 'spaces' && ! $id && $request->input('bulk_mode') === 'mixed') {
            return $this->saveUnitGroups($request, $organizationId, $actor);
        }

        if ($module === 'spaces' && ! $id && (int) $request->input('bulk_count', 1) > 1) {
            return $this->saveUnitBatch($request, $organizationId, $actor);
        }

        $this->validateRecord($request, $module, $organizationId, $id);
        $input = $request->input();
        $values = $input['values'] ?? [];
        $modelClass = self::TYPES[$module];

        return DB::transaction(function () use ($request, $module, $organizationId, $actor, $id, $values, $input, $modelClass): Model {
            $record = $id ? $modelClass::where('organization_id', $organizationId)->findOrFail($id) : null;
            if (! $record && in_array($module, ['properties', 'spaces'], true)) {
                app(PlanLimitService::class)->assertCanAdd(Organization::findOrFail($organizationId), $module === 'properties' ? 'properties' : 'units');
            }
            $before = $record?->getAttributes();
            $status = Str::lower($input['status']);

            $saved = match ($module) {
                'properties' => $this->saveProperty($record, $organizationId, $input, $values, $status),
                'spaces' => $this->saveUnit($record, $organizationId, $input, $values, $status),
                'occupants' => $this->saveTenant($record, $organizationId, $input, $values, $status),
                'meters' => $this->saveMeter($record, $organizationId, $input, $values, $status),
                'tariffs' => $this->saveTariff($record, $organizationId, $input, $values, $status),
                'maintenance' => $this->saveTicket($record, $organizationId, $actor, $input, $values, $status),
            };

            app(AuditLogger::class)->record($organizationId, $actor->id, $id ? $module.'.updated' : $module.'.created', $saved, $before, $saved->getAttributes(), $request);

            return $saved;
        });
    }

    private function saveUnitBatch(Request $request, int $organizationId, User $actor): Unit
    {
        $request->validate([
            'bulk_count' => ['required', 'integer', 'min:2', 'max:500'],
            'bulk_prefix' => ['nullable', 'string', 'max:40'],
            'bulk_sequence' => ['required', Rule::in(['numeric', 'letters'])],
            'bulk_start' => ['required', 'string', 'max:50'],
            'occupancy_counts' => ['required', 'array:vacant,occupied,reserved,maintenance'],
            'occupancy_counts.vacant' => ['required', 'integer', 'min:0'],
            'occupancy_counts.occupied' => ['required', 'integer', 'min:0'],
            'occupancy_counts.reserved' => ['required', 'integer', 'min:0'],
            'occupancy_counts.maintenance' => ['required', 'integer', 'min:0'],
        ]);

        $count = (int) $request->input('bulk_count');
        $prefix = trim((string) $request->input('bulk_prefix', ''));
        $sequence = $request->input('bulk_sequence');
        $start = trim((string) $request->input('bulk_start'));
        $occupancyCounts = $request->input('occupancy_counts');
        if (array_sum($occupancyCounts) !== $count) {
            throw ValidationException::withMessages(['occupancy_counts' => 'Occupancy counts must add up to the batch size.']);
        }
        if ($sequence === 'numeric' && ! ctype_digit($start)) {
            throw ValidationException::withMessages(['bulk_start' => 'Enter a number to start the numeric sequence.']);
        }
        if ($sequence === 'letters' && ! preg_match('/^[A-Za-z]+$/', $start)) {
            throw ValidationException::withMessages(['bulk_start' => 'Enter letters only, such as A or AA, to start the letter sequence.']);
        }
        $labels = collect(range(0, $count - 1))->map(function (int $offset) use ($prefix, $start, $sequence): string {
            return $sequence === 'numeric' ? $prefix.(string) ((int) $start + $offset) : $this->excelLabel($this->excelLabelIndex($start) + $offset);
        });
        if ($labels->max(fn (string $label): int => strlen($label)) > 50) {
            throw ValidationException::withMessages(['bulk_prefix' => 'Generated unit labels must be 50 characters or fewer.']);
        }
        $input = $request->all();
        $input['title'] = $labels->first();
        $request->merge(['title' => $input['title'], 'status' => 'Vacant']);
        $this->validateRecord($request, 'spaces', $organizationId, null);

        $property = Property::where('organization_id', $organizationId)->findOrFail(data_get($input, 'values.property'));
        $duplicates = Unit::where('property_id', $property->id)->whereIn('unit_number', $labels)->pluck('unit_number');
        if ($duplicates->isNotEmpty()) {
            throw ValidationException::withMessages(['bulk_count' => 'These unit labels already exist: '.$duplicates->implode(', ')]);
        }

        return DB::transaction(function () use ($request, $organizationId, $actor, $input, $labels, $count, $occupancyCounts): Unit {
            app(PlanLimitService::class)->assertCanAdd(Organization::findOrFail($organizationId), 'units', $count);
            $first = null;
            $statuses = collect(['vacant', 'occupied', 'reserved', 'maintenance'])->flatMap(fn (string $status): array => array_fill(0, (int) $occupancyCounts[$status], $status))->values();
            foreach ($labels as $index => $label) {
                $unitInput = $input;
                $unitInput['title'] = $label;
                $unit = $this->saveUnit(null, $organizationId, $unitInput, $input['values'] ?? [], $statuses[$index]);
                $first ??= $unit;
                app(AuditLogger::class)->record($organizationId, $actor->id, 'spaces.created', $unit, null, $unit->getAttributes(), $request);
            }

            return $first;
        });
    }

    private function saveUnitGroups(Request $request, int $organizationId, User $actor): Unit
    {
        $validated = $request->validate([
            'unit_groups' => ['required', 'array', 'min:1', 'max:20'],
            'unit_groups.*.count' => ['required', 'integer', 'min:1', 'max:500'],
            'unit_groups.*.unit_type' => ['required', 'string', 'max:40'],
            'unit_groups.*.prefix' => ['nullable', 'string', 'max:40'],
            'unit_groups.*.sequence' => ['required', Rule::in(['numeric', 'letters'])],
            'unit_groups.*.start' => ['required', 'string', 'max:50'],
            'unit_groups.*.vacant' => ['required', 'integer', 'min:0'],
            'unit_groups.*.occupied' => ['required', 'integer', 'min:0'],
            'unit_groups.*.reserved' => ['required', 'integer', 'min:0'],
            'unit_groups.*.maintenance' => ['required', 'integer', 'min:0'],
        ]);
        $groups = $validated['unit_groups'];
        $total = array_sum(array_column($groups, 'count'));
        if ($total > 500) {
            throw ValidationException::withMessages(['unit_groups' => 'A mixed batch can contain up to 500 units.']);
        }

        $input = $request->all();
        $input['title'] = 'Mixed unit batch';
        $request->merge(['title' => $input['title'], 'status' => 'Vacant']);
        $this->validateRecord($request, 'spaces', $organizationId, null);
        $property = Property::where('organization_id', $organizationId)->findOrFail(data_get($input, 'values.property'));
        $unitGroups = collect($groups)->map(function (array $group, int $index): array {
            if (array_sum(array_map('intval', array_intersect_key($group, array_flip(['vacant', 'occupied', 'reserved', 'maintenance'])))) !== (int) $group['count']) {
                throw ValidationException::withMessages(["unit_groups.{$index}.vacant" => 'Occupancy counts must add up to this group’s quantity.']);
            }
            $start = trim($group['start']);
            if ($group['sequence'] === 'numeric' && ! ctype_digit($start)) {
                throw ValidationException::withMessages(["unit_groups.{$index}.start" => 'Enter a number to start this sequence.']);
            }
            if ($group['sequence'] === 'letters' && ! preg_match('/^[A-Za-z]+$/', $start)) {
                throw ValidationException::withMessages(["unit_groups.{$index}.start" => 'Enter letters such as A or AA to start this sequence.']);
            }
            $labels = collect(range(0, (int) $group['count'] - 1))->map(fn (int $offset): string => ($group['sequence'] === 'numeric' ? trim((string) ($group['prefix'] ?? '')).(string) ((int) $start + $offset) : $this->excelLabel($this->excelLabelIndex($start) + $offset)));
            if ($labels->contains(fn (string $label): bool => strlen($label) > 50)) {
                throw ValidationException::withMessages(["unit_groups.{$index}.prefix" => 'Generated unit labels must be 50 characters or fewer.']);
            }

            $statuses = collect(['vacant', 'occupied', 'reserved', 'maintenance'])->flatMap(fn (string $status): array => array_fill(0, (int) $group[$status], $status))->values();

            return ['unit_type' => trim($group['unit_type']), 'labels' => $labels, 'statuses' => $statuses];
        });
        $labels = $unitGroups->flatMap(fn (array $group) => $group['labels']);
        if ($labels->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['unit_groups' => 'Each generated unit label must be unique within this property.']);
        }
        $duplicates = Unit::where('property_id', $property->id)->whereIn('unit_number', $labels)->pluck('unit_number');
        if ($duplicates->isNotEmpty()) {
            throw ValidationException::withMessages(['unit_groups' => 'These unit labels already exist: '.$duplicates->implode(', ')]);
        }

        return DB::transaction(function () use ($request, $organizationId, $actor, $input, $unitGroups, $total): Unit {
            app(PlanLimitService::class)->assertCanAdd(Organization::findOrFail($organizationId), 'units', $total);
            $first = null;
            foreach ($unitGroups as $group) {
                foreach ($group['labels'] as $index => $label) {
                    $unitInput = $input;
                    $unitInput['title'] = $label;
                    $values = $input['values'] ?? [];
                    $values['unit_type'] = $group['unit_type'];
                    $unit = $this->saveUnit(null, $organizationId, $unitInput, $values, $group['statuses'][$index]);
                    $first ??= $unit;
                    app(AuditLogger::class)->record($organizationId, $actor->id, 'spaces.created', $unit, null, $unit->getAttributes(), $request);
                }
            }

            return $first;
        });
    }

    private function excelLabelIndex(string $label): int
    {
        $index = 0;
        foreach (str_split(strtoupper($label)) as $letter) {
            $index = ($index * 26) + ord($letter) - 64;
        }

        return $index - 1;
    }

    private function excelLabel(int $index): string
    {
        $label = '';
        while ($index >= 0) {
            $label = chr(($index % 26) + 65).$label;
            $index = intdiv($index, 26) - 1;
        }

        return $label;
    }

    public function delete(string $module, int $organizationId, User $actor, int $id, Request $request): void
    {
        $modelClass = self::TYPES[$module];
        $record = $modelClass::where('organization_id', $organizationId)->findOrFail($id);
        $before = $record->getAttributes();

        if ($module === 'properties' && ($record->units()->exists() || $record->blocks()->exists())) {
            throw ValidationException::withMessages(['record' => 'This property has units or blocks. Archive it instead of deleting its history.']);
        }
        if ($module === 'spaces' && ($record->occupancies()->exists() || $record->invoices()->exists())) {
            throw ValidationException::withMessages(['record' => 'This unit has occupancy or invoice history and cannot be deleted.']);
        }
        if ($module === 'occupants' && ($record->invoices()->exists() || $record->payments()->exists() || $record->occupancies()->exists())) {
            $record->update(['status' => 'inactive']);
            app(AuditLogger::class)->record($organizationId, $actor->id, 'tenant.archived', $record, $before, $record->getAttributes(), $request);

            return;
        }
        if ($module === 'meters' && ($record->readings()->exists() || $record->assignments()->exists())) {
            $record->update(['status' => 'removed']);
            $record->activeAssignment()->whereNull('ends_at')->update(['ends_at' => today()]);
            app(AuditLogger::class)->record($organizationId, $actor->id, 'meter.removed', $record, $before, $record->getAttributes(), $request);

            return;
        }
        if ($module === 'tariffs') {
            $record->update(['status' => 'inactive', 'effective_to' => today()]);
            app(AuditLogger::class)->record($organizationId, $actor->id, 'tariff.deactivated', $record, $before, $record->getAttributes(), $request);

            return;
        }

        $record->delete();
        app(AuditLogger::class)->record($organizationId, $actor->id, $module.'.deleted', $record, $before, null, $request);
    }

    private function saveProperty(?Property $record, int $organizationId, array $input, array $values, string $status): Property
    {
        $data = ['organization_id' => $organizationId, 'name' => $input['title'], 'code' => $input['reference'] ?: Str::upper(Str::random(8)), 'status' => $status, 'type' => $values['type'] ?? 'Other', 'address' => $values['address'] ?? null, 'city' => $values['city'] ?? null, 'description' => $input['summary'] ?? null];
        $record ? $record->update($data) : $record = Property::create($data);

        return $record;
    }

    private function saveUnit(?Unit $record, int $organizationId, array $input, array $values, string $status): Unit
    {
        $property = Property::where('organization_id', $organizationId)->findOrFail($values['property']);
        $block = ! empty($values['block']) ? PropertyBlock::where('organization_id', $organizationId)->where('property_id', $property->id)->findOrFail($values['block']) : null;
        $floor = ! empty($values['floor']) ? PropertyFloor::where('organization_id', $organizationId)->where('property_id', $property->id)->findOrFail($values['floor']) : null;
        if ($floor?->block_id && $block && $floor->block_id !== $block->id) {
            throw ValidationException::withMessages(['values.floor' => 'Choose a floor in the selected block, or clear the block selection.']);
        }
        $block ??= $floor?->block;
        $data = ['organization_id' => $organizationId, 'property_id' => $property->id, 'block_id' => $block?->id, 'floor_id' => $floor?->id, 'unit_number' => $input['title'], 'unit_type' => $values['unit_type'] ?? null, 'floor_area' => $values['floor_area'] ?? null, 'occupancy_status' => Str::snake($status), 'notes' => $input['summary'] ?? null];
        $record ? $record->update($data) : $record = Unit::create($data);

        return $record;
    }

    private function saveTenant(?Tenant $record, int $organizationId, array $input, array $values, string $status): Tenant
    {
        $unit = ! empty($values['unit']) ? Unit::where('organization_id', $organizationId)->findOrFail($values['unit']) : null;

        return DB::transaction(function () use ($record, $organizationId, $input, $values, $status, $unit): Tenant {
            $email = $values['email'] ?? null;
            $portalUser = $email ? User::whereRaw('LOWER(email) = ?', [strtolower($email)])->first() : null;
            $data = ['organization_id' => $organizationId, 'user_id' => $portalUser?->id, 'name' => $input['title'], 'email' => $email, 'phone' => $values['phone'] ?? null, 'status' => $status];
            $record ? $record->update($data) : $record = Tenant::create($data);
            if ($unit) {
                $active = $record->occupancies()->where('status', 'active')->first();
                $moveIn = $values['move_in'] ?? today()->toDateString();
                $otherOccupancy = $unit->occupancies()->where('status', 'active')->where('tenant_id', '!=', $record->id)->exists();
                if ($otherOccupancy) {
                    throw ValidationException::withMessages(['values.unit' => 'This unit already has an active tenant. End that occupancy before assigning another tenant.']);
                }
                if ($active && $active->unit_id !== $unit->id) {
                    $moveOut = Carbon::parse($moveIn)->subDay()->toDateString();
                    if ($moveOut < $active->move_in_date->toDateString()) {
                        throw ValidationException::withMessages(['values.move_in' => 'The new move-in date must be after the current occupancy began.']);
                    }
                    $active->update(['status' => 'ended', 'move_out_date' => $moveOut]);
                    $active->unit()->update(['occupancy_status' => 'vacant']);
                    $unit->update(['occupancy_status' => 'occupied']);
                    $active = null;
                }
                if (! $active) {
                    Occupancy::create(['organization_id' => $organizationId, 'tenant_id' => $record->id, 'unit_id' => $unit->id, 'move_in_date' => $moveIn, 'status' => 'active']);
                }
                $unit->update(['occupancy_status' => 'occupied']);
            } else {
                $active = $record->occupancies()->where('status', 'active')->first();
                if ($active) {
                    $active->update(['status' => 'ended', 'move_out_date' => today()]);
                    $active->unit()->update(['occupancy_status' => 'vacant']);
                }
            }

            return $record;
        });
    }

    private function saveMeter(?Meter $record, int $organizationId, array $input, array $values, string $status): Meter
    {
        $property = Property::where('organization_id', $organizationId)->findOrFail($values['property']);
        $unit = ! empty($values['unit']) ? Unit::where('organization_id', $organizationId)->where('property_id', $property->id)->findOrFail($values['unit']) : null;

        return DB::transaction(function () use ($record, $organizationId, $input, $values, $status, $property, $unit): Meter {
            $data = ['organization_id' => $organizationId, 'meter_number' => $input['title'], 'serial_number' => $values['serial'] ?? null, 'utility_type' => $values['utility'], 'category' => $values['category'] ?? 'individual', 'manufacturer' => $values['manufacturer'] ?? null, 'installed_at' => $values['installed_at'] ?? today(), 'initial_reading' => $values['initial_reading'] ?? 0, 'multiplier' => $values['multiplier'] ?? 1, 'rollover_value' => $values['rollover_value'] ?? null, 'status' => $status];
            $record ? $record->update($data) : $record = Meter::create($data);
            $current = $record->activeAssignment()->first();
            if (! $current || $current->property_id !== $property->id || $current->unit_id !== $unit?->id) {
                $current?->update(['ends_at' => today()->subDay()]);
                MeterAssignment::create(['organization_id' => $organizationId, 'meter_id' => $record->id, 'property_id' => $property->id, 'unit_id' => $unit?->id, 'starts_at' => today(), 'assignment_type' => $unit ? 'individual' : 'shared']);
            }

            return $record;
        });
    }

    private function saveTariff(?Tariff $record, int $organizationId, array $input, array $values, string $status): Tariff
    {
        $property = ! empty($values['property']) ? Property::where('organization_id', $organizationId)->findOrFail($values['property']) : null;
        $meter = ! empty($values['meter']) ? Meter::where('organization_id', $organizationId)->findOrFail($values['meter']) : null;
        if ($meter && $meter->utility_type !== $values['utility']) {
            throw ValidationException::withMessages(['values.meter' => 'The selected meter must use the same utility as this tariff.']);
        }
        if ($meter && $property && ! $meter->assignments()->where('property_id', $property->id)->exists()) {
            throw ValidationException::withMessages(['values.meter' => 'The selected meter is not assigned to the selected property.']);
        }
        $data = ['organization_id' => $organizationId, 'property_id' => $property?->id, 'meter_id' => $meter?->id, 'name' => $input['title'], 'utility_type' => $values['utility'], 'rate_type' => $values['rate_type'], 'fixed_charge' => $values['fixed_charge'] ?? 0, 'price_per_unit' => $values['unit_rate'] ?? 0, 'effective_from' => $input['recorded_on'] ?? today(), 'status' => $status, 'currency' => 'KES'];
        $effectiveFrom = Carbon::parse($data['effective_from']);
        if ($record) {
            if ($effectiveFrom->lessThanOrEqualTo($record->effective_from)) {
                throw ValidationException::withMessages(['recorded_on' => 'A tariff version must start after the current version began.']);
            }
            $version = $record->version + 1;
            $record->update(['status' => 'inactive', 'effective_to' => $effectiveFrom->copy()->subDay()]);
            $tariff = Tariff::create($data + ['version' => $version]);
        } else {
            $tariff = Tariff::create($data + ['version' => 1]);
        }
        if ($tariff->rate_type === 'tiered') {
            $previousTo = null;
            $sawOpenEnded = false;
            if (empty($values['tier_1_rate'])) {
                throw ValidationException::withMessages(['values.tier_1_rate' => 'A tiered tariff must start with tier 1.']);
            }
            for ($tier = 1; $tier <= 3; $tier++) {
                $rate = $values['tier_'.$tier.'_rate'] ?? null;
                if ($rate === null || $rate === '') {
                    if (($values['tier_'.$tier.'_from'] ?? '') !== '' || ($values['tier_'.$tier.'_to'] ?? '') !== '') {
                        throw ValidationException::withMessages(['values.tier_'.$tier.'_rate' => 'Enter a rate for every configured tier.']);
                    }

                    continue;
                }
                if ($sawOpenEnded) {
                    throw ValidationException::withMessages(['values.tier_'.$tier.'_rate' => 'No tier may follow an open-ended tier.']);
                }
                $from = (float) ($values['tier_'.$tier.'_from'] ?? 0);
                $to = ($values['tier_'.$tier.'_to'] ?? '') === '' ? null : (float) $values['tier_'.$tier.'_to'];
                if (($tier === 1 && $from !== 0.0) || ($tier > 1 && ($previousTo === null || $from !== $previousTo)) || ($to !== null && $to <= $from)) {
                    throw ValidationException::withMessages(['values.tier_'.$tier.'_from' => 'Tariff tiers must start at zero, be continuous, and have an upper bound greater than the lower bound.']);
                }
                $tariff->tiers()->create([
                    'from_units' => $from,
                    'to_units' => $to,
                    'price_per_unit' => $rate,
                    'sort_order' => $tier,
                ]);
                $previousTo = $to;
                $sawOpenEnded = $to === null;
                if ($to === null && $tier < 3 && ! empty($values['tier_'.($tier + 1).'_rate'])) {
                    throw ValidationException::withMessages(['values.tier_'.$tier.'_to' => 'Only the final tariff tier may have no upper bound.']);
                }
            }
            if (! $tariff->tiers()->exists()) {
                throw ValidationException::withMessages(['values.tier_1_rate' => 'A tiered tariff needs at least one tier rate.']);
            }
        }

        return $tariff;
    }

    private function saveTicket(?MaintenanceTicket $record, int $organizationId, User $actor, array $input, array $values, string $status): MaintenanceTicket
    {
        $property = Property::where('organization_id', $organizationId)->findOrFail($values['property']);
        $unit = ! empty($values['unit']) ? Unit::where('organization_id', $organizationId)->where('property_id', $property->id)->findOrFail($values['unit']) : null;
        $data = ['organization_id' => $organizationId, 'property_id' => $property->id, 'unit_id' => $unit?->id, 'title' => $input['title'], 'description' => $input['summary'] ?? null, 'category' => $values['category'] ?? 'other', 'priority' => $values['priority'] ?? 'normal', 'status' => $status, 'created_by' => $actor->id];
        $record ? $record->update($data) : $record = MaintenanceTicket::create($data);

        return $record;
    }

    private function present(string $module, Model $item): WorkspaceRecord
    {
        $activeOccupancy = $module === 'occupants' ? $item->occupancies->first() : null;
        $attributes = match ($module) {
            'properties' => ['title' => $item->name, 'reference' => $item->code, 'summary' => trim(($item->address ?? '').' '.($item->city ?? '')), 'values' => ['type' => $item->type, 'address' => $item->address, 'city' => $item->city]],
            'spaces' => ['title' => $item->unit_number, 'reference' => $item->property->name, 'summary' => collect([$item->block?->name, $item->floor?->name])->filter()->implode(' · '), 'values' => ['property' => $item->property_id, 'block' => $item->block_id, 'floor' => $item->floor_id, 'unit_type' => $item->unit_type, 'floor_area' => $item->floor_area, 'occupancy' => Str::title($item->occupancy_status)]],
            'occupants' => ['title' => $item->name, 'reference' => $item->email, 'summary' => $activeOccupancy?->unit ? $activeOccupancy->unit->property->name.' · Unit '.$activeOccupancy->unit->unit_number : null, 'values' => ['email' => $item->email, 'phone' => $item->phone, 'unit' => $activeOccupancy?->unit_id, 'move_in' => $activeOccupancy?->move_in_date?->toDateString()]],
            'meters' => ['title' => $item->meter_number, 'reference' => $item->serial_number, 'summary' => $item->notes, 'values' => ['utility' => $item->utility_type, 'serial' => $item->serial_number, 'multiplier' => $item->multiplier, 'category' => $item->category, 'manufacturer' => $item->manufacturer, 'installed_at' => $item->installed_at?->toDateString(), 'initial_reading' => $item->initial_reading, 'rollover_value' => $item->rollover_value, 'property' => $item->activeAssignment()->value('property_id'), 'unit' => $item->activeAssignment()->value('unit_id')]],
            'tariffs' => ['title' => $item->name, 'reference' => strtoupper($item->utility_type), 'summary' => $item->rate_type, 'amount' => $item->price_per_unit, 'recorded_on' => $item->effective_from, 'values' => ['utility' => $item->utility_type, 'property' => $item->property_id, 'meter' => $item->meter_id, 'rate_type' => $item->rate_type, 'unit_rate' => $item->price_per_unit, 'fixed_charge' => $item->fixed_charge]],
            default => ['title' => $item->title, 'reference' => (string) $item->id, 'summary' => $item->description, 'values' => ['property' => $item->property_id, 'unit' => $item->unit_id, 'category' => $item->category, 'priority' => $item->priority]],
        };
        $dto = new WorkspaceRecord;
        $dto->forceFill($attributes + ['id' => $item->id, 'module' => $module, 'status' => $module === 'spaces' ? Str::title($item->occupancy_status) : Str::title($item->status), 'amount' => $attributes['amount'] ?? null, 'recorded_on' => $attributes['recorded_on'] ?? null, 'created_at' => $item->created_at]);

        return $dto;
    }

    private function validateRecord(Request $request, string $module, int $organizationId, ?int $id): void
    {
        $rules = [
            'title' => ['required', 'string', 'max:180'],
            'reference' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in($this->statuses($module))],
            'summary' => ['nullable', 'string', 'max:3000'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'recorded_on' => ['nullable', 'date'],
            'values' => ['nullable', 'array:'.implode(',', array_map(fn (array $field): string => $field[0], $this->fields($module, $organizationId)))],
        ];
        foreach ($this->fields($module, $organizationId) as [$key, $label, $type, $options]) {
            $rules['values.'.$key] = match ($type) {
                'number' => ['nullable', 'numeric', 'min:0'], 'date' => ['nullable', 'date'], 'email' => ['nullable', 'email', 'max:255'],
                'select' => ['nullable', Rule::in(array_map(fn ($option) => is_array($option) ? $option['value'] : $option, $options))],
                default => ['nullable', 'string', 'max:255'],
            };
        }
        foreach (['spaces' => ['property'], 'occupants' => ['unit'], 'meters' => ['property', 'utility'], 'tariffs' => ['utility', 'rate_type'], 'maintenance' => ['property']] as $resource => $requiredFields) {
            if ($resource === $module) {
                foreach ($requiredFields as $field) {
                    $rules['values.'.$field] = array_merge(['required'], $rules['values.'.$field] ?? []);
                }
            }
        }
        foreach (['property' => 'properties', 'unit' => 'units', 'meter' => 'meters'] as $field => $table) {
            if ($request->input('values.'.$field)) {
                $rules['values.'.$field] = ['required', 'integer', Rule::exists($table, 'id')->where('organization_id', $organizationId)];
            }
        }
        Validator::make($request->all(), $rules)->validate();
    }

    private function options($items, string $valueKey, callable $label): array
    {
        return $items->map(fn ($item) => ['value' => (string) $item->{$valueKey}, 'label' => $label($item)])->all();
    }
}
