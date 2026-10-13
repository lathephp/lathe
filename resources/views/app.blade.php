<!DOCTYPE html>
<html lang="{{ $page['props']['locale'] ?? 'en' }}" dir="{{ ($page['props']['rtl'] ?? false) ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('lathe.name') }}</title>
        @latheStyles
    </head>
    <body>
        {{-- Inertia's page data, written here so it carries the CSP nonce like every other tag. --}}
        <script data-page="app" type="application/json"@if (Illuminate\Support\Facades\Vite::cspNonce()) nonce="{{ Illuminate\Support\Facades\Vite::cspNonce() }}"@endif>{!! json_encode($page, JSON_HEX_TAG) !!}</script>
        <div id="app"></div>
        <noscript>Lathe needs JavaScript.</noscript>
        @latheScripts
    </body>
</html>
