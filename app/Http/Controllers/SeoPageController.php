<?php

namespace App\Http\Controllers;

use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class SeoPageController extends Controller
{
    public function show(string $page): Response
    {
        $content = config("seo.pages.{$page}");

        abort_if(! is_array($content), 404);

        return Inertia::render('Seo/Show', [
            'seoPage' => $content,
            'relatedPages' => $this->relatedPages($page),
        ])->withViewData([
            'seo' => [
                'title' => $content['meta_title'],
                'description' => $content['description'],
                'image' => $content['image'],
                'url' => url($content['path']),
            ],
        ]);
    }

    /**
     * @return array<int, array{title: string, path: string}>
     */
    private function relatedPages(string $currentPage): array
    {
        return collect(config('seo.pages', []))
            ->except($currentPage)
            ->map(fn (array $page): array => Arr::only($page, ['title', 'path']))
            ->values()
            ->all();
    }
}
