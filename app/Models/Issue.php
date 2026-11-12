<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Issue extends Model
{
    public const IMPACTS = ['critical', 'serious', 'moderate', 'minor'];

    protected $fillable = [
        'site_id', 'page_id', 'fingerprint', 'rule_id', 'impact', 'wcag', 'help', 'description', 'help_url',
        'target', 'html', 'failure_summary', 'status', 'first_seen_scan_id', 'last_seen_scan_id', 'fixed_in_scan_id',
    ];

    protected function casts(): array
    {
        return ['wcag' => 'array'];
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
}
