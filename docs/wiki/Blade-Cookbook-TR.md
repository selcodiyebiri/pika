# Blade Yemek Kitabı

[Ana Sayfa](Home-TR) | [English](Blade-Cookbook) | **Türkçe**

Blade, Pika'nın şablon dilidir: birkaç ek etiketi olan HTML. Bu sayfa `shop` adında küçük bir **özel tema** kurar ve her gün kullanacağınız Blade özelliklerini gösterir. Aşağıdaki dosyalar eksiksizdir ve projenin test takımı tarafından çalıştırılır.

## Görünümler nasıl bulunur
Bir controller `$this->view('products', $data)` çağırır. Pika `products.blade.php` dosyasını önce controller'ın tema klasöründe, sonra `content/themes/shared` içinde arar. Nokta klasör ayırıcıdır: `view('notes.index')` demek `notes/index.blade.php`, `@include('partials.card')` demek `partials/card.blade.php` demektir.

```
content/themes/
  default/    herkese açık site      (controller'lar Controller'dan türer)
  admin/      yönetim alanı          (controller'lar AdminController'dan türer)
  shared/     ikisinin de kullanabildiği görünümler: login, forgot, reset, layouts/base, layouts/auth, partials/flash
  shop/       aşağıda oluşturacağınız kendi temanız
```
`shared` yedek olduğu için, bir ortak sayfayı tek bir tema için, o temada aynı adda bir dosya oluşturarak **değiştirebilirsiniz** (örneğin `content/themes/shop/login.blade.php`).

## Adım 1: yeni bir tema kullanan controller
`$theme` özelliği klasörü seçer. Veri dizisi görünümde değişkenlere dönüşür (`$title`, `$products`, ...).

**Dosya:** `app/controllers/Shop.php`
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
**Dosya:** `routes/shop.php`
```php
<?php
$router->add('GET|HEAD', 'shop', 'Shop@index', ['name' => 'SHOP']);
```

## Adım 2: bir şablon (layout)
Şablon, her sayfanın paylaştığı çerçevedir. Boşlukları `@yield` ile işaretler; sayfalar bunları `@section` ile doldurur. `@yield('subtitle', 'All products')` bir varsayılana sahiptir. `@stack('scripts')`, sayfaların `@push` ile eklediği parçaları toplar. `layouts.base`, `shared` içinden gelir ve `<html>` iskeletini, stil dosyasını ve `body` bölümünü sağlar.

**Dosya:** `content/themes/shop/layouts/shop.blade.php`
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

## Adım 3: bir parça (partial)
Partial, yeniden kullanılabilir bir parçadır. `@include('partials.product-card', [...])` ona değişken geçirir.

**Dosya:** `content/themes/shop/partials/product-card.blade.php`
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

## Adım 4: sayfa
Bu tek sayfa yaygın etiketleri gösterir. Altındaki tabloyu okuyun.

**Dosya:** `content/themes/shop/products.blade.php`
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
`/shop/` adresini açın.

| Etiket | Ne yapar |
|---|---|
| `{{ $x }}` | `$x` değerini **kaçırarak** basar (`<` karakteri `&lt;` olur). Kullanıcıdan ya da veritabanından gelen her şey için kullanın. |
| `{!! $x !!}` | ham HTML basar. Yalnızca kendi oluşturduğunuz çıktı için, örneğin `icon('shield')`, `csrf_field()` ya da `$pages->render()`. |
| `{{-- ... --}}` | tarayıcıya asla ulaşmayan bir yorum. |
| `@if` `@elseif` `@else` `@endif` | koşullar. `@unless (...)` "değilse" demektir. `@isset($x)` ve `@empty($x)` bir değişkeni sınar. |
| `@foreach` `@forelse ... @empty` | döngüler. İçinde `$loop->iteration` (1, 2, ...), `$loop->first`, `$loop->last`, `$loop->index`. |
| `@include('name', [...])` | bir partial ekler ve değişken geçirir. |
| `@extends` `@section` `@yield` | şablonlar: bir sayfa şablonunun boşluklarını doldurur. |
| `@push('name')` `@stack('name')` | bir sayfa, şablonun ayırdığı bir yere (genellikle bir script) bir şey ekler. |
| `@php ... @endphp` | düz PHP bloğu. Mantığı controller'da tutun; bunu az kullanın. |
| `@verbatim` | Blade `{{ }}` ifadelerine dokunmaz (Vue, Alpine ve benzerleri için). |
| `@json($data)` | veriyi bir `<script>` içinde güvenli olan JSON olarak basar. |

## Görünümlerde Pika'nın kendi yardımcıları
| Görünümde | Sonuç |
|---|---|
| `{{ route('LOGIN') }}`, `@route('LOGIN')` | isimli bir rotanın URL'si; parametreyle: `route('NOTES_DELETE', ['any' => $id])`. |
| `{!! csrf_field() !!}` | her formun ihtiyaç duyduğu gizli CSRF girdisi. |
| `{{ old('email') }}` | başarısız bir gönderimden önce ziyaretçinin yazdığı (parola ve jeton alanları için asla). |
| `{!! icon('shield') !!}` | bir Heroicons SVG'si (isimler `src/Icons.php` içinde). |
| `{{ asset('css/pika.css') }}` | `public/assets/` içindeki bir dosyanın URL'si. |
| `{{ csp_nonce() }}` | tek bir satır içi `<script>` ya da `<style>` öğesinin çalışmasına izin veren istek başına nonce. |
| `@include('partials.flash')` | `flash('status', ...)` ve `flash('error', ...)` mesajlarını basar. |
| `currentUser()` | giriş yapmış kullanıcı ya da `false`; yönetim alanında `currentAdminUser()`. |

## Sayfayı güvenli tutan kurallar
1. `{{ }}` ile basın. `Notebook <A5>` gibi bir ad metin olarak görünür; `{!! !!}` ile HTML olurdu.
2. Veri değiştiren her formda `{!! csrf_field() !!}` bulunur ve `method="post"` kullanılır.
3. Tarayıcı, `nonce="{{ csp_nonce() }}"` taşımayan satır içi `<script>` ve `<style>` öğelerini engeller. Stilleri `public/assets/css/pika.css` içine, script'leri `public/assets/js/` içine koyun.
4. Bir görünüme geçirdiğiniz değişkenlere sır ya da başka kullanıcıların verisini koymayın.
