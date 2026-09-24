@if($totals->isNotEmpty())
    <details class="fp-panel fp-totals">
        <summary>Recorded quantities · {{ $totalsPeriod }}</summary>
        <p class="fp-small">Totals are listed by item and unit.@if($totals->count() >= 24) Showing the first 24 totals; see the history below for every record.@endif</p>
        <ul class="fp-total-list">
            @foreach($totals as $total)
                <li>
                    <div>
                        <strong>{{ $categoryLabels[$total->{$categoryKey}] ?? 'Type not recorded' }}</strong>
                        <p class="fp-small">{{ number_format($total->records) }} {{ (int) $total->records === 1 ? 'record' : 'records' }}
                            @if($total->quantities_recorded < $total->records)
                                · {{ number_format($total->records - $total->quantities_recorded) }} without quantity
                            @endif
                        </p>
                    </div>
                    <span class="fp-quantity">@if($total->total_quantity !== null){{ number_format((float) $total->total_quantity, $quantityDecimals) }} {{ $unitLabels[$total->quantity_unit] ?? '— unit not recorded' }}@else Quantity not recorded @endif</span>
                </li>
            @endforeach
        </ul>
    </details>
@endif
