<?php

namespace App\Http\Controllers;

use App\Models\Issue;
use App\Models\Organization;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    /** Portfolio view across every workspace: the agency overview of all clients. */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $user->workspace();

        $workspaces = $user->organizations()->withCount('sites')->orderBy('name')->get()->map(function (Organization $org) {
            $siteIds = $org->sites()->pluck('id');
            $latestScores = Scan::whereIn('site_id', $siteIds)->where('status', Scan::COMPLETED)
                ->whereIn('id', Scan::selectRaw('max(id)')->whereIn('site_id', $siteIds)->where('status', Scan::COMPLETED)->groupBy('site_id'))
                ->pluck('score')->filter(fn ($s) => $s !== null);
            $open = Issue::whereIn('site_id', $siteIds)->where('status', 'open');

            return [
                'id' => $org->id,
                'name' => $org->name,
                'role' => $org->pivot->role,
                'sites' => $org->sites_count,
                'people' => $org->users()->count(),
                'average_score' => $latestScores->isNotEmpty() ? (int) round($latestScores->avg()) : null,
                'lowest_score' => $latestScores->min(),
                'open_issues' => (clone $open)->count(),
                'critical' => (clone $open)->where('impact', 'critical')->count(),
                'overdue' => (clone $open)->whereNotNull('due_on')->whereDate('due_on', '<', today())->count(),
            ];
        });

        return Inertia::render('workspaces/index', ['workspaces' => $workspaces]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $organization = Organization::createFor($request->user(), $data['name']);
        $request->user()->switchTo($organization);

        return to_route('sites.index')->with('success', "Workspace {$organization->name} created.");
    }

    public function switch(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($organization->hasUser($request->user()), 403);
        $request->user()->switchTo($organization);

        return to_route('sites.index');
    }

    public function edit(Request $request, Organization $organization): Response
    {
        abort_unless($organization->hasUser($request->user()), 403);

        return Inertia::render('workspaces/settings', [
            'organization' => $organization->only(['id', 'name', 'personal']),
            'people' => $organization->users()->orderBy('name')->get()->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->pivot->role]),
            'myRole' => $organization->roleOf($request->user()),
            'canDelete' => $organization->roleOf($request->user()) === 'owner' && $request->user()->organizations()->count() > 1,
        ]);
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($organization->isManager($request->user()), 403);
        $organization->update($request->validate(['name' => ['required', 'string', 'max:120']]));

        return back()->with('success', 'Workspace renamed.');
    }

    public function addPerson(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($organization->isManager($request->user()), 403);
        $data = $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', Rule::in($this->assignableRoles($request->user(), $organization))],
        ]);

        $user = User::where('email', $data['email'])->first();
        if (! $user) {
            return back()->withErrors(['email' => 'No Curbcut account uses this email. Ask them to create an account first.']);
        }
        if ($organization->hasUser($user)) {
            return back()->withErrors(['email' => "{$user->name} is already in this workspace."]);
        }

        $organization->users()->attach($user, ['role' => $data['role']]);

        return back()->with('success', "{$user->name} joined {$organization->name}.");
    }

    public function changeRole(Request $request, Organization $organization, User $user): RedirectResponse
    {
        abort_unless($organization->isManager($request->user()) && $organization->hasUser($user), 403);
        $data = $request->validate(['role' => ['required', Rule::in($this->assignableRoles($request->user(), $organization))]]);

        // Admins cannot change owners, and the last owner cannot be demoted.
        abort_if($organization->roleOf($user) === 'owner' && $organization->roleOf($request->user()) !== 'owner', 403);
        if ($organization->roleOf($user) === 'owner' && $data['role'] !== 'owner' && $this->ownerCount($organization) === 1) {
            return back()->with('error', 'A workspace needs at least one owner.');
        }

        $organization->users()->updateExistingPivot($user->id, ['role' => $data['role']]);

        return back()->with('success', "{$user->name} is now {$data['role']}.");
    }

    public function removePerson(Request $request, Organization $organization, User $user): RedirectResponse
    {
        $self = $request->user()->is($user);
        abort_unless(($self || $organization->isManager($request->user())) && $organization->hasUser($user), 403);
        abort_if(! $self && $organization->roleOf($user) === 'owner' && $organization->roleOf($request->user()) !== 'owner', 403);

        if ($organization->roleOf($user) === 'owner' && $this->ownerCount($organization) === 1) {
            return back()->with('error', 'A workspace needs at least one owner. Make someone else owner first.');
        }

        $organization->users()->detach($user);
        Issue::whereIn('site_id', $organization->sites()->select('id'))->where('assignee_id', $user->id)->update(['assignee_id' => null]);
        if ($user->current_organization_id === $organization->id) {
            $user->forceFill(['current_organization_id' => null])->save();
        }

        return $self ? to_route('workspaces.index')->with('success', "You left {$organization->name}.") : back()->with('success', "{$user->name} was removed.");
    }

    public function destroy(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($organization->roleOf($request->user()) === 'owner', 403);
        if ($request->user()->organizations()->count() <= 1) {
            return back()->with('error', 'You cannot delete your only workspace.');
        }

        $request->validate(['confirm' => ['required', 'in:'.$organization->name]], ['confirm.in' => 'Type the workspace name exactly to confirm.']);
        $organization->delete();

        return to_route('workspaces.index')->with('success', 'Workspace deleted.');
    }

    /** @return list<string> */
    private function assignableRoles(User $actor, Organization $organization): array
    {
        return $organization->roleOf($actor) === 'owner' ? Organization::ROLES : ['admin', 'member'];
    }

    private function ownerCount(Organization $organization): int
    {
        return $organization->users()->wherePivot('role', 'owner')->count();
    }
}
