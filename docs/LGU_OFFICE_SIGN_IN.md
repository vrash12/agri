# LGU-specific office sign-in

Status: deployed October 25, 2026 in runtime `cdbc85c`; the live Mayantoc tenant sign-in redirects to its scoped central form. See [the release receipt](OPERATIONAL_RELEASE_2026_10_25.md). The owner selected an LGU-branded sign-in entry that opens the existing secure central dashboard. This does not move office modules onto tenant hosts or create accounts.

## Workflow

Each published, active LGU website links **Office sign in** to its own entry. The Settings website editor also provides **Open LGU office sign in** for published pages. With tenant hosting enabled, `https://{slug}.agritarlac.online/login` accepts GET/HEAD only and redirects to the fixed central `APP_URL/lgus/{slug}/login`. Without tenant hosting, the link goes directly to that central path. The central page shows the approved LGU logo, municipality/city, supervising province and office name, with the existing credential form, required confidentiality acknowledgment and agricultural slideshow.

Credentials POST only to the central `/lgus/{slug}/login` endpoint. Municipal heads and municipal staff must belong to that exact numeric municipality. Province, region, owner, veterinary, visitor, evaluator and another municipality's identities cannot sign in through this entry. Provincial/regional/system users retain the central `/login` page; the farmer tab retains the shared farmer portal. Existing passwords and permissions are reused. There is no self-registration, automatic staff provisioning or new LGU privilege.

Successful LGU sign-in opens the central dashboard and discards an older intended destination. Existing municipality policies determine every subsequent read/write. Logout, session expiry and other central session behavior stay unchanged. Already signed-in users follow the existing guest-route redirect rather than silently switching accounts. An unpublished/unknown/inactive LGU or a missing profile fails with 404; such staff may still use central sign-in. Ramos therefore needs its approved profile and public website saved/published before its branded entry is available.

## Architecture and safeguards

- `PublishedLguWebsite` supplies the same publication/active-office/profile lookup to public pages, media and LGU sign-in. No operational records are loaded into the public form.
- `ResolveLguWebsiteHost` redirects a permitted tenant `/login` before starting a tenant session, after a bounded publication lookup. It still rejects tenant credential POSTs, office routes, APIs and arbitrary callbacks. The new exception does not permit the central credential route on a tenant hostname.
- `LguLoginController` resolves the website from the fixed route and calls the existing `AuthController` with that trusted context. `AuthController` constrains the account query by numeric municipality and municipal role **before** issuing any session/remember token. Submitted scope/role fields are ignored. Wrong-LGU credentials receive the same invalid-credentials message as a wrong password.
- Existing validation, CSRF, account activation/scope checks, session regeneration, farmer/office audience exclusion, inactivity limits and audit suppression remain. The five-attempt email/IP lockout is shared across central and LGU forms, so switching entries does not reset it. Central credential POSTs retain the 30/minute IP throttle. Central LGU form GETs are bounded at 120/minute, and the pre-session tenant redirect has its own 60/minute IP ceiling.
- LGU sign-in responses and the tenant redirect are private/non-storable. `SESSION_DOMAIN` stays blank; no cookie sharing, bearer handoff, password URL, CORS exception or CSP relaxation is introduced. Successful login audit metadata adds only `entry_municipality_id`; passwords and hashes are never logged or flashed.

## Verification

The selected isolated suites passed **128 tests / 2,378 assertions** across LGU/profile/publication/media, municipal/province/region isolation, central and farmer login, shared lockouts, CSRF, headers, encrypted session storage/history and module boundaries. After the final form/Settings-view refinements, all **50 focused tests / 531 assertions** passed again. New LGU cases also verify veterinary central redirect and central heartbeat/idle expiry/logout using a complete synthetic province schema. The existing missing `.env.testing` warning marks remain.

A broader attempt included `ProvincialVeterinaryAccessTest` and `SessionIdleTimeoutTest`: their ten legacy cases require an imported baseline schema and failed during fixture setup because the disposable in-memory database has no `provinces` table, before reaching authentication. Representative cases from each suite reproduce the same failure with the original, unmodified `AuthController` loaded from the prior Git revision. Those suites are not claimed as passing; their fixture files were not changed. The corresponding authentication/idle behavior is covered by the new isolated cases.

Actual-layout browser previews use synthetic LGU relationships and the current public logo. The central page uses native required/autocomplete fields and its scoped form action. Desktop and a 390px iframe show loaded artwork and no horizontal overflow. Preview submission is disabled; no real account or production login was attempted.

## Release requirements

No migration, account provisioning, DNS update or new environment variable is required. Deploy controllers, middleware, address/publication services, routes and shared views together. Mirror `public/css/login-layout.css` to served `public_html/css` (0644); LGU hosts share that directory through the existing symlink. Rebuild optimized autoloading and configuration/route/view caches without clearing the general cache or provider counters. Retain host-only sessions and existing private settings. Include the preceding local footer correction (`e3641d0`) in the reviewed release. Verify the new tenant redirect, central LGU page, scope denial, normal central/farmer sign-in and unchanged demo host before reopening.
