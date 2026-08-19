<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FarmPlot extends Model
{
    protected $table = 'farm_plots';

    protected $fillable = [
        'farmer_id',
        'name',
        'polygon_json',
        'area_ha',
        'centroid_lat',
        'centroid_lng',
        'color',
    ];

    protected $casts = [
        'polygon_json' => 'array',
        'area_ha' => 'float',
        'centroid_lat' => 'float',
        'centroid_lng' => 'float',
    ];

    public function farmer()
    {
        return $this->belongsTo(Farmer::class);
    }
}