{{-- The plotting workspace runs from public/js/farmers-maps.js. Server values
     reach it through the window.__* config block in farmers/maps.blade.php. --}}
@push('scripts')
@php($farmersMapsScriptVersion = @filemtime(public_path('js/farmers-maps.js')) ?: 1)
<script src="{{ asset('js/farmers-maps.js') }}?v={{ $farmersMapsScriptVersion }}"></script>
@endpush
