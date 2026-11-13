<?php

namespace App\Http\Controllers;

use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\Role;
use App\Services\AuditLogger;
use App\Services\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NotificationWorkspaceController extends Controller
{
    public function index(Request $request, CurrentOrganization $organizations): View
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization && $request->user()->hasOrganizationPermission($organization, 'notifications.view'), 403);

        return view('workspace.notifications', [
            'organization' => $organization,
            'organizations' => $request->user()->organizations()->where('organizations.status', 'active')->orderBy('name')->get(),
            'roleName' => Role::find($organization->users()->where('users.id', $request->user()->id)->first()?->pivot?->role_id)?->name,
            'logs' => NotificationLog::where('organization_id', $organization->id)->latest()->paginate(15),
            'templates' => NotificationTemplate::query()->where(fn ($query) => $query->where('organization_id', $organization->id)->orWhereNull('organization_id'))->orderBy('channel')->orderBy('key')->get(),
            'currentModule' => 'notifications',
        ]);
    }

    public function send(Request $request, CurrentOrganization $organizations, AuditLogger $audit): RedirectResponse
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization && $request->user()->hasOrganizationPermission($organization, 'notifications.manage'), 403);
        $data = $request->validate([
            'channel' => ['required', Rule::in(['email', 'sms', 'whatsapp'])],
            'recipient' => ['required', 'string', 'max:190'],
            'subject' => ['nullable', 'string', 'max:180'],
            'message' => ['nullable', 'string', 'max:5000', 'required_without:template_id'],
            'template_id' => ['nullable', 'integer', Rule::exists('notification_templates', 'id')->where(fn ($query) => $query->where('organization_id', $organization->id)->orWhereNull('organization_id'))],
        ]);

        $template = ! empty($data['template_id']) ? NotificationTemplate::query()->where(fn ($query) => $query->where('organization_id', $organization->id)->orWhereNull('organization_id'))->where('channel', $data['channel'])->where('is_active', true)->find($data['template_id']) : null;
        if (! empty($data['template_id']) && ! $template) {
            throw ValidationException::withMessages(['template_id' => 'Choose an active message template for the selected channel.']);
        }
        $message = $data['message'] ?: $template?->body;
        $subject = $data['subject'] ?: $template?->subject;
        $message = str_replace(['{{organization}}', '{{recipient}}'], [$organization->name, $data['recipient']], (string) $message);
        $subject = str_replace(['{{organization}}', '{{recipient}}'], [$organization->name, $data['recipient']], (string) $subject);

        $log = NotificationLog::create([
            'organization_id' => $organization->id, 'user_id' => $request->user()->id,
            'channel' => $data['channel'], 'recipient' => $data['recipient'], 'template_key' => $template?->key, 'status' => 'queued',
        ]);

        if ($data['channel'] !== 'email') {
            $log->update(['status' => 'failed', 'error_message' => strtoupper($data['channel']).' provider is not configured.']);

            return to_route('workspace.module', 'notifications')->with('error', strtoupper($data['channel']).' delivery needs a provider configuration. No message was sent.');
        }

        $email = validator(['recipient' => $data['recipient']], ['recipient' => ['required', 'email']])->validate()['recipient'];
        try {
            Mail::raw($message, function ($mail) use ($email, $subject, $organization): void {
                $mail->to($email)->subject($subject ?: 'UtilityFlow update from '.$organization->name);
            });
            $log->update(['status' => 'sent', 'sent_at' => now()]);
            $audit->record($organization->id, $request->user()->id, 'notification.sent', $log, null, ['channel' => 'email', 'recipient' => $email], $request);
        } catch (\Throwable $exception) {
            $log->update(['status' => 'failed', 'error_message' => mb_substr($exception->getMessage(), 0, 1000)]);

            return to_route('workspace.module', 'notifications')->with('error', 'Email could not be delivered. Check your mail provider configuration.');
        }

        return to_route('workspace.module', 'notifications')->with('success', 'Email queued for delivery.');
    }

    public function storeTemplate(Request $request, CurrentOrganization $organizations, AuditLogger $audit): RedirectResponse
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization && $request->user()->hasOrganizationPermission($organization, 'notifications.manage'), 403);
        $data = $request->validate([
            'key' => ['required', 'alpha_dash:ascii', 'max:100', Rule::unique('notification_templates', 'key')->where('organization_id', $organization->id)->where('channel', $request->input('channel'))],
            'channel' => ['required', Rule::in(['email', 'sms', 'whatsapp'])], 'subject' => ['nullable', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:5000'], 'is_active' => ['nullable', 'boolean'],
        ]);
        $template = NotificationTemplate::create($data + ['organization_id' => $organization->id, 'is_active' => $request->boolean('is_active')]);
        $audit->record($organization->id, $request->user()->id, 'notification_template.created', $template, null, $template->only(['key', 'channel', 'subject', 'is_active']), $request);

        return to_route('workspace.module', 'notifications')->with('success', 'Notification template created.');
    }

    public function updateTemplate(Request $request, NotificationTemplate $template, CurrentOrganization $organizations, AuditLogger $audit): RedirectResponse
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization && $template->organization_id === $organization->id, 404);
        abort_unless($request->user()->hasOrganizationPermission($organization, 'notifications.manage'), 403);
        $data = $request->validate([
            'key' => ['required', 'alpha_dash:ascii', 'max:100', Rule::unique('notification_templates', 'key')->where('organization_id', $organization->id)->where('channel', $request->input('channel'))->ignore($template->id)],
            'channel' => ['required', Rule::in(['email', 'sms', 'whatsapp'])], 'subject' => ['nullable', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:5000'], 'is_active' => ['nullable', 'boolean'],
        ]);
        $before = $template->only(['key', 'channel', 'subject', 'body', 'is_active']);
        $template->update($data + ['is_active' => $request->boolean('is_active')]);
        $audit->record($organization->id, $request->user()->id, 'notification_template.updated', $template, $before, $template->only(['key', 'channel', 'subject', 'is_active']), $request);

        return to_route('workspace.module', 'notifications')->with('success', 'Notification template updated.');
    }

    public function destroyTemplate(Request $request, NotificationTemplate $template, CurrentOrganization $organizations, AuditLogger $audit): RedirectResponse
    {
        $organization = $organizations->resolve($request->user());
        abort_unless($organization && $template->organization_id === $organization->id, 404);
        abort_unless($request->user()->hasOrganizationPermission($organization, 'notifications.manage'), 403);
        $before = $template->only(['key', 'channel', 'subject', 'is_active']);
        $template->delete();
        $audit->record($organization->id, $request->user()->id, 'notification_template.deleted', $template, $before, null, $request);

        return to_route('workspace.module', 'notifications')->with('success', 'Notification template deleted.');
    }
}
