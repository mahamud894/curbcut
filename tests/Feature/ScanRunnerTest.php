<?php

use App\Models\Issue;
use App\Models\Scan;
use App\Models\Site;
use App\Scanning\Scanner;
use App\Scanning\ScanRunner;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeScanner;

beforeEach(function () {
    config(['curbcut.allow_private_networks' => true]);
    Http::fake([
        'https://example.test/sitemap.xml' => Http::response(
            '<?xml version="1.0"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.
            '<url><loc>https://example.test/</loc></url><url><loc>https://example.test/about</loc></url></urlset>',
            200, ['Content-Type' => 'application/xml'],
        ),
    ]);
    $this->scanner = new FakeScanner;
    app()->instance(Scanner::class, $this->scanner);
    $this->site = Site::factory()->create();
});

function runScan(Site $site): Scan
{
    $scan = $site->scans()->create(['status' => Scan::QUEUED]);
    app(ScanRunner::class)->run($scan);

    return $scan->fresh();
}

test('the first scan records every violation as a new open issue', function () {
    $this->scanner->results['https://example.test/'] = ['status' => 'ok', 'httpStatus' => 200, 'passes' => 20, 'violations' => [
        FakeScanner::violation('image-alt', 'critical', ['img.hero', 'img.logo']),
        FakeScanner::violation('color-contrast', 'serious', ['p.muted']),
    ]];

    $scan = runScan($this->site);

    expect($scan->status)->toBe(Scan::COMPLETED)
        ->and($scan->pages_total)->toBe(2)
        ->and($scan->pages_scanned)->toBe(2)
        ->and($scan->issues_open)->toBe(3)
        ->and($scan->issues_new)->toBe(3)
        ->and($scan->issues_fixed)->toBe(0)
        ->and($scan->critical)->toBe(2)
        ->and($scan->serious)->toBe(1)
        // Home page: 100 - (10 + 10 + 5) = 75, about page: 100 -> average 88 (87.5 rounded)
        ->and($scan->score)->toBe(88)
        ->and($this->site->fresh()->last_scanned_at)->not->toBeNull();
});

test('a later scan marks missing issues as fixed and counts only new ones', function () {
    $home = 'https://example.test/';
    $this->scanner->results[$home] = ['status' => 'ok', 'httpStatus' => 200, 'violations' => [
        FakeScanner::violation('image-alt', 'critical', ['img.hero', 'img.logo']),
    ]];
    runScan($this->site);

    // img.logo was fixed, a new form label problem appeared.
    $this->scanner->results[$home] = ['status' => 'ok', 'httpStatus' => 200, 'violations' => [
        FakeScanner::violation('image-alt', 'critical', ['img.hero']),
        FakeScanner::violation('label', 'critical', ['input#email']),
    ]];
    $second = runScan($this->site);

    expect($second->issues_new)->toBe(1)
        ->and($second->issues_fixed)->toBe(1)
        ->and($second->issues_open)->toBe(2)
        ->and(Issue::where('target', 'img.logo')->value('status'))->toBe('fixed')
        ->and(Issue::where('target', 'img.logo')->value('fixed_in_scan_id'))->toBe($second->id);
});

test('an issue that comes back after being fixed counts as new again', function () {
    $home = 'https://example.test/';
    $this->scanner->results[$home] = ['status' => 'ok', 'httpStatus' => 200, 'violations' => [FakeScanner::violation('button-name', 'critical', ['button.menu'])]];
    runScan($this->site);
    $this->scanner->results[$home] = ['status' => 'ok', 'httpStatus' => 200, 'violations' => []];
    runScan($this->site);
    $this->scanner->results[$home] = ['status' => 'ok', 'httpStatus' => 200, 'violations' => [FakeScanner::violation('button-name', 'critical', ['button.menu'])]];
    $third = runScan($this->site);

    expect($third->issues_new)->toBe(1)
        ->and(Issue::count())->toBe(1)
        ->and(Issue::first()->status)->toBe('open');
});

test('issues on a page that fails to load stay open', function () {
    $home = 'https://example.test/';
    $this->scanner->results[$home] = ['status' => 'ok', 'httpStatus' => 200, 'violations' => [FakeScanner::violation('image-alt', 'critical', ['img.hero'])]];
    runScan($this->site);

    $this->scanner->results[$home] = ['status' => 'ok', 'httpStatus' => 503, 'violations' => []];
    $second = runScan($this->site);

    expect($second->issues_fixed)->toBe(0)
        ->and($second->pages_failed)->toBe(1)
        ->and(Issue::first()->status)->toBe('open');
});

test('a scan fails cleanly when no page can be scanned', function () {
    foreach (['https://example.test/', 'https://example.test/about'] as $url) {
        $this->scanner->results[$url] = ['status' => 'failed', 'error' => 'net::ERR_NAME_NOT_RESOLVED'];
    }

    $scan = runScan($this->site);

    expect($scan->status)->toBe(Scan::FAILED)->and($scan->error)->toContain('None of the pages');
});

test('the scheduler only queues sites that are due', function () {
    Queue::fake();
    $due = Site::factory()->create(['scan_frequency' => 'daily', 'last_scanned_at' => now()->subDays(2)]);
    Site::factory()->create(['scan_frequency' => 'weekly', 'last_scanned_at' => now()->subDays(2)]);
    Site::factory()->create(['scan_frequency' => 'manual', 'last_scanned_at' => null]);

    $this->artisan('curbcut:schedule-scans')->expectsOutputToContain('Queued 2 scheduled scan(s).');

    // The site from beforeEach (weekly, never scanned) and the overdue daily site.
    expect($due->scans()->count())->toBe(1)
        ->and(Scan::where('trigger', 'scheduled')->count())->toBe(2);
});
