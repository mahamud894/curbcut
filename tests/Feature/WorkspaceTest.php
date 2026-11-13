<?php

use App\Models\Issue;
use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(fn () => config(['curbcut.allow_private_networks' => true]));

test('every user gets a personal workspace', function () {
    $user = User::factory()->create(['name' => 'Rina']);

    $workspace = $user->workspace();

    expect($workspace->name)->toBe("Rina's workspace")
        ->and($workspace->roleOf($user))->toBe('owner')
        ->and($user->fresh()->current_organization_id)->toBe($workspace->id);
});

test('new sites belong to the current workspace and its people can work on them', function () {
    Queue::fake();
    $agency = User::factory()->create();
    $client = Organization::createFor($agency, 'Client: Bakery');
    $agency->switchTo($client);
    $colleague = User::factory()->create();
    $client->users()->attach($colleague, ['role' => 'member']);

    $this->actingAs($agency)->post('/sites', ['name' => 'Bakery', 'url' => 'https://bakery.test', 'max_pages' => 5, 'scan_frequency' => 'manual']);
    $site = Site::first();

    expect($site->organization_id)->toBe($client->id);
    $this->actingAs($colleague)->get("/sites/{$site->id}")->assertOk();
    $this->actingAs($colleague)->delete("/sites/{$site->id}")->assertForbidden();
    $this->actingAs(User::factory()->create())->get("/sites/{$site->id}")->assertForbidden();
});

test('the sites list shows only the current workspace plus guest sites', function () {
    $user = User::factory()->create();
    $a = $user->workspace();
    $b = Organization::createFor($user, 'Client B');
    Site::factory()->create(['user_id' => $user->id, 'organization_id' => $a->id, 'name' => 'Site in A']);
    Site::factory()->create(['user_id' => $user->id, 'organization_id' => $b->id, 'name' => 'Site in B']);
    $guestSite = Site::factory()->create(['name' => 'Guest site']);
    $guestSite->members()->attach($user);

    $this->actingAs($user)->get('/sites')->assertInertia(fn ($page) => $page
        ->has('sites', 2)->where('sites.0.name', 'Guest site')->where('sites.0.shared', true)->where('sites.1.name', 'Site in A'));

    $this->actingAs($user)->post("/workspaces/{$b->id}/switch")->assertRedirect('/sites');
    $this->actingAs($user)->get('/sites')->assertInertia(fn ($page) => $page->has('sites', 2)->where('sites.1.name', 'Site in B'));
});

test('users cannot switch to workspaces they do not belong to', function () {
    $other = Organization::createFor(User::factory()->create(), 'Private');
    $this->actingAs(User::factory()->create())->post("/workspaces/{$other->id}/switch")->assertForbidden();
});

test('the overview lists every workspace with its health', function () {
    $user = User::factory()->create(['name' => 'Zara']);
    $user->workspace();
    $org = Organization::createFor($user, 'Client C');
    $site = Site::factory()->create(['user_id' => $user->id, 'organization_id' => $org->id]);
    $site->scans()->create(['status' => 'completed', 'score' => 72]);
    Issue::factory()->count(2)->create(['site_id' => $site->id, 'impact' => 'critical']);

    $this->actingAs($user)->get('/workspaces')->assertInertia(fn ($page) => $page
        ->component('workspaces/index')
        ->has('workspaces', 2)
        ->where('workspaces.0.name', 'Client C')
        ->where('workspaces.0.average_score', 72)
        ->where('workspaces.0.critical', 2));
});

test('owners and admins manage people with roles; members cannot', function () {
    $owner = User::factory()->create();
    $org = Organization::createFor($owner, 'Agency');
    $admin = User::factory()->create(['email' => 'admin@agency.test']);
    $member = User::factory()->create(['email' => 'member@agency.test']);

    $this->actingAs($owner)->post("/workspaces/{$org->id}/people", ['email' => 'admin@agency.test', 'role' => 'admin'])->assertSessionHas('success');
    $this->actingAs($admin)->post("/workspaces/{$org->id}/people", ['email' => 'member@agency.test', 'role' => 'member'])->assertSessionHas('success');
    // Admins cannot create owners or demote the owner.
    $this->actingAs($admin)->put("/workspaces/{$org->id}/people/{$member->id}", ['role' => 'owner'])->assertSessionHasErrors('role');
    $this->actingAs($admin)->put("/workspaces/{$org->id}/people/{$owner->id}", ['role' => 'member'])->assertForbidden();
    // Members cannot manage people.
    $this->actingAs($member)->post("/workspaces/{$org->id}/people", ['email' => 'x@y.z', 'role' => 'member'])->assertForbidden();

    expect($org->roleOf($admin))->toBe('admin')->and($org->roleOf($member))->toBe('member');
});

test('the last owner cannot leave or be demoted', function () {
    $owner = User::factory()->create();
    $org = Organization::createFor($owner, 'Solo');

    $this->actingAs($owner)->put("/workspaces/{$org->id}/people/{$owner->id}", ['role' => 'member'])->assertSessionHas('error');
    $this->actingAs($owner)->delete("/workspaces/{$org->id}/people/{$owner->id}")->assertSessionHas('error');
    expect($org->roleOf($owner))->toBe('owner');
});

test('removing someone from a workspace unassigns their issues', function () {
    $owner = User::factory()->create();
    $org = $owner->workspace();
    $dev = User::factory()->create();
    $org->users()->attach($dev, ['role' => 'member']);
    $site = Site::factory()->create(['user_id' => $owner->id, 'organization_id' => $org->id]);
    $issue = Issue::factory()->create(['site_id' => $site->id, 'assignee_id' => $dev->id]);

    $this->actingAs($owner)->delete("/workspaces/{$org->id}/people/{$dev->id}")->assertSessionHas('success');

    expect($issue->fresh()->assignee_id)->toBeNull()->and($org->hasUser($dev))->toBeFalse();
});

test('deleting a workspace needs its name and removes its sites', function () {
    $owner = User::factory()->create();
    $owner->workspace();
    $org = Organization::createFor($owner, 'Old client');
    Site::factory()->create(['user_id' => $owner->id, 'organization_id' => $org->id]);

    $this->actingAs($owner)->delete("/workspaces/{$org->id}", ['confirm' => 'wrong'])->assertSessionHasErrors('confirm');
    $this->actingAs($owner)->delete("/workspaces/{$org->id}", ['confirm' => 'Old client'])->assertRedirect('/workspaces');

    expect(Organization::find($org->id))->toBeNull()->and(Site::count())->toBe(0);
});

test('sign-up can be turned off', function () {
    config(['curbcut.registration' => false]);

    $this->get('/register')->assertNotFound();
    $this->post('/register', ['name' => 'X', 'email' => 'x@example.com', 'password' => 'password', 'password_confirmation' => 'password'])->assertNotFound();
    $this->get('/login')->assertInertia(fn ($page) => $page->where('canRegister', false));
});

test('admins can create users from the command line', function () {
    $this->artisan('curbcut:user', ['email' => 'ops@example.com', '--name' => 'Ops', '--password' => 'secret-pass'])->assertSuccessful();

    $user = User::where('email', 'ops@example.com')->firstOrFail();
    expect($user->email_verified_at)->not->toBeNull()
        ->and($user->organizations()->count())->toBe(1);

    $this->artisan('curbcut:user', ['email' => 'ops@example.com', '--name' => 'Ops', '--password' => 'secret-pass'])->assertFailed();
});
