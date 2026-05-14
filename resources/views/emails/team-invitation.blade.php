<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('Invitation') }}</title>
</head>
<body style="font-family: Inter, Arial, sans-serif; color: #1f2937; background: #f8fafc; margin: 0; padding: 24px;">
    <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e5e7eb;">

        <div style="padding: 24px 28px; border-bottom: 3px solid #0e4f79;">
            <span style="font-size: 22px; font-weight: 700; color: #0e4f79;">{{ $companyName }}</span>
        </div>

        <div style="padding: 28px; font-size: 14px; line-height: 1.6;">
            <p style="margin: 0 0 16px;">{{ __('Hello :name,', ['name' => $invite->name]) }}</p>

            <p style="margin: 0 0 16px;">
                {{ __(':inviter has invited you to join :company on Nautiqs as :role.', [
                    'inviter' => $invite->invited_by_name,
                    'company' => $companyName,
                    'role'    => $invite->role === \App\Models\User::ROLE_TENANT_ADMIN ? __('Admin') : __('Salesperson'),
                ]) }}
            </p>

            <p style="margin: 0 0 24px;">
                {{ __('Click the button below to set your password and access the workspace.') }}
            </p>

            <p style="margin: 0 0 24px; text-align: center;">
                <a href="{{ $acceptUrl }}"
                   style="display: inline-block; padding: 12px 28px; background: #0e4f79; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px;">
                    {{ __('Accept invitation') }}
                </a>
            </p>

            <p style="margin: 0 0 8px; font-size: 12px; color: #6b7280;">
                {{ __('Or copy this link into your browser:') }}
            </p>
            <p style="margin: 0 0 24px; font-size: 12px; color: #6b7280; word-break: break-all;">
                {{ $acceptUrl }}
            </p>

            <p style="margin: 0; font-size: 12px; color: #9ca3af;">
                {{ __('This invitation expires on :date.', ['date' => $invite->expires_at?->translatedFormat('F j, Y')]) }}
                {{ __("If you didn't expect this email, you can safely ignore it.") }}
            </p>
        </div>

        <div style="padding: 16px 28px; background: #f8fafc; border-top: 1px solid #e5e7eb; font-size: 12px; color: #6b7280;">
            {{ __('Sent from') }} {{ $companyName }}
        </div>
    </div>
</body>
</html>
