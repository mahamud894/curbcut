<?php

use App\Jobs\RunScan;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(fn () => config(['curbcut.allow_private_networks' => true]));

test('guests are sent to the login page', function () {
    $this->get('/sites')->assertRedirect('/login');
});

test('a user can add a site and the first scan is queued', function () {
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->post('/sites', [
        'name' => 'Example shop',
        'url' => 'example.test',
        'max_pages' => 25,
        'scan_frequency' => 'daily',
    ])->assertRedirect();

    $site = Site::first();
    expect($site->user_id)->toBe($user->id)
        ->and($site->url)->toBe('https://example.test/')
        ->and($site->scans()->count())->toBe(1);
    Queue::assertPushed(RunScan::class);
});

test('private network addresses are rejected unless allowed', function () {
    config(['curbcut.allow_private_networks' => false]);
    $user = User::factory()->create();

    $this->actingAs($user)->post('/sites', [
        'name' => 'Router', 'url' => 'http://192.168.1.1/', 'max_pages' => 5, 'scan_frequency' => 'manual',
    ])->assertSessionHasErrors('url');

    expect(Site::count())->toBe(0);
});

test('users cannot see or change other people\'s sites', function () {
    $site = Site::factory()->create();
    $intruder = User::factory()->create();

    $this->actingAs($intruder)->get("/sites/{$site->id}")->assertForbidden();
    $this->actingAs($intruder)->get("/sites/{$site->id}/issues")->assertForbidden();
    $this->actingAs($intruder)->post("/sites/{$site->id}/scans")->assertForbidden();
    $this->actingAs($intruder)->delete("/sites/{$site->id}")->assertForbidden();
    expect(Site::count())->toBe(1);
});

test('a second scan is not queued while one is running', function () {
    Queue::fake();
    $site = Site::factory()->create();
    $this->actingAs($site->user)->post("/sites/{$site->id}/scans")->assertSessionHas('success');
    $this->actingAs($site->user)->post("/sites/{$site->id}/scans")->assertSessionHas('error');

    expect($site->scans()->count())->toBe(1);
});

test('the owner can open every site screen', function () {
    $site = Site::factory()->create();
    foreach (['', '/issues', '/pages', '/settings'] as $suffix) {
        $this->actingAs($site->user)->get("/sites/{$site->id}{$suffix}")->assertOk();
    }
    $this->actingAs($site->user)->get('/sites')->assertOk();
});
