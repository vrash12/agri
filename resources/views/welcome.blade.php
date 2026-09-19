<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Find farmer services, Department of Agriculture resources, and guidance for your visit to the municipal agriculture office.">
    <meta name="theme-color" content="#236344">
    <title>Farmer services | AgriGOV</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    @include('partials.design-tokens')
    <link rel="stylesheet" href="{{ asset('css/welcome.css') }}">
    <script src="{{ asset('js/welcome.js') }}" defer></script>
    @include('partials.branding-head')
</head>
<body class="welcome-page" id="top">
    <a class="welcome-skip" href="#main-content">Skip to content</a>
    <div class="welcome-utility">
        <div class="welcome-container">
            <span>Information for farmers, fisherfolk &amp; agriculture offices</span>
            <a href="https://pagasa.dost.gov.ph/agri-weather">PAGASA weather advisories <span aria-hidden="true">↗</span></a>
        </div>
    </div>
    <header class="welcome-header">
        <div class="welcome-container welcome-nav-wrap">
            <a class="welcome-brand" href="{{ route('welcome') }}" aria-label="AgriGOV, Agriculture Information System home">
                <x-brand />
                <span class="welcome-brand-name">Agriculture<br>Information System</span>
            </a>
            <button class="welcome-menu" type="button" aria-expanded="false" aria-controls="welcome-navigation" hidden>Menu <span aria-hidden="true">☰</span></button>
            <nav id="welcome-navigation" aria-label="Main navigation">
                <a href="#services">Services</a>
                <a href="#initiatives">DA initiatives</a>
                <a href="{{ route('farmer-portal.login') }}">Farmer sign in</a>
                <a href="#visit">Before your visit</a>
                <a class="welcome-button welcome-office-link" href="{{ route('login') }}">Office sign in</a>
            </nav>
        </div>
    </header>

    <main id="main-content" tabindex="-1">
        <section class="welcome-hero welcome-container" aria-labelledby="welcome-title">
            <div class="welcome-hero-copy">
                <p class="welcome-kicker">Your guide to local agriculture services</p>
                <h1 id="welcome-title" lang="fil">Mas malapit ang serbisyo sa magsasaka.</h1>
                <p class="welcome-hero-intro">Find support for your farm, practical information for your next season, and the right place to ask for help.</p>
                <a class="welcome-button" href="#services">Find farmer services <span aria-hidden="true">↓</span></a>
                <p class="welcome-hero-note" lang="fil">Para sa mga magsasaka, mangingisda, at mga komunidad na kanilang pinapakain.</p>
            </div>
            <figure class="welcome-hero-figure">
                <img class="welcome-field-photo" src="{{ asset('images/welcome/rice-fields.jpg') }}" width="1280" height="960" alt="Golden rice fields beneath a blue sky in Murcia, Negros Occidental" fetchpriority="high">
                <figcaption>
                    <span class="welcome-photo-label">Supporting the people who grow our food</span>
                    <span>Rice fields in Murcia, Negros Occidental</span>
                </figcaption>
            </figure>
        </section>

        <section class="welcome-season" aria-label="Season planning">
            <div class="welcome-container welcome-season-inner">
                <div><span class="welcome-season-label">Before you head to the field</span><p>Make the weather part of your plan.</p></div>
                <p>Check official forecasts and advisories before planting, harvesting, or going out to sea.</p>
                <a href="https://pagasa.dost.gov.ph/agri-weather" class="welcome-text-link">Check PAGASA <span aria-hidden="true">↗</span></a>
            </div>
        </section>

        <section class="welcome-services welcome-container welcome-section" id="services" aria-labelledby="services-title">
            <div class="welcome-section-heading">
                <div><p class="welcome-section-label">Farmer services</p><h2 id="services-title">What do you need help with?</h2></div>
                <p>Your local agriculture office is your starting point. Choose a service to see what to discuss with the staff.</p>
            </div>
            <div class="welcome-service-list">
                <details class="welcome-service">
                    <summary><img class="welcome-service-photo" src="{{ asset('images/welcome/crop-support.jpg') }}" width="112" height="88" alt="" loading="lazy" decoding="async"><span><strong>Farmer registration</strong><small>Profiles, RSBSA details &amp; record updates</small></span><span class="welcome-plus" aria-hidden="true"></span></summary>
                    <div class="welcome-service-body"><p>Ask the agriculture office to check your farmer profile, registered farm details, and RSBSA information. Tell the staff if your contact details or farming activities have changed.</p><p>Registration and corrections are handled by the office. There is no public account registration on this site.</p><a href="#visit">Prepare for your office visit</a></div>
                </details>
                <details class="welcome-service">
                    <summary><img class="welcome-service-photo" src="{{ asset('images/welcome/seeds-inputs.jpg') }}" width="112" height="88" alt="" loading="lazy" decoding="async"><span><strong>Seeds &amp; farm inputs</strong><small>Rice, corn, vegetables &amp; other crop support</small></span><span class="welcome-plus" aria-hidden="true"></span></summary>
                    <div class="welcome-service-body"><p>Ask about seed, fertilizer, and other farm-input assistance for your crop and planting season. Staff can explain the requirements, schedules, and releases recorded for your farm.</p><p>Availability and eligibility depend on the program and your local office. This page does not accept applications.</p><a href="#visit">Prepare for your office visit</a></div>
                </details>
                <details class="welcome-service">
                    <summary><img class="welcome-service-photo" src="{{ asset('images/welcome/fisheries.jpg') }}" width="112" height="88" alt="" loading="lazy" decoding="async"><span><strong>Fisheries assistance</strong><small>Fingerlings, feed &amp; fishing equipment</small></span><span class="welcome-plus" aria-hidden="true"></span></summary>
                    <div class="welcome-service-body"><p>Discuss your fishing or aquaculture activity with the agriculture office. Ask which fisheries programs are available, what documentation is needed, and where to get technical guidance.</p><a href="#initiatives">View fisheries resources</a></div>
                </details>
                <details class="welcome-service">
                    <summary><img class="welcome-service-photo" src="{{ asset('images/welcome/animal-health.jpg') }}" width="112" height="88" alt="" loading="lazy" decoding="async"><span><strong>Animal-health services</strong><small>Vaccination, deworming &amp; treatment</small></span><span class="welcome-plus" aria-hidden="true"></span></summary>
                    <div class="welcome-service-body"><p>Contact your agriculture or veterinary office about service schedules for livestock, poultry, and pets. Have the animal species, number of animals, and previous service information ready.</p><p>For a sick animal, contact veterinary staff directly for assessment.</p><a href="#visit">Plan your office visit</a></div>
                </details>
                <details class="welcome-service">
                    <summary><img class="welcome-service-photo" src="{{ asset('images/welcome/rice-fields.jpg') }}" width="112" height="88" alt="" loading="lazy" decoding="async"><span><strong>Farm records &amp; parcel mapping</strong><small>Farm location, mapped parcels &amp; registry cards</small></span><span class="welcome-plus" aria-hidden="true"></span></summary>
                    <div class="welcome-service-body"><p>Ask staff to review your farm location and recorded parcels. If you have a local farmer registry card, its QR code opens the associated public land-information page.</p><p>A mapped parcel or registry card is not proof of land ownership or a cadastral survey.</p><a href="#visit">Prepare your farm information</a></div>
                </details>
                <details class="welcome-service">
                    <summary><img class="welcome-service-photo" src="{{ asset('images/welcome/machinery.jpg') }}" width="112" height="88" alt="" loading="lazy" decoding="async"><span><strong>Cooperatives &amp; machinery</strong><small>Farmer groups &amp; equipment inquiries</small></span><span class="welcome-plus" aria-hidden="true"></span></summary>
                    <div class="welcome-service-body"><p>Ask about cooperative membership records and agricultural machinery managed through your local office. Staff can explain equipment availability and the applicable arrangements.</p><p>This website does not provide public machinery bookings.</p><a href="#visit">Plan your office visit</a></div>
                </details>
            </div>
            <p class="welcome-service-footnote">Services are coordinated by the responsible local office. Confirm current requirements and schedules before travelling.</p>
        </section>

        <section class="welcome-initiatives welcome-section" id="initiatives" aria-labelledby="initiatives-title">
            <div class="welcome-container">
                <div class="welcome-section-heading"><div><p class="welcome-section-label">DA initiatives &amp; resources</p><h2 id="initiatives-title">Programs and learning,<br>straight from the source.</h2></div><p>Go directly to the agencies behind the programs. These links open their official websites.</p></div>
                <div class="welcome-resource-layout">
                    <article class="welcome-feature-resource">
                        <span class="welcome-resource-source">Department of Agriculture</span>
                        <h3>Start with your<br>farmer registration.</h3>
                        <p>The Registry System for Basic Sectors in Agriculture (RSBSA) helps identify farmers and fisherfolk for agricultural programs.</p>
                        <p>Ask your local agriculture office to check your registration and keep your information up to date.</p>
                        <a href="https://finder-rsbsa.da.gov.ph/ph" class="welcome-resource-link">Open DA’s RSBSA Finder <span aria-hidden="true">↗</span></a>
                    </article>
                    <div class="welcome-resource-list">
                        <article><span class="welcome-resource-source">DA-PhilRice</span><h3><a href="https://rcef-seed.philrice.gov.ph/rcef_site/home">Rice programs &amp; growing guidance <span aria-hidden="true">↗</span></a></h3><p>Read about certified rice seeds and rice production learning resources from PhilRice.</p></article>
                        <article><span class="welcome-resource-source">Agricultural Training Institute</span><h3><a href="https://elearn.e-extension.gov.ph/">Learning for your next season <span aria-hidden="true">↗</span></a></h3><p>Find online courses on crops, livestock, fisheries, and farm practices through ATI.</p></article>
                        <article><span class="welcome-resource-source">Bureau of Fisheries and Aquatic Resources</span><h3><a href="https://www.bfar.da.gov.ph/">Resources for fisherfolk <span aria-hidden="true">↗</span></a></h3><p>Find fisheries program information and official advisories from BFAR.</p></article>
                        <article><span class="welcome-resource-source">Department of Agriculture</span><h3><a href="https://www.da.gov.ph/">Agriculture news &amp; announcements <span aria-hidden="true">↗</span></a></h3><p>Read announcements and program updates from the Department of Agriculture.</p></article>
                    </div>
                </div>
            </div>
        </section>

        <section class="welcome-visit welcome-container welcome-section" id="visit" aria-labelledby="visit-title">
            <div><p class="welcome-section-label">Before your visit</p><h2 id="visit-title">A little preparation<br>goes a long way.</h2><p>Visit the agriculture office of the municipality where your farm or livelihood is registered. For animal-health concerns, ask for the responsible veterinary office.</p><p class="welcome-visit-note">Requirements vary by service. Confirm the office location, schedule, and required documents with your LGU before you go.</p></div>
            <div class="welcome-checklist"><h3>Have these details ready, if available</h3><ul><li><span>Your farmer information</span><p>RSBSA number or farmer registry card, and your current contact details.</p></li><li><span>Your farm or livelihood details</span><p>Barangay, municipality, crops or species, and the area or activity concerned.</p></li><li><span>Your previous assistance or service</span><p>Relevant release slips, service records, or other documents related to your inquiry.</p></li><li><span>What you need help with</span><p>The program, record correction, or service you would like to discuss.</p></li></ul></div>
        </section>

        <section class="welcome-office" aria-labelledby="office-title"><div class="welcome-container"><div><h2 id="office-title">Working at an agriculture office?</h2><p>Sign in to manage your municipality’s records, services, and reports.</p></div><a class="welcome-button" href="{{ route('login') }}">Open office sign in</a></div></section>
    </main>
    <footer class="welcome-footer welcome-container">
        <div><x-brand /><p>Farmer information. Local services. Connected offices.</p><p class="welcome-footer-note">Public information only. Personal records and office tools require authorized access.</p></div>
        <div class="welcome-footer-links"><a href="#top">Back to top ↑</a></div>
        <details class="welcome-photo-credit" id="photo-credit">
            <summary>Photography &amp; credits</summary>
            <p>Scenes of farming and fishing in the Philippines. Photographs from Wikimedia Commons, cropped for display.</p>
            <ul>
                <li>Rice fields, Murcia: <a href="https://commons.wikimedia.org/wiki/File:Rice_fields_under_the_clear_blue_sky.jpg">Mark Daniel Lecciones</a> · <a href="https://creativecommons.org/licenses/by-sa/4.0/">CC BY-SA 4.0</a>.</li>
                <li>Rice planting, Happao: <a href="https://commons.wikimedia.org/wiki/File:Planting_rice_in_the_Happao_terraces.jpg">BENNY GROSS.1</a> · <a href="https://creativecommons.org/licenses/by-sa/4.0/">CC BY-SA 4.0</a>.</li>
                <li>Rice panicles, Baliuag: <a href="https://commons.wikimedia.org/wiki/File:1291Poblacion_Bulacan_Baliuag_Proper_68.jpg">Judgefloro</a> · <a href="https://creativecommons.org/publicdomain/zero/1.0/">CC0</a>.</li>
                <li>Fishing boat, Ilocos Norte: <a href="https://commons.wikimedia.org/wiki/File:Fishing_boat_Philippines._(37044333240).jpg">Bernard Spragg. NZ</a> · <a href="https://creativecommons.org/publicdomain/zero/1.0/">CC0</a>.</li>
                <li>Carabao, Dumaguete: <a href="https://commons.wikimedia.org/wiki/File:Carabao.jpg">Mike Gonzalez (TheCoffee)</a> · <a href="https://creativecommons.org/licenses/by-sa/3.0/">CC BY-SA 3.0</a>.</li>
                <li>Tractor, Camiling: <a href="https://commons.wikimedia.org/wiki/File:Camiling,Tarlacjf2070_12.JPG">Ramon FVelasquez</a> · <a href="https://creativecommons.org/licenses/by-sa/3.0/">CC BY-SA 3.0</a>.</li>
            </ul>
        </details>
    </footer>
</body>
</html>
