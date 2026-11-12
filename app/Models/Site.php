<?php

namespace App\Models;

use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory;

    public const FREQUENCIES = ['manual', 'daily', 'weekly'];

    protected $fillable = ['name', 'url', 'sitemap_url', 'max_pages', 'scan_frequency', 'last_scanned_at'];

    protected function casts(): array
    {
        return ['last_scanned_at' => 'datetime', 'max_pages' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(Scan::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    public function latestScan(): HasOne
    {
        return $this->hasOne(Scan::class)->latestOfMany();
    }

    public function latestCompletedScan(): HasOne
    {
        return $this->hasOne(Scan::class)->ofMany(['id' => 'max'], fn ($query) => $query->where('status', Scan::COMPLETED));
    }

    public function hasActiveScan(): bool
    {
        return $this->scans()->whereIn('status', [Scan::QUEUED, Scan::RUNNING])->exists();
    }

    /** Whether a scheduled scan is due, based on the frequency and the last scan time. */
    public function isDueForScheduledScan(?\DateTimeInterface $now = null): bool
    {
        $now = $now ? Carbon::instance($now) : now();

        return match ($this->scan_frequency) {
            'daily' => ! $this->last_scanned_at || $this->last_scanned_at->lte($now->copy()->subDay()),
            'weekly' => ! $this->last_scanned_at || $this->last_scanned_at->lte($now->copy()->subWeek()),
            default => false,
        };
    }
}
