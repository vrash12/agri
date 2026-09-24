<?php

namespace App\Http\Controllers;

use App\Models\FarmerPortalAccount;
use App\Support\FarmerPortalRecords;
use Illuminate\Http\Request;

class FarmerPortalController extends Controller
{
    public function __construct(private FarmerPortalRecords $records)
    {
    }

    public function home(Request $request)
    {
        [$account, $farmer] = $this->context($request);
        $overview = $this->records->overview($account);
        $recent = $this->records->recentActivity($account);
        $cropYear = now()->year;
        $crops = $this->records->seasonalCrops($account, $recent['parcels']->pluck('id')->all(), $cropYear);

        return view('farmer_portal.home', compact('account', 'farmer', 'overview', 'recent', 'cropYear', 'crops'));
    }

    public function profile(Request $request)
    {
        [$account, $farmer] = $this->context($request);

        return view('farmer_portal.profile', compact('account', 'farmer'));
    }

    public function parcels(Request $request)
    {
        $data = $request->validate(['year' => ['nullable', 'integer', 'between:1900,'.(now()->year + 1)], 'page' => ['nullable', 'integer', 'min:1', 'max:100000']]);
        [$account, $farmer] = $this->context($request);
        $cropYear = (int) ($data['year'] ?? now()->year);
        $plots = $this->records->plots($account)->select(['id', 'name', 'area_ha'])->orderBy('id')->paginate(10)->withQueryString();
        $crops = $this->records->seasonalCrops($account, $plots->pluck('id')->all(), $cropYear);
        foreach ($plots as $plot) {
            $plot->setRelation('seasonalCrops', $crops->get($plot->id, collect()));
        }
        $totals = $this->records->parcelTotals($account);

        return view('farmer_portal.parcels', compact('account', 'farmer', 'plots', 'cropYear', 'totals'));
    }

    public function assistance(Request $request)
    {
        $request->validate(['page' => ['nullable', 'integer', 'min:1', 'max:100000']]);
        [$account, $farmer] = $this->context($request);
        $releases = $this->records->releaseDetails($account)
            ->orderByDesc('date_received')->orderByDesc('id')->paginate(15)->withQueryString();
        $totals = $this->records->assistanceTotals($account);

        return view('farmer_portal.assistance', compact('account', 'farmer', 'releases', 'totals'));
    }

    public function harvests(Request $request)
    {
        $data = $request->validate([
            'year' => ['nullable', 'integer', 'between:1900,'.(now()->year + 1)],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);
        [$account, $farmer] = $this->context($request);
        $harvestYear = isset($data['year']) ? (int) $data['year'] : null;
        $query = $this->records->harvestDetails($account)
            ->orderByDesc('date_harvested')->orderByDesc('harvest_year')->orderByDesc('id');
        if ($harvestYear !== null) {
            $query->where('harvest_year', $harvestYear);
        }
        $harvests = $query->paginate(15)->withQueryString();
        $totals = $this->records->harvestTotals($account, $harvestYear);

        return view('farmer_portal.harvests', compact('account', 'farmer', 'harvests', 'harvestYear', 'totals'));
    }

    public function map(Request $request, int $plot)
    {
        [$account, $farmer] = $this->context($request);
        $parcel = $this->records->plots($account)->whereKey($plot)->firstOrFail(['id', 'name', 'area_ha']);

        return view('farmer_portal.map', compact('account', 'farmer', 'parcel'));
    }

    public function geometry(Request $request, int $plot)
    {
        return response()->json(['plot' => $this->records->geometry($request->attributes->get('farmerPortalAccount'), $plot)]);
    }

    public function mapParcels(Request $request)
    {
        $data = $request->validate([
            'year' => ['nullable', 'integer', 'between:1900,'.(now()->year + 1)],
            'after_id' => ['nullable', 'integer', 'between:0,2147483647'],
        ]);

        return response()->json($this->records->mapPage(
            $request->attributes->get('farmerPortalAccount'),
            (int) ($data['year'] ?? now()->year),
            (int) ($data['after_id'] ?? 0)
        ));
    }

    private function context(Request $request): array
    {
        /** @var FarmerPortalAccount $account */
        $account = $request->attributes->get('farmerPortalAccount');

        return [$account, $this->records->farmer($account)];
    }
}
