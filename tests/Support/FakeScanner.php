<?php

namespace Tests\Support;

use App\Scanning\Scanner;

/** Returns canned axe results per URL instead of launching a browser. */
class FakeScanner implements Scanner
{
    /** @var array<string, array<string, mixed>> */
    public array $results = [];

    /** @var list<string> */
    public array $scanned = [];

    public function scan(array $urls, callable $onResult): void
    {
        foreach ($urls as $url) {
            $this->scanned[] = $url;
            $onResult(['url' => $url] + ($this->results[$url] ?? ['status' => 'ok', 'httpStatus' => 200, 'violations' => [], 'passes' => 10]));
        }
    }

    public static function violation(string $id, string $impact, array $targets): array
    {
        return [
            'id' => $id,
            'impact' => $impact,
            'tags' => ['wcag2a', 'wcag111'],
            'help' => "Help for {$id}",
            'description' => "Description of {$id}",
            'helpUrl' => "https://dequeuniversity.com/rules/axe/4.10/{$id}",
            'nodes' => array_map(fn ($t) => ['target' => $t, 'html' => "<div class=\"{$t}\"></div>", 'failureSummary' => 'Fix it'], $targets),
        ];
    }
}
