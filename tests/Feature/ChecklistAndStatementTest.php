<?php

use App\Compliance\Conformance;
use App\Compliance\Wcag;
use App\Models\Activity;
use App\Models\Issue;
use App\Models\Site;
use App\Models\User;

beforeEach(function () {
    $this->site = Site::factory()->create(['name' => 'Acme shop']);
    $this->owner = $this->site->user;
});

test('the WCAG 2.2 list has the 55 level A and AA criteria', function () {
    expect(Wcag::CRITERIA)->toHaveCount(55)
        ->and(collect(Wcag::CRITERIA)->where(1, 'A'))->toHaveCount(31)
        ->and(Wcag::exists('4.1.1'))->toBeFalse()
        ->and(Wcag::fromAxeTag('wcag1410'))->toBe('1.4.10')
        ->and(Wcag::understandingUrl('1.4.3'))->toBe('https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html');
});

test('testers record checklist results and the change is logged', function () {
    $this->actingAs($this->owner)->put("/sites/{$this->site->id}/checklist/2.1.1", ['status' => 'fail', 'notes' => 'Menu cannot be opened with the keyboard.'])
        ->assertSessionHas('success');

    $item = $this->site->checklistItems()->first();
    expect($item->criterion)->toBe('2.1.1')
        ->and($item->status)->toBe('fail')
        ->and($item->tested_by)->toBe($this->owner->id)
        ->and(Activity::where('action', 'checklist.updated')->first()->properties)->toMatchArray(['from' => 'not_tested', 'to' => 'fail']);

    $this->actingAs($this->owner)->put("/sites/{$this->site->id}/checklist/9.9.9", ['status' => 'pass'])->assertNotFound();
});

test('conformance is only full when every criterion passes and nothing automated is open', function () {
    $conformance = app(Conformance::class);
    expect($conformance->assess($this->site)['suggested'])->toBe('partial');

    foreach (array_keys(Wcag::CRITERIA) as $id) {
        $this->site->checklistItems()->create(['criterion' => $id, 'status' => 'pass']);
    }
    expect($conformance->assess($this->site)['suggested'])->toBe('full');

    // An open automated contrast issue makes 1.4.3 fail even though the checklist says pass.
    Issue::factory()->create(['site_id' => $this->site->id, 'wcag' => ['wcag2aa', 'wcag143'], 'rule_id' => 'color-contrast']);
    $assessment = $conformance->assess($this->site);
    expect($assessment['suggested'])->toBe('partial')
        ->and(collect($assessment['failing'])->pluck('id')->all())->toBe(['1.4.3']);
});

test('a statement needs an organisation and contact before it can be published', function () {
    $this->actingAs($this->owner)->get("/sites/{$this->site->id}/statement")->assertOk();
    $this->actingAs($this->owner)->put("/sites/{$this->site->id}/statement/publish", ['published' => true])->assertSessionHas('error');

    $this->actingAs($this->owner)->put("/sites/{$this->site->id}/statement", ['organisation' => 'Acme Ltd', 'contact_email' => 'a11y@acme.test']);
    $this->actingAs($this->owner)->put("/sites/{$this->site->id}/statement/publish", ['published' => true])->assertSessionHas('success');

    expect($this->site->statement->fresh()->isPublished())->toBeTrue()
        ->and(Activity::where('action', 'statement.published')->exists())->toBeTrue();
});

test('the public statement page works without login only while published', function () {
    $this->actingAs($this->owner)->get("/sites/{$this->site->id}/statement");
    $statement = $this->site->statement()->first();
    $statement->update(['organisation' => 'Acme Ltd', 'contact_email' => 'a11y@acme.test']);
    $url = "/accessibility-statement/{$statement->public_token}";

    auth()->logout();
    $this->get($url)->assertNotFound();

    $statement->update(['published_at' => now()]);
    $this->get($url)->assertOk()->assertInertia(fn ($page) => $page
        ->component('statement/public')
        ->where('statement.organisation', 'Acme Ltd')
        ->where('statement.conformance', 'partial'));

    $this->get('/accessibility-statement/not-a-real-token')->assertNotFound();
});

test('people without access cannot see the checklist or statement editor', function () {
    $stranger = User::factory()->create();
    $this->actingAs($stranger)->get("/sites/{$this->site->id}/checklist")->assertForbidden();
    $this->actingAs($stranger)->get("/sites/{$this->site->id}/statement")->assertForbidden();
    $this->actingAs($stranger)->put("/sites/{$this->site->id}/checklist/1.1.1", ['status' => 'pass'])->assertForbidden();
});
