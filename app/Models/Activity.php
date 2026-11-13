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

    /** Plain-language description for reports. */
    public function describe(): string
    {
        $who = $this->user?->name ?? 'Someone';
        $p = $this->properties ?? [];
        $status = ['pass' => 'passing', 'fail' => 'failing', 'not_applicable' => 'not applicable', 'not_tested' => 'not tested'];

        return match ($this->action) {
            'issue.assigned' => ! empty($p['assignee']) ? "{$who} assigned “{$p['issue']}” to {$p['assignee']}" : "{$who} unassigned “{$p['issue']}”",
            'issue.due_date_changed' => ! empty($p['due_on']) ? "{$who} set the due date of “{$p['issue']}” to {$p['due_on']}" : "{$who} removed the due date of “{$p['issue']}”",
            'issue.dismissed' => "{$who} dismissed “{$p['issue']}” as ".strtolower((string) ($p['reason'] ?? '')),
            'issue.restored' => "{$who} reopened “{$p['issue']}”",
            'issue.commented' => "{$who} commented on “{$p['issue']}”",
            'checklist.updated' => "{$who} marked {$p['criterion']} {$p['title']} as ".($status[$p['to'] ?? ''] ?? ($p['to'] ?? '')),
            'statement.updated' => "{$who} edited the accessibility statement",
            'statement.published' => "{$who} published the accessibility statement",
            'statement.unpublished' => "{$who} unpublished the accessibility statement",
            'member.added' => "{$who} gave {$p['member']} access",
            'member.removed' => "{$who} removed {$p['member']}",
            'report.generated' => "{$who} downloaded an audit report",
            default => "{$who}: {$this->action}",
        };
    }
}
