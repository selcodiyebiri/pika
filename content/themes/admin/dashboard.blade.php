@extends('layouts.admin')

@section('title', $title . ' · Pika Admin')
@section('heading', $title)

@section('content')
    <div class="stats">
        <div class="card stat">
            <div class="stat-top">Users <span class="stat-icon">{!! icon('users') !!}</span></div>
            <div class="stat-value">{{ $stats['users'] ?? 0 }}</div>
            <div class="stat-note">{{ $stats['admins'] ?? 0 }} administrator(s)</div>
        </div>
        <div class="card stat">
            <div class="stat-top">Sign-ins, 24 h <span class="stat-icon">{!! icon('key') !!}</span></div>
            <div class="stat-value">{{ $stats['logins'] ?? 0 }}</div>
            <div class="stat-note">successful logins</div>
        </div>
        <div class="card stat">
            <div class="stat-top">Failed attempts, 24 h <span class="stat-icon">{!! icon('alert') !!}</span></div>
            <div class="stat-value">{{ $stats['failed'] ?? 0 }}</div>
            <div class="stat-note">wrong passwords or locked out</div>
        </div>
        <div class="card stat">
            <div class="stat-top">Migrations <span class="stat-icon">{!! icon('database') !!}</span></div>
            <div class="stat-value">{{ $stats['migrations'] ?? 0 }}</div>
            <div class="stat-note">applied to the database</div>
        </div>
    </div>

    <div class="cols">
        <section class="card">
            <div class="card-head"><h2>Recent activity</h2><span class="badge">{!! icon('activity', 'icon icon-sm') !!} audit log</span></div>
            @if (!empty($activity))
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>User</th><th>Action</th><th>IP address</th><th>When</th></tr></thead>
                        <tbody>
                        @foreach ($activity as $row)
                            <tr>
                                <td><strong>{{ $row['username'] }}</strong></td>
                                <td><span class="badge {{ $row['tone'] }}">{{ $row['action'] }}</span></td>
                                <td class="muted">{{ $row['ip'] }}</td>
                                <td class="muted">{{ $row['time'] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty">Nothing has happened yet.</div>
            @endif
        </section>

        <section class="card">
            <div class="card-head"><h2>System</h2><span class="badge {{ ($system['env'] ?? '') === 'production' ? 'badge-success' : 'badge-warn' }}">{{ $system['env'] ?? 'unknown' }}</span></div>
            <dl class="kv">
                <dt>PHP</dt><dd>{{ $system['php'] ?? '' }}</dd>
                <dt>MySQL</dt><dd>{{ $system['mysql'] ?? '' }}</dd>
                <dt>Debug mode</dt><dd><span class="badge {{ !empty($system['debug']) ? 'badge-danger' : 'badge-success' }}">{{ !empty($system['debug']) ? 'on' : 'off' }}</span></dd>
                <dt>Time zone</dt><dd>{{ $system['timezone'] ?? '' }}</dd>
                <dt>Server time</dt><dd>{{ $system['time'] ?? '' }}</dd>
            </dl>
        </section>
    </div>
@endsection
