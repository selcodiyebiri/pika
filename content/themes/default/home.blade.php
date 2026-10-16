@extends('layouts.site')

@section('title', $title)

@section('content')
    <section class="hero">
        <div class="container">
            <span class="hero-pill">{!! icon('sparkles', 'icon icon-sm') !!} Secure by default</span>
            <h1 class="hero-title">{{ $title }}</h1>
            <p class="hero-lead">A small PHP framework base for your own projects: sessions, CSRF, rate limits, password reset, themes and a command line, ready on day one.</p>
            <div class="hero-actions">
                @if (currentUser() || currentAdminUser())
                    @if (currentAdminUser())
                        <a class="btn btn-primary btn-lg" href="{{ currentAdminUrl() }}/">{!! icon('grid') !!} Open the admin area</a>
                    @endif
                @else
                    <a class="btn btn-primary btn-lg" href="{{ route('LOGIN') }}">Sign in</a>
                    <a class="btn btn-ghost btn-lg" href="{{ currentAdminUrl() }}/login/">{!! icon('shield') !!} Admin login</a>
                @endif
            </div>
        </div>
    </section>

    <section class="container features">
        <article class="card feature">
            <div class="tile">{!! icon('shield') !!}</div>
            <h3>Secure by default</h3>
            <p>Strict CSP with nonces, CSRF on every write, throttled logins, hardened sessions and a production mode that refuses unsafe settings.</p>
        </article>
        <article class="card feature">
            <div class="tile">{!! icon('database') !!}</div>
            <h3>MySQL and Blade</h3>
            <p>PDO with prepared statements, a small query builder, migrations with rollback, and Blade views.</p>
        </article>
        <article class="card feature">
            <div class="tile">{!! icon('layers') !!}</div>
            <h3>Site and admin themes</h3>
            <p>Separate themes with shared views, so login and password reset are written once and styled everywhere.</p>
        </article>
        <article class="card feature">
            <div class="tile">{!! icon('terminal') !!}</div>
            <h3>Command line and installer</h3>
            <p><code>php pika install</code>, or a locked web installer for hosting without a terminal.</p>
        </article>
    </section>
@endsection
