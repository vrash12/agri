<?php

namespace App\Http\Controllers;

use App\Models\Farmer;
use App\Platform\Observability\UsageMetrics;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class MapUsageController extends Controller
{
    public function __invoke(Request $request, UsageMetrics $metrics)
    {
        $this->authorize('viewAny', Farmer::class);
        $data = $request->validate([
            'map' => ['required', Rule::in(UsageMetrics::MAPS)],
            'event' => ['required', Rule::in(['created', 'reused'])],
        ]);
        $metrics->map($data['map'], $data['event']);

        return response()->noContent()->header('Cache-Control', 'private, no-store');
    }
}
