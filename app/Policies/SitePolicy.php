<?php

namespace App\Policies;

use App\Models\Site;
use App\Models\User;

class SitePolicy
{
    /** Workspace people and site guests can see the site and work on its issues, checklist and statement. */
    public function view(User $user, Site $site): bool
    {
        return $site->hasMember($user);
    }

    public function update(User $user, Site $site): bool
    {
        return $site->hasMember($user);
    }

    /** Only workspace owners and admins can delete the site or change who else has access to it. */
    public function delete(User $user, Site $site): bool
    {
        return $site->isManagedBy($user);
    }

    public function manageMembers(User $user, Site $site): bool
    {
        return $site->isManagedBy($user);
    }
}
