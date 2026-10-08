<?php

namespace App\Support;

final class AnimalHealthFormOptions
{
    public const SERVICE_SUGGESTIONS = [
        'vaccination' => ['Anti-rabies vaccine', 'Hemorrhagic septicemia vaccine', 'Hog cholera vaccine', 'Newcastle disease vaccine', 'Fowl pox vaccine', 'Other livestock vaccine'],
        'deworming' => ['Ivermectin', 'Albendazole', 'Levamisole', 'Fenbendazole', 'Piperazine', 'Other dewormer'],
        'vitamins' => ['Multivitamins', 'Vitamin A-D-E', 'Vitamin B complex', 'Iron dextran', 'Electrolytes with vitamins', 'Mineral supplementation'],
        'treatment' => ['Wound treatment', 'Antibiotic treatment', 'Respiratory treatment', 'Diarrhea treatment', 'Ectoparasite treatment', 'Supportive treatment'],
    ];

    public const BREEDS_BY_TYPE = [
        'Dog' => ['Aspin (Asong Pinoy)', 'Mixed Breed', 'Other'], 'Cat' => ['Domestic Shorthair (Puspin)', 'Mixed Breed', 'Other'],
        'Cattle' => ['Brahman', 'Holstein Friesian', 'Sahiwal', 'Native cattle', 'Crossbreed', 'Other'], 'Carabao' => ['Philippine native carabao', 'Murrah cross', 'Other'],
        'Goat' => ['Native goat', 'Boer', 'Anglo-Nubian', 'Crossbreed', 'Other'], 'Sheep' => ['Native sheep', 'Dorper', 'Crossbreed', 'Other'],
        'Swine' => ['Large White', 'Landrace', 'Duroc', 'Native pig', 'Crossbreed', 'Other'], 'Chicken' => ['Native chicken', 'Broiler', 'Layer', 'Free-range', 'Other'],
        'Duck' => ['Itik Pinas', 'Muscovy', 'Mallard', 'Other'], 'Turkey' => ['Native turkey', 'Broad Breasted White', 'Other'],
        'Horse' => ['Native horse', 'Thoroughbred', 'Crossbreed', 'Other'], 'Rabbit' => ['New Zealand White', 'Californian', 'Native / Mixed', 'Other'],
        'Other' => ['Not specified', 'Other'],
    ];
}
