<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Services\AuditLogger;
use App\Services\CurrentOrganization;
use App\Services\DarajaStkPushService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrganizationSettingsController extends Controller
{
    public function index(Request $request, CurrentOrganization $organizations): View
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization, 403);
        abort_unless($request->user()->hasOrganizationPermission($organization, 'settings.view'), 403);

        return view('workspace.settings', [
            'organization' => $organization,
            'colorPalettes' => config('brand_palettes'),
            'organizations' => $request->user()->organizations()->where('organizations.status', 'active')->orderBy('name')->get(),
            'roleName' => Role::find($organization->users()->where('users.id', $request->user()->id)->first()?->pivot?->role_id)?->name,
            'darajaConfigured' => app(DarajaStkPushService::class)->isConfigured($organization),
            'currentModule' => 'settings',
        ]);
    }

    public function update(Request $request, CurrentOrganization $organizations, AuditLogger $audit): RedirectResponse
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization && $request->user()->hasOrganizationPermission($organization, 'settings.manage'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'timezone' => ['required', 'timezone'],
            'color_palette' => ['required', Rule::in(array_keys(config('brand_palettes')))],
            'daraja_environment' => ['nullable', Rule::in(['sandbox', 'production'])],
            'daraja_consumer_key' => ['nullable', 'string', 'max:2048'],
            'daraja_consumer_secret' => ['nullable', 'string', 'max:2048'],
            'daraja_shortcode' => ['nullable', 'string', 'max:32'],
            'daraja_passkey' => ['nullable', 'string', 'max:2048'],
            'daraja_transaction_type' => ['nullable', Rule::in(['CustomerPayBillOnline', 'CustomerBuyGoodsOnline'])],
        ]);
        $darajaInputs = ['daraja_environment', 'daraja_consumer_key', 'daraja_consumer_secret', 'daraja_shortcode', 'daraja_passkey', 'daraja_transaction_type'];
        $daraja = array_intersect_key($data, array_flip($darajaInputs));
        foreach (['daraja_consumer_key', 'daraja_consumer_secret', 'daraja_passkey'] as $secret) {
            if (blank($daraja[$secret] ?? null)) {
                unset($daraja[$secret]);
            }
        }
        $requiredCredentials = [
            $daraja['daraja_consumer_key'] ?? $organization->daraja_consumer_key,
            $daraja['daraja_consumer_secret'] ?? $organization->daraja_consumer_secret,
            $daraja['daraja_shortcode'] ?? $organization->daraja_shortcode,
            $daraja['daraja_passkey'] ?? $organization->daraja_passkey,
        ];
        if (($daraja['daraja_environment'] ?? null) !== null && collect($requiredCredentials)->contains(fn (?string $credential): bool => blank($credential))) {
            return back()->withErrors(['daraja_consumer_key' => 'Complete all Safaricom credentials before enabling M-Pesa.'])->withInput();
        }
        $data = array_merge(collect($data)->except($darajaInputs)->all(), $daraja);
        $before = $organization->only(['name', 'email', 'phone', 'timezone', 'color_palette', 'daraja_environment', 'daraja_shortcode', 'daraja_transaction_type']);
        DB::transaction(function () use ($organization, $data, $audit, $request, $before): void {
            $organization->update($data);
            $audit->record($organization->id, $request->user()->id, 'organization.settings_updated', $organization, $before, $organization->only(array_keys($before)), $request);
        });

        return to_route('workspace.module', 'settings')->with('success', 'Organization settings updated.');
    }
}
