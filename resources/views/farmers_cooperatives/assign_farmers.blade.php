@extends('layouts.app')

@section('title', 'Manage Cooperative Members')

@push('styles')
  @include('partials.operations-ui-styles')
  <style>
    .member-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;padding:14px}.member-card{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:11px;border:1px solid var(--module-border);border-radius:9px;background:#fff}.member-card-copy{min-width:0}.member-card-copy strong,.member-card-copy small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.member-card-copy strong{font-size:10px}.member-card-copy small{margin-top:3px;color:var(--module-muted);font-size:8px}.member-empty{grid-column:1/-1;padding:30px;text-align:center;color:var(--module-muted);font-size:10px}.member-modal-backdrop{position:fixed;inset:0;z-index:900;display:none;background:rgba(12,24,16,.56);backdrop-filter:blur(2px)}.member-modal{position:fixed;inset:50% auto auto 50%;z-index:901;display:none;width:min(1120px,calc(100vw - 30px));max-height:calc(100vh - 38px);transform:translate(-50%,-50%);overflow:hidden;border:1px solid var(--module-border);border-radius:13px;background:#fff;box-shadow:0 25px 70px rgba(15,30,20,.28)}.member-modal.is-open,.member-modal-backdrop.is-open{display:block}.member-modal-head,.member-modal-foot{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 15px;border-bottom:1px solid var(--module-border)}.member-modal-head h2{margin:0;font-size:14px}.member-modal-head p{margin:3px 0 0;color:var(--module-muted);font-size:9px}.member-modal-search{width:min(330px,100%)}.member-modal-body{max-height:calc(100vh - 190px);overflow:auto}.member-modal-table{min-width:920px}.member-modal-foot{border-top:1px solid var(--module-border);border-bottom:0;background:#fbfcfb}.member-modal-foot span{color:var(--module-muted);font-size:9px}.member-modal-tools{display:flex;gap:7px;flex-wrap:wrap}.member-checkbox{width:16px;height:16px;accent-color:var(--module-green)}body.member-modal-open{overflow:hidden}@media(max-width:760px){.member-list{grid-template-columns:1fr}.member-modal-head{align-items:stretch;flex-direction:column}.member-modal-search{width:100%}.member-modal-body{max-height:calc(100vh - 245px)}.member-modal-foot{align-items:flex-start;flex-direction:column}.member-modal-tools,.member-modal-tools .module-button{width:100%}}
  </style>
@endpush

@php
  $selectedFarmerIds = collect($selectedFarmerIds ?? [])->map(fn ($id) => (int) $id)->all();
  $selectedFarmers = $farmers->filter(fn ($farmer) => in_array((int) $farmer->id, $selectedFarmerIds, true));
  $selectedArea = (float) $selectedFarmers->sum('farm_area_ha');
@endphp

@section('content')
<div class="module-page">
  <header class="module-header">
    <div><div class="module-eyebrow">Cooperative membership</div><h1>{{ $record->name }}</h1><p>Select registered farmers from the cooperative’s municipality and save the final membership list.</p></div>
    <div class="module-actions"><a class="module-button" href="{{ route('farmers-cooperatives.export-excel', $record) }}"><svg viewBox="0 0 24 24"><path d="M12 15V3M7 10l5 5 5-5M5 21h14"></path></svg>Export current list</a><button class="module-button module-button-primary" id="openMemberPicker" type="button"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"></path></svg>Select farmers</button></div>
  </header>

  @if(session('success'))<div class="module-alert">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="module-alert module-alert-error"><strong>Please review the membership list.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

  <section class="module-kpis" aria-label="Membership summary">
    <article class="module-kpi"><div class="module-kpi-top"><span class="module-kpi-label">Selected members</span><span class="module-kpi-icon"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"></circle><path d="M3 20a6 6 0 0 1 12 0M16 11a4 4 0 0 1 4 4v5"></path></svg></span></div><strong id="selectedMemberCount">{{ count($selectedFarmerIds) }}</strong><small>Farmers ready to save</small></article>
    <article class="module-kpi"><div class="module-kpi-top"><span class="module-kpi-label">Available farmers</span><span class="module-kpi-icon module-kpi-icon-blue"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"></circle><path d="M4 21a8 8 0 0 1 16 0"></path></svg></span></div><strong>{{ number_format($farmers->count()) }}</strong><small>Within the assigned municipality</small></article>
    <article class="module-kpi"><div class="module-kpi-top"><span class="module-kpi-label">Selected farm area</span><span class="module-kpi-icon module-kpi-icon-amber"><svg viewBox="0 0 24 24"><path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3V6Z"></path></svg></span></div><strong id="selectedAreaTotal">{{ number_format($selectedArea, 2) }} <small>ha</small></strong><small>Combined recorded farm area</small></article>
    <article class="module-kpi"><div class="module-kpi-top"><span class="module-kpi-label">Municipality</span><span class="module-kpi-icon"><svg viewBox="0 0 24 24"><path d="M4 21V7l8-4 8 4v14M9 21v-5h6v5M8 9h.01M12 9h.01M16 9h.01M8 12h.01M16 12h.01"></path></svg></span></div><strong style="font-size:18px">{{ $record->municipality?->name ?? auth()->user()->municipality?->name ?? 'Assigned office' }}</strong><small>Membership is restricted to this office</small></article>
  </section>

  <form method="POST" action="{{ route('farmers-cooperatives.save-assigned-farmers', $record) }}" id="membershipForm">@csrf @method('PUT')
    <section class="module-panel">
      <div class="module-panel-head"><div><h2>Selected farmers</h2><p>Review the membership list before saving. Removing a card updates the pending selection.</p></div><button class="module-button module-button-small" id="openMemberPickerSecondary" type="button">Add or remove farmers</button></div>
      <div class="member-list" id="selectedMemberList">
        @forelse($selectedFarmers as $farmer)
          @php $fullName = trim($farmer->last_name.', '.$farmer->first_name.' '.($farmer->middle_name ?? '').' '.($farmer->ext_name ?? '')); @endphp
          <article class="member-card" data-selected-member="{{ $farmer->id }}"><div class="member-card-copy"><strong>{{ $fullName }}</strong><small>FFRS {{ $farmer->ffrs ?: 'not assigned' }} · {{ $farmer->farm_location ?: 'location not recorded' }} · {{ $farmer->farm_area_ha !== null ? number_format((float) $farmer->farm_area_ha, 2).' ha' : 'area not recorded' }}</small></div><button class="module-button module-button-danger module-button-small" type="button" data-remove-member="{{ $farmer->id }}">Remove</button></article>
        @empty
          <div class="member-empty" id="memberEmptyState">No farmers selected. Open the farmer picker to build this cooperative’s membership.</div>
        @endforelse
      </div>
      <div id="membershipInputs">@foreach($selectedFarmerIds as $id)<input type="hidden" name="farmer_ids[]" value="{{ $id }}">@endforeach</div>
      <div class="module-form-actions"><a class="module-button" href="{{ route('farmers-cooperatives.index') }}">Cancel</a><button class="module-button module-button-primary" type="submit">Save membership</button></div>
    </section>
  </form>
</div>

<div class="member-modal-backdrop" id="memberModalBackdrop"></div>
<section class="member-modal" id="memberModal" role="dialog" aria-modal="true" aria-labelledby="memberModalTitle">
  <div class="member-modal-head"><div><h2 id="memberModalTitle">Select cooperative farmers</h2><p>Search and select any registered farmer available to this municipality.</p></div><div class="module-search-wrap member-modal-search"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg><input class="module-input" id="memberSearch" type="search" placeholder="Search name, FFRS, or location"></div></div>
  <div class="member-modal-body">
    <table class="module-table member-modal-table" id="memberPickerTable">
      <thead><tr><th>Select</th><th>Farmer</th><th>FFRS</th><th>Gender</th><th>Farm location</th><th class="module-numeric">Farm area</th></tr></thead>
      <tbody>
        @forelse($farmers as $farmer)
          @php
            $fullName = trim($farmer->last_name.', '.$farmer->first_name.' '.($farmer->middle_name ?? '').' '.($farmer->ext_name ?? ''));
            $location = trim(($farmer->farm_location ?: 'Not recorded').($farmer->farm_municipality ? ' · '.$farmer->farm_municipality : ''));
            $search = mb_strtolower($fullName.' '.$farmer->ffrs.' '.$location);
          @endphp
          <tr data-member-row data-search="{{ $search }}"><td><input class="member-checkbox" type="checkbox" value="{{ $farmer->id }}" data-member-checkbox data-name="{{ $fullName }}" data-ffrs="{{ $farmer->ffrs ?: 'not assigned' }}" data-location="{{ $location }}" data-area="{{ (float) ($farmer->farm_area_ha ?? 0) }}" @checked(in_array((int) $farmer->id, $selectedFarmerIds, true))></td><td><strong>{{ $fullName }}</strong></td><td class="module-mono">{{ $farmer->ffrs ?: '—' }}</td><td>{{ $farmer->gender ?: '—' }}</td><td>{{ $location }}</td><td class="module-numeric">{{ $farmer->farm_area_ha !== null ? number_format((float) $farmer->farm_area_ha, 2).' ha' : '—' }}</td></tr>
        @empty<tr><td colspan="6"><div class="module-empty"><strong>No farmers available</strong><span>Add farmer profiles to this municipality before assigning cooperative members.</span></div></td></tr>@endforelse
      </tbody>
    </table>
  </div>
  <div class="member-modal-foot"><span><strong id="modalSelectionCount">{{ count($selectedFarmerIds) }}</strong> selected · <span id="visibleFarmerCount">{{ $farmers->count() }}</span> visible</span><div class="member-modal-tools"><button class="module-button" id="selectVisibleMembers" type="button">Select visible</button><button class="module-button" id="clearMemberSelection" type="button">Clear selection</button><button class="module-button" id="closeMemberPicker" type="button">Cancel</button><button class="module-button module-button-primary" id="applyMemberSelection" type="button">Apply selection</button></div></div>
</section>
@endsection

@push('scripts')
<script>
(() => {
  const modal=document.getElementById('memberModal'); const backdrop=document.getElementById('memberModalBackdrop'); const search=document.getElementById('memberSearch'); const checkboxes=[...document.querySelectorAll('[data-member-checkbox]')]; const rows=[...document.querySelectorAll('[data-member-row]')]; const list=document.getElementById('selectedMemberList'); const inputs=document.getElementById('membershipInputs'); const count=document.getElementById('selectedMemberCount'); const area=document.getElementById('selectedAreaTotal'); const modalCount=document.getElementById('modalSelectionCount'); const visibleCount=document.getElementById('visibleFarmerCount');
  const open=()=>{modal.classList.add('is-open');backdrop.classList.add('is-open');document.body.classList.add('member-modal-open');setTimeout(()=>search.focus(),50)}; const close=()=>{modal.classList.remove('is-open');backdrop.classList.remove('is-open');document.body.classList.remove('member-modal-open')}; const selected=()=>checkboxes.filter(box=>box.checked);
  const updateCounts=()=>{modalCount.textContent=selected().length};
  const render=()=>{const members=selected();inputs.innerHTML='';list.innerHTML='';let totalArea=0;members.forEach(box=>{const input=document.createElement('input');input.type='hidden';input.name='farmer_ids[]';input.value=box.value;inputs.appendChild(input);totalArea+=Number(box.dataset.area||0);const card=document.createElement('article');card.className='member-card';card.dataset.selectedMember=box.value;card.innerHTML=`<div class="member-card-copy"><strong>${box.dataset.name}</strong><small>FFRS ${box.dataset.ffrs} · ${box.dataset.location} · ${Number(box.dataset.area||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})} ha</small></div><button class="module-button module-button-danger module-button-small" type="button" data-remove-member="${box.value}">Remove</button>`;list.appendChild(card)});if(!members.length){const empty=document.createElement('div');empty.className='member-empty';empty.textContent='No farmers selected. Open the farmer picker to build this cooperative’s membership.';list.appendChild(empty)}count.textContent=members.length;area.innerHTML=`${totalArea.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})} <small>ha</small>`;updateCounts();bindRemove()};
  const bindRemove=()=>document.querySelectorAll('[data-remove-member]').forEach(button=>button.onclick=()=>{const box=checkboxes.find(item=>item.value===button.dataset.removeMember);if(box)box.checked=false;render()});
  const filter=()=>{const query=(search.value||'').trim().toLocaleLowerCase();let visible=0;rows.forEach(row=>{row.hidden=!row.dataset.search.includes(query);if(!row.hidden)visible++});visibleCount.textContent=visible};
  document.getElementById('openMemberPicker').addEventListener('click',open);document.getElementById('openMemberPickerSecondary').addEventListener('click',open);document.getElementById('closeMemberPicker').addEventListener('click',close);backdrop.addEventListener('click',close);search.addEventListener('input',filter);checkboxes.forEach(box=>box.addEventListener('change',updateCounts));document.getElementById('applyMemberSelection').addEventListener('click',()=>{render();close()});document.getElementById('clearMemberSelection').addEventListener('click',()=>{checkboxes.forEach(box=>box.checked=false);updateCounts()});document.getElementById('selectVisibleMembers').addEventListener('click',()=>{rows.filter(row=>!row.hidden).forEach(row=>row.querySelector('[data-member-checkbox]').checked=true);updateCounts()});document.addEventListener('keydown',event=>{if(event.key==='Escape'&&modal.classList.contains('is-open'))close()});bindRemove();updateCounts();
})();
</script>
@endpush
