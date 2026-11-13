<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\PropertyBlock;
use App\Models\PropertyFloor;
use App\Models\Role;
use App\Services\AuditLogger;
use App\Services\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PropertyStructureController extends Controller
{
    public function index(Request $request, CurrentOrganization $organizations): View
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization && $request->user()->hasOrganizationPermission($organization, 'properties.view'), 403);
        $properties = Property::query()->with(['blocks.floors', 'floors.block', 'units'])
            ->where('organization_id', $organization->id)->orderBy('name')->get();

        return view('workspace.structure', [
            'organization' => $organization,
            'organizations' => $request->user()->organizations()->where('organizations.status', 'active')->orderBy('name')->get(),
            'roleName' => Role::find($organization->users()->where('users.id', $request->user()->id)->first()?->pivot?->role_id)?->name,
            'currentModule' => 'structure',
            'properties' => $properties,
        ]);
    }

    public function storeBlock(Request $request, CurrentOrganization $organizations, AuditLogger $audit): RedirectResponse
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization && $request->user()->hasOrganizationPermission($organization, 'properties.manage'), 403);
        $data = $request->validate([
            'property_id' => ['required', 'integer', Rule::exists('properties', 'id')->where('organization_id', $organization->id)],
            'name' => ['required', 'string', 'max:100'], 'code' => ['required', 'string', 'max:40', Rule::unique('property_blocks', 'code')->where('property_id', $request->input('property_id'))],
        ]);
        $block = PropertyBlock::create($data + ['organization_id' => $organization->id]);
        $audit->record($organization->id, $request->user()->id, 'property.block_created', $block, null, $block->only(['property_id', 'name', 'code']), $request);

        return to_route('workspace.structure')->with('success', 'Property block created.');
    }

    public function storeFloor(Request $request, CurrentOrganization $organizations, AuditLogger $audit): RedirectResponse
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization && $request->user()->hasOrganizationPermission($organization, 'properties.manage'), 403);
        $data = $request->validate([
            'property_id' => ['required', 'integer', Rule::exists('properties', 'id')->where('organization_id', $organization->id)],
            'block_id' => ['nullable', 'integer', Rule::exists('property_blocks', 'id')->where('organization_id', $organization->id)->where('property_id', $request->input('property_id'))],
            'name' => ['required', 'string', 'max:80'], 'level' => ['nullable', 'integer', 'between:-10,200'],
        ]);
        $floor = PropertyFloor::create($data + ['organization_id' => $organization->id]);
        $audit->record($organization->id, $request->user()->id, 'property.floor_created', $floor, null, $floor->only(['property_id', 'block_id', 'name', 'level']), $request);

        return to_route('workspace.structure')->with('success', 'Property floor created.');
    }

    public function updateBlock(Request $request, PropertyBlock $block, CurrentOrganization $organizations, AuditLogger $audit): RedirectResponse
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization && $block->organization_id === $organization->id, 404);
        abort_unless($request->user()->hasOrganizationPermission($organization, 'properties.manage'), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'code' => ['required', 'string', 'max:40', Rule::unique('property_blocks', 'code')->where('property_id', $block->property_id)->ignore($block->id)]]);
        $before = $block->only(['name', 'code']);
        $block->update($data);
        $audit->record($organization->id, $request->user()->id, 'property.block_updated', $block, $before, $block->only(['name', 'code']), $request);

        return to_route('workspace.structure')->with('success', 'Property block updated.');
    }

    public function updateFloor(Request $request, PropertyFloor $floor, CurrentOrganization $organizations, AuditLogger $audit): RedirectResponse
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization && $floor->organization_id === $organization->id, 404);
        abort_unless($request->user()->hasOrganizationPermission($organization, 'properties.manage'), 403);
        $data = $request->validate(['block_id' => ['nullable', 'integer', Rule::exists('property_blocks', 'id')->where('organization_id', $organization->id)->where('property_id', $floor->property_id)], 'name' => ['required', 'string', 'max:80'], 'level' => ['nullable', 'integer', 'between:-10,200']]);
        $before = $floor->only(['block_id', 'name', 'level']);
        $floor->update($data);
        $audit->record($organization->id, $request->user()->id, 'property.floor_updated', $floor, $before, $floor->only(['block_id', 'name', 'level']), $request);

        return to_route('workspace.structure')->with('success', 'Property floor updated.');
    }

    public function destroyBlock(Request $request, PropertyBlock $block, CurrentOrganization $organizations, AuditLogger $audit): RedirectResponse
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization && $block->organization_id === $organization->id, 404);
        abort_unless($request->user()->hasOrganizationPermission($organization, 'properties.manage'), 403);
        if ($block->floors()->exists() || $block->units()->exists()) {
            return back()->withErrors(['block' => 'This block has floors or units and cannot be deleted.']);
        }
        $before = $block->only(['property_id', 'name', 'code']);
        $block->delete();
        $audit->record($organization->id, $request->user()->id, 'property.block_deleted', $block, $before, null, $request);

        return to_route('workspace.structure')->with('success', 'Property block deleted.');
    }

    public function destroyFloor(Request $request, PropertyFloor $floor, CurrentOrganization $organizations, AuditLogger $audit): RedirectResponse
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization && $floor->organization_id === $organization->id, 404);
        abort_unless($request->user()->hasOrganizationPermission($organization, 'properties.manage'), 403);
        if ($floor->units()->exists()) {
            return back()->withErrors(['floor' => 'This floor has units and cannot be deleted.']);
        }
        $before = $floor->only(['property_id', 'block_id', 'name', 'level']);
        $floor->delete();
        $audit->record($organization->id, $request->user()->id, 'property.floor_deleted', $floor, $before, null, $request);

        return to_route('workspace.structure')->with('success', 'Property floor deleted.');
    }
}
