# Map label visibility

The Farmers parcel map and Municipality geofences workspace provide a **Map labels: On/Off** button. Labels start on each time the page loads. Turning them off switches the Google base map to satellite imagery without Google's place/road labels; turning them on restores the labeled map. The municipality workspace also synchronizes the button when its native Google map-type selector changes.

This control affects Google's base-map labels only. AgriGOV parcels, boundary shapes, municipality labels, selected records and editing state remain unchanged. Google attribution and map controls remain visible. No database setting, account permission, saved geofence appearance or export configuration changes.

The Farmers 3D map changes `Map3DElement.mode` between `MapMode.HYBRID` and `MapMode.SATELLITE`. The municipality 2D map uses `setMapTypeId('satellite')`, retaining the previous labeled map type for restoration. This works with the existing map ID and does not rely on incompatible inline map styles. Buttons remain disabled until their map initializes.

Google references:

- [3D label toggle example](https://developers.google.com/maps/documentation/javascript/examples/3d/toggle-labels)
- [Map types](https://developers.google.com/maps/documentation/javascript/maptypes)

Implemented locally; not yet deployed. A scoped GitHub release needs the two map JavaScript files and their Blade views, with the scripts mirrored to both Hostinger public directories and compiled views refreshed. No migration or new API configuration is required. Preserve unrelated pending farmer-ID changes in these shared files when preparing the release.


Verification: 34 focused JavaScript tests passed, covering both label controls, native map-type changes, unchanged overlays/camera and existing geofence editing/style behavior. Both scripts pass syntax checks; Blade compilation and git diff whitespace checks pass. A synthetic rendered municipality page with a Google API stub passed mouse/keyboard On/Off checks and desktop/390px layout inspection. Real Google imagery was not re-tested in this change; the implementation follows Google's documented map modes.
