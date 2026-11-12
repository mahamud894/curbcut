<?php

namespace App\Jobs;

use App\Models\Scan;
use App\Scanning\ScanRunner;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunScan implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public Scan $scan) {}

    public function uniqueId(): string
    {
        return (string) $this->scan->id;
    }

    public function handle(ScanRunner $runner): void
    {
        $runner->run($this->scan->fresh());
    }

    public function failed(\Throwable $e): void
    {
        $this->scan->update(['status' => Scan::FAILED, 'error' => mb_substr($e->getMessage(), 0, 2000), 'finished_at' => now()]);
    }
}
