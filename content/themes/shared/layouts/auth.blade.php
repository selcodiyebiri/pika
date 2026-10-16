@extends('layouts.base')

@section('body_class', 'auth-body')

@section('body')
    <main class="auth-wrap">
        <a class="brand" href="{{ route('HOME') }}"><span class="brand-mark">{!! icon('bolt') !!}</span> Pika</a>
        <div class="card auth-card">
            <div class="card-body">
                @include('partials.flash')
                @yield('content')
            </div>
        </div>
        <p class="auth-foot">@yield('foot')</p>
    </main>
@endsection
