<?php

namespace App\Http\Controllers;

use App\Models\AgriculturalMachinery;
use App\Models\Farmer;
use App\Models\FarmersCooperative;
use App\Support\AuditTrail;
use App\Support\MunicipalityAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AgriculturalMachineryController extends Controller
{
    public function __construct(
        private MunicipalityAccess $municipalityAccess
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', AgriculturalMachinery::class);

        $perPage = max(10, min((int) $request->query('per_page', 15), 100));
        $query = $this->filteredQuery($request);

        $summary = (clone $query)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN availability_status = 'available' THEN 1 ELSE 0 END) as available")
            ->selectRaw("SUM(CASE WHEN availability_status = 'in_use' THEN 1 ELSE 0 END) as in_use")
            ->selectRaw('SUM(CASE WHEN farmer_id IS NOT NULL THEN 1 ELSE 0 END) as farmer_assigned')
            ->selectRaw('SUM(CASE WHEN farmers_cooperative_id IS NOT NULL THEN 1 ELSE 0 END) as cooperative_assigned')
            ->selectRaw('SUM(CASE WHEN farmer_id IS NULL AND farmers_cooperative_id IS NULL THEN 1 ELSE 0 END) as unassigned')
            ->selectRaw('COALESCE(SUM(acquisition_cost), 0) as total_value')
            ->first();

        $maintenanceAttention = (clone $query)
            ->needsMaintenanceAttention()
            ->count();

        $categoryChart = (clone $query)
            ->select('category', DB::raw('COUNT(*) as total'))
            ->groupBy('category')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'label' => AgriculturalMachinery::CATEGORIES[$row->category]
                    ?? ucfirst(str_replace('_', ' ', $row->category)),
                'total' => (int) $row->total,
            ]);

        $conditionChart = (clone $query)
            ->select('condition_status', DB::raw('COUNT(*) as total'))
            ->groupBy('condition_status')
            ->get()
            ->map(fn ($row) => [
                'label' => AgriculturalMachinery::CONDITIONS[$row->condition_status]
                    ?? ucfirst(str_replace('_', ' ', $row->condition_status)),
                'key' => $row->condition_status,
                'total' => (int) $row->total,
            ]);

        $maintenanceQueue = (clone $query)
            ->needsMaintenanceAttention()
            ->with([
                'farmer:id,first_name,middle_name,last_name,ext_name',
                'cooperative:id,name',
            ])
            ->orderByRaw('next_maintenance_date IS NULL')
            ->orderBy('next_maintenance_date')
            ->limit(5)
            ->get();

        $records = $this->applySort(
            (clone $query)->with([
                'municipality:id,name,province',
                'farmer:id,first_name,middle_name,last_name,ext_name,ffrs',
                'cooperative:id,name',
            ]),
            (string) $request->query('sort', 'asset_code')
        )
            ->paginate($perPage)
            ->withQueryString();

        return view('agricultural_machineries.index', [
            'records' => $records,
            'summary' => $summary,
            'maintenanceAttention' => $maintenanceAttention,
            'maintenanceQueue' => $maintenanceQueue,
            'categoryChart' => $categoryChart,
            'conditionChart' => $conditionChart,
            'categories' => AgriculturalMachinery::CATEGORIES,
            'conditions' => AgriculturalMachinery::CONDITIONS,
            'availabilityStatuses' => AgriculturalMachinery::AVAILABILITY_STATUSES,
            'municipalities' => $this->municipalityAccess->choices($request->user()),
            'canChooseMunicipality' => $request->user()->canAccessAllMunicipalities(),
            'filters' => $request->only([
                'q',
                'municipality_id',
                'category',
                'condition_status',
                'availability_status',
                'holder_type',
                'maintenance',
                'sort',
                'per_page',
            ]),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', AgriculturalMachinery::class);

        return view('agricultural_machineries.create', $this->formData(
            $request,
            null
        ));
    }

    public function store(Request $request)
    {
        $this->authorize('create', AgriculturalMachinery::class);

        $machinery = AgriculturalMachinery::create(
            $this->validatedPayload($request)
        );

        return redirect()
            ->route('machinery-inventory.index')
            ->with(
                'success',
                "Machinery {$machinery->asset_code} was added to the inventory."
            );
    }

    public function edit(
        Request $request,
        AgriculturalMachinery $machinery
    ) {
        $this->authorize('update', $machinery);

        return view(
            'agricultural_machineries.edit',
            $this->formData($request, $machinery)
        );
    }

    public function update(
        Request $request,
        AgriculturalMachinery $machinery
    ) {
        $this->authorize('update', $machinery);

        $machinery->update($this->validatedPayload($request, $machinery));

        return redirect()
            ->route('machinery-inventory.index')
            ->with(
                'success',
                "Machinery {$machinery->asset_code} was updated."
            );
    }

    public function destroy(AgriculturalMachinery $machinery)
    {
        $this->authorize('delete', $machinery);

        $assetCode = $machinery->asset_code;
        $machinery->delete();

        return redirect()
            ->route('machinery-inventory.index')
            ->with('success', "Machinery {$assetCode} was removed.");
    }

    public function export(Request $request)
    {
        $this->authorize('export', AgriculturalMachinery::class);

        $records = $this->applySort(
            $this->filteredQuery($request)->with([
                'municipality:id,name',
                'farmer:id,first_name,middle_name,last_name,ext_name,ffrs',
                'cooperative:id,name',
            ]),
            (string) $request->query('sort', 'asset_code')
        )->get();

        $filename = 'machinery_inventory_'.now()->format('Ymd_His').'.csv';

        AuditTrail::record(
            'exported',
            'Machinery inventory',
            $request->user()->name.' exported the machinery inventory.',
            [
                'metadata' => [
                    'row_count' => $records->count(),
                    'filters' => $request->only([
                        'q',
                        'municipality_id',
                        'category',
                        'condition_status',
                        'availability_status',
                        'holder_type',
                        'maintenance',
                    ]),
                ],
            ]
        );

        return response()->streamDownload(function () use ($records) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, [
                'Asset Code',
                'Machinery',
                'Category',
                'Brand',
                'Model',
                'Serial Number',
                'Assigned To',
                'Holder Type',
                'Municipality',
                'Condition',
                'Availability',
                'Location',
                'Acquisition Source',
                'Acquisition Date',
                'Acquisition Cost',
                'Service Hours',
                'Last Maintenance',
                'Next Maintenance',
                'Notes',
            ]);

            foreach ($records as $record) {
                fputcsv($stream, [
                    $record->asset_code,
                    $record->name,
                    $record->category_label,
                    $record->brand,
                    $record->model,
                    $record->serial_number,
                    $record->holder_label,
                    ucfirst($record->holder_type),
                    $record->municipality?->name,
                    $record->condition_label,
                    $record->availability_label,
                    $record->location,
                    $record->acquisition_source_label,
                    optional($record->acquisition_date)->format('Y-m-d'),
                    $record->acquisition_cost,
                    $record->service_hours,
                    optional($record->last_maintenance_date)->format('Y-m-d'),
                    optional($record->next_maintenance_date)->format('Y-m-d'),
                    $record->notes,
                ]);
            }

            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function holders(Request $request)
    {
        $this->authorize('create', AgriculturalMachinery::class);

        $data = $request->validate([
            'municipality_id' => ['nullable', 'integer'],
            'holder_type' => ['required', Rule::in(['farmer', 'cooperative'])],
        ]);
        $municipalityId = $this->municipalityAccess->resolveForWrite(
            $request->user(),
            $data['municipality_id'] ?? null
        );

        if ($data['holder_type'] === 'cooperative') {
            $holders = FarmersCooperative::query()
                ->where('municipality_id', $municipalityId)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (FarmersCooperative $cooperative) => [
                    'id' => $cooperative->id,
                    'label' => $cooperative->name,
                ]);
        } else {
            $holders = Farmer::query()
                ->where('municipality_id', $municipalityId)
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get([
                    'id',
                    'first_name',
                    'middle_name',
                    'last_name',
                    'ext_name',
                    'ffrs',
                ])
                ->map(function (Farmer $farmer) {
                    $name = trim(implode(' ', array_filter([
                        $farmer->first_name,
                        $farmer->middle_name,
                        $farmer->last_name,
                        $farmer->ext_name,
                    ])));

                    return [
                        'id' => $farmer->id,
                        'label' => $name.($farmer->ffrs ? ' · '.$farmer->ffrs : ''),
                    ];
                });
        }

        return response()->json(['holders' => $holders->values()]);
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = AgriculturalMachinery::query();
        $this->municipalityAccess->applyOptionalFilter(
            $query,
            $request->user(),
            $request->query('municipality_id')
        );

        $search = trim((string) $request->query('q', ''));

        return $query
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('asset_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhere('serial_number', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhereHas('farmer', function (Builder $query) use ($search) {
                            $query->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('ffrs', 'like', "%{$search}%");
                        })
                        ->orWhereHas('cooperative', fn (Builder $query) => $query->where('name', 'like', "%{$search}%")
                        );
                });
            })
            ->when(
                array_key_exists(
                    (string) $request->query('category'),
                    AgriculturalMachinery::CATEGORIES
                ),
                fn (Builder $query) => $query->where(
                    'category',
                    $request->query('category')
                )
            )
            ->when(
                array_key_exists(
                    (string) $request->query('condition_status'),
                    AgriculturalMachinery::CONDITIONS
                ),
                fn (Builder $query) => $query->where(
                    'condition_status',
                    $request->query('condition_status')
                )
            )
            ->when(
                array_key_exists(
                    (string) $request->query('availability_status'),
                    AgriculturalMachinery::AVAILABILITY_STATUSES
                ),
                fn (Builder $query) => $query->where(
                    'availability_status',
                    $request->query('availability_status')
                )
            )
            ->when(
                $request->query('holder_type') === 'farmer',
                fn (Builder $query) => $query->whereNotNull('farmer_id')
            )
            ->when(
                $request->query('holder_type') === 'cooperative',
                fn (Builder $query) => $query->whereNotNull(
                    'farmers_cooperative_id'
                )
            )
            ->when(
                $request->query('holder_type') === 'unassigned',
                fn (Builder $query) => $query
                    ->whereNull('farmer_id')
                    ->whereNull('farmers_cooperative_id')
            )
            ->when(
                $request->query('maintenance') === 'overdue',
                fn (Builder $query) => $query
                    ->whereNotNull('next_maintenance_date')
                    ->whereDate('next_maintenance_date', '<', today())
            )
            ->when(
                $request->query('maintenance') === 'due',
                fn (Builder $query) => $query
                    ->whereBetween('next_maintenance_date', [
                        today()->toDateString(),
                        today()->addDays(30)->toDateString(),
                    ])
            )
            ->when(
                $request->query('maintenance') === 'attention',
                fn (Builder $query) => $query->needsMaintenanceAttention()
            );
    }

    private function applySort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'name' => $query->orderBy('name')->orderBy('asset_code'),
            'newest' => $query->latest()->orderBy('asset_code'),
            'value' => $query->orderByDesc('acquisition_cost')->orderBy('asset_code'),
            'maintenance' => $query
                ->orderByRaw('next_maintenance_date IS NULL')
                ->orderBy('next_maintenance_date')
                ->orderBy('asset_code'),
            default => $query->orderBy('asset_code'),
        };
    }

    private function formData(
        Request $request,
        ?AgriculturalMachinery $machinery
    ): array {
        $municipalities = $this->municipalityAccess->choices($request->user());
        $selectedMunicipalityId = old(
            'municipality_id',
            $machinery?->municipality_id
                ?? $request->query('municipality_id')
                ?? $request->user()->municipality_id
        );
        $farmers = Farmer::query();
        $cooperatives = FarmersCooperative::query();

        if ($request->user()->canAccessAllMunicipalities()) {
            $validMunicipality = $municipalities->contains(
                'id',
                (int) $selectedMunicipalityId
            );
            if ($validMunicipality) {
                $farmers->where('municipality_id', $selectedMunicipalityId);
                $cooperatives->where('municipality_id', $selectedMunicipalityId);
            } else {
                $farmers->whereRaw('1 = 0');
                $cooperatives->whereRaw('1 = 0');
            }
        } else {
            $this->municipalityAccess->scope($farmers, $request->user());
            $this->municipalityAccess->scope($cooperatives, $request->user());
        }

        return [
            'record' => $machinery,
            'municipalities' => $municipalities,
            'canChooseMunicipality' => $request->user()->canAccessAllMunicipalities(),
            'selectedMunicipalityId' => $selectedMunicipalityId,
            'farmers' => $farmers
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get([
                    'id',
                    'municipality_id',
                    'first_name',
                    'middle_name',
                    'last_name',
                    'ext_name',
                    'ffrs',
                ]),
            'cooperatives' => $cooperatives
                ->orderBy('name')
                ->get(['id', 'municipality_id', 'name']),
            'categories' => AgriculturalMachinery::CATEGORIES,
            'conditions' => AgriculturalMachinery::CONDITIONS,
            'availabilityStatuses' => AgriculturalMachinery::AVAILABILITY_STATUSES,
            'acquisitionSources' => AgriculturalMachinery::ACQUISITION_SOURCES,
        ];
    }

    private function validatedPayload(
        Request $request,
        ?AgriculturalMachinery $machinery = null
    ): array {
        $request->merge([
            'asset_code' => strtoupper(trim((string) $request->input('asset_code'))),
        ]);

        $municipalityId = $this->municipalityAccess->resolveForWrite(
            $request->user(),
            $request->input('municipality_id')
        );

        $holderType = (string) $request->input('holder_type');
        $holderTable = $holderType === 'cooperative'
            ? 'farmers_cooperatives'
            : 'farmers';

        $assetCodeRule = Rule::unique(
            'agricultural_machineries',
            'asset_code'
        )->where(fn ($query) => $query->where(
            'municipality_id',
            $municipalityId
        ));

        if ($machinery) {
            $assetCodeRule->ignore($machinery->getKey());
        }

        $nextMaintenanceRules = ['nullable', 'date'];
        if ($request->filled('last_maintenance_date')) {
            $nextMaintenanceRules[] = 'after_or_equal:last_maintenance_date';
        }

        $data = $request->validate([
            'municipality_id' => ['nullable', 'integer'],
            'holder_type' => ['required', Rule::in(['farmer', 'cooperative'])],
            'holder_id' => [
                'required',
                'integer',
                Rule::exists($holderTable, 'id')->where(
                    fn ($query) => $query->where(
                        'municipality_id',
                        $municipalityId
                    )
                ),
            ],
            'asset_code' => ['required', 'string', 'max:60', $assetCodeRule],
            'name' => ['required', 'string', 'max:150'],
            'category' => [
                'required',
                Rule::in(array_keys(AgriculturalMachinery::CATEGORIES)),
            ],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'year_acquired' => [
                'nullable',
                'integer',
                'min:1900',
                'max:'.(now()->year + 1),
            ],
            'acquisition_date' => ['nullable', 'date'],
            'acquisition_source' => [
                'nullable',
                Rule::in(array_keys(AgriculturalMachinery::ACQUISITION_SOURCES)),
            ],
            'acquisition_cost' => ['nullable', 'numeric', 'min:0'],
            'condition_status' => [
                'required',
                Rule::in(array_keys(AgriculturalMachinery::CONDITIONS)),
            ],
            'availability_status' => [
                'required',
                Rule::in(array_keys(
                    AgriculturalMachinery::AVAILABILITY_STATUSES
                )),
            ],
            'location' => ['nullable', 'string', 'max:255'],
            'service_hours' => ['nullable', 'numeric', 'min:0'],
            'last_maintenance_date' => ['nullable', 'date'],
            'next_maintenance_date' => $nextMaintenanceRules,
            'notes' => ['nullable', 'string', 'max:3000'],
        ], [
            'holder_id.exists' => 'The selected holder must belong to the machinery municipality.',
            'asset_code.unique' => 'This asset code is already used in the selected municipality.',
            'next_maintenance_date.after_or_equal' => 'The next maintenance date cannot be before the last maintenance date.',
        ]);

        $data['municipality_id'] = $municipalityId;
        $data['asset_code'] = strtoupper(trim($data['asset_code']));
        $data['farmer_id'] = $holderType === 'farmer'
            ? (int) $data['holder_id']
            : null;
        $data['farmers_cooperative_id'] = $holderType === 'cooperative'
            ? (int) $data['holder_id']
            : null;

        unset($data['holder_type'], $data['holder_id']);

        return $data;
    }
}
