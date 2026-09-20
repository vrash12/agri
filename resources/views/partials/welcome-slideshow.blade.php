@php
    $welcomeScenes = [
        ['title' => 'Rice country', 'layout' => 'harvest', 'photos' => [
            ['rice-planting', 960, 640, 'Farmers planting rice in the Happao terraces, Ifugao', 'Rice planting', 'Happao, Ifugao'],
            ['rice-panicles', 330, 247, 'Bundles of harvested rice panicles in Baliuag, Bulacan', 'The harvest', 'Baliuag, Bulacan'],
            ['rice-fields', 330, 247, 'Golden rice fields under a blue sky in Murcia, Negros Occidental', 'Fields of gold', 'Murcia, Negros Occidental'],
            ['rice-drying', 330, 247, 'Harvested rice laid out to dry in Basey, Samar', 'After the harvest', 'Basey, Samar'],
        ]],
        ['title' => 'People who grow', 'layout' => 'growers', 'photos' => [
            ['benguet-farmer', 960, 720, 'A farmer tending vegetables inside a greenhouse in Benguet', 'Care in every crop', 'Benguet'],
            ['vegetable-harvest', 330, 247, 'A farm worker harvesting leafy vegetables in Pulilan, Bulacan', 'Fresh from the field', 'Pulilan, Bulacan'],
            ['atok-farms', 330, 247, 'Vegetable farms on the hillsides of Sayangan, Atok, Benguet', 'Highland farms', 'Atok, Benguet'],
            ['mountain-vegetables', 330, 440, 'Vegetable plots and flowering plants on the slopes of Mount Pulag', 'Mountain-grown', 'Mount Pulag'],
        ]],
        ['title' => 'Life by the water', 'layout' => 'coast', 'photos' => [
            ['fishing-boat', 960, 567, 'A blue fishing boat on the shore in Ilocos Norte', 'Life by the water', 'Ilocos Norte'],
            ['seaweed-farming', 330, 220, 'Seaweed farmers handling their harvest in Caluya, Antique', 'Seaweed harvest', 'Caluya, Antique'],
            ['fishpond', 330, 219, 'A fishpond surrounded by coconut palms in Badian, Cebu', 'Fishponds', 'Badian, Cebu'],
            ['seaweed-lines', 330, 210, 'Eucheuma seaweed growing along underwater cultivation lines in the Philippines', 'Growing at sea', 'Philippines'],
        ]],
        ['title' => 'More from our land', 'layout' => 'orchard', 'photos' => [
            ['banana-farm', 960, 640, 'Rows of banana plants at Baton Farm in Danao, Cebu', 'More from our land', 'Danao, Cebu'],
            ['pineapple-fields', 330, 186, 'Pineapple fields divided by red-earth paths in Manolo Fortich, Bukidnon', 'Pineapple country', 'Manolo Fortich, Bukidnon'],
            ['cacao', 330, 247, 'Ripe cacao pods growing on a tree in Puerto Princesa, Palawan', 'Cacao on the tree', 'Puerto Princesa, Palawan'],
            ['coffee-cherries', 330, 438, 'Dried coffee cherries displayed at IFEX Philippines 2025', 'Coffee cherries', 'IFEX Philippines'],
        ]],
        ['title' => 'Around the farm', 'layout' => 'farm', 'photos' => [
            ['farm-machinery', 960, 720, 'An agricultural tractor with a disc attachment in Camiling, Tarlac', 'Working the land', 'Camiling, Tarlac'],
            ['corn-fields', 330, 247, 'A farmer among rows of corn on a hillside in Pilar, Capiz', 'Rows of corn', 'Pilar, Capiz'],
            ['carabao', 330, 247, 'A carabao standing in a shallow stream in Dumaguete', 'On the farm', 'Dumaguete, Negros Oriental'],
            ['goat', 330, 441, 'A young goat standing on grass on a Philippine farm', 'Livestock', 'Philippines'],
        ]],
    ];
@endphp
<section class="welcome-gallery" data-welcome-slideshow aria-label="Scenes of Philippine agriculture" aria-roledescription="carousel" aria-describedby="welcome-gallery-description">
    <div class="welcome-collage">
        <p class="welcome-collage-label">Rooted in our communities</p>
        <div class="welcome-gallery-slides" id="welcome-scenes">
            @foreach ($welcomeScenes as $scene)
                @php($isFirstScene = $loop->first)
                <div class="welcome-scene welcome-scene-{{ $scene['layout'] }}" data-scene role="group" aria-roledescription="slide" aria-label="{{ $loop->iteration }} of {{ $loop->count }}: {{ $scene['title'] }}" @if (! $isFirstScene) hidden @endif>
                    @foreach ($scene['photos'] as $photo)
                        <figure class="welcome-collage-photo welcome-collage-photo-{{ $loop->iteration }}" data-collage-photo>
                            <div class="welcome-scene-media">
                                <img @if ($isFirstScene) src="{{ asset('images/welcome/collage/'.$photo[0].'.jpg') }}" @else data-src="{{ asset('images/welcome/collage/'.$photo[0].'.jpg') }}" @endif width="{{ $photo[1] }}" height="{{ $photo[2] }}" alt="{{ $photo[3] }}" @if ($isFirstScene && $loop->first) fetchpriority="high" @else loading="lazy" @endif decoding="async">
                                <p class="welcome-scene-fallback" data-image-fallback hidden>Photo unavailable: {{ $photo[4] }}</p>
                            </div>
                            <figcaption><span>{{ $photo[4] }}</span><small>{{ $photo[5] }}</small></figcaption>
                        </figure>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
    <p class="welcome-gallery-description" id="welcome-gallery-description">20 photographs. Five different collages.</p>
    <div class="welcome-gallery-toolbar" data-gallery-controls hidden>
        <div class="welcome-gallery-select" role="group" aria-label="Choose a collage">
            @foreach ($welcomeScenes as $scene)
                <button type="button" data-scene-select="{{ $loop->index }}" aria-label="Show {{ strtolower($scene['title']) }} collage" aria-pressed="{{ $loop->first ? 'true' : 'false' }}" aria-controls="welcome-scenes"><span aria-hidden="true">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span></button>
            @endforeach
        </div>
        <button class="welcome-gallery-play" type="button" data-gallery-play aria-controls="welcome-scenes">Pause slideshow</button>
    </div>
</section>
