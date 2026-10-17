# Views and Themes

[Home](Home) | **English** | [Türkçe](Views-and-Themes-TR)

Views are Blade templates (`*.blade.php`) in `content/themes/<theme>/`. New to Blade? The [Blade Cookbook](Blade-Cookbook) builds a theme step by step and explains every tag.

## Themes
* `content/themes/default`: the public site (`Controller`).
* `content/themes/admin`: the admin area (`AdminController`).
* `content/themes/shared`: views both areas use (login, forgot, reset, `layouts/base`, `layouts/auth`, `partials/`).

A controller picks its theme with the `$theme` property. A view is looked up in that theme first, then in `shared`, so you can override a shared page by creating the same file in your theme.
```php
class Shop extends Controller {
    protected string $theme = 'shop';   // content/themes/shop
}
```

## Writing views
```blade
@extends('layouts.site')
@section('title', $title)
@section('content')
    <h1>{{ $title }}</h1>              {{-- {{ }} escapes --}}
    {!! icon('shield') !!}              {{-- {!! !!} prints raw HTML: only for trusted output --}}
    <a href="{{ route('LOGIN') }}">Sign in</a>
@endsection
```
Layouts: `layouts.site` (public), `layouts.admin` (admin), `layouts.auth` (login-style pages), all extending `layouts.base`.

## Security rules for views
* `{{ }}` escapes. Never print user input with `{!! !!}`.
* The Content-Security-Policy blocks inline code. Inline `<style>` and `<script>` need `nonce="{{ csp_nonce() }}"`; better, put styles in `public/assets/css/pika.css`.
* Forms that change data include `{!! csrf_field() !!}`.

## Forms, messages, assets, icons
* `flash('status', 'Saved')` shows a message after the next redirect; `flash_now()` shows it on this request. The `partials.flash` view prints them.
* `old('field')` refills a form with the previous input (never for password or token fields); call `keep_old($_POST)` before redirecting back.
* `asset('css/pika.css')` builds an asset URL under `public/assets/`.
* `icon('name')` prints a Heroicons SVG (names in `src/Icons.php`).
* The look comes from `public/assets/css/pika.css` (light and dark, no external requests).
