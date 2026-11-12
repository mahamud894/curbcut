<?php

namespace App\Scanning;

class UrlTools
{
    /** Normalise a URL so the same page is always stored the same way. */
    public static function normalize(string $url): ?string
    {
        $parts = parse_url(trim($url));
        if (! $parts || empty($parts['host']) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        $port = isset($parts['port']) && ! in_array([$scheme, $parts['port']], [['http', 80], ['https', 443]], true) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '/';
        $path = $path === '' ? '/' : preg_replace('#/{2,}#', '/', $path);
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';

        return "{$scheme}://{$host}{$port}{$path}{$query}";
    }

    /** Resolve a link found on $base into an absolute URL. */
    public static function resolve(string $href, string $base): ?string
    {
        $href = trim($href);
        if ($href === '' || preg_match('#^(mailto|tel|javascript|data):#i', $href) || str_starts_with($href, '#')) {
            return null;
        }
        if (preg_match('#^https?://#i', $href)) {
            return self::normalize(strtok($href, '#'));
        }

        $b = parse_url($base);
        if (! $b || empty($b['host'])) {
            return null;
        }
        $origin = $b['scheme'].'://'.$b['host'].(isset($b['port']) ? ':'.$b['port'] : '');
        $href = strtok($href, '#');
        if (str_starts_with($href, '//')) {
            return self::normalize($b['scheme'].':'.$href);
        }
        if (str_starts_with($href, '/')) {
            return self::normalize($origin.$href);
        }

        $dir = preg_replace('#/[^/]*$#', '/', $b['path'] ?? '/');
        $path = $dir.$href;
        $segments = [];
        foreach (explode('/', parse_url($path, PHP_URL_PATH) ?? '') as $segment) {
            if ($segment === '..') {
                array_pop($segments);
            } elseif ($segment !== '.') {
                $segments[] = $segment;
            }
        }
        $query = parse_url($path, PHP_URL_QUERY);

        return self::normalize($origin.implode('/', $segments).($query ? '?'.$query : ''));
    }

    public static function sameOrigin(string $a, string $b): bool
    {
        $pa = parse_url($a);
        $pb = parse_url($b);

        return isset($pa['host'], $pb['host'])
            && strtolower($pa['host']) === strtolower($pb['host'])
            && ($pa['scheme'] ?? '') === ($pb['scheme'] ?? '')
            && ($pa['port'] ?? null) === ($pb['port'] ?? null);
    }

    /** True when the host resolves only to public addresses. */
    public static function isPublicHost(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! $host) {
            return false;
        }
        $host = trim($host, '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if ($ips === []) {
            return false;
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }

    public static function isAllowedTarget(string $url): bool
    {
        return config('curbcut.allow_private_networks') || self::isPublicHost($url);
    }
}
