<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function store(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('manageMembers', $site);
        $data = $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', $data['email'])->first();
        if (! $user) {
            return back()->withErrors(['email' => 'No Curbcut account uses this email. Ask them to create an account first.']);
        }
        if ($site->hasMember($user)) {
            return back()->withErrors(['email' => "{$user->name} already has access to this site."]);
        }
        if ($site->organization?->hasUser($user)) {
            return back()->withErrors(['email' => "{$user->name} is in the {$site->organization->name} workspace and already has access."]);
        }

        $site->members()->attach($user);
        Activity::record($site, $request->user(), 'member.added', $user, ['member' => $user->name]);

        return back()->with('success', "{$user->name} was added as a guest and can now work on {$site->name}.");
    }

    public function destroy(Request $request, Site $site, User $user): RedirectResponse
    {
        $this->authorize('manageMembers', $site);
        $site->members()->detach($user);
        // Their open assignments go back to the unassigned pile.
        $site->issues()->where('assignee_id', $user->id)->update(['assignee_id' => null]);
        Activity::record($site, $request->user(), 'member.removed', $user, ['member' => $user->name]);

        return back()->with('success', "{$user->name} no longer has access to {$site->name}.");
    }
}
