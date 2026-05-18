<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

/**
 * Platform settings — branding, sign-up gate, email-tracking URL,
 * maintenance mode. All platform-wide concerns; nothing dealer-specific.
 */
class SettingsController extends Controller
{
    public function edit()
    {
        return view('admin.settings.edit', [
            'settings' => PlatformSetting::singleton(),
        ]);
    }

    public function update(Request $request)
    {
        $settings = PlatformSetting::singleton();

        $data = $request->validate([
            'platform_name'           => 'nullable|string|max:80',
            'logo'                    => 'nullable|image|max:2048',
            'signups_enabled'         => 'nullable|boolean',
            'email_tracking_base_url' => 'nullable|url|max:255',
            'maintenance_mode'        => 'nullable|boolean',
            'maintenance_message'     => 'nullable|string|max:500',
        ]);

        $logoPath = $settings->logo_path;
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('platform', 'public');
        }

        // Checkboxes don't post a value when unchecked; coerce explicitly so
        // toggling off actually sticks.
        $payload = [
            'platform_name'           => $data['platform_name'] ?? 'Nautiqs',
            'logo_path'               => $logoPath,
            'signups_enabled'         => (bool) ($data['signups_enabled'] ?? false),
            'email_tracking_base_url' => $data['email_tracking_base_url'] ?? null,
            'maintenance_mode'        => (bool) ($data['maintenance_mode'] ?? false),
            'maintenance_message'     => $data['maintenance_message'] ?? null,
        ];
        $before = $settings->only(array_keys($payload));
        $settings->update($payload);

        AuditLogger::record('platform.settings.update',
            target: $settings, before: $before, after: $payload,
            targetLabel: 'Platform settings');

        return back()->with('status', __('Platform settings saved.'));
    }
}
