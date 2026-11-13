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
    /** A sentence for reports, in the current language. Matches activity-text.tsx in the interface. */
    public function describe(): string
    {
        $who = $this->user?->name ?? tr('Someone');
        $p = $this->properties ?? [];
        $status = ['pass' => 'passing', 'fail' => 'failing', 'not_applicable' => 'not applicable', 'not_tested' => 'not tested'];
        $issue = $p['issue'] ?? '';

        return match ($this->action) {
            'issue.assigned' => ! empty($p['assignee'])
                ? tr('{who} assigned “{issue}” to {assignee}', ['who' => $who, 'issue' => $issue, 'assignee' => $p['assignee']])
                : tr('{who} unassigned “{issue}”', ['who' => $who, 'issue' => $issue]),
            'issue.due_date_changed' => ! empty($p['due_on'])
                ? tr('{who} set the due date of “{issue}” to {date}', ['who' => $who, 'issue' => $issue, 'date' => $p['due_on']])
                : tr('{who} removed the due date of “{issue}”', ['who' => $who, 'issue' => $issue]),
            'issue.dismissed' => tr('{who} dismissed “{issue}”: {reason}', ['who' => $who, 'issue' => $issue, 'reason' => tr((string) ($p['reason'] ?? ''))]),
            'issue.restored' => tr('{who} reopened “{issue}”', ['who' => $who, 'issue' => $issue]),
            'issue.commented' => tr('{who} commented on “{issue}”', ['who' => $who, 'issue' => $issue]),
            'checklist.updated' => tr('{who} marked {criterion} {title} as {status}', [
                'who' => $who, 'criterion' => $p['criterion'] ?? '', 'title' => tr((string) ($p['title'] ?? '')),
                'status' => tr($status[$p['to'] ?? ''] ?? (string) ($p['to'] ?? '')),
            ]),
            'statement.updated' => tr('{who} edited the accessibility statement', ['who' => $who]),
            'statement.published' => tr('{who} published the accessibility statement', ['who' => $who]),
            'statement.unpublished' => tr('{who} unpublished the accessibility statement', ['who' => $who]),
            'member.added' => tr('{who} gave {member} access', ['who' => $who, 'member' => $p['member'] ?? '']),
            'member.removed' => tr('{who} removed {member}', ['who' => $who, 'member' => $p['member'] ?? '']),
            'report.generated' => tr('{who} downloaded an audit report', ['who' => $who]),
            default => "{$who}: {$this->action}",
        };
    }
}
