@once
<style>
  /* Harvest records. Only what the shared operations styles do not already give. */
  .harvest-source{display:inline-flex;align-items:center;gap:5px;padding:3px 8px;border-radius:999px;font-size:11px;font-weight:800;letter-spacing:.02em}
  .harvest-source.projected{color:#17643a;background:#e7f4eb}
  .harvest-source.direct{color:#4a5a80;background:#eef1f8}
  .harvest-quantity{display:flex;flex-direction:column;gap:2px}
  .harvest-quantity strong{font-size:15px;font-variant-numeric:tabular-nums}
  .harvest-quantity small{color:var(--module-muted);font-size:11px}

  /* One card per unit, because the units are never added together. */
  .harvest-unit-totals{display:flex;flex-wrap:wrap;gap:8px;margin-top:6px}
  .harvest-unit-total{display:flex;flex-direction:column;padding:8px 12px;border:1px solid var(--module-line,#e2e8e5);border-radius:8px;background:#fff}
  .harvest-unit-total strong{font-size:16px;font-variant-numeric:tabular-nums}
  .harvest-unit-total small{color:var(--module-muted);font-size:11px}

  .harvest-empty{padding:40px 24px;text-align:center;color:var(--module-muted)}
  .harvest-empty strong{display:block;margin-bottom:6px;color:var(--module-ink,#22322b);font-size:16px}

  .harvest-locked-note{margin:0;color:var(--module-muted);font-size:12px;line-height:1.5}

  @media (max-width: 720px){
    .harvest-unit-totals{flex-direction:column}
    .harvest-unit-total{width:100%}
  }
</style>
@endonce
