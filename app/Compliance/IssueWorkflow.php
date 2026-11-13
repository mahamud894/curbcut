<?php

namespace App\Compliance;

use App\Models\Activity;
use App\Models\Issue;
use App\Models\Site;
use App\Models\User;
use App\Notifications\IssueAssigned;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Applies triage changes to issues and records them in the audit trail. */
class IssueWorkflow
{
    /**
     * @param  iterable<Issue>  $issues
     * @param  array{assignee_id?: ?int, due_on?: ?string, dismiss?: ?array{reason: string, note: ?string}, restore?: bool}  $changes
     */
    public function apply(Site $site, iterable $issues, array $changes, User $actor): int
    {
        $assignee = array_key_exists('assignee_id', $changes) && $changes['assignee_id']
            ? $site->team()->firstWhere('id', (int) $changes['assignee_id'])
            : null;

        $count = 0;
        $toNotify = [];
        DB::transaction(function () use ($site, $issues, $changes, $actor, $assignee, &$count, &$toNotify) {
            foreach ($issues as $issue) {
                $log = [];

                if (array_key_exists('assignee_id', $changes) && $issue->assignee_id !== $assignee?->id) {
                    $issue->assignee_id = $assignee?->id;
                    $log[] = ['assigned', ['assignee' => $assignee?->name]];
                    if ($assignee && $assignee->id !== $actor->id) {
                        $toNotify[$issue->id] = $issue;
                    }
                }

                if (array_key_exists('due_on', $changes)) {
                    $due = $changes['due_on'] ? Carbon::parse($changes['due_on'])->startOfDay() : null;
                    if ($issue->due_on?->toDateString() !== $due?->toDateString()) {
                        $issue->due_on = $due;
                        $log[] = ['due_date_changed', ['due_on' => $due?->toDateString()]];
                    }
                }

                if (! empty($changes['dismiss']) && $issue->status === 'open') {
                    $issue->fill([
                        'status' => 'dismissed',
                        'dismissed_reason' => $changes['dismiss']['reason'],
                        'dismissed_note' => $changes['dismiss']['note'] ?? null,
                        'dismissed_by' => $actor->id,
                        'dismissed_at' => now(),
                    ]);
                    $log[] = ['dismissed', ['reason' => Issue::DISMISS_REASONS[$changes['dismiss']['reason']] ?? $changes['dismiss']['reason'], 'note' => $changes['dismiss']['note'] ?? null]];
                }

                if (! empty($changes['restore']) && $issue->status === 'dismissed') {
                    $issue->fill(['status' => 'open', 'dismissed_reason' => null, 'dismissed_note' => null, 'dismissed_by' => null, 'dismissed_at' => null]);
                    $log[] = ['restored', []];
                }

                if ($log !== []) {
                    $issue->save();
                    foreach ($log as [$action, $properties]) {
                        Activity::record($site, $actor, "issue.{$action}", $issue, $properties + ['issue' => $issue->help]);
                    }
                    $count++;
                }
            }
        });

        foreach ($toNotify as $issue) {
            $assignee->notify(new IssueAssigned($issue, $actor));
        }

        return $count;
    }
}
