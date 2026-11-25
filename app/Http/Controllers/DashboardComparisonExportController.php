<?php

namespace App\Http\Controllers;

use App\Modules\Reporting\Application\DashboardMetrics;
use App\Support\AuditTrail;
use App\Support\CsvExport;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Downloads the dashboard's municipality comparison exactly as it is shown.
 *
 * The rows come from DashboardMetrics::municipalityComparison(), the same scoped
 * query the chart and figures table use, so the file cannot disagree with the
 * screen. It carries aggregates only: no farmer names, identifiers or addresses.
 */
class DashboardComparisonExportController extends Controller
{
    public function __invoke(Request $request, DashboardMetrics $metrics): StreamedResponse
    {
        $validated = $request->validate([
            'report_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
        ]);
        $year = (int) ($validated['report_year'] ?? now()->year);

        $comparison = $metrics->municipalityComparison($request->user(), $year);
        abort_if($comparison === null, 404, 'A comparison needs at least two municipalities in your area.');

        AuditTrail::record(
            'exported',
            'Dashboard',
            'Exported the municipality comparison.',
            ['metadata' => [
                'report_year' => $year,
                'municipality_count' => count($comparison['labels']),
                'indicator_count' => count($comparison['series']),
            ]]
        );

        return response()->streamDownload(function () use ($comparison, $year): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, CsvExport::row(['Municipality comparison', 'Reporting year '.$year]));
            fputcsv($stream, CsvExport::row(array_merge(['Municipality'], array_map(
                fn (array $series): string => $series['name'].' ('.$series['unit'].')',
                $comparison['series']
            ))));

            foreach ($comparison['labels'] as $index => $label) {
                fputcsv($stream, CsvExport::row(array_merge([$label], array_map(
                    fn (array $series): string => isset($series['values'][$index])
                        ? number_format((float) $series['values'][$index], (int) ($series['decimals'] ?? 0), '.', '')
                        : 'Not recorded',
                    $comparison['series']
                ))));
            }

            fputcsv($stream, []);
            foreach (['not_recorded', 'undated', 'undated_releases'] as $key) {
                if (! empty($comparison[$key])) {
                    fputcsv($stream, CsvExport::row([$comparison[$key]['label'], (string) $comparison[$key]['count']]));
                }
            }
            fputcsv($stream, CsvExport::row(['No combined score or ranking is calculated. Rates with nothing to measure are "Not recorded".']));
            fclose($stream);
        }, 'municipality_comparison_'.$year.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
