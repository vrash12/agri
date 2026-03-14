<?php

namespace App\Exports;

use App\Models\RiceSeedDistribution;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class RiceSeedDistributionsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    protected ?string $q;

    public function __construct(?string $q = null)
    {
        $this->q = $q ? trim($q) : null;
    }

    public function query()
    {
        $q = $this->q;

        return RiceSeedDistribution::query()
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('last_name', 'like', "%{$q}%")
                        ->orWhere('first_name', 'like', "%{$q}%")
                        ->orWhere('middle_name', 'like', "%{$q}%")
                        ->orWhere('ffrs', 'like', "%{$q}%")
                        ->orWhere('farm_location', 'like', "%{$q}%");
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name');
    }

    public function headings(): array
    {
        return [
            'No.',
            'Last Name',
            'First Name',
            'Middle Name',
            'FFRS No.',
            'Date of Birth',
            'Location of Farm',
            'Gender',
            'ARB',
            '4Ps',
            'IP',
            'PWD',
            'SC',
            'OFW',
            'Farm Area (ha)',
            'No. of kgs Received',
            'Date Received',
        ];
    }

    public function map($r): array
    {
        static $i = 0;
        $i++;

        return [
            $i,
            $r->last_name,
            $r->first_name,
            $r->middle_name,
            $r->ffrs,
            optional($r->date_of_birth)->format('Y-m-d'),
            $r->farm_location,
            $r->gender,
            $r->is_arb ? 'Y' : 'N',
            $r->is_4ps ? 'Y' : 'N',
            $r->is_ip  ? 'Y' : 'N',
            $r->is_pwd ? 'Y' : 'N',
            $r->is_sc  ? 'Y' : 'N',
            $r->is_ofw ? 'Y' : 'N',
            $r->farm_area_ha,
            $r->kgs_received,
            optional($r->date_received)->format('Y-m-d'),
        ];
    }
}
