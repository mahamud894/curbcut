<?php

namespace App\Actions;

use App\Jobs\RunScan;
use App\Models\Scan;
use App\Models\Site;

class StartScan
{
    /** Queue a scan for the site, unless one is already waiting or running. */
    public function __invoke(Site $site, string $trigger = 'manual'): ?Scan
    {
        if ($site->hasActiveScan()) {
            return null;
        }

        $scan = $site->scans()->create(['status' => Scan::QUEUED, 'trigger' => $trigger]);
        RunScan::dispatch($scan);

        return $scan;
    }
}
