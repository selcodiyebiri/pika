@extends('layouts.base')

@section('body')
    <input type="checkbox" id="nav-toggle" class="nav-toggle" aria-hidden="true">
    <div class="shell">
        <label for="nav-toggle" class="nav-backdrop" aria-hidden="true"></label>
        <aside class="sidebar">
            <a class="brand" href="{{ route('ADMIN_HOME') }}"><span class="brand-mark">{!! icon('bolt') !!}</span> Pika</a>
            <nav>
                <div class="nav-label">Menu</div>
                <a class="nav-link {{ ($nav ?? 'dashboard') === 'dashboard' ? 'active' : '' }}" href="{{ route('ADMIN_HOME') }}">{!! icon('grid') !!} Dashboard</a>
                <a class="nav-link {{ ($nav ?? '') === 'jobs' ? 'active' : '' }}" href="{{ route('ADMIN_JOBS') }}">{!! icon('terminal') !!} Jobs</a>
                <a class="nav-link" href="{{ route('HOME') }}" target="_blank" rel="noopener">{!! icon('home') !!} View site</a>
            </nav>
            <div class="sidebar-foot">
                <div>Signed in as <strong>{{ currentAdminUser() ? currentAdminUser()->username : 'admin' }}</strong></div>
                <form method="post" action="{{ route('ADMIN_LOGOUT') }}">
                    {!! csrf_field() !!}
                    <button class="btn-link" type="submit">{!! icon('logout') !!} Sign out</button>
                </form>
            </div>
        </aside>

        <div class="main">
            <header class="topbar">
                <label for="nav-toggle" class="nav-toggle-label" aria-label="Toggle navigation">{!! icon('menu') !!}</label>
                <h1>@yield('heading')</h1>
                <span class="spacer"></span>
                <span class="user-chip">
                    <span class="avatar">{{ strtoupper(substr(currentAdminUser() ? currentAdminUser()->username : 'A', 0, 1)) }}</span>
                    <span class="user-name">{{ currentAdminUser() ? currentAdminUser()->username : '' }}</span>
                </span>
            </header>
            <div class="content">
                @include('partials.flash')
                @yield('content')
            </div>
        </div>
    </div>
@endsection
