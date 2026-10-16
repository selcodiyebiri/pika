@extends('layouts.auth')

@section('title', $title . ' · Pika')

@section('content')
    @if ($invalid)
        <h1 class="auth-title">Link expired</h1>
        <div class="alert alert-error" role="alert">{!! icon('error') !!}<div>This reset link is invalid or has expired.</div></div>
        <a class="btn btn-primary btn-block" href="{{ route('FORGOT') }}">Request a new link</a>
    @else
        <h1 class="auth-title">Choose a new password</h1>
        <p class="auth-sub">Use at least 10 characters. You will be signed out everywhere else.</p>
        <form method="post">
            {!! csrf_field() !!}
            <div class="field">
                <label for="password">New password</label>
                @include('partials.password-input', ['id' => 'password', 'name' => 'password', 'autocomplete' => 'new-password', 'minlength' => 10, 'maxlength' => 72])
            </div>
            <div class="field">
                <label for="password_confirmation">Repeat password</label>
                @include('partials.password-input', ['id' => 'password_confirmation', 'name' => 'password_confirmation', 'autocomplete' => 'new-password'])
            </div>
            <button class="btn btn-primary btn-block btn-lg" type="submit">{!! icon('lock') !!} Change password</button>
        </form>
    @endif
@endsection

@section('foot')
    <a href="{{ route('LOGIN') }}">&larr; Back to sign in</a>
@endsection
