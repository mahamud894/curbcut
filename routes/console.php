<?php

use App\Actions\StartScan;
use App\Models\Site;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('curbcut:schedule-scans', function (StartScan $start) {
    $queued = 0;
    Site::whereIn('scan_frequency', ['daily', 'weekly'])->each(function (Site $site) use ($start, &$queued) {
        if ($site->isDueForScheduledScan() && $start($site, 'scheduled')) {
            $queued++;
        }
    });
    $this->info("Queued {$queued} scheduled scan(s).");
})->purpose('Queue scans for sites whose daily or weekly scan is due');

Schedule::command('curbcut:schedule-scans')->hourly()->withoutOverlapping();
