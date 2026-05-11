<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @php
            $seoTitle = $seo['title'] ?? config('seo.title', config('app.name', 'Downloora'));
            $seoDescription = $seo['description'] ?? config('seo.description');
            $seoImage = asset(ltrim((string) ($seo['image'] ?? config('seo.image', '/image1.jpg')), '/'));
            $seoUrl = $seo['url'] ?? url()->current();
            $schema = [
                '@context' => 'https://schema.org',
                '@type' => 'WebApplication',
                'name' => config('app.name', 'Downloora'),
                'url' => config('app.url'),
                'description' => $seoDescription,
                'applicationCategory' => 'CloudStorageApplication',
                'operatingSystem' => 'Web',
                'image' => $seoImage,
                'offers' => [
                    '@type' => 'Offer',
                    'price' => '5.99',
                    'priceCurrency' => 'EUR',
                ],
            ];
        @endphp
        <meta name="description" content="{{ $seoDescription }}">
        <meta name="robots" content="{{ config('seo.robots', 'index, follow') }}">
        <link rel="canonical" href="{{ $seoUrl }}">
        <meta property="og:site_name" content="{{ config('app.name', 'Downloora') }}">
        <meta property="og:type" content="website">
        <meta property="og:title" content="{{ $seoTitle }}">
        <meta property="og:description" content="{{ $seoDescription }}">
        <meta property="og:url" content="{{ $seoUrl }}">
        <meta property="og:image" content="{{ $seoImage }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $seoTitle }}">
        <meta name="twitter:description" content="{{ $seoDescription }}">
        <meta name="twitter:image" content="{{ $seoImage }}">
        <script type="application/ld+json">@json($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>

        <link rel="icon" href="/logo_icon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/logo_icon.svg">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        <x-inertia::head>
            <title>{{ $seoTitle }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <noscript>
            <nav aria-label="Popular Downloora pages">
                @foreach (config('seo.pages', []) as $seoPage)
                    <a href="{{ $seoPage['path'] }}">{{ $seoPage['title'] }}</a>
                @endforeach
            </nav>
        </noscript>
        <x-inertia::app />
    </body>
</html>
