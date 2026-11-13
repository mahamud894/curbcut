<?php

namespace App\Http\Resources;

use App\Models\Check;
use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Check */
class CheckResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'site_id' => $this->site_id,
            'status' => $this->status,
            'base_url' => $this->base_url,
            'ref' => $this->ref,
            'pages' => ['requested' => count($this->paths), 'scanned' => $this->pages_scanned, 'failed' => $this->pages_failed],
            'issues' => [
                'new' => $this->issues_new,
                'existing' => $this->issues_existing,
                'fixed' => $this->issues_fixed,
                'new_by_impact' => $this->new_by_impact ?? array_fill_keys(Issue::IMPACTS, 0),
            ],
            'error' => $this->error,
            'results' => $this->when($this->isFinished(), fn () => $this->results),
            'created_at' => $this->created_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
        ];
    }
}
