<?php

namespace App\Http\Controllers;

use App\Models\Farmer;
use App\Models\FarmPlot;
use Illuminate\Http\Request;

class FarmPlotController extends Controller
{
  public function index(Farmer $farmer)
  {
    return response()->json([
      'plots' => FarmPlot::where('farmer_id', $farmer->id)->orderByDesc('id')->get(),
    ]);
  }

  public function store(Request $request, Farmer $farmer)
  {
    $data = $request->validate([
      'name' => ['nullable','string','max:120'],
      'color' => [
        'nullable',
        'string',
        'max:16',
        // ✅ allow #RGB, #RRGGBB, #RRGGBBAA
        'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'
      ],
      'polygon' => ['required','array','min:3'],
      'polygon.*.lat' => ['required','numeric','between:-90,90'],
      'polygon.*.lng' => ['required','numeric','between:-180,180'],
    ]);

    $polygon = $data['polygon'];

    // centroid (simple average)
    $sumLat = 0; $sumLng = 0;
    foreach ($polygon as $p) { $sumLat += $p['lat']; $sumLng += $p['lng']; }
    $centroidLat = $sumLat / count($polygon);
    $centroidLng = $sumLng / count($polygon);

    $areaHa = $this->areaHectaresSpherical($polygon);

    $plot = FarmPlot::create([
      'farmer_id' => $farmer->id,
      'name' => $data['name'] ?? null,
      'color' => $data['color'] ?? null,     // ✅ save color
      'polygon_json' => $polygon,
      'area_ha' => $areaHa,
      'centroid_lat' => $centroidLat,
      'centroid_lng' => $centroidLng,
    ]);

    return response()->json(['plot' => $plot], 201);
  }

  public function destroy(FarmPlot $plot)
  {
    $plot->delete();
    return response()->json(['ok' => true]);
  }

  // Approx spherical polygon area (m² -> ha).
  private function areaHectaresSpherical(array $poly): float
  {
    $R = 6378137.0; // meters
    $n = count($poly);
    if ($n < 3) return 0.0;

    $pts = $poly;
    $pts[] = $poly[0];

    $sum = 0.0;
    for ($i = 0; $i < $n; $i++) {
      $lat1 = deg2rad($pts[$i]['lat']);
      $lon1 = deg2rad($pts[$i]['lng']);
      $lat2 = deg2rad($pts[$i+1]['lat']);
      $lon2 = deg2rad($pts[$i+1]['lng']);
      $sum += ($lon2 - $lon1) * (2 + sin($lat1) + sin($lat2));
    }

    $areaM2 = abs($sum) * ($R * $R / 2.0);
    return $areaM2 / 10000.0; // ha
  }
}