<?php

namespace App\Scanning;

use RuntimeException;
use Symfony\Component\Process\Process;

/** Runs scanner/scan.mjs (Playwright + axe-core) and streams its JSON-lines output. */
class NodeScanner implements Scanner
{
    public function scan(array $urls, callable $onResult): void
    {
        if ($urls === []) {
            return;
        }

        $env = array_filter(['CURBCUT_CHROME_PATH' => config('curbcut.chrome_path')]);
        $timeoutMs = (int) config('curbcut.page_timeout_ms');
        $process = new Process(
            [config('curbcut.node_binary'), config('curbcut.scanner_script')],
            base_path(),
            $env ?: null,
            json_encode(['urls' => $urls, 'timeoutMs' => $timeoutMs, 'tags' => config('curbcut.wcag_tags')]),
            // Generous overall limit: two navigation attempts per page plus browser start-up.
            count($urls) * ($timeoutMs / 1000) * 2 + 60,
        );

        $buffer = '';
        $seen = [];
        $process->run(function (string $type, string $output) use (&$buffer, &$seen, $onResult) {
            if ($type !== Process::OUT) {
                return;
            }
            $buffer .= $output;
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);
                if ($line !== '' && is_array($result = json_decode($line, true)) && isset($result['url'])) {
                    $seen[$result['url']] = true;
                    $onResult($result);
                }
            }
        });

        if (! $process->isSuccessful() && $seen === []) {
            throw new RuntimeException('Scanner worker failed: '.trim($process->getErrorOutput() ?: $process->getOutput()));
        }

        // Pages the worker never reported (e.g. it crashed mid-batch) are recorded as failed.
        foreach ($urls as $url) {
            if (! isset($seen[$url])) {
                $onResult(['url' => $url, 'status' => 'failed', 'error' => 'The scanner stopped before reaching this page.']);
            }
        }
    }
}
