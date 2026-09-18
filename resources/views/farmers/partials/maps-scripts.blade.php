{{-- The plotting workspace runs from public/js/farmers-maps.js. Server values
     reach it through the window.__* config block in farmers/maps.blade.php. --}}
@push('scripts')
@php($parcelDisplayScriptVersion = @filemtime(public_path('js/parcel-display-geometry.js')) ?: 1)
<script src="{{ asset('js/parcel-display-geometry.js') }}?v={{ $parcelDisplayScriptVersion }}"></script>
{{-- KMZ import unzips in the browser, so JSZip is fetched only when a KMZ is
     actually chosen. The pin travels with it. --}}
<script>window.__cdnJszip = @json(\App\Support\Cdn::asset('jszip_js'));</script>
@php($farmersMapsScriptVersion = @filemtime(public_path('js/farmers-maps.js')) ?: 1)
<script src="{{ asset('js/farmers-maps.js') }}?v={{ $farmersMapsScriptVersion }}"></script>
@endpush
