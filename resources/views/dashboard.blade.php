<!-- resources/views/dashboard.blade.php -->
@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
  <div class="card">
    <div class="card-header">
      <div>
        <h1 class="h1">Dashboard</h1>
        <p class="p">Welcome, <strong>{{ auth()->user()->name }}</strong>. Manage LGU agriculture records here.</p>
      </div>

      {{--
      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a class="btn" href="{{ route('rice-seed-distributions.index') }}">Open Rice Seed Module</a>
        <a class="btn btn-soft" style="box-shadow:none;" href="{{ route('rice-seed-distributions.create') }}">+ Add Recipient</a>
      </div>
      --}}
    </div>

    {{--
    <div style="padding:16px;">
      <div class="grid grid-3">
        <div class="card" style="box-shadow:none;">
          <div class="card-header" style="border-bottom:1px solid var(--border);">
            <div>
              <h2 class="h1" style="font-size:16px;">Rice Seed Distribution</h2>
              <p class="p">Quick summary of records.</p>
            </div>
          </div>

          <div style="padding:16px;">
            @php
              $totalRecipients = \App\Models\RiceSeedDistribution::count();
              $totalKgs = \App\Models\RiceSeedDistribution::sum('kgs_received');
              $latest = \App\Models\RiceSeedDistribution::latest()->take(5)->get();
            @endphp

            <div style="display:flex; gap:12px; flex-wrap:wrap;">
              <div style="flex:1; min-width:140px; padding:12px; border:1px solid var(--border); border-radius:14px;">
                <div style="font-size:12px; color:var(--muted); font-weight:700;">Total Recipients</div>
                <div style="font-size:22px; font-weight:900; margin-top:4px;">{{ $totalRecipients }}</div>
              </div>

              <div style="flex:1; min-width:140px; padding:12px; border:1px solid var(--border); border-radius:14px;">
                <div style="font-size:12px; color:var(--muted); font-weight:700;">Total Kgs Distributed</div>
                <div style="font-size:22px; font-weight:900; margin-top:4px;">{{ number_format((float)$totalKgs, 2) }}</div>
              </div>
            </div>

            <div style="margin-top:14px;">
              <div style="font-weight:900; margin-bottom:8px;">Latest Entries</div>

              @if($latest->count())
                <div style="border:1px solid var(--border); border-radius:14px; overflow:hidden;">
                  <table>
                    <thead>
                      <tr>
                        <th>Recipient</th>
                        <th>Kgs</th>
                        <th>Date</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach($latest as $r)
                        <tr>
                          <td style="font-weight:700;">
                            {{ $r->last_name }}, {{ $r->first_name }}
                          </td>
                          <td style="font-weight:900;">{{ $r->kgs_received }}</td>
                          <td>{{ $r->date_received }}</td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              @else
                <p class="p" style="margin:0;">No entries yet. Add your first record.</p>
              @endif
            </div>

            <div style="margin-top:14px; display:flex; gap:10px; flex-wrap:wrap;">
              <a class="btn btn-soft" style="box-shadow:none;" href="{{ route('rice-seed-distributions.index') }}">View All Records</a>
              <a class="btn" href="{{ route('rice-seed-distributions.create') }}">+ Add New</a>
            </div>
          </div>
        </div>

        <div class="card" style="box-shadow:none;">
          <div class="card-header" style="border-bottom:1px solid var(--border);">
            <div>
              <h2 class="h1" style="font-size:16px;">Quick Actions</h2>
              <p class="p">Common tasks for staff.</p>
            </div>
          </div>

          <div style="padding:16px; display:grid; gap:10px;">
            <a class="link" href="{{ route('rice-seed-distributions.create') }}">➕ Add Recipient</a>
            <a class="link" href="{{ route('rice-seed-distributions.index') }}">📋 View Distribution List</a>
            <a class="link" href="{{ route('rice-seed-distributions.index', ['per_page' => 50]) }}">🔎 Browse (50 rows)</a>
          </div>
        </div>

        <div class="card" style="box-shadow:none;">
          <div class="card-header" style="border-bottom:1px solid var(--border);">
            <div>
              <h2 class="h1" style="font-size:16px;">Account</h2>
              <p class="p">Your session information.</p>
            </div>
          </div>

          <div style="padding:16px;">
            <div style="border:1px solid var(--border); border-radius:14px; padding:12px;">
              <div style="font-size:12px; color:var(--muted); font-weight:700;">Logged in as</div>
              <div style="font-weight:900; margin-top:4px;">{{ auth()->user()->name }}</div>
              <div style="font-size:13px; color:var(--muted); margin-top:4px;">{{ auth()->user()->email }}</div>
            </div>

            <div style="margin-top:12px;">
              <form method="POST" action="{{ route('logout') }}" style="margin:0;">
                @csrf
                <button type="submit" class="btn btn-danger" style="width:100%;">Logout</button>
              </form>
              <div class="p" style="margin-top:10px;">
                Tip: Keep your password private and logout after office hours.
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
    --}}
  </div>
@endsection
