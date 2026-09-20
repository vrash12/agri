# Geofence browsing performance

## Change

The municipality geofence page previously created every boundary polygon and label synchronously, then destroyed and rebuilt them when changing municipality or returning to the overview. Search also requested full municipality detail on every keystroke.

`public/js/municipality-boundaries.js` now:

- Retains unchanged polygons and detaches off-screen groups instead of reconstructing them.
- Restricts overview rendering to the map viewport plus a 15% margin, updating 80 ms after map idle.
- Creates at most 12 boundary groups per animation frame, yielding sooner after eight milliseconds. One complex group can exceed that budget; it is not split into separate frames.
- Invalidates pending drawing batches when selection or viewport changes.
- Evicts hidden groups above a 160-group cache target; all visible boundaries remain available even when their count exceeds that target.
- Shows municipality labels at zoom 10 or closer and for the selected municipality.
- Computes bounds once per geometry object and fits the complete selection from source bounds, including outside parcels, without scanning duplicated outline overlays.
- Debounces search by 250 ms, aborts superseded fetches, and ignores stale responses. Repeated selection reuses the current payload; switching away and back, retrying, or completing a write fetches current data.
- Cancels an open editor before changing the selected boundary, including within the same municipality.

No coordinates are simplified or persisted by this change. Existing colors, opacity controls, source geometry, edit-version checks, exports, server authorization, and municipality isolation remain in use.

## Validation

Run:

```powershell
node --check public/js/municipality-boundaries.js
node --test tests/JavaScript/municipality-boundary-rendering.test.cjs tests/JavaScript/municipality-boundary-editor.test.cjs
```

The rendering suite runs the complete production script against a deterministic DOM/Google Maps adapter. It covers viewport culling and reuse, cancellation, fast searches and stale responses, source-based fitting, outside parcels, unchanged coordinates, label visibility, opacity, fresh colors after saving, and editor target protection. The editor suite checks unchanged survey coordinates during name/color and vertex edits.

## Limits and deployment

Initial HTML still embeds every current boundary in the user's authorized scope. The selected municipality endpoint still loads and classifies all its parcels. Aborting fetch prevents obsolete browser work but may not stop a server request already running. Those are separate server/payload limits if slower connections or larger parcel collections require a further change.

Deploy the JavaScript to both `public/js/` and Hostinger's served `public_html/js/` when these are separate directories. The existing Blade asset URL uses file modification time to invalidate the browser cache. No migration, configuration change, dependency install, or database write is required. Reload an already-open geofence page after deployment.
