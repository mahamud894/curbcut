<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)->withPivot('role')->withTimestamps();
    }

    public function currentOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'current_organization_id');
    }

    /** The workspace the user is working in; creates a personal one when they have none. */
    public function workspace(): Organization
    {
        $current = $this->currentOrganization;
        if ($current && $current->hasUser($this)) {
            return $current;
        }

        $organization = $this->organizations()->orderBy('organizations.id')->first()
            ?? Organization::createFor($this, "{$this->name}'s workspace", personal: true);
        $this->switchTo($organization);

        return $organization;
    }

    public function switchTo(Organization $organization): void
    {
        $this->forceFill(['current_organization_id' => $organization->id])->save();
        $this->setRelation('currentOrganization', $organization);
    }

    /** Sites in any of the user's workspaces, plus sites they were invited to as a guest. */
    public function accessibleSites(): Builder
    {
        return Site::query()->where(fn ($q) => $q->whereIn('organization_id', $this->organizations()->select('organizations.id'))
            ->orWhereHas('members', fn ($m) => $m->whereKey($this->id)));
    }
}
