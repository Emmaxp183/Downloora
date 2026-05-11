<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

class SeoController extends Controller
{
    public function robots(): Response
    {
        return response(implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Sitemap: '.$this->absoluteUrl('/sitemap.xml'),
        ])."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function sitemap(): Response
    {
        $urls = collect(config('seo.sitemap', []))
            ->filter(fn (array $entry): bool => Route::has($entry['route']))
            ->map(fn (array $entry): array => [
                'loc' => $this->absoluteUrl(route($entry['route'], [], false)),
                'lastmod' => now()->toDateString(),
                'changefreq' => $entry['changefreq'] ?? 'weekly',
                'priority' => $entry['priority'] ?? '0.8',
            ])
            ->values();

        return response()->view('sitemap', [
            'urls' => $urls,
        ])->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    private function absoluteUrl(string $path): string
    {
        return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
    }
}
