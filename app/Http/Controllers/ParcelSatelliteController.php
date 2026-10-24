<?php

namespace App\Http\Controllers;

use App\Exceptions\SatelliteUnavailable;
use App\Models\FarmPlot;
use App\Modules\Climate\Application\ParcelVegetationSnapshot;
use App\Modules\Climate\Application\SatelliteIndex;
use App\Modules\Climate\Application\SentinelImagery;
use App\Modules\Climate\Application\SentinelParcel;
use App\Support\AuditTrail;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ParcelSatelliteController extends Controller
{
    public function __construct(private SentinelImagery $imagery, private SentinelParcel $parcels, private ParcelVegetationSnapshot $vegetation) {}

    public function show(Request $request, FarmPlot $plot)
    {
        $this->authorize('view', $plot);
        $parcel = null;
        $geometryError = null;
        try {
            $parcel = $this->parcels->describe($plot);
        } catch (InvalidArgumentException $exception) {
            $geometryError = $exception->getMessage();
        }

        $view = $request->boolean('modal') ? 'farm_plots.satellite_modal' : 'farm_plots.satellite';
        $today = CarbonImmutable::now('UTC')->startOfDay();
        $latestCompleteDay = $today->subDay();

        return response()->view($view, [
            'plot' => $plot, 'parcel' => $parcel, 'geometryError' => $geometryError,
            'configured' => $this->imagery->configured(),
            'indexes' => SatelliteIndex::definitions(),
            'from' => $latestCompleteDay->subDays(29)->toDateString(),
            'to' => $latestCompleteDay->toDateString(), 'today' => $today->toDateString(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function analyse(Request $request, FarmPlot $plot)
    {
        $this->authorize('view', $plot);
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d', 'after_or_equal:2017-03-28', 'before_or_equal:today'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:today'],
            'max_cloud' => ['required', 'integer', 'min:0', 'max:100'],
            'index' => ['sometimes', 'required', 'string', Rule::in(SatelliteIndex::IDS)],
        ]);
        if (CarbonImmutable::parse($data['from'])->diffInDays(CarbonImmutable::parse($data['to'])) > 30) {
            throw ValidationException::withMessages(['to' => 'Choose up to 31 days per request.']);
        }
        try {
            $result = $this->imagery->statistics($plot, $data['from'], $data['to'], (int) $data['max_cloud'], $data['index'] ?? 'ndvi');
            $this->vegetation->remember($plot, $result);
            AuditTrail::record('viewed', 'Satellite observations', 'Reviewed Sentinel-2 observations for a saved parcel.', [
                'auditable' => $plot, 'metadata' => ['from' => $data['from'], 'to' => $data['to'], 'source' => 'Sentinel-2 L2A', 'index' => $result['index']],
            ]);

            return response()->json($result);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['parcel' => $exception->getMessage()]);
        } catch (SatelliteUnavailable $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status);
        }
    }

    public function image(Request $request, FarmPlot $plot)
    {
        $this->authorize('view', $plot);
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:2017-03-28', 'before_or_equal:today'],
            'max_cloud' => ['required', 'integer', 'min:0', 'max:100'],
            'layer' => ['required', 'string', Rule::in([...SatelliteIndex::IDS, 'true-color'])],
        ]);
        try {
            $image = $this->imagery->image($plot, $data['date'], (int) $data['max_cloud'], $data['layer']);

            return response($image, 200, [
                'Content-Type' => 'image/png', 'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; sandbox",
            ]);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['parcel' => $exception->getMessage()]);
        } catch (SatelliteUnavailable $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status);
        }
    }
}
