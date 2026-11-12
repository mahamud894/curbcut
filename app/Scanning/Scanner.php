<?php

namespace App\Scanning;

interface Scanner
{
    /**
     * Scan each URL and call $onResult with one decoded result per page:
     *   ['url' => ..., 'status' => 'ok'|'failed', 'httpStatus' => int|null, 'title' => ?string,
     *    'violations' => [...axe violations...], 'passes' => int, 'durationMs' => int, 'error' => ?string]
     *
     * @param  list<string>  $urls
     * @param  callable(array<string, mixed>): void  $onResult
     */
    public function scan(array $urls, callable $onResult): void;
}
