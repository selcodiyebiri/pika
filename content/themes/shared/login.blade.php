@extends('layouts.auth')

@section('title', $title . ' · Pika')

@section('content')
    @if (!empty($badge))
        <p><span class="badge">{!! icon('shield', 'icon icon-sm') !!} {{ $badge }}</span></p>
    @endif
    <h1 class="auth-title">Welcome back</h1>
    <p class="auth-sub">Sign in to continue.</p>

    @if ($error)
        <div class="alert alert-error" role="alert">{!! icon('error') !!}<div>{{ $error }}</div></div>
    @endif

    <form method="post">
        {!! csrf_field() !!}
        <div class="field">
            <label for="username">Username</label>
            <input class="input" id="username" name="username" type="text" value="{{ old('username') }}" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus>
        </div>
        <div class="field">
            <label for="password">Password</label>
            @include('partials.password-input', ['id' => 'password', 'name' => 'password'])
        </div>
        <div class="row-between field">
            @if ($remember)
                <label class="checkbox"><input type="checkbox" name="remember" value="1"> Remember me</label>
            @else
                <span></span>
            @endif
            @if (!empty($forgotUrl))
                <a class="small" href="{{ $forgotUrl }}">Forgot password?</a>
            @endif
        </div>
        <button class="btn btn-primary btn-block btn-lg" type="submit">Sign in</button>
    </form>
@endsection

@section('foot')
    <a href="{{ route('HOME') }}">&larr; Back to site</a>
@endsection
