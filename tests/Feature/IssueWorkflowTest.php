<?php

use App\Models\Activity;
use App\Models\Issue;
use App\Models\Scan;
use App\Models\Site;
use App\Models\User;
use App\Notifications\IssueAssigned;
use App\Scanning\Scanner;
use App\Scanning\ScanRunner;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Support\FakeScanner;

beforeEach(function () {
    $this->site = Site::factory()->create();
    $this->owner = $this->site->user;
    $this->member = User::factory()->create();
    $this->site->members()->attach($this->member);
});

test('members can open the site and its issues', function () {
    $issue = Issue::factory()->create(['site_id' => $this->site->id]);

    $this->actingAs($this->member)->get("/sites/{$this->site->id}")->assertOk();
    $this->actingAs($this->member)->get("/sites/{$this->site->id}/issues/{$issue->id}")->assertOk();
    $this->actingAs($this->member)->get('/sites')->assertOk()->assertSee($this->site->name);
});

test('members cannot delete the site or manage members', function () {
    $this->actingAs($this->member)->delete("/sites/{$this->site->id}")->assertForbidden();
    $this->actingAs($this->member)->post("/sites/{$this->site->id}/members", ['email' => 'x@y.z'])->assertForbidden();
});

test('the owner adds and removes members by email', function () {
    $newcomer = User::factory()->create(['email' => 'tester@example.test']);
    $issue = Issue::factory()->create(['site_id' => $this->site->id, 'assignee_id' => $this->member->id]);

    $this->actingAs($this->owner)->post("/sites/{$this->site->id}/members", ['email' => 'tester@example.test'])->assertSessionHas('success');
    expect($this->site->hasMember($newcomer))->toBeTrue();

    $this->actingAs($this->owner)->post("/sites/{$this->site->id}/members", ['email' => 'nobody@example.test'])->assertSessionHasErrors('email');

    $this->actingAs($this->owner)->delete("/sites/{$this->site->id}/members/{$this->member->id}");
    expect($this->site->fresh()->hasMember($this->member))->toBeFalse()
        ->and($issue->fresh()->assignee_id)->toBeNull();
});

test('assigning an issue notifies the assignee and is recorded', function () {
    Notification::fake();
    $issue = Issue::factory()->create(['site_id' => $this->site->id]);

    $this->actingAs($this->owner)->patch("/sites/{$this->site->id}/issues/{$issue->id}", [
        'assignee_id' => $this->member->id,
        'due_on' => '2030-01-31',
    ])->assertSessionHas('success');

    $issue->refresh();
    expect($issue->assignee_id)->toBe($this->member->id)
        ->and($issue->due_on->toDateString())->toBe('2030-01-31')
        ->and(Activity::where('action', 'issue.assigned')->count())->toBe(1)
        ->and(Activity::where('action', 'issue.due_date_changed')->count())->toBe(1);
    Notification::assertSentTo($this->member, IssueAssigned::class);
});

test('issues cannot be assigned to people outside the site', function () {
    $outsider = User::factory()->create();
    $issue = Issue::factory()->create(['site_id' => $this->site->id]);

    $this->actingAs($this->owner)->patch("/sites/{$this->site->id}/issues/{$issue->id}", ['assignee_id' => $outsider->id])
        ->assertSessionHasErrors('assignee_id');
    expect($issue->fresh()->assignee_id)->toBeNull();
});

test('every issue of a rule can be assigned at once', function () {
    Notification::fake();
    Issue::factory()->count(3)->create(['site_id' => $this->site->id, 'rule_id' => 'color-contrast']);
    Issue::factory()->create(['site_id' => $this->site->id, 'rule_id' => 'image-alt']);

    $this->actingAs($this->owner)->patch("/sites/{$this->site->id}/issues", ['rule_id' => 'color-contrast', 'assignee_id' => $this->member->id])
        ->assertSessionHas('success', '3 issues updated.');

    expect(Issue::where('assignee_id', $this->member->id)->count())->toBe(3);
});

test('comments are added to an issue', function () {
    $issue = Issue::factory()->create(['site_id' => $this->site->id]);

    $this->actingAs($this->member)->post("/sites/{$this->site->id}/issues/{$issue->id}/comments", ['body' => 'Fixed in the next release.']);

    expect($issue->comments()->first()->body)->toBe('Fixed in the next release.')
        ->and($issue->comments()->first()->user_id)->toBe($this->member->id);
});

test('a dismissed issue stays dismissed when a scan still finds it, and is excluded from the score', function () {
    config(['curbcut.allow_private_networks' => true]);
    Http::fake(['*' => Http::response('', 404)]);
    $scanner = new FakeScanner;
    app()->instance(Scanner::class, $scanner);
    $home = 'https://example.test/';
    $scanner->results[$home] = ['status' => 'ok', 'httpStatus' => 200, 'violations' => [FakeScanner::violation('color-contrast', 'serious', ['p.muted'])]];

    $first = $this->site->scans()->create(['status' => Scan::QUEUED]);
    app(ScanRunner::class)->run($first);
    $issue = Issue::first();

    $this->actingAs($this->owner)->patch("/sites/{$this->site->id}/issues/{$issue->id}", ['dismiss' => ['reason' => 'false_positive', 'note' => 'Text is decorative.']]);
    expect($issue->fresh()->status)->toBe('dismissed');

    $second = $this->site->scans()->create(['status' => Scan::QUEUED]);
    app(ScanRunner::class)->run($second);
    $second->refresh();

    expect($issue->fresh()->status)->toBe('dismissed')
        ->and($second->issues_new)->toBe(0)
        ->and($second->issues_open)->toBe(0)
        ->and($second->score)->toBe(100);

    // Restoring brings it back into the open list.
    $this->actingAs($this->owner)->patch("/sites/{$this->site->id}/issues/{$issue->id}", ['restore' => true]);
    expect($issue->fresh()->status)->toBe('open')->and($issue->fresh()->dismissed_reason)->toBeNull();
});

test('the issue list can be filtered to my issues and dismissed issues', function () {
    Issue::factory()->create(['site_id' => $this->site->id, 'assignee_id' => $this->member->id]);
    Issue::factory()->create(['site_id' => $this->site->id]);
    Issue::factory()->create(['site_id' => $this->site->id, 'status' => 'dismissed']);

    $this->actingAs($this->member)->get("/sites/{$this->site->id}/issues?assignee=me")
        ->assertInertia(fn ($page) => $page->where('issues.total', 1));
    $this->actingAs($this->member)->get("/sites/{$this->site->id}/issues?status=dismissed")
        ->assertInertia(fn ($page) => $page->where('issues.total', 1));
});
