<?php

namespace App\Policies;

use App\Models\Site;
use App\Models\User;

class SitePolicy
{
    /** Owners and members can see the site and work on its issues, checklist and statement. */
    public function view(User $user, Site $site): bool
    {
        return $site->hasMember($user);
    }

    public function update(User $user, Site $site): bool
    {
        return $site->hasMember($user);
    }

    /** Only the owner can delete the site or change who has access to it. */
    public function delete(User $user, Site $site): bool
    {
        return $site->user_id === $user->id;
    }

    public function manageMembers(User $user, Site $site): bool
    {
        return $site->user_id === $user->id;
    }
}
