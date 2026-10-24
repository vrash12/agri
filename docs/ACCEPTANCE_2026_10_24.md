# Acceptance evidence — October 24, 2026

Status: local engineering checks completed below; **physical-device, screen-reader audio and staff approval remain unverified**. No production deployment, account operation or office-data change ran. Those human checks cannot be signed on behalf of staff or inferred from emulation.

## Isolated candidate

The release worktree uses Laravel 12.69.3, local PHP 8.4.10 and a new loopback MariaDB 10.4.32 database installed with all 33 repository migrations. Synthetic browser fixtures contain two towns/provinces, two municipal staff, two farmers and 33 source rows. No production records were copied. The server binds only `127.0.0.1:8782`, with separate private storage, key, cache, sessions, views and logs; it is not a public or phone-accessible test site.

Outgoing Laravel HTTP requests were blocked. Office-help replies were faked with a visibly labelled synthetic three-step answer to test presentation. Record reports and off-topic refusal used the real application logic. Real AI inference, Google Maps and external-provider acceptance were not repeated.

## Browser and keyboard evidence

| Check | Observed result |
| --- | --- |
| Normal staff sign-in, required notice and dashboard | Passed on the freshly migrated MariaDB schema |
| Farmers → Outside LGU → Delisted | Passed via rendered links and Enter; correct town and source-row counts; other-office row absent |
| Original fields | Enter opened native disclosures; green focus outline visible; long values wrapped |
| Pagination/search | 10/page then 11–16 of 16; no-match state and Clear search passed |
| Delisted safeguards | Reasons and original fields present; no linked active-profile/ID or reactivation action |
| Responsive reflow | No horizontal overflow at **measured CSS widths** 358, 389, 582, 768 and 1910; fields stack on phones |
| Accessibility structure | Named headings, navigation, labels, native disclosures, dialog, log/status and semantic ordered list inspected |
| Chat keyboard | Enter opened the native modal and focused composer; close and Escape returned focus to launcher; Enter activated Show full reply and returned focus to composer |
| Shared navigation | Enter opened the phone menu and focused Close menu; Farmers opened by Enter. Skip to content moved focus to `mainContent` |
| Reply reveal | Partial text observed with `aria-busy=true` and answer `aria-hidden=true`; completion announced “Reply ready.” |
| Numbered reply | Three steps rendered as one decimal `<ol>` with three `<li>` elements |
| Agriculture-only scope | Space-travel poem request received the agriculture/AgriLGU-only refusal |
| Phone chat | Dialog/composer/Send fit measured 389px width; no horizontal dialog overflow |
| Console | No warnings/errors captured in final registry/chat checks |
| Session expiry | Idle session returned to sign-in; synthetic sign-in resumed normally |

The viewport control uses a nonstandard device scale: dimensions were checked with `innerWidth`. The smallest attempted override measured 358px, so **320px, true 200% zoom, touch and on-screen keyboards are not certified**. Overrides were reset after testing. Tab reached Send/About; at the browser chrome boundary `:focus` became unavailable, so a complete Tab-cycle audit is not claimed. Actual screen-reader audio and OS reduced-motion settings were not exercised; motion/interruption behavior has JavaScript regression coverage.

## Defects corrected

- Outside LGU's explanation was blank because inline Blade `@else` joined the following word. Separated directives and a regression assertion fix it.
- Added the missing eight original tables/user fields for empty installations, plus guarded interactive first-owner setup. Existing complete legacy cores are adopted without changes.
- Corrected foreign-key/index rollback order on MariaDB and SQLite.
- Corrected Ramos's checksum to match Git's LF artifact. The former checksum was reproduced by converting only the final newline to CRLF. No geometry, source-file bytes or ownership changed; integrity checks remain mandatory.
- Legacy transactional tests now migrate in-memory schema. Fixtures provide MySQL date-part/order functions; the shared age expression preserves MySQL native behavior and supports completed birthdays in SQLite. Farmer search uses an explicit SQL escape character for literal `%`, `_`, `!` and backslash on both engines. The demo-seeder test creates its own synthetic owner/geography.

## Automated/database evidence

- Initial complete PHP run: **1,989 tests; 1,955 passed, 34 failed; 34,868 assertions**. This was not a passing run. Its failures led to the corrections above.
- Every affected suite, fresh installation and registry sections rerun after fixes: **202 passed, 7 skipped, 3,105 assertions**. Seven MySQL-only logical-backup tests are explicitly skipped in SQLite, never counted as passes. The updated literal-search assertions and new age-expression tests then passed separately: **13 tests / 58 assertions**. The entire long-running suite was not repeated after focused corrections.
- JavaScript: **338 passed, zero failures**, including typing skip/reduced-motion/cancellation/safe rendering and workbook navigation.
- Fresh installation: **9 tests / 114 assertions**, including repeat migration, rollback/reinstall, no automatic accounts/data, legacy preservation, partial/unrelated refusal and owner/audit/password safeguards.
- MariaDB: **33 migrations / 30 tables**, initially zero accounts/farmers/geography. Repeat migration, full reset and reinstall passed. Synthetic browser checks then ran on this schema.
- A synthetic logical backup restored into a separate newly created MariaDB database: **30 table counts / 82 rows matched**. This does not certify real production disaster recovery or private-file/key recovery.
- Native MariaDB literal-character searches and birthday-age checks passed; temporary rows were rolled back.
- Pint, changed PHP syntax, Blade compilation, route/command discovery and whitespace checks passed. Private logs and synthetic screenshots remain outside version control; credentials are excluded. The generated phone-chat proof is also available locally in ignored `output/acceptance-2026-10-24/chat-phone.png`.

## Human acceptance packet

Use an approved candidate environment with synthetic data. Record tester, date, exact candidate commit, OS/browser, expected/actual result and defects. Publishing that environment requires a separate authorized deployment; localhost cannot be opened by a physical phone.

| Tester/device | Required scenario | Status |
| --- | --- | --- |
| Android + Chrome; iPhone + Safari | Sign in, menu, both sections, search, pagination, original fields; portrait/landscape, touch, long values and on-screen keyboard | Pending |
| Keyboard-only desktop | Sign-in → skip link → navigation/filter/disclosure/pagination → all chat controls → Escape; visible focus and correct return | Partial engineering checks passed; human walkthrough pending |
| NVDA + Firefox/Chrome; VoiceOver + Safari | Read labels, context/disclosure state; concise typing status, readable final answer, numbered-list semantics without repeated character announcements | Pending |
| OS reduced motion and 200% browser zoom | No progressive animation when motion reduced; controls/content reachable under true zoom | Automated motion coverage only; actual settings pending |
| Municipal agriculture staff | Confirm Outside LGU vs Delisted meaning, row counts, original address, reasons and read-only safeguards against expected office workflow | Pending |

Fix and rerun failed cases before signing. Neither the application nor the coding agent generates a staff signature or approval decision.
