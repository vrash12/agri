{{-- Public descriptions and fictional illustrations only. Never query office records for previews. --}}
@php
    $featureGroups = [
        [
            'id' => 'people', 'title' => 'Farmers & their records',
            'intro' => 'From the first registry entry to a farmer checking their own records.',
            'features' => [
                [
                    'title' => 'Farmer registry', 'summary' => 'One place for identity, farm and service information.',
                    'items' => [
                        'Maintain farmer profiles, photographs, RSBSA and FFRS references, farm details and sector classifications.',
                        'Find farmers by name or registry ID, filter records and review their linked parcels and services.',
                        'Retain imported source rows alongside the farmer profile, with separate Outside LGU and Delisted reference sections.',
                    ],
                ],
                [
                    'title' => 'Digital IDs & QR parcel pages', 'summary' => 'An LGU-issued registry card, on screen or in print.',
                    'items' => [
                        'Preview both sides, enlarge the QR code, print the card or download a PNG with the issuing LGU logo and office details.',
                        'Include farmer and registry references, recorded farm information and printed signature lines.',
                        'Scan the QR to open a limited public parcel map. The card is a local registry card, not a national government ID or proof of land ownership.',
                    ],
                ],
                [
                    'title' => 'Farmer self-service portal', 'summary' => 'A private view of the farmer’s own information.',
                    'items' => [
                        'Use a separate farmer sign-in issued by authorized agriculture staff after identity verification.',
                        'View the farmer profile, all owned parcel boundaries, selected-year seasonal crops, assistance and harvest history.',
                        'View records without changing office data; ask authorized staff for registry corrections or help recovering access.',
                    ],
                ],
                [
                    'title' => 'Record quality & duplicate review', 'summary' => 'Keep incomplete and conflicting information visible.',
                    'items' => [
                        'Review missing registry references, farm locations and unmapped farmers through office attention lists.',
                        'Match approved imports within the correct municipality and flag ambiguous or duplicate records for review.',
                        'Keep related farmers, parcels, releases and memberships in the same LGU, with checks before protected records can be removed.',
                    ],
                ],
            ],
        ],
        [
            'id' => 'maps', 'title' => 'Land, crops & maps',
            'intro' => 'Connect farmer records with mapped parcels, seasons and local boundaries.',
            'features' => [
                [
                    'title' => 'GIS parcel workspace', 'summary' => 'Locate farms and work with their boundaries.',
                    'items' => [
                        'Draw, edit or import parcel boundaries, calculate mapped area and keep parcels linked to their farmer.',
                        'Search locations, focus or hide parcels, use satellite and hybrid views, toggle map labels and review parcel details.',
                        'Import supported KML, KMZ and XML files, with boundary and ownership validation.',
                    ],
                ],
                [
                    'title' => 'Seasonal crops & planted sections', 'summary' => 'See the recorded crop for each year and season.',
                    'items' => [
                        'Record crops by year and dry or wet season, either for a whole parcel or within drawn crop sections.',
                        'Use crop colors, icons, filters and farmer land/crop charts to review rice, corn and other recorded crops.',
                        'Record a harvest against an eligible saved crop section. A crop color alone does not measure planted area or production.',
                    ],
                ],
                [
                    'title' => 'Municipality & barangay references', 'summary' => 'Review farms in their local planning context.',
                    'items' => [
                        'View active municipality boundaries and available barangay reference layers within the account’s area.',
                        'Review parcels outside, crossing or near a municipality boundary, including records needing geometry checks.',
                        'Authorized administrators can draw, import, version, activate and archive geofences. Reference boundaries are for planning, not cadastral surveys.',
                    ],
                ],
                [
                    'title' => 'Parcel sheets & land snapshots', 'summary' => 'Bring map information into office reports.',
                    'items' => [
                        'Print parcel sheets and download high-resolution satellite map PNGs with parcel information.',
                        'Download scoped municipality land snapshots showing the boundary and mapped parcels.',
                        'Keep map frames aligned with the current boundaries and show a useful failure message when imagery is unavailable.',
                    ],
                ],
            ],
        ],
        [
            'id' => 'climate', 'title' => 'Weather & field decisions',
            'intro' => 'Bring dated observations and field evidence together for staff review.',
            'features' => [
                [
                    'title' => 'Satellite field checks', 'summary' => 'Review vegetation and moisture indicators.',
                    'items' => [
                        'Request a parcel check using NDVI, NDMI, SAVI or EVI, with a map, legend and dated measurements for the selected index.',
                        'Review available observation dates, natural-color comparisons, index guides and completed satellite history alongside the parcel.',
                        'Cloud cover, resolution and missing observations remain visible. An index alone does not diagnose drought or crop damage.',
                    ],
                ],
                [
                    'title' => 'El Niño readiness', 'summary' => 'Track office priorities and reported affected parcels.',
                    'items' => [
                        'Review municipality and available barangay risk maps with rainfall evidence, saved NDVI readings and reported affected parcels.',
                        'Authorized staff record Normal, Watch, Alert or Critical assessments with supporting evidence and review dates.',
                        'Missing or expired assessments stay Not assessed. Weather and satellite readings do not automatically declare a disaster.',
                    ],
                ],
                [
                    'title' => 'Crop guidance & field visits', 'summary' => 'Compare evidence, record observations and follow up.',
                    'items' => [
                        'Record dated parcel visits, crop stage, observed condition, verified water supply and available soil or slope evidence.',
                        'Compare Rice, Corn and Mungbean reference requirements with the same field evidence, keeping sources and unknown values visible.',
                        'Review crop-stress prompts and satellite trends, schedule a next visit and retain completed follow-up history. These comparisons support review rather than prescribe planting.',
                    ],
                ],
                [
                    'title' => 'Weather & rainfall evidence', 'summary' => 'Plan around forecasts and official advisories.',
                    'items' => [
                        'Read municipality-level forecasts and agriculture guidance, with direct links to official PAGASA bulletins.',
                        'Review dated Open-Meteo forecasts and CHIRPS recent-rainfall comparisons where source coverage is available.',
                        'Keep source dates and unavailable data visible. Municipality forecasts are not measurements taken on an individual farm.',
                    ],
                ],
            ],
        ],
        [
            'id' => 'programs', 'title' => 'Assistance & harvests',
            'intro' => 'Follow recorded releases, their coverage and the production reported afterward.',
            'features' => [
                [
                    'title' => 'Agriculture & fisheries assistance', 'summary' => 'Record what was released, to whom and when.',
                    'items' => [
                        'Track rice, corn and other seeds, fertilizer and crop inputs, plus fingerlings, feed and fishing gear.',
                        'Record beneficiaries, program and item details, release dates, quantities and units within the responsible LGU.',
                        'Filter release histories and export scoped reports while keeping release counts, beneficiaries and quantities distinct.',
                    ],
                ],
                [
                    'title' => 'Rice seed distribution sheets', 'summary' => 'Organize seasonal releases in an editable sheet.',
                    'items' => [
                        'Prepare distribution batches with planting season and year, beneficiary rows, rice area, seed variety and bag weight.',
                        'Review supported NRP Excel imports and retain the applicable source details.',
                        'Record harvest season, area and production explicitly, with separate seed and harvest bag weights.',
                    ],
                ],
                [
                    'title' => 'Harvests & production', 'summary' => 'Keep recorded output connected to its crop context.',
                    'items' => [
                        'Record supported direct harvests or release-linked rice harvests with farmer, year, season, commodity and unit.',
                        'Link eligible direct harvests to a saved parcel crop section and review crop-to-harvest records.',
                        'Compare production by municipality, commodity and unit. Missing entries remain distinct from recorded zero production.',
                    ],
                ],
                [
                    'title' => 'Assistance coverage maps', 'summary' => 'See where recorded support has reached.',
                    'items' => [
                        'Explore municipality assistance coverage by assistance type, program reference and recorded planting year or season.',
                        'Compare municipality release counts, distinct linked farmers and quantities by unit, with planting-year and season filters where recorded.',
                        'Keep municipalities without usable boundaries and incomplete records in the report. Coverage colors describe municipality releases, not individual parcel eligibility.',
                    ],
                ],
            ],
        ],
        [
            'id' => 'operations', 'title' => 'Everyday office work',
            'intro' => 'Manage services, groups and equipment with reports that support follow-up.',
            'features' => [
                [
                    'title' => 'Animal-health services', 'summary' => 'Record care for livestock, poultry and pets.',
                    'items' => [
                        'Record vaccination, deworming, vitamins or supplementation, and treatment by species and municipality.',
                        'Maintain owner or beneficiary details, service dates, animal counts and applicable service information.',
                        'Review filtered service records, monthly trends and species totals; veterinary accounts have dedicated Animal Health access.',
                    ],
                ],
                [
                    'title' => 'Cooperatives & memberships', 'summary' => 'Keep farmer groups and their members connected.',
                    'items' => [
                        'Maintain cooperative profiles, office and contact information, and municipality-owned membership records.',
                        'Assign eligible farmers to a cooperative and review its member list.',
                        'Export a formatted member workbook and retain membership changes in the audit trail.',
                    ],
                ],
                [
                    'title' => 'Machinery inventory & maintenance', 'summary' => 'Know the equipment and what needs attention.',
                    'items' => [
                        'Maintain machinery asset codes, types, holders, condition and availability within each municipality.',
                        'Record acquisition details, service hours and maintenance dates, and review equipment needing service.',
                        'Filter inventory, export CSV reports and compare condition and availability by equipment type.',
                    ],
                ],
                [
                    'title' => 'Dashboards & operational reports', 'summary' => 'Start with priorities, then open the detail.',
                    'items' => [
                        'Review registry and mapping coverage, annual assistance reach, animal services, cooperatives, machinery and recent activity.',
                        'Open attention lists for incomplete records, unmapped farmers and equipment needing maintenance.',
                        'Explore reporting-year trends and municipality comparisons with readable figures behind the charts, within the account’s assigned area.',
                    ],
                ],
            ],
        ],
        [
            'id' => 'connected', 'title' => 'Connected offices & guidance',
            'intro' => 'Help staff find answers and let each LGU share approved public information.',
            'features' => [
                [
                    'title' => 'Agriculture & system Assistant', 'summary' => 'Ask for guidance in English or Filipino.',
                    'items' => [
                        'Ask about reviewed agriculture topics or system workflows, with sourced help and step-by-step numbered answers.',
                        'Find a farmer or request permitted summaries and reports through the office conversation; unrelated questions are declined.',
                        'Private record answers run locally within the user’s permissions. The Assistant cannot edit records, create accounts or approve requests.',
                    ],
                ],
                [
                    'title' => 'LGU profiles & ID issuer settings', 'summary' => 'Use the correct office identity on farmer cards.',
                    'items' => [
                        'Authorized managers maintain the LGU logo, office name, approved contacts, website, address and location.',
                        'Set the optional printed office signatory and preview issuer details from Settings.',
                        'New card previews and exports use the saved issuing-office profile; previously printed cards retain their original details.',
                    ],
                ],
                [
                    'title' => 'Public LGU websites', 'summary' => 'Give each participating office its own public page.',
                    'items' => [
                        'Prepare welcome text, services, announcements, office hours and a cover photo for an LGU website.',
                        'Preview content before publication, then list the published page in the public LGU directory.',
                        'Publish approved office information while keeping operational records private. Optional LGU subdomains require separate hosting setup.',
                    ],
                ],
                [
                    'title' => 'Protected office files', 'summary' => 'Keep reference and backup files within office access.',
                    'items' => [
                        'Upload and organize permitted files in the municipality-owned Backup Folder.',
                        'Search files, preview supported documents and download originals according to the account’s permissions.',
                        'Retain authorized upload, download and removal activity. This file folder does not automatically schedule database backups.',
                    ],
                ],
            ],
        ],
        [
            'id' => 'administration', 'title' => 'Access, accountability & data',
            'intro' => 'Support multiple offices with clear ownership and careful record handling.',
            'features' => [
                [
                    'title' => 'Accounts & geographic access', 'summary' => 'Give each person the tools for their responsibility.',
                    'items' => [
                        'Support System Owners, Regional Heads, provincial administrators and staff, municipal heads and staff, and veterinary roles.',
                        'Manage accounts, active status and authorized assignments with region, province and municipality scope.',
                        'Provide restricted visitor and time-limited GIS evaluation access separately from staff and farmer identities.',
                    ],
                ],
                [
                    'title' => 'Audit trail & accountability', 'summary' => 'Review important actions in their office context.',
                    'items' => [
                        'Review sign-ins, supported record changes, exports and administrative actions with actor, date, module and geographic context.',
                        'Filter events, inspect available before-and-after values and download permitted audit CSV reports.',
                        'Keep audit visibility within the authorized oversight area and exclude credentials and protected values.',
                    ],
                ],
                [
                    'title' => 'Privacy & shared editing', 'summary' => 'Protect records while colleagues work together.',
                    'items' => [
                        'Keep personal records and files behind authorized sign-in, with limited public QR information.',
                        'Use sign-in attempt limits, inactivity expiry, an upcoming-session warning and account status checks.',
                        'Reject stale edits and duplicate submissions rather than silently replacing a colleague’s newer changes.',
                    ],
                ],
                [
                    'title' => 'Imports, exports & service usage', 'summary' => 'Move approved data and monitor shared services.',
                    'items' => [
                        'Use supported Excel and geographic-file imports, with validation and record matching for the relevant module.',
                        'Download permitted CSV and Excel reports, registry cards, parcel sheets and map images.',
                        'Authorized oversight accounts can review recorded satellite and static-map usage and system metrics. Local counters are not complete provider billing.',
                    ],
                ],
            ],
        ],
    ];
