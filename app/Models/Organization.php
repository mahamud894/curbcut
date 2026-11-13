<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    public const ROLES = ['owner', 'admin', 'member'];

    protected $fillable = ['name', 'personal'];

    protected function casts(): array
    {
        return ['personal' => 'boolean'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function roleOf(User $user): ?string
    {
        if ($this->relationLoaded('users')) {
            return $this->users->firstWhere('id', $user->id)?->pivot->role;
        }

        return $this->users()->whereKey($user->id)->first()?->pivot->role;
    }

    public function hasUser(User $user): bool
    {
        return $this->roleOf($user) !== null;
    }

    /** Owners and admins manage people, settings and sites. */
    public function isManager(User $user): bool
    {
        return in_array($this->roleOf($user), ['owner', 'admin'], true);
    }

    public static function createFor(User $user, string $name, bool $personal = false): self
    {
        $organization = self::create(['name' => $name, 'personal' => $personal]);
        $organization->users()->attach($user, ['role' => 'owner']);

        return $organization;
    }
}
