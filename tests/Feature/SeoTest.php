<?php

test('landing page includes crawler metadata', function () {
    $this->get(route('home'))
        ->assertSuccessful()
        ->assertSee('<meta name="description" content="'.e(config('seo.description')).'">', false)
        ->assertSee('<meta name="robots" content="index, follow">', false)
        ->assertSee('<meta property="og:title" content="'.e(config('seo.title')).'">', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
        ->assertSee('application/ld+json', false)
        ->assertSee('CloudStorageApplication', false)
        ->assertSee('/cloud-torrent-storage', false)
        ->assertSee('/torrent-to-cloud', false)
        ->assertSee('/seedr-alternative', false)
        ->assertSee('/private-torrent-cloud', false)
        ->assertSee('/download-social-media-videos', false)
        ->assertSee('/one-click-torrent-seeding', false);
});

test('public seo pages include page specific metadata', function (string $pageKey) {
    $page = config("seo.pages.{$pageKey}");

    $this->get(route($page['route']))
        ->assertSuccessful()
        ->assertSee('<title>'.e($page['meta_title']).'</title>', false)
        ->assertSee('<meta name="description" content="'.e($page['description']).'">', false)
        ->assertSee('<link rel="canonical" href="'.url($page['path']).'">', false)
        ->assertSee(e($page['heading']), false)
        ->assertSee(e($page['features'][0]['title']), false)
        ->assertSee(e($page['questions'][0]['question']), false);
})->with([
    'cloud torrent storage' => 'cloud-torrent-storage',
    'torrent to cloud' => 'torrent-to-cloud',
    'seedr alternative' => 'seedr-alternative',
    'private torrent cloud' => 'private-torrent-cloud',
    'download social media videos' => 'download-social-media-videos',
    'one click torrent seeding' => 'one-click-torrent-seeding',
]);

test('robots file points crawlers to the sitemap', function () {
    config(['app.url' => 'https://downloora.com']);

    $this->get(route('robots'))
        ->assertSuccessful()
        ->assertHeader('content-type', 'text/plain; charset=UTF-8')
        ->assertSee('User-agent: *', false)
        ->assertSee('Allow: /', false)
        ->assertSee('Sitemap: https://downloora.com/sitemap.xml', false);
});

test('sitemap exposes public pages', function () {
    config(['app.url' => 'https://downloora.com']);

    $this->get(route('sitemap'))
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/xml; charset=UTF-8')
        ->assertSee('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', false)
        ->assertSee('<loc>https://downloora.com/</loc>', false)
        ->assertSee('<loc>https://downloora.com/cloud-torrent-storage</loc>', false)
        ->assertSee('<loc>https://downloora.com/torrent-to-cloud</loc>', false)
        ->assertSee('<loc>https://downloora.com/seedr-alternative</loc>', false)
        ->assertSee('<loc>https://downloora.com/private-torrent-cloud</loc>', false)
        ->assertSee('<loc>https://downloora.com/download-social-media-videos</loc>', false)
        ->assertSee('<loc>https://downloora.com/one-click-torrent-seeding</loc>', false)
        ->assertSee('<changefreq>weekly</changefreq>', false)
        ->assertSee('<priority>1.0</priority>', false);
});
