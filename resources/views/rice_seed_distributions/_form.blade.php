{{-- resources/views/rice_seed_distributions/_form.blade.php --}}

@php
  $r = $record ?? null;
  $val = fn($k, $d='') => old($k, data_get($r, $k, $d));

  // ✅ Seed lists based on your CURRENT unique values from Excel
  $seedVarietyClaimedOptions = [
    'BIO ZARAP','Habilis Plus','Hatao Dinorado','LP 2096','LP 534','LP 937',
    'S6003','SL-19H','SL-20H','SL-39H','SL-68H','SL-8H','US 88',
  ];

  $cropEstablishmentOptions = ['Direct', 'Transplanted'];
  $seedClassOptions = ['Certified', 'Not Specified'];
@endphp

@if ($errors->any())
  <div class="errorbox">
    <strong>Please fix the following:</strong>
    <ul>
      @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
@endif

@push('styles')
<style>
  /* Small form polish */
  .section-card{ box-shadow:none; }
  .section-head{ border-bottom:1px solid var(--border); }
  .section-title{ font-size:16px; margin:0; }
  .form-help{ margin:6px 0 0; }
  .req{ color:#b91c1c; font-weight:900; }

  label{ font-weight:900; font-size:13px; display:block; margin-bottom:6px; }
  .field{ display:flex; flex-direction:column; gap:6px; }
  .hint{ font-size:12px; color:var(--muted); margin-top:2px; }

  .chipbox{ display:flex; flex-wrap:wrap; gap:10px; }
  .chip{
    display:flex; align-items:center; gap:8px;
    padding:10px 12px; border:1px solid var(--border);
    border-radius:14px; background:#fff; cursor:pointer;
    user-select:none;
  }
  .chip input{ width:16px; height:16px; accent-color: var(--green2); }
  .chip span{ font-weight:900; }

  /* Make native selects match input if Tom Select is not loaded */
  select.input{
    width:100%;
    padding:10px 12px;
    border:1px solid var(--border);
    border-radius:12px;
    background:#fff;
    font-size:13px;
    outline:none;
  }
  select.input:focus{
    border-color: rgba(34,197,94,.65);
    box-shadow: 0 0 0 4px var(--focus);
  }
</style>
@endpush

{{-- FARMER INFORMATION --}}
<div class="card section-card">
  <div class="card-header section-head">
    <div>
      <h2 class="h1 section-title">Farmer Information</h2>
      <p class="p form-help">Fill in the recipient details (based on NRP distribution sheet).</p>
    </div>
  </div>

  <div style="padding:16px;">
    <div class="grid grid-3">
      <div class="field">
        <label>Last Name <span class="req">*</span></label>
        <input class="input" name="last_name" value="{{ $val('last_name') }}" required>
      </div>

      <div class="field">
        <label>First Name <span class="req">*</span></label>
        <input class="input" name="first_name" value="{{ $val('first_name') }}" required>
      </div>

      <div class="field">
        <label>Middle Name</label>
        <input class="input" name="middle_name" value="{{ $val('middle_name') }}">
      </div>

      <div class="field">
        <label>Ext Name (Jr./Sr./III)</label>
        <input class="input" name="ext_name" value="{{ $val('ext_name') }}" placeholder="e.g. Jr">
      </div>

      <div class="field">
        <label>FFRS / RSBSA Number</label>
        <input class="input" name="ffrs" value="{{ $val('ffrs') }}" placeholder="e.g. 03-69-010-000178">
        <div class="hint">Used for quick searching and farmer grouping.</div>
      </div>

      <div class="field">
        <label>Contact Number</label>
        <input class="input" name="contact_number" value="{{ $val('contact_number') }}" placeholder="e.g. 09xxxxxxxxx">
      </div>

      <div class="field">
        <label>Date of Birth</label>
        <input class="input" type="date" name="date_of_birth" value="{{ $val('date_of_birth') }}">
      </div>

      <div class="field">
        <label>Gender</label>
        {{-- Tom Select: add js-select + input --}}
        <select class="input js-select" name="gender">
          <option value="">— Select —</option>
          @foreach(['Male','Female','Other'] as $g)
            <option value="{{ $g }}" @selected($val('gender') === $g)>{{ $g }}</option>
          @endforeach
        </select>
      </div>

      <div class="field">
        <label>Seed Class</label>
        {{-- Tom Select: add js-select + input --}}
        <select class="input js-select" name="seed_class">
          <option value="">— Select —</option>
          @foreach($seedClassOptions as $sc)
            <option value="{{ $sc }}" @selected($val('seed_class') === $sc)>{{ $sc }}</option>
          @endforeach
        </select>
      </div>
    </div>
  </div>
</div>

{{-- FARM LOCATION --}}
<div style="margin-top:14px;" class="card section-card">
  <div class="card-header section-head">
    <div>
      <h2 class="h1 section-title">Farm Location</h2>
      <p class="p form-help">Keep a full location field, plus province/municipality if needed.</p>
    </div>
  </div>

  <div style="padding:16px;">
    <div class="grid grid-3">
      <div class="field col-span-3">
        <label>Location of FARM <span class="req">*</span></label>
        <input class="input" name="farm_location" value="{{ $val('farm_location') }}" required
               placeholder="e.g. Brgy. San Isidro, Sitio ____">
      </div>

      <div class="field">
        <label>Province</label>
        <input class="input" name="farm_province" value="{{ $val('farm_province') }}" placeholder="e.g. Tarlac">
      </div>

      <div class="field">
        <label>Municipality</label>
        <input class="input" name="farm_municipality" value="{{ $val('farm_municipality') }}" placeholder="e.g. Ramos">
      </div>

      <div class="field">
        <label>Ecosystem</label>
        <input class="input" name="ecosystem" value="{{ $val('ecosystem') }}" placeholder="(optional)">
      </div>

      <div class="field">
        <label>Ecosystem Source</label>
        <input class="input" name="ecosystem_source" value="{{ $val('ecosystem_source') }}" placeholder="(optional)">
      </div>
    </div>
  </div>
</div>

{{-- ELIGIBILITY TAGS --}}
<div style="margin-top:14px;" class="card section-card">
  <div class="card-header section-head">
    <div>
      <h2 class="h1 section-title">Eligibility Tags (Y/N)</h2>
      <p class="p form-help">Tick the applicable categories.</p>
    </div>
  </div>

  <div style="padding:16px;">
    <div class="chipbox">
      @foreach([
        'is_arb' => 'ARB',
        'is_4ps' => '4PS',
        'is_ip'  => 'IP',
        'is_pwd' => 'PWD',
        'is_sc'  => 'SC',
        'is_ofw' => 'OFW',
      ] as $field => $label)
        <input type="hidden" name="{{ $field }}" value="0">
        <label class="chip">
          <input type="checkbox" name="{{ $field }}" value="1"
                 @checked((int)$val($field, 0) === 1)>
          <span>{{ $label }}</span>
        </label>
      @endforeach
    </div>
  </div>
</div>

{{-- CLAIM / DISTRIBUTION DETAILS --}}
<div style="margin-top:14px;" class="card section-card">
  <div class="card-header section-head">
    <div>
      <h2 class="h1 section-title">Claim / Distribution Details</h2>
      <p class="p form-help">NRP fields: seed variety claimed, claimed area, lots, sowing period.</p>
    </div>
  </div>

  <div style="padding:16px;">
    <div class="grid grid-3">
      <div class="field col-span-3">
        <label>Seed Variety Claimed</label>
        {{-- Tom Select: add js-select + input --}}
        <select class="input js-select" name="seed_variety_claimed">
          <option value="">— Select —</option>
          @foreach($seedVarietyClaimedOptions as $sv)
            <option value="{{ $sv }}" @selected($val('seed_variety_claimed') === $sv)>{{ $sv }}</option>
          @endforeach
        </select>
      </div>

      <div class="field">
        <label>Claimed Area (ha)</label>
        <input class="input" type="number" step="0.01" min="0" name="claimed_area_ha" value="{{ $val('claimed_area_ha') }}">
      </div>

      <div class="field">
        <label>Claimed Seeds (kg)</label>
        <input class="input" type="number" step="0.01" min="0" name="claimed_seeds_kg" value="{{ $val('claimed_seeds_kg') }}">
      </div>

      <div class="field">
        <label>Crop Establishment</label>
        {{-- Tom Select: add js-select + input --}}
        <select class="input js-select" name="crop_establishment">
          <option value="">— Select —</option>
          @foreach($cropEstablishmentOptions as $ce)
            <option value="{{ $ce }}" @selected($val('crop_establishment') === $ce)>{{ $ce }}</option>
          @endforeach
        </select>
      </div>

      <div class="field col-span-3">
        <label>Lot Series (can be multiple)</label>
        <textarea class="input" name="lot_series" rows="3"
                  placeholder="Paste lot codes here (separated by comma or new line)">{{ $val('lot_series') }}</textarea>
      </div>

      <div class="field col-span-3">
        <label>Date of Sowing (Label)</label>
        <input class="input" name="date_of_sowing_label" value="{{ $val('date_of_sowing_label') }}"
               placeholder="e.g. Third Week of June">
      </div>
    </div>
  </div>
</div>

{{-- LGU RECEIVED DETAILS --}}
<div style="margin-top:14px;" class="card section-card">
  <div class="card-header section-head">
    <div>
      <h2 class="h1 section-title">LGU Received Details</h2>
      <p class="p form-help">Farm area, kilograms received, and date received.</p>
    </div>
  </div>

  <div style="padding:16px;">
    <div class="grid grid-3">
      <div class="field">
        <label>Farm Area (ha)</label>
        <input class="input" type="number" step="0.01" min="0" name="farm_area_ha" value="{{ $val('farm_area_ha') }}">
      </div>

      <div class="field">
        <label>No. of kgs Received <span class="req">*</span></label>
        <input class="input" type="number" step="0.01" min="0" name="kgs_received" value="{{ $val('kgs_received') }}" required>
      </div>

      <div class="field">
        <label>Date Received <span class="req">*</span></label>
        <input class="input" type="date" name="date_received" value="{{ $val('date_received') }}" required>
      </div>
    </div>
  </div>
</div>

{{-- PRODUCTION / MONITORING --}}
<div style="margin-top:14px;" class="card section-card">
  <div class="card-header section-head">
    <div>
      <h2 class="h1 section-title">Production / Monitoring (Optional)</h2>
      <p class="p form-help">Production bags, area harvested, and planted variety.</p>
    </div>
  </div>

  <div style="padding:16px;">
    <div class="grid grid-3">
      <div class="field">
        <label>Average Weight per Bag (kg)</label>
        <input class="input" type="number" min="0" name="avg_weight_per_bag_kg" value="{{ $val('avg_weight_per_bag_kg') }}">
      </div>

      <div class="field">
        <label>Total Production (no. of bags)</label>
        <input class="input" type="number" min="0" name="total_production_bags" value="{{ $val('total_production_bags') }}">
      </div>

      <div class="field">
        <label>Average Area Harvested (ha)</label>
        <input class="input" type="number" step="0.01" min="0" name="avg_area_harvested_ha" value="{{ $val('avg_area_harvested_ha') }}">
      </div>

      <div class="field col-span-3">
        <label>Seed Variety Planted</label>
        <input class="input" name="seed_variety_planted" value="{{ $val('seed_variety_planted') }}" placeholder="e.g. NSIC Rc ___">
      </div>
    </div>
  </div>
</div>

{{-- ACTIONS --}}
<div style="margin-top:16px; display:flex; gap:10px; flex-wrap:wrap;">
  <button class="btn" type="submit">{{ $buttonText ?? 'Save' }}</button>
  <a class="btn btn-soft" style="box-shadow:none;" href="{{ route('rice-seed-distributions.index') }}">Cancel</a>
</div>
