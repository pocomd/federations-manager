<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitation — {{ \App\Models\SystemPreference::get('app_name', config('app.name')) }}</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        .wrapper { max-width: 640px; margin: 30px auto; background: #fff; border-radius: 6px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .header { background: #1a3c5e; color: #fff; padding: 24px 32px; font-size: 18px; font-weight: bold; }
        .content { padding: 32px; color: #333; font-size: 15px; line-height: 1.7; }
        .btn { display: inline-block; margin: 24px 0 8px; padding: 12px 28px; background: #e87722; color: #fff; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 15px; }
        .meta { margin-top: 24px; padding: 16px; background: #f8f9fa; border-radius: 4px; font-size: 13px; color: #555; }
        .footer { background: #f4f4f4; padding: 16px 32px; font-size: 12px; color: #888; border-top: 1px solid #e0e0e0; }
        a { color: #1a3c5e; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            {{ \App\Models\SystemPreference::get('app_name', config('app.name')) }}
        </div>
        <div class="content">
            <p>You have been invited to join <strong>{{ $invitation->federation?->name ?? 'the federation' }}</strong>.</p>

            @if ($invitation->entity)
                <p>You are being added as a co-manager of: <strong>{{ $invitation->entity->getDisplayName() ?? $invitation->entity->entity_id }}</strong>.</p>
            @endif

            <p>Click the button below to create your account and accept the invitation:</p>

            <a href="{{ route('register.invitation.show', $invitation->token) }}" class="btn">
                Accept Invitation
            </a>

            <div class="meta">
                <strong>Invited email:</strong> {{ $invitation->email }}<br>
                @if ($invitation->expires_at)
                    <strong>Expires:</strong> {{ $invitation->expires_at->timezone(config('app.timezone'))->format('d M Y H:i') }}
                @endif
            </div>

            <p style="margin-top:24px; font-size:13px; color:#777;">
                If you did not expect this invitation, you can ignore this email.
            </p>
        </div>
        <div class="footer">
            <a href="{{ \App\Models\SystemPreference::get('app_url', config('app.url')) }}">
                {{ \App\Models\SystemPreference::get('app_url', config('app.url')) }}
            </a>
        </div>
    </div>
</body>
</html>
