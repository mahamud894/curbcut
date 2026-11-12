<?php

use App\Models\Site;
use App\Scanning\PageDiscovery;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => config(['curbcut.allow_private_networks' => true]));

test('pages come from a sitemap index, filtered to the same site', function () {
    Http::fake([
        'https://shop.test/sitemap.xml' => Http::response('<sitemapindex><sitemap><loc>https://shop.test/pages.xml</loc></sitemap></sitemapindex>'),
        'https://shop.test/pages.xml' => Http::response('<urlset>'.
            '<url><loc>https://shop.test/</loc></url><url><loc>https://shop.test/cart</loc></url>'.
            '<url><loc>https://other.test/</loc></url><url><loc>https://shop.test/brochure.pdf</loc></url></urlset>'),
    ]);
    $site = Site::factory()->make(['url' => 'https://shop.test/', 'max_pages' => 10]);

    expect(app(PageDiscovery::class)->discover($site))->toBe(['https://shop.test/', 'https://shop.test/cart']);
});

test('without a sitemap the crawler follows same-site links up to the page limit', function () {
    Http::fake([
        'https://shop.test/sitemap.xml' => Http::response('Not found', 404),
        'https://shop.test/' => Http::response('<a href="/a">A</a><a href="b">B</a><a href="https://elsewhere.test/">X</a><a href="mailto:x@y.z">M</a>', 200, ['Content-Type' => 'text/html']),
        'https://shop.test/a' => Http::response('<a href="/c">C</a>', 200, ['Content-Type' => 'text/html']),
        'https://shop.test/b' => Http::response('ok', 200, ['Content-Type' => 'text/html']),
        'https://shop.test/c' => Http::response('ok', 200, ['Content-Type' => 'text/html']),
    ]);
    $site = Site::factory()->make(['url' => 'https://shop.test/', 'max_pages' => 3]);

    expect(app(PageDiscovery::class)->discover($site))->toBe(['https://shop.test/', 'https://shop.test/a', 'https://shop.test/b']);
});
