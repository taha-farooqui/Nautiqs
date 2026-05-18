<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('Maintenance') }} · {{ config('app.name', 'Nautiqs') }}</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400..800&display=swap" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/remixicon@4.6.0/fonts/remixicon.css" rel="stylesheet" />
        @vite(['resources/css/app.css'])
    </head>
    <body class="font-sans antialiased bg-slate-50 text-gray-800 min-h-screen flex items-center justify-center px-4">
        <div class="text-center max-w-md">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-amber-100 text-amber-700 flex items-center justify-center mb-5">
                <i class="ri-tools-line text-3xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('We will be right back') }}</h1>
            <p class="text-sm text-gray-600 mt-3 leading-relaxed">
                {{ $message ?: __('The platform is briefly under maintenance. Please check back in a few minutes.') }}
            </p>
        </div>
    </body>
</html>
