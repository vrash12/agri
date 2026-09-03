<?php

namespace App\Support;

use App\Models\MunicipalityBoundary;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class MunicipalityBoundaryGuard
{
    public function __construct(private GeoGeometry $geometry)
    {
    }

    /**
     * @param  array<int, array{lat:mixed,lng:mixed}>  $parcel
     * @return array{status:string,boundary:?MunicipalityBoundary,message:?string}
     */
    public function inspect(int $municipalityId, array $parcel): array
    {
        try {
            // Validate parcel topology even before a municipality boundary exists.
            $this->geometry->fromLatLngRing($parcel);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'polygon' => $exception->getMessage(),
            ]);
        }

        $boundary = Cache::remember(
            'municipality-boundary:active:v1:'.$municipalityId,
            now()->addMinutes(30),
            fn () => MunicipalityBoundary::query()
                ->with('municipality:id,name')
                ->active()
                ->where('municipality_id', $municipalityId)
                ->latest('id')
                ->first()
        );

        if (! $boundary) {
            return [
                'status' => 'unconfigured',
                'boundary' => null,
                'message' => null,
            ];
        }

        try {
            $status = $this->geometry->classifyParcel($parcel, $boundary->geojson);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'polygon' => $exception->getMessage(),
            ]);
        }

        if ($status === 'outside') {
            throw ValidationException::withMessages([
                'polygon' => 'This parcel is completely outside the active '.($boundary->municipality?->name ?? 'municipality').' boundary and cannot be saved.',
            ]);
        }

        $message = match ($status) {
            'partial' => 'The parcel crosses the active municipality boundary. It was saved, but it needs field review.',
            'near_boundary' => 'The parcel is within '.(int) config('geofencing.near_boundary_meters', 20).' meters of the municipality boundary and should be verified.',
            default => null,
        };

        return [
            'status' => $status,
            'boundary' => $boundary,
            'message' => $message,
        ];
    }
}
