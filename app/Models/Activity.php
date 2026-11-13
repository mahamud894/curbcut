<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Activity extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['site_id', 'user_id', 'subject_type', 'subject_id', 'action', 'properties'];

    protected function casts(): array
    {
        return ['properties' => 'array'];
    }

    public static function record(Site $site, ?User $user, string $action, ?Model $subject = null, array $properties = []): self
    {
        return self::create([
            'site_id' => $site->id,
            'user_id' => $user?->id,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'action' => $action,
            'properties' => $properties ?: null,
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
