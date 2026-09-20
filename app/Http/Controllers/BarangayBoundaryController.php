<?php

namespace App\Http\Controllers;

use App\Models\Municipality;
use App\Models\MunicipalityBoundary;
use App\Support\BarangayBoundaryReferences;
use App\Support\MunicipalityAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class BarangayBoundaryController extends Controller
{
    public function __invoke(Request $request, MunicipalityAccess $access, BarangayBoundaryReferences $references): JsonResponse
    {
        $this->authorize('viewAny', MunicipalityBoundary::class);
        $validated = $request->validate(['municipality_id' => ['required', 'integer', 'min:1']]);
        $municipality = $access->scopeMunicipalities(Municipality::query()->active(), $request->user())
            ->whereHas('supervisingProvince', fn ($query) => $query->active())
            ->whereKey($validated['municipality_id'])->firstOrFail();

        try {
            return response()->json($references->forMunicipality($municipality));
        } catch (Throwable $error) {
            report($error);

            return response()->json(['message' => 'Barangay boundaries are temporarily unavailable. Please try again later.'], 503);
        }
    }
}
