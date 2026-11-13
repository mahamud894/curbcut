<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Statement extends Model
{
    public const CONFORMANCE = ['full', 'partial', 'none'];

    protected $fillable = [
        'site_id', 'organisation', 'contact_email', 'contact_phone', 'conformance_override',
        'additional_limitations', 'feedback_process', 'enforcement_procedure', 'public_token', 'published_at',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (Statement $statement) {
            $statement->public_token ??= Str::random(32);
        });
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }
}
