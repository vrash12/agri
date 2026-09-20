{{-- The plotting workspace runs from public/js/farmers-maps.js. Server values
     reach it through the window.__* config block in farmers/maps.blade.php. --}}
@push('scripts')
<script>
window.__parcelCropLayerUrl = @json(route('farm-plots.crop-layer'));
window.__parcelCropEditUrl = @json(route('farm-plots.seasonal-crops.edit', ['plot' => '__ID__']));
</script>
<script src="{{ asset('js/parcel-crop-layer.js') }}?v={{ @filemtime(public_path('js/parcel-crop-layer.js')) ?: 1 }}"></script>
@php($parcelDisplayScriptVersion = @filemtime(public_path('js/parcel-display-geometry.js')) ?: 1)
<script src="{{ asset('js/parcel-display-geometry.js') }}?v={{ $parcelDisplayScriptVersion }}"></script>
{{-- KMZ import unzips in the browser, so JSZip is fetched only when a KMZ is
     actually chosen. The pin travels with it. --}}
<script>window.__cdnJszip = @json(\App\Support\Cdn::asset('jszip_js'));</script>
@php($farmersMapsScriptVersion = @filemtime(public_path('js/farmers-maps.js')) ?: 1)
<script src="{{ asset('js/farmers-maps.js') }}?v={{ $farmersMapsScriptVersion }}"></script>
@endpush
