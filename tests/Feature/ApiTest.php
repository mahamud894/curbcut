<?php

use App\Jobs\RunScan;
use App\Models\Check;
use App\Models\Issue;
use App\Models\Scan;
use App\Models\Site;
use App\Models\User;
use App\Reports\PdfRenderer;
use App\Scanning\Scanner;
use App\Scanning\ScanRunner;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeScanner;

beforeEach(function () {
    config(['curbcut.allow_private_networks' => true]);
    Http::fake(['https://example.test/sitemap.xml' => Http::response(
        '<?xml version="1.0"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.
        '<url><loc>https://example.test/</loc></url><url><loc>https://example.test/about</loc></url></urlset>',
        200, ['Content-Type' => 'application/xml'],
    )]);
    $this->scanner = new FakeScanner;
    app()->instance(Scanner::class, $this->scanner);
    $this->user = User::factory()->create();
    $this->site = Site::factory()->create(['user_id' => $this->user->id, 'name' => 'Shop', 'sitemap_url' => 'https://example.test/sitemap.xml']);

    $this->scanner->results['https://example.test/'] = ['status' => 'ok', 'httpStatus' => 200, 'violations' => [
        FakeScanner::violation('image-alt', 'critical', ['img.hero']),
        FakeScanner::violation('color-contrast', 'serious', ['p.muted']),
    ]];
    $scan = $this->site->scans()->create(['status' => Scan::QUEUED]);
    app(ScanRunner::class)->run($scan);
});

test('requests without a token are rejected with JSON', function () {
    $this->getJson('/api/v1/sites')->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
    $this->get('/api/v1/sites')->assertUnauthorized();
});

test('a token lists only the sites its user can see', function () {
    Sanctum::actingAs($this->user, ['read']);
    Site::factory()->create(['name' => 'Someone else']);

    $this->getJson('/api/v1/sites')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Shop')
        ->assertJsonPath('data.0.score', 93) // (85 + 100) / 2, rounded
        ->assertJsonPath('data.0.open_issues', 2)
        ->assertJsonPath('data.0.open_by_impact.critical', 1);

    $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.email', $this->user->email);
});

test('sites, scans, issues and pages can be read', function () {
    Sanctum::actingAs($this->user, ['read']);
    $scanId = $this->site->scans()->value('id');

    $this->getJson("/api/v1/sites/{$this->site->id}")->assertOk()->assertJsonPath('data.workspace.name', $this->user->workspace()->name);
    $this->getJson("/api/v1/sites/{$this->site->id}/scans")->assertOk()->assertJsonPath('data.0.issues.new', 2);
    $this->getJson("/api/v1/sites/{$this->site->id}/scans/{$scanId}")->assertOk()->assertJsonPath('data.status', 'completed');
    $this->getJson("/api/v1/sites/{$this->site->id}/issues?impact=critical")
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.rule_id', 'image-alt')->assertJsonPath('data.0.page.url', 'https://example.test/');
    $issueId = Issue::where('rule_id', 'image-alt')->value('id');
    $this->getJson("/api/v1/sites/{$this->site->id}/issues/{$issueId}")->assertOk()->assertJsonPath('data.target', 'img.hero');
    $this->getJson("/api/v1/sites/{$this->site->id}/pages")->assertOk()->assertJsonPath('meta.total', 2);
});

test('other users\' sites and mismatched ids are not reachable', function () {
    Sanctum::actingAs(User::factory()->create(), ['read', 'scan']);
    $this->getJson("/api/v1/sites/{$this->site->id}")->assertForbidden();
    $this->postJson("/api/v1/sites/{$this->site->id}/scans")->assertForbidden();

    Sanctum::actingAs($this->user, ['read']);
    $other = Site::factory()->create(['user_id' => $this->user->id]);
    $issueId = Issue::value('id');
    $this->getJson("/api/v1/sites/{$other->id}/issues/{$issueId}")->assertNotFound();
});

test('starting scans needs the scan ability', function () {
    Queue::fake();
    Sanctum::actingAs($this->user, ['read']);
    $this->postJson("/api/v1/sites/{$this->site->id}/scans")->assertForbidden();

    Sanctum::actingAs($this->user, ['read', 'scan']);
    $this->postJson("/api/v1/sites/{$this->site->id}/scans")->assertStatus(202)->assertJsonPath('data.trigger', 'api')->assertJsonPath('data.status', 'queued');
    $this->postJson("/api/v1/sites/{$this->site->id}/scans")->assertStatus(409);
    Queue::assertPushed(RunScan::class, 1);
});

