@php
  $insightCount = max(0, (int) $count);
  $insightPercent = $total > 0 ? $insightCount / $total * 100 : null;
  $insightWidth = min(100, max(0, $insightPercent ?? 0));
  $insightShare = $insightPercent === null ? 'Share unavailable' : ($insightPercent > 0 && $insightPercent < .1 ? '<0.1%' : number_format($insightPercent, 1).'%');
  $insightColor = isset($rank)
      ? ['green', 'gold', 'blue', 'teal', 'violet'][($rank - 1) % 5]
      : match (mb_strtolower(trim((string) $label))) {
          'female' => 'violet', 'male' => 'blue', 'other' => 'gold', default => 'muted',
      };
@endphp
<div class="registry-insight-row" data-insight-row data-insight-color="{{ $insightColor }}">
  <dt>
    @isset($rank)<span class="registry-insight-rank" aria-hidden="true">{{ $rank }}</span>@else<span class="registry-insight-key" aria-hidden="true"></span>@endisset
    <span>{{ trim((string) $label) !== '' ? $label : 'Not recorded' }}</span>
  </dt>
  <dd>
    <strong>{{ number_format($insightCount) }} <span class="registry-insight-sr">{{ Str::plural('farmer', $insightCount) }}</span></strong>
    <span class="registry-insight-share">{{ $insightShare }}</span>
  </dd>
  <dd class="registry-insight-track" aria-hidden="true"><span style="width:{{ number_format($insightWidth, 3, '.', '') }}%"></span></dd>
</div>
