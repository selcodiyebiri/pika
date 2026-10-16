@extends('layouts.auth')

@section('title', $title . ' · Pika')

@section('content')
    <h1 class="auth-title">Forgot your password?</h1>
    <p class="auth-sub">Enter your e-mail address and we will send you a link to choose a new one.</p>

    <form method="post">
        {!! csrf_field() !!}
        <div class="field">
            <label for="email">E-mail address</label>
            <input class="input" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
        </div>
        <button class="btn btn-primary btn-block btn-lg" type="submit">{!! icon('mail') !!} Send reset link</button>
    </form>
@endsection

@section('foot')
    <a href="{{ route('LOGIN') }}">&larr; Back to sign in</a>
@endsection
