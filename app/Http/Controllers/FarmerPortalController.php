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

        return view('farmer_portal.home', compact('account', 'farmer') + [
            'parcelCount' => $this->records->plots($account)->count(),
            'assistanceCount' => $this->records->releases($account)->count(),
        ]);
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

        return view('farmer_portal.parcels', compact('account', 'farmer', 'plots', 'cropYear'));
    }

    public function assistance(Request $request)
    {
        $request->validate(['page' => ['nullable', 'integer', 'min:1', 'max:100000']]);
        [$account, $farmer] = $this->context($request);
        $releases = $this->records->releases($account)
            ->select(['id', 'date_received', 'input_category', 'seed_variety_claimed', 'kgs_received', 'quantity_unit'])
            ->orderByDesc('date_received')->orderByDesc('id')->paginate(15)->withQueryString();

        return view('farmer_portal.assistance', compact('account', 'farmer', 'releases'));
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

    private function context(Request $request): array
    {
        /** @var FarmerPortalAccount $account */
        $account = $request->attributes->get('farmerPortalAccount');

        return [$account, $this->records->farmer($account)];
    }
}
