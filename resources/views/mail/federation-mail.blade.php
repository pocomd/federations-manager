<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }}</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        .wrapper { max-width: 640px; margin: 30px auto; background: #fff; border-radius: 6px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .header { background: #1a3c5e; color: #fff; padding: 24px 32px; font-size: 18px; font-weight: bold; }
        .content { padding: 32px; color: #333; font-size: 15px; line-height: 1.7; }
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
            {!! nl2br(e($body)) !!}
        </div>
        <div class="footer">
            <a href="{{ \App\Models\SystemPreference::get('app_url', config('app.url')) }}">
                {{ \App\Models\SystemPreference::get('app_url', config('app.url')) }}
            </a>
        </div>
    </div>
</body>
</html>
