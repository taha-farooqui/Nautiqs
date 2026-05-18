<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

/**
 * Platform settings — branding (platform name + logo). Single page.
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
            'platform_name' => 'nullable|string|max:80',
            'logo'          => 'nullable|image|max:2048',
        ]);

        $logoPath = $settings->logo_path;
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('platform', 'public');
        }

        $payload = [
            'platform_name' => $data['platform_name'] ?? 'Nautiqs',
            'logo_path'     => $logoPath,
        ];
        $before = $settings->only(array_keys($payload));
        $settings->update($payload);

        AuditLogger::record('platform.settings.update',
            target: $settings, before: $before, after: $payload,
            targetLabel: 'Platform branding');

        return back()->with('status', __('Platform settings saved.'));
    }
}
