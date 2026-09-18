<?php

namespace App\Http\Controllers;

use App\Models\Farmer;
use App\Models\FarmersCooperative;
use App\Support\AuditTrail;
use App\Support\ConcurrentWrite;
use App\Support\CsvExport;
use App\Support\MunicipalityAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class FarmersCooperativeController extends Controller
{
    public function __construct(
        private MunicipalityAccess $municipalityAccess,
        private ConcurrentWrite $concurrentWrite
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', FarmersCooperative::class);

        $q = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $sort = trim((string) $request->query('sort', 'name'));
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(5, min($perPage, 100));

        $query = FarmersCooperative::query();
        $this->municipalityAccess->applyOptionalFilter(
            $query,
            $request->user(),
            $request->query('municipality_id')
        );

        $query->when($q !== '', function ($query) use ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('chairperson', 'like', "%{$q}%")
                  ->orWhere('contact_number', 'like', "%{$q}%")
                  ->orWhere('address', 'like', "%{$q}%")
                  ->orWhere('description', 'like', "%{$q}%");
            });
        })
            ->when($status === 'with_members', function ($query) {
                $query->whereHas('farmers');
            })
            ->when($status === 'empty', function ($query) {
                $query->whereDoesntHave('farmers');
            });

        $summaryRows = (clone $query)
            ->withCount('farmers')
            ->get(['id']);

        $totalCooperatives = $summaryRows->count();
        $totalMembers = (int) $summaryRows->sum('farmers_count');
        $cooperativesWithMembers = $summaryRows
            ->where('farmers_count', '>', 0)
            ->count();
        $emptyCooperatives = max(
            $totalCooperatives - $cooperativesWithMembers,
            0
        );

        $records = (clone $query)
            ->with('municipality:id,name,province')
            ->withCount(['farmers', 'machineries'])
            ->when($sort === 'members', function ($query) {
                $query->orderByDesc('farmers_count')->orderBy('name');
            })
            ->when($sort === 'newest', function ($query) {
                $query->orderByDesc('created_at')->orderBy('name');
            })
            ->when(! in_array($sort, ['members', 'newest'], true), function ($query) {
                $query->orderBy('name');
            })
            ->paginate($perPage)
            ->withQueryString();

        return view('farmers_cooperatives.index', [
            'records' => $records,
            'q' => $q,
            'status' => $status,
            'sort' => $sort,
            'perPage' => $perPage,
            'totalCooperatives' => $totalCooperatives,
            'totalMembers' => $totalMembers,
            'cooperativesWithMembers' => $cooperativesWithMembers,
            'emptyCooperatives' => $emptyCooperatives,
            'municipalities' => $this->municipalityAccess->choices(
                $request->user()
            ),
            'canChooseMunicipality' => $request->user()
                ->canAccessAllMunicipalities(),
            'selectedMunicipalityId' => $request->query('municipality_id'),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', FarmersCooperative::class);

        return view('farmers_cooperatives.create', [
            'record' => null,
            'municipalities' => $this->municipalityAccess->choices(
                $request->user()
            ),
            'canChooseMunicipality' => $request->user()
                ->canAccessAllMunicipalities(),
            'selectedMunicipalityId' => $request->query('municipality_id')
                ?? $request->user()->municipality_id,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', FarmersCooperative::class);
        $data = $request->validate([
            'municipality_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'chairperson' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);
        $data['municipality_id'] = $this->municipalityAccess
            ->resolveForWrite(
                $request->user(),
                $data['municipality_id'] ?? null
            );

        $cooperative = $this->concurrentWrite->transaction(
            fn () => FarmersCooperative::create($data)
        );

        return redirect()
            ->route('farmers-cooperatives.assign-farmers', $cooperative)
            ->with('success', 'Farmers cooperative added successfully. You can now assign farmers.');
    }

    public function edit(
        Request $request,
        FarmersCooperative $farmersCooperative
    ) {
        $this->authorize('update', $farmersCooperative);

        return view('farmers_cooperatives.edit', [
            'record' => $farmersCooperative,
            'municipalities' => $this->municipalityAccess->choices(
                $request->user()
            ),
            'canChooseMunicipality' => $request->user()
                ->canAccessAllMunicipalities(),
            'selectedMunicipalityId' => $farmersCooperative
                ->municipality_id,
        ]);
    }

    public function update(Request $request, FarmersCooperative $farmersCooperative)
    {
        $this->authorize('update', $farmersCooperative);
        $data = $request->validate([
            'municipality_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'chairperson' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);
        $municipalityId = $this->municipalityAccess->resolveForWrite(
            $request->user(),
            $data['municipality_id'] ?? null
        );

        $data['municipality_id'] = $municipalityId;
        $this->concurrentWrite->execute(
            $farmersCooperative,
            $request->input('_record_version'),
            function (FarmersCooperative $current) use (
                $data,
                $municipalityId
            ): void {
                if (
                    $municipalityId !== (int) $current->municipality_id
                    && $current->farmers()->exists()
                ) {
                    throw ValidationException::withMessages([
                        'municipality_id' => 'Remove assigned farmers before moving this cooperative to another municipality.',
                    ]);
                }

                $current->update($data);
            }
        );

        return redirect()
            ->route('farmers-cooperatives.index')
            ->with('success', 'Farmers cooperative updated successfully.');
    }

    public function destroy(FarmersCooperative $farmersCooperative)
    {
        $this->authorize('delete', $farmersCooperative);
        $this->concurrentWrite->locked(
            $farmersCooperative,
            fn (FarmersCooperative $current) => $current->delete()
        );

        return redirect()
            ->route('farmers-cooperatives.index')
            ->with('success', 'Farmers cooperative deleted successfully.');
    }

    public function assignFarmers(FarmersCooperative $farmersCooperative)
    {
        $this->authorize('update', $farmersCooperative);

        $farmers = Farmer::query()
            ->where('municipality_id', $farmersCooperative->municipality_id)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get([
                'id',
                'ffrs',
                'last_name',
                'first_name',
                'middle_name',
                'ext_name',
                'date_of_birth',
                'gender',
                'farm_location',
                'farm_municipality',
                'farm_province',
                'farm_area_ha',
            ]);

        $selectedFarmerIds = $farmersCooperative->farmers()
            ->where(
                'farmers.municipality_id',
                $farmersCooperative->municipality_id
            )
            ->pluck('farmers.id')
            ->toArray();

        return view('farmers_cooperatives.assign_farmers', [
            'record' => $farmersCooperative,
            'farmers' => $farmers,
            'selectedFarmerIds' => $selectedFarmerIds,
        ]);
    }

    public function saveAssignedFarmers(Request $request, FarmersCooperative $farmersCooperative)
    {
        $this->authorize('update', $farmersCooperative);
        $data = $request->validate([
            'farmer_ids' => ['nullable', 'array'],
            'farmer_ids.*' => [
                'integer',
                Rule::exists('farmers', 'id')->where(
                    fn ($query) => $query->where(
                        'municipality_id',
                        $farmersCooperative->municipality_id
                    )
                ),
            ],
        ]);

        $newFarmerIds = collect($data['farmer_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $this->concurrentWrite->execute(
            $farmersCooperative,
            $request->input('_record_version'),
            function (FarmersCooperative $current) use ($newFarmerIds): void {
                $oldFarmerIds = $current->farmers()
                    ->pluck('farmers.id')
                    ->map(fn ($id) => (int) $id)
                    ->sort()
                    ->values()
                    ->all();

                $current->farmers()->sync($newFarmerIds);

                if ($oldFarmerIds !== $newFarmerIds) {
                    $current->touch();
                    AuditTrail::record(
                        'membership_updated',
                        'Cooperatives',
                        auth()->user()->name.' updated the membership of “'.$current->name.'”.',
                        [
                            'auditable' => $current,
                            'old_values' => ['farmer_ids' => $oldFarmerIds],
                            'new_values' => ['farmer_ids' => $newFarmerIds],
                            'metadata' => [
                                'members_before' => count($oldFarmerIds),
                                'members_after' => count($newFarmerIds),
                            ],
                        ]
                    );
                }
            }
        );

        return redirect()
            ->route('farmers-cooperatives.index')
            ->with('success', 'Assigned farmers updated successfully.');
    }

    public function exportExcel(FarmersCooperative $farmersCooperative)
    {
        $this->authorize('export', $farmersCooperative);

        // Queried rather than eager-loaded. `load()` pulled every member into memory
        // at once; a cooperative is unbounded in principle and this export runs on
        // the same request that builds the workbook.
        $members = $farmersCooperative->farmers()
            ->where('farmers.municipality_id', $farmersCooperative->municipality_id)
            ->orderBy('farmers.last_name')
            ->orderBy('farmers.first_name');

        $memberCount = (clone $members)->count();

        /*
         * Recorded before the file is built.
         *
         * The sheet carries each member's date of birth and gender. The machinery and
         * audit-trail exports already record who took a copy; this one did not, even
         * though its contents are more personal than either.
         */
        AuditTrail::record(
            'exported',
            'Cooperatives',
            auth()->user()->name.' exported the assigned farmers of '.$farmersCooperative->name.'.',
            [
                'auditable_id' => (string) $farmersCooperative->getKey(),
                'metadata' => [
                    'row_count' => $memberCount,
                    'includes_personal_data' => true,
                ],
            ]
        );

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Assigned Farmers');

        $lastColumn = 'L';

        // Top title with cooperative name
        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->setCellValue('A1', 'COOPERATIVE: '.($farmersCooperative->name ?? '—'));

        $sheet->mergeCells("A2:{$lastColumn}2");
        $sheet->setCellValue('A2', 'Assigned Farmers Export');

        $sheet->mergeCells("A3:{$lastColumn}3");
        $sheet->setCellValue('A3', 'Generated on: '.now()->format('F d, Y h:i A'));

        $this->styleTitle($sheet, "A1:{$lastColumn}1", '2E7D32', 16);
        $this->styleTitle($sheet, "A2:{$lastColumn}2", '66BB6A', 13);
        $this->styleSubTitle($sheet, "A3:{$lastColumn}3");

        // Table header
        $headers = [
            'No.',
            'FFRS No.',
            'Last Name',
            'First Name',
            'Middle Name',
            'Ext Name',
            'Gender',
            'Date of Birth',
            'Farm Location',
            'Municipality',
            'Province',
            'Farm Area (ha)',
        ];

        $headerRow = 5;
        $sheet->fromArray($headers, null, "A{$headerRow}");
        $this->styleTableHeader($sheet, "A{$headerRow}:{$lastColumn}{$headerRow}");

        $row = 6;
        $count = 1;

        if ($memberCount === 0) {
            $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
            $this->put($sheet, "A{$row}", 'No assigned farmers found for this cooperative.');
            $sheet->getStyle("A{$row}:{$lastColumn}{$row}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        } else {
            // Chunked so the workbook is built from a bounded slice at a time.
            $members->chunk(500, function ($chunk) use ($sheet, &$row, &$count) {
                foreach ($chunk as $farmer) {
                    $this->put($sheet, "A{$row}", $count++);
                    $this->put($sheet, "B{$row}", $farmer->ffrs);
                    $this->put($sheet, "C{$row}", $farmer->last_name);
                    $this->put($sheet, "D{$row}", $farmer->first_name);
                    $this->put($sheet, "E{$row}", $farmer->middle_name);
                    $this->put($sheet, "F{$row}", $farmer->ext_name);
                    $this->put($sheet, "G{$row}", $farmer->gender);
                    $this->put($sheet, "H{$row}", $farmer->date_of_birth
                        ? \Illuminate\Support\Carbon::parse($farmer->date_of_birth)->format('Y-m-d')
                        : '');
                    $this->put($sheet, "I{$row}", $farmer->farm_location);
                    $this->put($sheet, "J{$row}", $farmer->farm_municipality);
                    $this->put($sheet, "K{$row}", $farmer->farm_province);
                    $this->put($sheet, "L{$row}", $farmer->farm_area_ha !== null
                        ? (float) $farmer->farm_area_ha
                        : '');

                    $row++;
                }
            });
        }

        // Improve layout
        $sheet->freezePane('A6');
        $this->autoSizeColumns($sheet, range('A', 'L'));

        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->getRowDimension(2)->setRowHeight(24);
        $sheet->getRowDimension(3)->setRowHeight(22);

        $filename = 'cooperative_'.
            Str::slug($farmersCooperative->name ?: 'export').
            '_assigned_farmers_'.
            now()->format('Ymd_His').
            '.xlsx';

        $tempFile = tempnam(sys_get_temp_dir(), 'coop_export_');

        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return response()->download(
            $tempFile,
            $filename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]
        )->deleteFileAfterSend(true);
    }

    /**
     * The single guarded write used by every cell in this workbook.
     *
     * Written explicitly as a string so a value beginning with `=`, `+`, `-` or `@`
     * is stored as text rather than becoming a live formula when the file is opened.
     * A farmer's name is not a formula, and a spreadsheet that executes one because
     * of what somebody typed into a registry field is a way into whichever machine
     * opened it. `CsvExport::value()` is the same guard the CSV exports use.
     */
    private function put(Worksheet $sheet, string $coordinate, mixed $value): void
    {
        $sheet->setCellValueExplicit($coordinate, CsvExport::value($value), DataType::TYPE_STRING);
    }

    private function styleTitle(Worksheet $sheet, string $range, string $fillColor, int $fontSize = 14): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true)->setSize($fontSize)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($fillColor);
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    }

    private function styleSubTitle(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setItalic(true)->setSize(11);
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    }

    private function styleTableHeader(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('1F4E78');
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    }

    private function autoSizeColumns(Worksheet $sheet, array $columns): void
    {
        foreach ($columns as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }
}