@endphp

<section class="welcome-system" id="system" aria-labelledby="system-title">
    <div class="welcome-system-head welcome-section">
        <div class="welcome-container welcome-system-layout">
            <div class="welcome-system-intro">
                <p class="welcome-section-label"><span class="welcome-section-number" aria-hidden="true">02</span> Inside AgriLGU</p>
                <h2 id="system-title" data-welcome-reveal>See the tools.<br><span>Follow the work.</span></h2>
                <div class="welcome-system-copy">
                    <p>From the first farmer record to a mapped parcel, an assistance release and an office report. Explore how AgriLGU brings the work together.</p>
                    <div class="welcome-tour-facts" aria-label="About this visual tour"><span><strong>28</strong> feature previews</span><span><strong>7</strong> connected workflows</span></div>
                    <a class="welcome-button welcome-system-entry" href="#feature-workflows">Explore the sample screens <span aria-hidden="true">↓</span></a>
                </div>
            </div>
            <figure class="welcome-tour-overview">
                <div class="welcome-tour-overview-heading"><span>YOUR OFFICE AT A GLANCE</span><span class="welcome-sample-badge">Sample data</span></div>
                <a href="#features-operations" aria-label="Explore the dashboard and office-work samples"><img src="{{ asset('images/showcase/operations-04.svg') }}" width="960" height="600" loading="lazy" decoding="async" alt="Illustrated Sample LGU dashboard with 48 fictional farmers, 36 mapped and 12 awaiting mapping."></a>
                <figcaption><strong>A clear starting point for the day.</strong><span>Illustrated interface using fictional records.</span></figcaption>
            </figure>
        </div>
    </div>
    <div class="welcome-tour-disclosure">
        <div class="welcome-container"><span class="welcome-sample-badge">Sample data only</span><p>All names, figures, maps and messages in these previews are fictional. They illustrate the features; they are not live LGU records or official assessments.</p></div>
    </div>
    <div class="welcome-container welcome-catalog">
        <div class="welcome-workflows" id="feature-workflows">
            <div class="welcome-workflows-heading"><p class="welcome-catalog-eyebrow">Choose a workflow</p><p>Open any sample to explore it in detail.</p></div>
                <nav class="welcome-catalog-nav" aria-label="Feature categories">
                    @foreach ($featureGroups as $group)
                        <a href="#features-{{ $group['id'] }}"><span class="welcome-workflow-number" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><span>{{ $group['title'] }}</span><span aria-hidden="true">↘</span></a>
                    @endforeach
                </nav>
            <p class="welcome-system-note">Your role and assigned area determine which tools and records you can use after signing in.</p>
        </div>
        @foreach ($featureGroups as $group)
            <section class="welcome-feature-group" id="features-{{ $group['id'] }}" aria-labelledby="features-{{ $group['id'] }}-title">
                <div class="welcome-feature-group-heading">
                    <span class="welcome-catalog-index" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                    <h3 id="features-{{ $group['id'] }}-title">{{ $group['title'] }}</h3>
                    <p>{{ $group['intro'] }}</p>
                    <a class="welcome-workflow-return" href="#feature-workflows">All workflows <span aria-hidden="true">↑</span></a>
                </div>
                <div class="welcome-feature-list">
                    @foreach ($group['features'] as $feature)
                        @php($previewId = $group['id'].'-'.str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT))
                        <article class="welcome-feature-card" aria-labelledby="feature-{{ $previewId }}-title">
                            <a class="welcome-preview-link" href="{{ asset('images/showcase/'.$previewId.'.svg') }}" data-welcome-preview data-preview-title="{{ $feature['title'] }}" data-preview-description="{{ $feature['summary'] }}" aria-label="Enlarge sample: {{ $feature['title'] }}">
                                <img src="{{ asset('images/showcase/'.$previewId.'.svg') }}" width="960" height="600" loading="lazy" decoding="async" alt="{{ $feature['title'] }} — illustrated interface using fictional sample data.">
                                <span class="welcome-preview-action">View sample <span aria-hidden="true">↗</span></span>
                            </a>
                            <details class="welcome-tool">
                                <summary><span class="welcome-tool-caption"><strong id="feature-{{ $previewId }}-title">{{ $feature['title'] }}</strong><small>{{ $feature['summary'] }}</small></span><span class="welcome-plus" aria-hidden="true"></span></summary>
                                <div class="welcome-tool-body"><ul>@foreach ($feature['items'] as $item)<li>{{ $item }}</li>@endforeach</ul></div>
                            </details>
                        </article>
                    @endforeach
                </div>
            </section>
        @endforeach
        <div class="welcome-catalog-access">
            <div><strong>Find your next step</strong><p>Farmers can open their own records. Office staff use their assigned workspace. Published LGU pages are open to everyone.</p></div>
            <div class="welcome-catalog-access-links"><a href="{{ route('farmer-portal.login') }}">Farmer sign in <span aria-hidden="true">→</span></a><a href="{{ route('lgu.index') }}">Find your LGU <span aria-hidden="true">→</span></a></div>
        </div>
    </div>
</section>

<dialog class="welcome-preview-dialog" id="welcome-preview-dialog" aria-labelledby="welcome-preview-title" aria-describedby="welcome-preview-notice">
    <div class="welcome-preview-dialog-header">
        <div><span class="welcome-sample-badge">Sample data</span><h2 id="welcome-preview-title">Feature preview</h2><p data-preview-description></p></div>
        <button class="welcome-preview-close" type="button" data-preview-close autofocus>Close <span aria-hidden="true">×</span></button>
    </div>
    <div class="welcome-preview-canvas"><img id="welcome-preview-image" width="960" height="600" alt=""><p data-preview-status role="status" aria-live="polite"></p></div>
    <div class="welcome-preview-dialog-footer">
        <div class="welcome-preview-caption"><p id="welcome-preview-notice">Illustrated preview. All data is fictional.</p><a href="#" data-preview-original>Open full-size image <span aria-hidden="true">↗</span></a></div>
        <div class="welcome-preview-paging"><button type="button" data-preview-previous><span aria-hidden="true">←</span> Previous</button><span data-preview-counter aria-live="polite"></span><button type="button" data-preview-next>Next <span aria-hidden="true">→</span></button></div>
    </div>
</dialog>
