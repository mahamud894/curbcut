<?php

namespace App\Jobs;

use App\Checks\CheckRunner;
use App\Models\Check;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunCheck implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public Check $check) {}

    public function handle(CheckRunner $runner): void
    {
        $runner->run($this->check->fresh());
    }

    public function failed(\Throwable $e): void
    {
        $this->check->update(['status' => Check::FAILED, 'error' => mb_substr($e->getMessage(), 0, 2000), 'finished_at' => now()]);
    }
}
