@props(['season' => null, 'year' => null, 'fallback' => 'Season not recorded'])
{{-- Prints a stored cropping season with its stored year, for example "Dry season
     2026". Both parts always come from the saved record, so a sheet never shows a
     season heading that was hardcoded for another year. See DESIGN_SYSTEM.md §13.
     Kept as a single echo: Blade leaves @endphp uncompiled when an echo follows it
     on the same line, and a newline here would be printed inside table cells. --}}
{{ trim(trim((string) ($season ?? '')).' '.trim((string) ($year ?? ''))) ?: $fallback }}
