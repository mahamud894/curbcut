<?php

namespace App\Http\Resources;

use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Site */
class SiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $latest = $this->latestCompletedScan;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => $this->url,
            'sitemap_url' => $this->sitemap_url,
            'max_pages' => $this->max_pages,
            'scan_frequency' => $this->scan_frequency,
            'workspace' => $this->organization ? ['id' => $this->organization->id, 'name' => $this->organization->name] : null,
            'score' => $latest?->score,
            'open_issues' => $this->whenHas('open_issues_count', fn () => (int) $this->open_issues_count),
            'open_by_impact' => $latest ? [
                'critical' => $latest->critical,
                'serious' => $latest->serious,
                'moderate' => $latest->moderate,
                'minor' => $latest->minor,
            ] : null,
            'last_scanned_at' => $this->last_scanned_at?->toIso8601String(),
            'scanning' => $this->hasActiveScan(),
            'web_url' => url("/sites/{$this->id}"),
        ];
    }
}
