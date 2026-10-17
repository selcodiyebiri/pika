# Görünümler ve Temalar

[Ana Sayfa](Home-TR) | [English](Views-and-Themes) | **Türkçe**

Görünümler, `content/themes/<tema>/` içindeki Blade şablonlarıdır (`*.blade.php`). Blade'e yeni misiniz? [Blade Yemek Kitabı](Blade-Cookbook-TR) adım adım bir tema kurar ve her etiketi açıklar.

## Temalar
* `content/themes/default`: herkese açık site (`Controller`).
* `content/themes/admin`: yönetim alanı (`AdminController`).
* `content/themes/shared`: iki alanın da kullandığı görünümler (login, forgot, reset, `layouts/base`, `layouts/auth`, `partials/`).

Bir controller temasını `$theme` özelliğiyle seçer. Görünüm önce o temada, sonra `shared` içinde aranır; böylece ortak bir sayfayı kendi temanızda aynı dosyayı oluşturarak değiştirebilirsiniz.
```php
class Shop extends Controller {
    protected string $theme = 'shop';   // content/themes/shop
}
```

## Görünüm yazmak
```blade
@extends('layouts.site')
@section('title', $title)
@section('content')
    <h1>{{ $title }}</h1>              {{-- {{ }} kaçırır --}}
    {!! icon('shield') !!}              {{-- {!! !!} ham HTML basar: yalnızca güvenilir çıktı için --}}
    <a href="{{ route('LOGIN') }}">Sign in</a>
@endsection
```
Şablonlar: `layouts.site` (herkese açık), `layouts.admin` (yönetim), `layouts.auth` (giriş benzeri sayfalar); hepsi `layouts.base` sınıfından türer.

## Görünümler için güvenlik kuralları
* `{{ }}` kaçırır. Kullanıcı girdisini asla `{!! !!}` ile basmayın.
* Content-Security-Policy satır içi kodu engeller. Satır içi `<style>` ve `<script>` için `nonce="{{ csp_nonce() }}"` gerekir; daha iyisi stilleri `public/assets/css/pika.css` içine koymaktır.
* Veri değiştiren formlar `{!! csrf_field() !!}` içerir.

## Formlar, mesajlar, varlıklar, ikonlar
* `flash('status', 'Saved')` sonraki yönlendirmeden sonra bir mesaj gösterir; `flash_now()` bu istekte gösterir. `partials.flash` görünümü bunları basar.
* `old('field')` formu önceki girdiyle doldurur (parola ve jeton alanları için asla); geri yönlendirmeden önce `keep_old($_POST)` çağırın.
* `asset('css/pika.css')`, `public/assets/` altındaki bir varlığın URL'sini oluşturur.
* `icon('name')` bir Heroicons SVG'si basar (isimler `src/Icons.php` içinde).
* Görünüm `public/assets/css/pika.css` dosyasından gelir (açık ve koyu, dış istek yok).
