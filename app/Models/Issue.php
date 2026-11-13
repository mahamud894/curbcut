<?php

namespace App\Models;

use Database\Factories\IssueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Issue extends Model
{
    /** @use HasFactory<IssueFactory> */
    use HasFactory;

    public const IMPACTS = ['critical', 'serious', 'moderate', 'minor'];

    public const STATUSES = ['open', 'fixed', 'dismissed'];

    public const DISMISS_REASONS = [
        'false_positive' => 'False positive',
        'wont_fix' => "Won't fix",
        'not_applicable' => 'Not applicable',
    ];

    protected $fillable = [
        'site_id', 'page_id', 'fingerprint', 'rule_id', 'impact', 'wcag', 'help', 'description', 'help_url',
        'target', 'html', 'failure_summary', 'status', 'first_seen_scan_id', 'last_seen_scan_id', 'fixed_in_scan_id',
        'assignee_id', 'due_on', 'dismissed_reason', 'dismissed_note', 'dismissed_by', 'dismissed_at',
    ];

    protected function casts(): array
    {
        return ['wcag' => 'array', 'due_on' => 'date', 'dismissed_at' => 'datetime'];
    }

    /** A stable identity for "the same problem on the same element of the same page" across scans. */
    public static function fingerprintFor(int $siteId, string $pageUrl, string $ruleId, string $target): string
    {
        return sha1(implode('|', [$siteId, $pageUrl, $ruleId, $target]));
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function dismisser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dismissed_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(IssueComment::class);
    }

    public function isOverdue(): bool
    {
        return $this->status === 'open' && $this->due_on !== null && $this->due_on->isPast() && ! $this->due_on->isToday();
    }
}
