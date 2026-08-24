@extends('layouts.app')

@section('title', ($isMunicipalHeadManager ?? false) ? 'Municipal Staff Management' : 'User Management')

@section('content')
@php
  $roleLabels = [
    \App\Models\User::ROLE_SUPER_ADMIN => 'Super Admin',
    \App\Models\User::ROLE_PROVINCIAL_STAFF => 'Provincial Staff',
    \App\Models\User::ROLE_PROVINCIAL_VET => 'Provincial Veterinary Office',
    \App\Models\User::ROLE_MUNICIPAL_HEAD => 'Head Agriculturist',
    \App\Models\User::ROLE_MUNICIPAL_STAFF => 'Municipal Staff',
  ];

  $roleClasses = [
    \App\Models\User::ROLE_SUPER_ADMIN => 'is-purple',
    \App\Models\User::ROLE_PROVINCIAL_STAFF => 'is-blue',
    \App\Models\User::ROLE_PROVINCIAL_VET => 'is-green',
    \App\Models\User::ROLE_MUNICIPAL_HEAD => 'is-yellow',
    \App\Models\User::ROLE_MUNICIPAL_STAFF => 'is-green',
  ];
@endphp

<div class="user-management-page">
  <section class="user-management-hero">
    <div>
      <div class="user-management-eyebrow">
        <span></span>
        {{ $isMunicipalHeadManager ? (($manager->municipality?->name ?? 'Municipal') . ' Agriculture Office') : 'Provincial Agriculture Office' }}
      </div>
      <h1>{{ $isMunicipalHeadManager ? 'Municipal Staff Management' : 'User Management' }}</h1>
      <p>
        @if($isMunicipalHeadManager)
          Manage municipal-staff accounts assigned to your municipality, including account status and login access.
        @else
          Manage provincial agriculture and veterinary accounts, head
          agriculturists, municipal staff, municipality assignments, account
          status, and login access.
        @endif
      </p>
    </div>

    <a class="btn user-create-btn" href="{{ route('admins.create') }}">
      <span aria-hidden="true">+</span>
      {{ $isMunicipalHeadManager ? 'Create Staff' : 'Create User' }}
    </a>
  </section>

  <section class="user-stat-grid">
    <article class="user-stat-card is-dark">
      <span>Total Accounts</span>
      <strong>{{ number_format($stats['total'] ?? 0) }}</strong>
      <small>{{ $isMunicipalHeadManager ? 'Staff in your municipality' : 'All registered system users' }}</small>
    </article>

    <article class="user-stat-card is-green">
      <span>Active Accounts</span>
      <strong>{{ number_format($stats['active'] ?? 0) }}</strong>
      <small>Accounts permitted to sign in</small>
    </article>

    @unless($isMunicipalHeadManager)
      <article class="user-stat-card is-blue">
        <span>Provincial Users</span>
        <strong>{{ number_format($stats['provincial'] ?? 0) }}</strong>
        <small>Provincial accounts, including module-limited offices</small>
      </article>

      <article class="user-stat-card is-yellow">
        <span>Municipal Heads</span>
        <strong>{{ number_format($stats['municipal_heads'] ?? 0) }}</strong>
        <small>Head agriculturists assigned</small>
      </article>
    @endunless

    <article class="user-stat-card is-purple">
      <span>Municipal Staff</span>
      <strong>{{ number_format($stats['municipal_staff'] ?? 0) }}</strong>
      <small>Municipal encoder and staff accounts</small>
    </article>
  </section>

  <section class="user-filter-card">
    <div class="user-filter-heading">
      <div>
        <h2>Search and filter</h2>
        <p>{{ $isMunicipalHeadManager ? 'Narrow municipal staff by name or status.' : 'Narrow accounts by role, municipality, or status.' }}</p>
      </div>

      @if(request()->hasAny(['q', 'role', 'status', 'municipality_id']))
        <a class="btn btn-soft" href="{{ route('admins.index') }}">Clear filters</a>
      @endif
    </div>

    <form method="GET" action="{{ route('admins.index') }}" class="user-filter-grid">
      <div class="user-filter-field user-filter-search">
        <label for="q">Search</label>
        <input
          class="input"
          id="q"
          name="q"
          type="search"
          value="{{ $q }}"
          placeholder="Name or email address"
        >
      </div>

      @unless($isMunicipalHeadManager)
        <div class="user-filter-field">
          <label for="role">Role</label>
          <select class="input js-select" id="role" name="role">
            <option value="">All roles</option>
            @foreach($roleOptions as $value => $label)
              <option value="{{ $value }}" @selected($role === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>

        <div class="user-filter-field">
          <label for="municipality_id">Municipality</label>
          <select class="input js-select" id="municipality_id" name="municipality_id">
            <option value="">All municipalities</option>
            @foreach($municipalities as $municipality)
              <option
                value="{{ $municipality->id }}"
                @selected((int) $municipalityId === (int) $municipality->id)
              >
                {{ $municipality->name }}
              </option>
            @endforeach
          </select>
        </div>
      @endunless

      <div class="user-filter-field">
        <label for="status">Status</label>
        <select class="input js-select" id="status" name="status">
          <option value="">All statuses</option>
          <option value="active" @selected($status === 'active')>Active</option>
          <option value="inactive" @selected($status === 'inactive')>Inactive</option>
        </select>
      </div>

      <div class="user-filter-field user-filter-rows">
        <label for="per_page">Rows</label>
        <select class="input js-select" id="per_page" name="per_page">
          @foreach([5, 10, 25, 50, 100] as $size)
            <option value="{{ $size }}" @selected((int) $perPage === $size)>{{ $size }}</option>
          @endforeach
        </select>
      </div>

      <div class="user-filter-actions">
        <button class="btn user-apply-btn" type="submit">Apply Filters</button>
      </div>
    </form>
  </section>

  <section class="user-table-card">
    <div class="user-table-heading">
      <div>
        <h2>User accounts</h2>
        <p>
          Showing {{ number_format($users->firstItem() ?? 0) }}–{{ number_format($users->lastItem() ?? 0) }}
          of {{ number_format($users->total()) }} matching accounts.
        </p>
      </div>
    </div>

    <div class="user-table-scroll">
      <table class="user-table">
        <thead>
          <tr>
            <th>User</th>
            <th>Role</th>
            <th>Office assignment</th>
            <th>Status</th>
            <th>Last login</th>
            <th class="user-actions-column">Actions</th>
          </tr>
        </thead>

        <tbody>
          @forelse($users as $account)
            @php
              $initials = collect(explode(' ', trim($account->name)))
                  ->filter()
                  ->take(2)
                  ->map(fn ($part) => strtoupper(mb_substr($part, 0, 1)))
                  ->join('');

              $roleLabel = $roleLabels[$account->role] ?? ucwords(str_replace('_', ' ', $account->role));
              $roleClass = $roleClasses[$account->role] ?? 'is-gray';
              $isOwnAccount = $account->id === auth()->id();
            @endphp

            <tr>
              <td data-label="User">
                <div class="user-identity">
                  <div class="user-list-avatar {{ $roleClass }}">{{ $initials ?: 'U' }}</div>
                  <div>
                    <div class="user-list-name">
                      {{ $account->name }}
                      @if($isOwnAccount)
                        <span class="user-you-badge">You</span>
                      @endif
                    </div>
                    <div class="user-list-email">{{ $account->email }}</div>
                  </div>
                </div>
              </td>

              <td data-label="Role">
                <span class="user-role-badge {{ $roleClass }}">{{ $roleLabel }}</span>
              </td>

              <td data-label="Office assignment">
                @if($account->isProvincialUser())
                  <div class="user-office-name">{{ $account->office_label }}</div>
                  <div class="user-office-sub">
                    {{ $account->isProvincialVeterinaryOffice() ? 'Animal Health only · All municipalities' : 'All municipalities' }}
                  </div>
                @else
                  <div class="user-office-name">{{ $account->municipality?->name ?? 'Not assigned' }}</div>
                  <div class="user-office-sub">Municipal Agriculture Office</div>
                @endif
              </td>

              <td data-label="Status">
                <span class="user-status-badge {{ $account->is_active ? 'is-active' : 'is-inactive' }}">
                  <span></span>
                  {{ $account->is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>

              <td data-label="Last login">
                @php($localLastLogin = \App\Support\LocalTime::fromUtc($account->last_login_at))
                <div class="user-date-main">
                  {{ $localLastLogin?->format('M d, Y') ?? 'Never' }}
                </div>
                @if($localLastLogin)
                  <div class="user-date-sub">{{ $localLastLogin->format('h:i A') }} PHT</div>
                @endif
              </td>

              <td data-label="Actions" class="user-actions-column">
                <div class="user-row-actions">
                  <a class="btn btn-soft user-row-btn" href="{{ route('admins.edit', $account) }}">
                    Edit
                  </a>

                  @if(!$isOwnAccount && !$account->isSuperAdmin())
                    <form
                      method="POST"
                      action="{{ route('admins.destroy', $account) }}"
                      onsubmit="return confirm('Delete {{ addslashes($account->name) }}? This cannot be undone.');"
                    >
                      @csrf
                      @method('DELETE')
                      <button class="btn btn-danger user-row-btn" type="submit">Delete</button>
                    </form>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6">
                <div class="user-empty-state">
                  <div class="user-empty-icon">U</div>
                  <h3>No user accounts found</h3>
                  <p>Change the filters or create a new {{ $isMunicipalHeadManager ? 'staff' : 'user' }} account.</p>
                  <a class="btn user-create-btn" href="{{ route('admins.create') }}">{{ $isMunicipalHeadManager ? 'Create Staff' : 'Create User' }}</a>
                </div>
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @include('partials.pagination', ['paginator' => $users, 'label' => 'user account'])
  </section>
</div>
@endsection

@push('styles')
<style>
  .user-management-page{display:flex;flex-direction:column;gap:16px;}
  .user-management-hero{
    display:flex;align-items:flex-start;justify-content:space-between;gap:20px;
    padding:26px;border-radius:24px;color:#fff;
    background:radial-gradient(circle at top right,rgba(250,204,21,.24),transparent 30%),linear-gradient(135deg,#052e16,#166534);
    box-shadow:0 18px 42px rgba(15,23,42,.12);
  }
  .user-management-eyebrow{display:inline-flex;align-items:center;gap:8px;padding:7px 11px;border:1px solid rgba(255,255,255,.2);border-radius:999px;background:rgba(255,255,255,.1);font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.45px;}
  .user-management-eyebrow span{width:7px;height:7px;border-radius:50%;background:#bbf7d0;box-shadow:0 0 0 4px rgba(187,247,208,.14);}
  .user-management-hero h1{margin:14px 0 7px;font-size:34px;line-height:1;font-weight:900;}
  .user-management-hero p{max-width:760px;margin:0;color:rgba(255,255,255,.78);font-size:13px;line-height:1.6;}
  .user-create-btn{color:#064e3b!important;border-color:#fde047!important;background:linear-gradient(135deg,#fef08a,#facc15)!important;box-shadow:0 12px 24px rgba(250,204,21,.22);}

  .user-stat-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;}
  .user-stat-card{position:relative;overflow:hidden;padding:16px;border:1px solid var(--border);border-radius:18px;background:#fff;box-shadow:0 9px 24px rgba(15,23,42,.05);}
  .user-stat-card::after{content:"";position:absolute;top:-34px;right:-30px;width:78px;height:78px;border-radius:50%;background:var(--stat-soft);}
  .user-stat-card span,.user-stat-card strong,.user-stat-card small{position:relative;z-index:1;display:block;}
  .user-stat-card span{color:#64748b;font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.35px;}
  .user-stat-card strong{margin-top:8px;color:var(--stat-color);font-size:25px;font-weight:900;}
  .user-stat-card small{margin-top:5px;color:#64748b;font-size:10px;line-height:1.4;}
  .user-stat-card.is-dark{--stat-color:#0f172a;--stat-soft:rgba(15,23,42,.08)}
  .user-stat-card.is-green{--stat-color:#15803d;--stat-soft:rgba(34,197,94,.12)}
  .user-stat-card.is-blue{--stat-color:#1d4ed8;--stat-soft:rgba(37,99,235,.11)}
  .user-stat-card.is-yellow{--stat-color:#a16207;--stat-soft:rgba(250,204,21,.16)}
  .user-stat-card.is-purple{--stat-color:#6d28d9;--stat-soft:rgba(124,58,237,.11)}

  .user-filter-card,.user-table-card{border:1px solid var(--border);border-radius:21px;background:#fff;box-shadow:0 10px 28px rgba(15,23,42,.055);}
  .user-filter-card{padding:17px;}
  .user-filter-heading,.user-table-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;}
  .user-filter-heading{margin-bottom:13px;}
  .user-filter-heading h2,.user-table-heading h2{margin:0;color:#0f172a;font-size:16px;font-weight:900;}
  .user-filter-heading p,.user-table-heading p{margin:4px 0 0;color:#64748b;font-size:11px;line-height:1.45;}
  .user-filter-grid{display:grid;grid-template-columns:minmax(240px,1.4fr) repeat(4,minmax(130px,.75fr)) auto;gap:10px;align-items:end;}
  .user-filter-field label{display:block;margin-bottom:6px;color:#475569;font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.3px;}
  .user-filter-actions{display:flex;align-items:flex-end;}
  .user-apply-btn{min-height:42px;color:#fff!important;border-color:#16a34a!important;background:linear-gradient(135deg,#22c55e,#15803d)!important;}

  .user-table-card{overflow:hidden;}
  .user-table-heading{padding:17px;border-bottom:1px solid var(--border);background:#f8fafc;}
  .user-table-scroll{overflow-x:auto;}
  .user-table{width:100%;min-width:980px;border-collapse:separate;border-spacing:0;font-size:12px;}
  .user-table th{padding:12px 14px;text-align:left;color:#475569;background:#fff;border-bottom:1px solid var(--border);font-size:9px;font-weight:900;text-transform:uppercase;letter-spacing:.35px;white-space:nowrap;}
  .user-table td{padding:13px 14px;border-bottom:1px solid #eef2f7;color:#334155;vertical-align:middle;}
  .user-table tbody tr:hover td{background:rgba(34,197,94,.035);}
  .user-table tbody tr:last-child td{border-bottom:0;}
  .user-identity{display:flex;align-items:center;gap:10px;min-width:210px;}
  .user-list-avatar{width:38px;height:38px;display:grid;place-items:center;flex:0 0 auto;border-radius:13px;color:var(--badge-color);background:var(--badge-bg);font-size:11px;font-weight:900;}
  .user-list-name{color:#0f172a;font-size:12px;font-weight:900;}
  .user-list-email{margin-top:3px;color:#64748b;font-size:10px;}
  .user-you-badge{display:inline-flex;margin-left:5px;padding:2px 6px;border-radius:999px;color:#166534;background:#dcfce7;font-size:8px;font-weight:900;text-transform:uppercase;vertical-align:middle;}
  .user-role-badge,.user-status-badge{display:inline-flex;align-items:center;gap:6px;padding:5px 8px;border:1px solid var(--badge-border);border-radius:999px;color:var(--badge-color);background:var(--badge-bg);font-size:9px;font-weight:900;white-space:nowrap;}
  .is-purple{--badge-color:#6d28d9;--badge-bg:#f5f3ff;--badge-border:#ddd6fe}
  .is-blue{--badge-color:#1d4ed8;--badge-bg:#eff6ff;--badge-border:#bfdbfe}
  .is-yellow{--badge-color:#a16207;--badge-bg:#fffbeb;--badge-border:#fde68a}
  .is-green{--badge-color:#15803d;--badge-bg:#ecfdf5;--badge-border:#bbf7d0}
  .is-gray{--badge-color:#475569;--badge-bg:#f8fafc;--badge-border:#e2e8f0}
  .user-status-badge.is-active{--badge-color:#15803d;--badge-bg:#ecfdf5;--badge-border:#bbf7d0}
  .user-status-badge.is-inactive{--badge-color:#b91c1c;--badge-bg:#fef2f2;--badge-border:#fecaca}
  .user-status-badge > span{width:6px;height:6px;border-radius:50%;background:currentColor;}
  .user-office-name,.user-date-main{color:#0f172a;font-size:11px;font-weight:900;}
  .user-office-sub,.user-date-sub{margin-top:3px;color:#64748b;font-size:9px;}
  .user-actions-column{text-align:right;}
  .user-row-actions{display:flex;justify-content:flex-end;gap:7px;}
  .user-row-actions form{margin:0;}
  .user-row-btn{padding:7px 9px!important;border-radius:10px!important;font-size:10px!important;box-shadow:none!important;}
  .user-empty-state{display:grid;place-items:center;padding:34px;text-align:center;}
  .user-empty-icon{width:48px;height:48px;display:grid;place-items:center;border-radius:16px;color:#166534;background:#dcfce7;font-size:18px;font-weight:900;}
  .user-empty-state h3{margin:11px 0 4px;color:#0f172a;font-size:15px;}
  .user-empty-state p{margin:0 0 12px;color:#64748b;font-size:11px;}

  @media(max-width:1220px){
    .user-stat-grid{grid-template-columns:repeat(3,minmax(0,1fr));}
    .user-filter-grid{grid-template-columns:repeat(3,minmax(0,1fr));}
    .user-filter-search{grid-column:span 2;}
  }
  @media(max-width:760px){
    .user-management-hero{flex-direction:column;padding:21px;}
    .user-management-hero .btn{width:100%;}
    .user-stat-grid{grid-template-columns:1fr 1fr;}
    .user-filter-grid{grid-template-columns:1fr;}
    .user-filter-search{grid-column:auto;}
    .user-filter-actions .btn{width:100%;}
  }
  @media(max-width:480px){.user-stat-grid{grid-template-columns:1fr;}}
</style>
@endpush
