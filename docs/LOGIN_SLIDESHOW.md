# Login photo gallery

The login retains the standalone A emblem and credential form at left. Eight Philippine agriculture photographs loop every seven seconds at right, with captions but no visible controls, counter, or photo-credit disclosure. Small screens stack the form first.

## Behavior

The first photo works without JavaScript. Other photos load lazily, with the next photo requested before its turn. Hidden tabs suspend the timer, and reduced-motion preference keeps the current photo still. Failed images show descriptive fallback text. Photo changes do not generate live screen-reader announcements. There is no carousel dependency or third-party runtime image request. Authentication and form behavior remain independent.

## Attribution

`public/photo-credits.html` lists authors, original source pages and licenses for all eight photos, and discloses CSS display cropping. The login footer links there as Image sources. These images illustrate agriculture and do not imply the people are AgriGOV users or beneficiaries. Original source files have not been retouched.

Original scenes: rice planting in Happao by BENNY GROSS.1 (CC BY-SA 4.0), rice fields in Murcia by Mark Daniel Lecciones (CC BY-SA 4.0), and farm machinery in Camiling by Ramon FVelasquez (CC BY-SA 3.0).

Added scenes: `carabao.jpg` by Mike Gonzalez (CC BY-SA 3.0); `fishing-boat.jpg` by Bernard Spragg. NZ (CC0); `rice-harvest.jpg` and `vegetable-harvest.jpg` by Judgefloro (CC0); `rice-drying.jpg` by Lawrence Ruiz (CC BY-SA 4.0). Source and license metadata were checked using Wikimedia Commons imageinfo on September 18, 2026. All five downloaded images were visually reviewed. Added photos total approximately 1.92 MiB; the carabao original is 728 x 546 and the other four previews are 1280 pixels wide.

## Release checks

Five JavaScript lifecycle tests cover eight-photo wrapping, visibility, reduced motion, image failures and missing/single-slide galleries. Deploy the login Blade, CSS, JavaScript, five additional images, and photo-credits HTML/CSS together; rebuild Blade views. No migrations or configuration changes are required.
