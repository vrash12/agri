<?php

return [
    'profile_version' => '2026-10-19.1',

    // Office recheck windows, not biological thresholds or provider refresh schedules.
    'freshness_days' => [
        'assessment' => 365, 'soil' => 730, 'terrain' => 1825,
        'climate' => 730, 'water' => 14, 'field' => 14, 'satellite' => 14,
    ],
    'minimum_climate_period_days' => 30,
    'satellite_minimum_gap_days' => 3,
    'satellite_maximum_gap_days' => 45,

    // Broad FAO Ecocrop reference ranges. A match is a screening result only.
    'profiles' => [
        'rice' => [
            'label' => 'Rice / Palay (irrigated lowland)',
            'ph' => [5.5, 7.0], 'temperature' => [20.0, 30.0],
            'textures' => ['clayey'], 'drainage' => ['poor'],
            'source' => 'https://ecocrop.apps.fao.org/ecocrop/srv/en/dataSheet?id=1574',
        ],
        'corn' => [
            'label' => 'Corn',
            'ph' => [5.0, 7.0], 'temperature' => [18.0, 33.0],
            'textures' => ['loamy'], 'drainage' => ['good'],
            'source' => 'https://ecocrop.apps.fao.org/ecocrop/srv/en/dataSheet?id=238663',
        ],
        'mungbean' => [
            'label' => 'Mungbean / Munggo',
            'ph' => [5.5, 6.2], 'temperature' => [21.0, 36.0],
            'textures' => ['loamy'], 'drainage' => ['good'],
            'source' => 'https://ecocrop.apps.fao.org/ecocrop/srv/en/dataSheet?id=2150',
        ],
    ],
];
