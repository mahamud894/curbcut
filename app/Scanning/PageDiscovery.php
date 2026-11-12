<?php

namespace App\Scanning;

use App\Models\Site;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Finds the pages of a site to scan: from its sitemap when one exists,
 * otherwise by following same-origin links from the start URL.
 */
class PageDiscovery
{
    private const SKIP_EXTENSIONS = '/\.(pdf|jpe?g|png|gif|svg|webp|ico|css|js|json|xml|zip|gz|mp4|mp3|webm|woff2?|ttf|docx?|xlsx?|pptx?)$/i';

    /** @return list<string> */
    public function discover(Site $site): array
    {
        $limit = max(1, min($site->max_pages, (int) config('curbcut.max_pages_limit')));
        $start = UrlTools::normalize($site->url);
        if (! $start) {
            return [];
        }

        $sitemapUrl = $site->sitemap_url ?: UrlTools::resolve('/sitemap.xml', $start);
        $urls = $sitemapUrl ? $this->fromSitemap($sitemapUrl, $start, $limit) : [];
        if ($urls === []) {
            $urls = $this->crawl($start, $limit);
        }

        // Always include the start page first.
        $urls = array_values(array_unique(array_merge([$start], $urls)));

        return array_slice($urls, 0, $limit);
    }

    /** @return list<string> */
    public function fromSitemap(string $sitemapUrl, string $start, int $limit, int $depth = 0): array
    {
        $xml = $this->fetch($sitemapUrl);
        if ($xml === null || ! str_contains($xml, '<')) {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml, options: LIBXML_NONET | LIBXML_NOCDATA);
        libxml_use_internal_errors($previous);
        if ($doc === false) {
            return [];
        }

        $urls = [];
        $name = strtolower($doc->getName());
        foreach ($doc->children() as $entry) {
            $loc = trim((string) ($entry->loc ?? ''));
            if ($loc === '') {
                continue;
            }
            if ($name === 'sitemapindex') {
                if ($depth < 2) {
                    $urls = array_merge($urls, $this->fromSitemap($loc, $start, $limit - count($urls), $depth + 1));
                }
            } elseif (($url = UrlTools::normalize($loc)) && UrlTools::sameOrigin($url, $start) && ! preg_match(self::SKIP_EXTENSIONS, parse_url($url, PHP_URL_PATH) ?? '')) {
                $urls[] = $url;
            }
            if (count($urls) >= $limit) {
                break;
            }
        }

        return array_slice(array_values(array_unique($urls)), 0, $limit);
    }

    /** @return list<string> */
    public function crawl(string $start, int $limit): array
    {
        $queue = [$start];
        $seen = [$start => true];
        $found = [];

        while ($queue !== [] && count($found) < $limit) {
            $url = array_shift($queue);
            $html = $this->fetch($url, htmlOnly: true);
            if ($html === null) {
                continue;
            }
            $found[] = $url;

            preg_match_all('/<a\s[^>]*href\s*=\s*["\']([^"\']+)["\']/i', $html, $matches);
            foreach ($matches[1] as $href) {
                $link = UrlTools::resolve(html_entity_decode($href), $url);
                if (! $link || isset($seen[$link]) || ! UrlTools::sameOrigin($link, $start) || preg_match(self::SKIP_EXTENSIONS, parse_url($link, PHP_URL_PATH) ?? '')) {
                    continue;
                }
                $seen[$link] = true;
                $queue[] = $link;
            }
        }

        return $found;
    }

    private function fetch(string $url, bool $htmlOnly = false): ?string
    {
        if (! UrlTools::isAllowedTarget($url)) {
            return null;
        }

        try {
            $response = Http::timeout(15)
                ->withUserAgent('CurbcutAccessibilityScanner/1.0 (+https://github.com/mahamud894/curbcut)')
                ->get($url);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }
        if ($htmlOnly && ! str_contains(strtolower($response->header('Content-Type')), 'html')) {
            return null;
        }

        return $response->body();
    }
}
