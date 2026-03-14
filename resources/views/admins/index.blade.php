@extends('layouts.app')

@section('title', 'Admins')

@push('styles')
<style>
  /* Page-specific polish */
  .toolbar{
    display:flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: flex-end;
    padding: 12px;
    border: 1px solid var(--border);
    background: rgba(255,255,255,.70);
    border-radius: 16px;
    box-shadow: 0 10px 28px rgba(2,6,23,.06);
  }

  .statpill{
    display:inline-flex;
    align-items:center;
    gap: 8px;
    padding: 8px 12px;
    border-radius: 999px;
    border: 1px solid rgba(34,197,94,.22);
    background: rgba(34,197,94,.10);
    color: #166534;
    font-weight: 900;
    font-size: 12px;
    white-space: nowrap;
  }
  .statpill .num{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width: 28px;
    height: 22px;
    padding: 0 8px;
    border-radius: 999px;
    background: #fff;
    border: 1px solid rgba(2,6,23,.10);
    color: #0b1220;
    font-weight: 900;
    font-size: 12px;
  }

  .table-wrap{
    margin-top: 14px;
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 14px 40px rgba(2,6,23,.08);
  }

  table.table{
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 13px;
  }

  .table thead th{
    background: #f8fafc;
    border-bottom: 1px solid var(--border);
    text-align: left;
    padding: 12px 12px;
    font-weight: 900;
    color: #0b1220;
    position: sticky;
    top: 0;
    z-index: 1;
  }

  .table tbody td{
    padding: 12px 12px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
  }

  .table tbody tr:nth-child(odd){
    background: rgba(248,250,252,.50);
  }

  .table tbody tr:hover{
    background: rgba(34,197,94,.06);
  }

  .namecell{
    display:flex;
    align-items:center;
    gap: 10px;
  }

  .avatar{
    width: 34px;
    height: 34px;
    border-radius: 12px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-weight: 900;
    background: rgba(250,204,21,.18);
    border: 1px solid rgba(250,204,21,.35);
    color: #854d0e;
    flex: 0 0 auto;
  }

  .subtle{
    color: var(--muted);
    font-size: 12px;
    margin-top: 2px;
    line-height: 1.2;
  }

  .row-actions{
    display:flex;
    gap: 10px;
    justify-content:flex-end;
    flex-wrap: wrap;
  }

  .btn-icon{
    display:inline-flex;
    align-items:center;
    gap: 8px;
  }

  @media (max-width: 900px){
    .table thead{ display:none; }

    .table tbody tr{
      display:block;
      padding: 12px;
      border-bottom: 1px solid var(--border);
      background: #fff;
    }
    .table tbody td{
      display:flex;
      justify-content:space-between;
      gap: 12px;
      padding: 8px 0;
      border-bottom: none;
    }
    .table tbody td::before{
      content: attr(data-label);
      font-weight: 900;
      color: #0b1220;
    }
    .row-actions{
      justify-content:flex-start;
    }
  }
</style>
@endpush

@section('content')
  <div class="card">
    <div class="card-header">
      <div>
        <h1 class="h1">Admins</h1>
        <p class="p">Head Admin can create, edit, and delete admin accounts.</p>

        <div style="margin-top:10px;">
          <span class="statpill">
            Total Admins <span class="num">{{ number_format($totalAdmins) }}</span>
          </span>
        </div>
      </div>

      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a class="btn" href="{{ route('admins.create') }}">+ Add Admin</a>
      </div>
    </div>

    <div style="padding:16px;">
      <form method="GET" action="{{ route('admins.index') }}" class="toolbar">
        <div style="flex:1; min-width:260px;">
          <label>Search</label>
          <input class="input" name="q" value="{{ $q }}" placeholder="Search name or email">
          <div class="subtle">Tip: type at least 2–3 letters to narrow results fast.</div>
        </div>

        <div style="width:200px;">
          <label>Rows</label>
          <select class="input js-select" name="per_page">
            @foreach([5,10,25,50,100] as $n)
              <option value="{{ $n }}" @selected((int)$perPage === $n)>{{ $n }}</option>
            @endforeach
          </select>
          <div class="subtle">Controls pagination size.</div>
        </div>

        <div style="display:flex; gap:10px; flex-wrap:wrap;">
          <button class="btn btn-soft" style="box-shadow:none;" type="submit">Apply</button>
          <a class="btn btn-soft" style="box-shadow:none;" href="{{ route('admins.index') }}">Reset</a>
        </div>
      </form>

      <div class="table-wrap">
        <div style="max-height: 560px; overflow:auto;">
          <table class="table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Created</th>
                <th style="width:240px; text-align:right;">Actions</th>
              </tr>
            </thead>

            <tbody>
              @forelse($admins as $a)
                @php
                  $initials = collect(explode(' ', trim($a->name)))
                    ->filter()
                    ->take(2)
                    ->map(fn($p) => strtoupper(mb_substr($p, 0, 1)))
                    ->join('');
                @endphp

                <tr>
                  <td data-label="Name">
                    <div class="namecell">
                      <div class="avatar" title="Admin">{{ $initials ?: 'A' }}</div>
                      <div>
                        <div style="font-weight:900;">{{ $a->name }}</div>
                        <div class="subtle">Admin account</div>
                      </div>
                    </div>
                  </td>

                  <td data-label="Email">
                    <div style="font-weight:700;">{{ $a->email }}</div>
                    <div class="subtle">Used for login</div>
                  </td>

                  <td data-label="Created">
                    <div style="font-weight:700;">{{ optional($a->created_at)->format('Y-m-d') }}</div>
                    <div class="subtle">{{ optional($a->created_at)->format('h:i A') }}</div>
                  </td>

                  <td data-label="Actions">
                    <div class="row-actions">
                      <a class="btn btn-soft btn-icon" style="box-shadow:none;" href="{{ route('admins.edit', $a) }}">
                        ✏️ Edit
                      </a>

                      <form method="POST" action="{{ route('admins.destroy', $a) }}" style="margin:0;"
                        onsubmit="return confirm('Delete this admin account?');">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-danger btn-icon" type="submit">🗑️ Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="4" style="padding:16px;">
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                      <div>
                        <div style="font-weight:900;">No admin accounts found.</div>
                        <div class="subtle">Try clearing filters or adding a new admin.</div>
                      </div>
                      <a class="btn" href="{{ route('admins.create') }}">+ Add Admin</a>
                    </div>
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      <div style="margin-top:14px;">
        {{ $admins->links() }}
      </div>
    </div>
  </div>
@endsection