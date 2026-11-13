<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Check extends Model
{
    public const QUEUED = 'queued';

    public const RUNNING = 'running';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    protected $fillable = [
        'site_id', 'user_id', 'status', 'base_url', 'ref', 'paths', 'pages_scanned', 'pages_failed',
        'issues_new', 'issues_existing', 'issues_fixed', 'new_by_impact', 'results', 'error', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'paths' => 'array',
            'new_by_impact' => 'array',
            'results' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::COMPLETED, self::FAILED], true);
    }

    /**
     * Whether the check fails at the given threshold: any new issue at this impact or worse.
     */
    public function failsAt(string $impact): bool
    {
        $levels = array_slice(Issue::IMPACTS, 0, array_search($impact, Issue::IMPACTS, true) + 1);

        return collect($levels)->sum(fn ($level) => (int) ($this->new_by_impact[$level] ?? 0)) > 0;
    }
}
