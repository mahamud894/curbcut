<?php

use App\Scanning\UrlTools;

test('urls are normalised consistently', function () {
    expect(UrlTools::normalize('HTTPS://Example.com'))->toBe('https://example.com/')
        ->and(UrlTools::normalize('https://example.com:443/a//b?x=1'))->toBe('https://example.com/a/b?x=1')
        ->and(UrlTools::normalize('http://example.com:8080/'))->toBe('http://example.com:8080/')
        ->and(UrlTools::normalize('ftp://example.com/'))->toBeNull()
        ->and(UrlTools::normalize('not a url'))->toBeNull();
});

test('relative links resolve against the page they were found on', function () {
    $base = 'https://example.com/blog/post.html';
    expect(UrlTools::resolve('/about', $base))->toBe('https://example.com/about')
        ->and(UrlTools::resolve('other.html', $base))->toBe('https://example.com/blog/other.html')
        ->and(UrlTools::resolve('../contact#form', $base))->toBe('https://example.com/contact')
        ->and(UrlTools::resolve('//cdn.example.com/x', $base))->toBe('https://cdn.example.com/x')
        ->and(UrlTools::resolve('mailto:a@b.c', $base))->toBeNull()
        ->and(UrlTools::resolve('#top', $base))->toBeNull();
});

test('private and loopback hosts are not public', function () {
    expect(UrlTools::isPublicHost('http://127.0.0.1/'))->toBeFalse()
        ->and(UrlTools::isPublicHost('http://10.0.0.5/'))->toBeFalse()
        ->and(UrlTools::isPublicHost('http://[::1]/'))->toBeFalse()
        ->and(UrlTools::isPublicHost('http://8.8.8.8/'))->toBeTrue();
});
