<?php

namespace App\Http\Controllers;

use App\Models\GeneratedReport;
use App\Modules\Reporting\Application\PeriodReports;
use App\Support\AuditTrail;
use App\Support\CsvExport;
use App\Support\LocalTime;
use App\Support\MunicipalityAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The in-app period report archive: list, generate on request, view and download. */
class GeneratedReportController extends Controller
{
    public function __construct(
        private PeriodReports $reports,
        private MunicipalityAccess $municipalityAccess,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', GeneratedReport::class);
        $validated = $request->validate([
            'period_type' => ['nullable', Rule::in(array_keys(GeneratedReport::PERIOD_TYPES))],
            'source' => ['nullable', Rule::in([GeneratedReport::SOURCE_SCHEDULED, GeneratedReport::SOURCE_ON_DEMAND])],
        ]);

        $reports = $this->reports->visibleQuery($request->user())
            ->when($validated['period_type'] ?? null, fn ($query, $type) => $query->where('period_type', $type))
            ->when($validated['source'] ?? null, fn ($query, $source) => $query->where('source', $source))
            ->orderByDesc('period_start')->orderByDesc('id')
            ->paginate(20, ['id', 'period_type', 'period_start', 'period_end', 'scope_type', 'title', 'source', 'created_at'])
            ->withQueryString();

        return view('reports.index', [
            'reports' => $reports,
            'filters' => $validated,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', GeneratedReport::class);
        $user = $request->user();

        return view('reports.create', [
            'municipalities' => $user->isMunicipalUser() ? collect() : $this->municipalityAccess->choices($user),
            'areaLabel' => $user->scopeLabel(),
            'years' => range(LocalTime::now()->year, max(1990, LocalTime::now()->year - 10)),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', GeneratedReport::class);
        $validated = $request->validate([
            'period_type' => ['required', Rule::in(array_keys(GeneratedReport::PERIOD_TYPES))],
            'year' => ['required', 'integer', 'min:1990', 'max:'.LocalTime::now()->year],
            'month' => ['nullable', 'required_if:period_type,month', 'integer', 'between:1,12'],
            'quarter' => ['nullable', 'required_if:period_type,quarter', 'integer', 'between:1,4'],
            'municipality_id' => ['nullable', 'integer'],
        ], [
            'month.required_if' => 'Choose the month to report.',
            'quarter.required_if' => 'Choose the quarter to report.',
        ]);

        $part = match ($validated['period_type']) {
            'month' => (int) $validated['month'],
            'quarter' => (int) $validated['quarter'],
            default => null,
        };
        $municipalityId = isset($validated['municipality_id']) ? (int) $validated['municipality_id'] : null;
        if ($municipalityId !== null && ! $request->user()->canAccessMunicipality($municipalityId)) {
            throw ValidationException::withMessages(['municipality_id' => 'Choose a municipality in your assigned area.']);
        }

        try {
            $report = $this->reports->generate($request->user(), $validated['period_type'], (int) $validated['year'], $part, $municipalityId);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['year' => $exception->getMessage()]);
        }

        return redirect()->route('reports.show', $report)->with('success', 'Report generated and saved to the archive.');
    }

    public function show(GeneratedReport $generatedReport)
    {
        $this->authorize('view', $generatedReport);

        return view('reports.show', ['report' => $generatedReport]);
    }

    public function download(GeneratedReport $generatedReport): StreamedResponse
    {
        $this->authorize('view', $generatedReport);
        AuditTrail::record('exported', 'Reports', 'Downloaded a period report.', [
            'auditable' => $generatedReport,
            'metadata' => ['period_type' => $generatedReport->period_type, 'scope_type' => $generatedReport->scope_type],
        ]);

        $summary = $generatedReport->summary;
        $name = 'agrilgu_report_'.$generatedReport->id.'_'.$generatedReport->period_start->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($generatedReport, $summary): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            foreach ($this->csvRows($generatedReport, $summary) as $row) {
                fputcsv($stream, CsvExport::row($row));
            }
            fclose($stream);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Flattens the stored aggregates into section / measure / value rows.
     *
     * @param  array<string, mixed>  $summary
     * @return iterable<int, array<int, string>>
     */
    private function csvRows(GeneratedReport $report, array $summary): iterable
    {
        $totals = $summary['totals'] ?? [];
        $labels = $summary['labels'] ?? [];
        $number = fn ($value, int $decimals = 0): string => $value === null ? 'Not recorded' : number_format((float) $value, $decimals, '.', '');

        yield [$report->title];
        yield ['Period', $report->period_start->toDateString().' to '.$report->period_end->toDateString()];
        yield ['Generated', (string) ($summary['generated_at'] ?? $report->created_at)];
        if (! empty($summary['partial'])) {
            yield ['Note', 'Generated before the period ended; later records are not included.'];
        }
        yield [];
        yield ['Section', 'Measure', 'Value'];
        $registry = $totals['registry'] ?? [];
        yield ['Registry (current)', 'Registered farmers', $number($registry['farmers'] ?? 0)];
        yield ['Registry (current)', 'Farmers added in period', $number($registry['new_farmers'] ?? 0)];
        yield ['Registry (current)', 'Farmers with a mapped parcel', $number($registry['mapped_farmers'] ?? 0)];
        yield ['Registry (current)', 'Mapping coverage (%)', $number($registry['mapping_coverage'] ?? null, 1)];
        yield ['Registry (current)', 'Missing FFRS reference', $number($registry['missing_ffrs'] ?? 0)];
        yield ['Registry (current)', 'Missing farm location', $number($registry['missing_location'] ?? 0)];

        $assistance = $totals['assistance'] ?? [];
        yield ['Assistance in period', 'Releases', $number($assistance['releases'] ?? 0)];
        yield ['Assistance in period', 'Unique beneficiaries', $number($assistance['beneficiaries'] ?? 0)];
        yield ['Assistance in period', 'Reach (% of registered farmers)', $number($assistance['reach'] ?? null, 1)];
        foreach ($assistance['categories'] ?? [] as $category => $figures) {
            $label = $labels['categories'][$category] ?? 'Category not recorded';
            yield ['Assistance in period', $label.' — releases', $number($figures['releases'] ?? 0)];
            yield ['Assistance in period', $label.' — beneficiaries', $number($figures['beneficiaries'] ?? 0)];
            foreach ($figures['quantities'] ?? [] as $unit => $quantity) {
                yield ['Assistance in period', $label.' — quantity ('.($labels['release_units'][$unit] ?? 'unit not recorded').')', $number($quantity, 3)];
            }
        }
        yield ['Assistance', 'Releases with no release date (all time)', $number($assistance['undated'] ?? 0)];

        $harvests = $totals['harvests'] ?? [];
        yield ['Harvests dated in period', 'Harvest records', $number($harvests['records'] ?? 0)];
        foreach ($harvests['production'] ?? [] as $key => $figures) {
            [$commodity, $unit] = array_pad(explode('|', (string) $key, 2), 2, '');
            yield ['Harvests dated in period', ($labels['commodities'][$commodity] ?? 'Commodity not recorded').' ('.($labels['harvest_units'][$unit] ?? 'unit not recorded').')', $number($figures['quantity'] ?? 0, 3)];
        }
        yield ['Harvests', 'Records for the period year with no harvest date', $number($harvests['undated'] ?? 0)];

        $health = $totals['animal_health'] ?? [];
        yield ['Animal Health in period', 'Services', $number($health['services'] ?? 0)];
        yield ['Animal Health in period', 'Animals covered', $number($health['animals'] ?? 0)];
        foreach ($health['types'] ?? [] as $type => $figures) {
            yield ['Animal Health in period', ($labels['services'][$type] ?? 'Service not recorded').' — services', $number($figures['services'] ?? 0)];
        }

        $machinery = $totals['machinery'] ?? [];
        yield ['Machinery (current)', 'Units', $number($machinery['units'] ?? 0)];
        foreach ($machinery['availability'] ?? [] as $status => $count) {
            yield ['Machinery (current)', $labels['availability'][$status] ?? 'Status not recorded', $number($count)];
        }

        if (! empty($summary['breakdown'])) {
            yield [];
            yield ['Municipality', 'Registered farmers', 'Mapping coverage (%)', 'Releases', 'Unique beneficiaries', 'Harvest records', 'Animal Health services'];
            foreach ($summary['breakdown'] as $row) {
                yield [$row['municipality'], $number($row['farmers']), $number($row['mapping_coverage'], 1), $number($row['releases']),
                    $number($row['beneficiaries']), $number($row['harvest_records']), $number($row['animal_services'])];
            }
        }
        yield [];
        yield ['Counts describe saved records. Quantities are never added across units. No combined score or ranking is calculated.'];
    }
}
