<?php

namespace App\Modules\LocalGovernment\Application;

use App\Models\MunicipalityWebsite;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Schema;

final class PublishedLguWebsite
{
    public static function ready(): bool
    {
        return Schema::hasTable('municipality_websites') && Schema::hasTable('municipality_profiles');
    }

    public function find(string $slug): MunicipalityWebsite
    {
        if (! self::ready()) {
            throw (new ModelNotFoundException)->setModel(MunicipalityWebsite::class);
        }

        return MunicipalityWebsite::query()->publiclyVisible()->where('slug', $slug)
            ->with(['municipality:id,name,province_id', 'municipality.supervisingProvince:id,name',
                'municipality.profile:id,municipality_id,office_name,website_url,email,phone,office_address,description,latitude,longitude,logo_path'])
            ->firstOrFail();
    }
}
