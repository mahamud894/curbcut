<?php

use App\Models\Activity;
use App\Models\Issue;
use App\Models\Site;
use App\Models\User;
use App\Reports\PdfRenderer;

beforeEach(function () {
    $this->site = Site::factory()->create(['name' => 'Acme shop']);
    $this->owner = $this->site->user;
    $scan = $this->site->scans()->create(['status' => 'completed', 'score' => 81, 'pages_scanned' => 4, 'issues_open' => 2, 'critical' => 1, 'serious' => 1, 'finished_at' => now()]);
    Issue::factory()->create(['site_id' => $this->site->id, 'rule_id' => 'image-alt', 'help' => 'Images must have alternative text', 'impact' => 'critical', 'first_seen_scan_id' => $scan->id]);
    Issue::factory()->create(['site_id' => $this->site->id, 'rule_id' => 'color-contrast', 'help' => 'Elements must meet minimum color contrast ratio thresholds', 'impact' => 'serious', 'wcag' => ['wcag2aa', 'wcag143']]);
    Issue::factory()->create(['site_id' => $this->site->id, 'status' => 'dismissed', 'dismissed_reason' => 'false_positive', 'dismissed_note' => 'Decorative divider.', 'help' => 'Dismissed thing']);
    $this->site->checklistItems()->create(['criterion' => '2.1.1', 'status' => 'fail', 'notes' => 'Menu is not keyboard accessible.']);
});

test('the HTML report contains every section with the right data', function () {
    $html = $this->actingAs($this->owner)->get("/sites/{$this->site->id}/report?format=html")->assertOk()->getContent();

    expect($html)
        ->toContain('<html lang="en">')
        ->toContain('Accessibility audit report')
        ->toContain('Acme shop')
        ->toContain('Partially conformant')
        ->toContain('1. Summary')
        ->toContain('2. WCAG 2.2 A and AA checklist')
        ->toContain('Menu is not keyboard accessible.')
        ->toContain('Images must have alternative text')
        ->toContain('Elements must meet minimum color contrast ratio thresholds')
        ->toContain('WCAG 1.4.3')
        ->toContain('Decorative divider.')
        ->toContain('6. Audit trail');
});

test('downloading the PDF is recorded in the audit trail', function () {
    $fake = tempnam(sys_get_temp_dir(), 'pdf');
    file_put_contents($fake, '%PDF-1.7 fake');
    $this->mock(PdfRenderer::class)->shouldReceive('render')->once()->andReturn($fake);

    $response = $this->actingAs($this->owner)->get("/sites/{$this->site->id}/report");

    $response->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($response->headers->get('content-disposition'))->toContain('accessibility-report-acme-shop-')
        ->and(Activity::where('action', 'report.generated')->count())->toBe(1);
});

test('people without access cannot download the report', function () {
    $this->actingAs(User::factory()->create())->get("/sites/{$this->site->id}/report")->assertForbidden();
});

test('the renderer produces a real PDF with Chrome', function () {
    $html = '<!DOCTYPE html><html lang="en"><head><title>Test report</title></head><body><main><h1>Hello</h1><p>Report body.</p></main></body></html>';
    $path = app(PdfRenderer::class)->render($html);

    expect(file_get_contents($path, length: 5))->toBe('%PDF-')
        ->and(filesize($path))->toBeGreaterThan(1000);
    unlink($path);
})->skip(fn () => ! collect(['/usr/bin/google-chrome', '/usr/bin/google-chrome-stable', '/usr/bin/chromium', '/usr/bin/chromium-browser', (string) env('CURBCUT_CHROME_PATH')])->filter()->contains(fn ($p) => is_executable($p)), 'Chrome is not installed');
