<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Site;
use App\Reports\PdfRenderer;
use App\Reports\SiteReport;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    /**
     * Download the audit report as a PDF, or view it as HTML with ?format=html.
     * The report is written in the site's language; ?lang=<code> picks another one.
     */
    public function show(Request $request, Site $site, SiteReport $report, PdfRenderer $pdf): Response
    {
        $this->authorize('view', $site);
        // API tokens need the "read" ability; signed-in browser sessions have no token.
        abort_if($request->user()->currentAccessToken() && ! $request->user()->tokenCan('read'), 403);
        $language = $request->query('lang');
        $language = is_string($language) && array_key_exists($language, config('locales')) ? $language : $site->language;

        $previous = app()->getLocale();
        app()->setLocale($language);
        try {
            $html = view('reports.site', $report->build($site, $request->user()))->render();
            if ($request->query('format') === 'html') {
                return response($html);
            }
            $path = $pdf->render($html);
        } finally {
            app()->setLocale($previous);
        }

        Activity::record($site, $request->user(), 'report.generated', $site);
        $filename = 'accessibility-report-'.Str::slug($site->name).'-'.now()->format('Y-m-d').'.pdf';

        return response()->download($path, $filename, ['Content-Type' => 'application/pdf'])->deleteFileAfterSend();
    }
}
