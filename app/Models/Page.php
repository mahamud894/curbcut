<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Page extends Model
{
    protected $fillable = ['site_id', 'url', 'url_hash', 'title'];

    public static function hashUrl(string $url): string
    {
        return sha1($url);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(ScanPage::class);
    }
}
