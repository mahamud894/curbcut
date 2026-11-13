<?php

namespace App\Reports;

use RuntimeException;
use Symfony\Component\Process\Process;

class PdfRenderer
{
    /** Render HTML to a PDF file with headless Chrome and return its path. */
    public function render(string $html): string
    {
        $path = tempnam(sys_get_temp_dir(), 'curbcut-report-').'.pdf';
        $env = array_filter(['CURBCUT_CHROME_PATH' => config('curbcut.chrome_path')]);

        $process = new Process([config('curbcut.node_binary'), base_path('scanner/pdf.mjs'), $path], base_path(), $env ?: null, $html, 120);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($path) || filesize($path) === 0) {
            throw new RuntimeException('Could not create the PDF: '.trim($process->getErrorOutput() ?: $process->getOutput()));
        }

        return $path;
    }
}
