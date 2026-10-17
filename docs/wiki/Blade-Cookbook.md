# Blade Cookbook

[Home](Home) | **English** | [Türkçe](Blade-Cookbook-TR)

Blade is the template language of Pika: HTML with a few extra tags. This page builds a small **custom theme** called `shop` and shows the Blade features you will use every day. The files below are complete and are run by the project's test suite.

## How views are found
A controller calls `$this->view('products', $data)`. Pika looks for `products.blade.php` in the controller's theme folder first, then in `content/themes/shared`. A dot is a folder separator: `view('notes.index')` is `notes/index.blade.php`, `@include('partials.card')` is `partials/card.blade.php`.

```
content/themes/
  default/    the public site        (controllers extend Controller)
  admin/      the admin area         (controllers extend AdminController)
  shared/     views both can use: login, forgot, reset, layouts/base, layouts/auth, partials/flash
  shop/       your own theme, created below
```
Because `shared` is the fallback, you can **replace** a shared page for one theme by creating a file with the same name in that theme (for example `content/themes/shop/login.blade.php`).

## Step 1: a controller that uses a new theme
The `$theme` property chooses the folder. The data array becomes variables in the view (`$title`, `$products`, ...).

**File:** `app/controllers/Shop.php`
```php
<?php
namespace App\Controllers;

use Pika\Controller,
    Symfony\Component\HttpFoundation\Request,
    Symfony\Component\HttpFoundation\Response;

class Shop extends Controller
{
    protected string $theme = 'shop';   // content/themes/shop

    public function index(Request $request, Response $response)
    {
        $products = [
            ['name' => 'Notebook <A5>', 'price' => 4.5, 'stock' => 12],
            ['name' => 'Pen', 'price' => 1.25, 'stock' => 0],
        ];

        return $response->setContent($this->view('products', [
            'title'    => 'Products',
            'products' => $products,
            'onSale'   => 0,
            'loggedIn' => (bool) currentUser(),
        ]));
    }
}
```
**File:** `routes/shop.php`
```php
<?php
$router->add('GET|HEAD', 'shop', 'Shop@index', ['name' => 'SHOP']);
```

## Step 2: a layout
A layout is the frame every page shares. It marks holes with `@yield`; pages fill them with `@section`. `@yield('subtitle', 'All products')` has a default. `@stack('scripts')` collects pieces that pages add with `@push`. `layouts.base` comes from `shared` and provides the `<html>` skeleton, the stylesheet and the `body` section.

**File:** `content/themes/shop/layouts/shop.blade.php`
```blade
@extends('layouts.base')

@section('title', $title . ' · Shop')

@section('body')
    <header class="site-nav">
        <div class="container nav-inner">
            <a class="brand" href="{{ route('SHOP') }}">Shop</a>
            <span class="muted">@yield('subtitle', 'All products')</span>
        </div>
    </header>

    <main class="container">
        @include('partials.flash')
        @yield('content')
    </main>

    @stack('scripts')
@endsection
```

## Step 3: a partial
A partial is a reusable piece. `@include('partials.product-card', [...])` passes it variables.

**File:** `content/themes/shop/partials/product-card.blade.php`
```blade
<div class="card">
    <div class="card-body">
        <strong>{{ $position }}. {{ $product['name'] }}</strong>
        <span class="muted">{{ number_format($product['price'], 2) }}</span>
        @if ($product['stock'] === 0)
            <span class="badge badge-danger">Sold out</span>
        @endif
    </div>
</div>
```

## Step 4: the page
This one page shows the common tags. The table below it explains each of them.

**File:** `content/themes/shop/products.blade.php`
```blade
@extends('layouts.shop')

@section('subtitle', count($products) . ' products')

@section('content')
    <h1>{{ $title }}</h1>

    {{-- A Blade comment: it is not sent to the browser --}}

    @forelse ($products as $product)
        @include('partials.product-card', ['product' => $product, 'position' => $loop->iteration])
    @empty
        <div class="empty">Nothing here yet.</div>
    @endforelse

    <p>
        Names:
        @foreach ($products as $product){{ $product['name'] }}@if (!$loop->last), @endif @endforeach
    </p>

    @if ($onSale > 0)
        <p>{{ $onSale }} on sale.</p>
    @elseif ($onSale === 0)
        <p>No sale right now.</p>
    @endif

    @unless ($loggedIn)
        <p class="muted">Sign in to see your prices.</p>
    @endunless

    @php
        $total = array_sum(array_column($products, 'price'));
    @endphp
    <p>Everything together: {{ number_format($total, 2) }}</p>

    <a class="btn btn-ghost" href="{{ route('SHOP') }}">Reload</a>

    @verbatim
        <p>{{ printed as it is, for JavaScript templates }}</p>
    @endverbatim
@endsection

@push('scripts')
    <script nonce="{{ csp_nonce() }}">
        const products = @json($products);
    </script>
@endpush
```
Open `/shop/`.

| Tag | What it does |
|---|---|
| `{{ $x }}` | prints `$x` **escaped** (`<` becomes `&lt;`). Use it for everything that came from a user or a database. |
| `{!! $x !!}` | prints raw HTML. Only for output you built yourself, such as `icon('shield')`, `csrf_field()` or `$pages->render()`. |
| `{{-- ... --}}` | a comment that never reaches the browser. |
| `@if` `@elseif` `@else` `@endif` | conditions. `@unless (...)` is "if not". `@isset($x)` and `@empty($x)` test a variable. |
| `@foreach` `@forelse ... @empty` | loops. Inside, `$loop->iteration` (1, 2, ...), `$loop->first`, `$loop->last`, `$loop->index`. |
| `@include('name', [...])` | inserts a partial and passes variables. |
| `@extends` `@section` `@yield` | layouts: a page fills the holes of its layout. |
| `@push('name')` `@stack('name')` | a page adds something (usually a script) to a place the layout reserved. |
| `@php ... @endphp` | a block of plain PHP. Keep logic in the controller; use this sparingly. |
| `@verbatim` | Blade leaves `{{ }}` alone (for Vue, Alpine and similar). |
| `@json($data)` | prints data as JSON that is safe inside a `<script>`. |

## Pika's own helpers in views
| In a view | Result |
|---|---|
| `{{ route('LOGIN') }}`, `@route('LOGIN')` | URL of a named route; with parameters: `route('NOTES_DELETE', ['any' => $id])`. |
| `{!! csrf_field() !!}` | the hidden CSRF input every form needs. |
| `{{ old('email') }}` | what the visitor typed before a failed submit (never for password or token fields). |
| `{!! icon('shield') !!}` | a Heroicons SVG (names in `src/Icons.php`). |
| `{{ asset('css/pika.css') }}` | URL of a file in `public/assets/`. |
| `{{ csp_nonce() }}` | the per-request nonce that lets one inline `<script>` or `<style>` run. |
| `@include('partials.flash')` | prints `flash('status', ...)` and `flash('error', ...)` messages. |
| `currentUser()` | the signed-in user or `false`; `currentAdminUser()` in the admin area. |

## Rules that keep a page safe
1. Print with `{{ }}`. A name such as `Notebook <A5>` shows as text; with `{!! !!}` it would be HTML.
2. Every form that changes data has `{!! csrf_field() !!}` and uses `method="post"`.
3. The browser blocks inline `<script>` and `<style>` unless they carry `nonce="{{ csp_nonce() }}"`. Put styles in `public/assets/css/pika.css` and scripts in `public/assets/js/`.
4. Do not put secrets or other users' data into variables you pass to a view.
