<?php

namespace App\Http\Resources;

use App\Models\Scan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Scan */
class ScanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'site_id' => $this->site_id,
            'status' => $this->status,
            'trigger' => $this->trigger,
            'score' => $this->score,
            'pages' => ['total' => $this->pages_total, 'scanned' => $this->pages_scanned, 'failed' => $this->pages_failed],
            'issues' => [
                'open' => $this->issues_open,
                'new' => $this->issues_new,
                'fixed' => $this->issues_fixed,
                'critical' => $this->critical,
                'serious' => $this->serious,
                'moderate' => $this->moderate,
                'minor' => $this->minor,
            ],
            'error' => $this->error,
            'created_at' => $this->created_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'web_url' => url("/sites/{$this->site_id}/scans/{$this->id}"),
        ];
    }
}
