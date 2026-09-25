<?php

namespace App\Http\Controllers;

use App\Exceptions\SatelliteUnavailable;
use App\Models\FarmPlot;
use App\Support\AuditTrail;
use App\Support\SentinelImagery;
use App\Support\SentinelParcel;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ParcelSatelliteController extends Controller
{
    public function __construct(private SentinelImagery $imagery, private SentinelParcel $parcels)
    {
    }

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

        return view($view, [
            'plot' => $plot, 'parcel' => $parcel, 'geometryError' => $geometryError,
            'configured' => $this->imagery->configured(),
            'from' => now()->utc()->subDays(30)->toDateString(), 'to' => now()->utc()->subDay()->toDateString(),
        ]);
    }

    public function analyse(Request $request, FarmPlot $plot)
    {
        $this->authorize('view', $plot);
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d', 'after_or_equal:2017-03-28', 'before_or_equal:today'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:today'],
            'max_cloud' => ['required', 'integer', 'min:0', 'max:100'],
        ]);
        if (CarbonImmutable::parse($data['from'])->diffInDays(CarbonImmutable::parse($data['to'])) > 30) {
            throw ValidationException::withMessages(['to' => 'Choose up to 31 days per request.']);
        }
        try {
            $result = $this->imagery->statistics($plot, $data['from'], $data['to'], (int) $data['max_cloud']);
            AuditTrail::record('viewed', 'Satellite observations', 'Reviewed Sentinel-2 observations for a saved parcel.', [
                'auditable' => $plot, 'metadata' => ['from' => $data['from'], 'to' => $data['to'], 'source' => 'Sentinel-2 L2A'],
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
            'layer' => ['required', 'in:ndvi,true-color'],
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
