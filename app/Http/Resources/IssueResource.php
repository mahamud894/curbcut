<?php

namespace App\Http\Resources;

use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Issue */
class IssueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'rule_id' => $this->rule_id,
            'impact' => $this->impact,
            'help' => $this->help,
            'description' => $this->description,
            'help_url' => $this->help_url,
            'wcag' => $this->wcag,
            'page' => $this->whenLoaded('page', fn () => ['id' => $this->page->id, 'url' => $this->page->url, 'title' => $this->page->title]),
            'target' => $this->target,
            'html' => $this->html,
            'failure_summary' => $this->failure_summary,
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? ['id' => $this->assignee->id, 'name' => $this->assignee->name] : null),
            'due_on' => $this->due_on?->toDateString(),
            'dismissed' => $this->status === 'dismissed' ? ['reason' => $this->dismissed_reason, 'note' => $this->dismissed_note, 'at' => $this->dismissed_at?->toIso8601String()] : null,
            'first_seen_scan_id' => $this->first_seen_scan_id,
            'last_seen_scan_id' => $this->last_seen_scan_id,
            'fixed_in_scan_id' => $this->fixed_in_scan_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'web_url' => url("/sites/{$this->site_id}/issues/{$this->id}"),
        ];
    }
}
