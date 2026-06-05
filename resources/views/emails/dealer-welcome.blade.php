<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('Welcome to Nautiqs') }}</title>
</head>
<body style="font-family: Inter, Arial, sans-serif; color: #1f2937; background: #f8fafc; margin: 0; padding: 24px;">
    <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e5e7eb;">

        <div style="padding: 24px 28px; border-bottom: 3px solid #0e4f79;">
            <span style="font-size: 22px; font-weight: 700; color: #0e4f79;">Nautiqs</span>
        </div>

        <div style="padding: 28px; font-size: 14px; line-height: 1.6;">
            <p style="margin: 0 0 16px;">{{ __('Hello :name,', ['name' => $user->name]) }}</p>

            <p style="margin: 0 0 16px;">
                {{ __('Your dealership account for :company is ready on Nautiqs. To get started, set your password using the secure link below.', [
                    'company' => $companyName,
                ]) }}
            </p>

            <table style="width: 100%; border-collapse: collapse; margin: 0 0 24px; background: #f8fafc; border-radius: 8px;">
                <tr>
                    <td style="padding: 12px 14px; font-size: 12px; color: #6b7280; width: 110px;">{{ __('Email') }}</td>
                    <td style="padding: 12px 14px; font-size: 14px; font-weight: 600;">{{ $user->email }}</td>
                </tr>
            </table>

            <p style="margin: 0 0 24px; text-align: center;">
                <a href="{{ $setupUrl }}"
                   style="display: inline-block; padding: 12px 28px; background: #0e4f79; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px;">
                    {{ __('Set up your password') }}
                </a>
            </p>

            <p style="margin: 0 0 8px; font-size: 12px; color: #6b7280;">
                {{ __('Or copy this link into your browser:') }}
            </p>
            <p style="margin: 0 0 24px; font-size: 12px; color: #6b7280; word-break: break-all;">
                {{ $setupUrl }}
            </p>

            <p style="margin: 0 0 8px; font-size: 13px; color: #b45309; background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; padding: 10px 12px;">
                <strong>{{ __('Heads up:') }}</strong>
                {{ __('This setup link expires in 60 minutes. If it has expired, use “Forgot password” on the login page to get a new one.') }}
            </p>

            <p style="margin: 16px 0 0; font-size: 12px; color: #9ca3af;">
                {{ __("If you didn't expect this email, you can safely ignore it.") }}
            </p>
        </div>

        <div style="padding: 16px 28px; background: #f8fafc; border-top: 1px solid #e5e7eb; font-size: 12px; color: #6b7280;">
            {{ __('Sent from') }} Nautiqs
        </div>
    </div>
</body>
</html>