test('a check compares a preview with the known issues without changing them', function () {
    Sanctum::actingAs($this->user, ['read', 'scan']);
    // On the preview, the contrast problem is gone, the image problem remains and a new one appears.
    $this->scanner->results['https://preview.example.test/'] = ['status' => 'ok', 'httpStatus' => 200, 'violations' => [
        FakeScanner::violation('image-alt', 'critical', ['img.hero']),
        FakeScanner::violation('button-name', 'critical', ['button.menu']),
    ]];

    $response = $this->postJson("/api/v1/sites/{$this->site->id}/checks", ['base_url' => 'https://preview.example.test', 'ref' => 'pr-12'])
        ->assertStatus(202);
    $check = Check::findOrFail($response->json('data.id'));

    expect($check->status)->toBe('completed')
        ->and($check->paths)->toEqualCanonicalizing(['/', '/about'])
        ->and($check->issues_new)->toBe(1)
        ->and($check->issues_existing)->toBe(1)
        ->and($check->issues_fixed)->toBe(1)
        ->and($check->failsAt('critical'))->toBeTrue()
        ->and($check->failsAt('minor'))->toBeTrue();

    $this->getJson("/api/v1/sites/{$this->site->id}/checks/{$check->id}")
        ->assertOk()
        ->assertJsonPath('data.issues.new_by_impact.critical', 1)
        ->assertJsonPath('data.results.issues.0.status', 'new')
        ->assertJsonPath('data.results.issues.0.rule_id', 'button-name')
        ->assertJsonPath('data.results.fixed.0.rule_id', 'color-contrast')
        ->assertJsonPath('data.ref', 'pr-12');

    // The site's own issues are untouched.
    expect(Issue::where('status', 'open')->count())->toBe(2)->and(Issue::where('rule_id', 'button-name')->exists())->toBeFalse();
});

test('checks can target explicit paths and refuse private addresses', function () {
    Sanctum::actingAs($this->user, ['read', 'scan']);
    $this->postJson("/api/v1/sites/{$this->site->id}/checks", ['base_url' => 'https://preview.example.test/', 'paths' => ['/pricing']])->assertStatus(202);
    expect($this->scanner->scanned)->toContain('https://preview.example.test/pricing');

    $this->postJson("/api/v1/sites/{$this->site->id}/checks", ['base_url' => 'https://preview.example.test', 'paths' => ['no-slash']])
        ->assertUnprocessable()->assertJsonValidationErrors('paths.0');

    config(['curbcut.allow_private_networks' => false]);
    $this->postJson("/api/v1/sites/{$this->site->id}/checks", ['base_url' => 'http://127.0.0.1:8080'])
        ->assertUnprocessable()->assertJsonValidationErrors('base_url');
});

test('the report can be downloaded with a token', function () {
    app()->instance(PdfRenderer::class, new class extends PdfRenderer
    {
        public function render(string $html): string
        {
            $path = tempnam(sys_get_temp_dir(), 'pdf');
            file_put_contents($path, '%PDF-1.7 fake');

            return $path;
        }
    });
    Sanctum::actingAs($this->user, ['read']);

    $this->get("/api/v1/sites/{$this->site->id}/report")->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

test('users can create and revoke tokens, and a real token works', function () {
    $this->actingAs($this->user)->post('/settings/api-tokens', ['name' => 'CI', 'can_scan' => true])->assertRedirect();
    $token = session('newToken');
    expect($token)->toBeString()->and($this->user->tokens()->first()->abilities)->toBe(['read', 'scan']);

    $this->get('/settings/api-tokens')->assertInertia(fn ($page) => $page->component('settings/api-tokens')->has('tokens', 1));

    auth()->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/sites')->assertOk()->assertJsonCount(1, 'data');

    $this->actingAs($this->user)->delete('/settings/api-tokens/'.$this->user->tokens()->first()->id)->assertRedirect();
    auth()->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/sites')->assertUnauthorized();
});

test('the api is rate limited', function () {
    config(['curbcut.api_rate_limit' => 2]);
    Sanctum::actingAs($this->user, ['read']);
    $this->getJson('/api/v1/me')->assertOk();
    $this->getJson('/api/v1/me')->assertOk();
    $this->getJson('/api/v1/me')->assertStatus(429);
});

test('an element whose selector shifted is not reported as new', function () {
    Sanctum::actingAs($this->user, ['read', 'scan']);
    // Same image problem, but the selector changed because markup was added before it.
    $this->scanner->results['https://preview.example.test/'] = ['status' => 'ok', 'httpStatus' => 200, 'violations' => [
        FakeScanner::violation('image-alt', 'critical', ['main > img:nth-child(2)']),
        FakeScanner::violation('color-contrast', 'serious', ['p.muted']),
    ]];

    $id = $this->postJson("/api/v1/sites/{$this->site->id}/checks", ['base_url' => 'https://preview.example.test'])->json('data.id');
    $check = Check::find($id);

    expect($check->issues_new)->toBe(0)->and($check->issues_fixed)->toBe(0)->and($check->issues_existing)->toBe(2);
});
