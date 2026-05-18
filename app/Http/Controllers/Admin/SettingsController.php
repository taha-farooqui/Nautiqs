<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Platform settings — branding + default email templates that new dealers
 * inherit on signup. Single page, two sections.
 */
class SettingsController extends Controller
{
    /** Template types shown in the editor. Subject + body per type. */
    private const TEMPLATE_TYPES = [
        'quote'              => ['icon' => 'ri-file-list-3-line',    'label' => 'Quote sending'],
        'order_confirmation' => ['icon' => 'ri-trophy-line',         'label' => 'Order confirmation'],
        'follow_up'          => ['icon' => 'ri-mail-send-line',      'label' => 'Follow-up reminder'],
    ];

    public function edit()
    {
        $settings = PlatformSetting::singleton();
        return view('admin.settings.edit', [
            'settings'      => $settings,
            'templateTypes' => self::TEMPLATE_TYPES,
        ]);
    }

    public function update(Request $request)
    {
        $settings = PlatformSetting::singleton();

        $data = $request->validate([
            'platform_name'        => 'nullable|string|max:80',
            'email_sender_name'    => 'nullable|string|max:120',
            'email_sender_address' => 'nullable|email|max:160',
            'logo'                 => 'nullable|image|max:2048',
        ]);

        // Handle logo upload separately so we can record the file move
        // before the model save fires.
        $logoPath = $settings->logo_path;
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('platform', 'public');
        }

        $payload = [
            'platform_name'        => $data['platform_name'] ?? 'Nautiqs',
            'email_sender_name'    => $data['email_sender_name'] ?? null,
            'email_sender_address' => $data['email_sender_address'] ?? null,
            'logo_path'            => $logoPath,
        ];
        $before = $settings->only(array_keys($payload));
        $settings->update($payload);

        AuditLogger::record('platform.settings.update',
            target: $settings, before: $before, after: $payload,
            targetLabel: 'Platform branding');

        return back()->with('status', __('Platform settings saved.'));
    }

    public function updateTemplate(Request $request, string $type)
    {
        if (! array_key_exists($type, self::TEMPLATE_TYPES)) {
            abort(404);
        }

        $data = $request->validate([
            'subject' => 'required|string|max:300',
            'body'    => 'required|string|max:50000',
        ]);

        $settings  = PlatformSetting::singleton();
        $templates = $settings->default_email_templates ?? [];
        $before    = $templates[$type] ?? null;
        $templates[$type] = $data;
        $settings->update(['default_email_templates' => $templates]);

        AuditLogger::record('platform.template.update',
            target: $settings, before: $before, after: $data,
            targetLabel: 'Default template: ' . self::TEMPLATE_TYPES[$type]['label']);

        return back()->with('status', __('Default :type template saved.', [
            'type' => __(self::TEMPLATE_TYPES[$type]['label']),
        ]));
    }
}
