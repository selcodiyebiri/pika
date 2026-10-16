@extends('layouts.base')

@section('body')
    <header class="site-nav">
        <div class="container nav-inner">
            <a class="brand" href="{{ route('HOME') }}"><span class="brand-mark">{!! icon('bolt') !!}</span> Pika</a>
            <nav class="nav-actions">
                @if (currentUser())
                    <span class="muted small">Signed in as <strong>{{ currentUser()->username }}</strong></span>
                    <form method="post" action="{{ route('LOGOUT') }}">
                        {!! csrf_field() !!}
                        <button class="btn btn-ghost" type="submit">{!! icon('logout') !!} Sign out</button>
                    </form>
                @else
                    <a class="btn btn-primary" href="{{ route('LOGIN') }}">Sign in</a>
                @endif
            </nav>
        </div>
    </header>

    <main>
        <div class="container">@include('partials.flash')</div>
        @yield('content')
    </main>

    <footer class="site-foot">
        <div class="container row-between">
            <span>&copy; {{ date('Y') }} Pika</span>
            <span>A security-minded PHP framework base</span>
        </div>
    </footer>
@endsection
