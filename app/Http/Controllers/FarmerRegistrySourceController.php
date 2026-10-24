<?php

namespace App\Http\Controllers;

use App\Models\Farmer;
use App\Models\User;
use App\Modules\Farmers\Application\FarmerRegistrySources;
use App\Modules\Farmers\Application\FarmerWorkspace;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class FarmerRegistrySourceController extends Controller
{
    public function index(Request $request, string $status, FarmerWorkspace $workspace, FarmerRegistrySources $sources)
    {
        $this->authorize('viewAny', Farmer::class);
        abort_unless(isset(FarmerRegistrySources::SECTIONS[$status]), 404);
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $input = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);
        $scope = $workspace->resolve($request, $user);
        $available = $sources->available();

        return response()->view('farmers.registry-sources', $scope + [
            'registrySourceStatus' => $status,
            'registrySourceTitle' => FarmerRegistrySources::SECTIONS[$status],
            'sourceCounts' => $sources->counts($user, $scope['workspaceMunicipalityIds']),
            'sourceRows' => $available ? $sources->directory($user, $scope['workspaceMunicipalityIds'], $status, $input['q'] ?? '', (int) ($input['per_page'] ?? 25)) : null,
            'sourceAvailable' => $available,
        ])->header('Cache-Control', 'private, no-store, max-age=0');
    }
}
