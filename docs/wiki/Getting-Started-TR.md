# Başlangıç

[Ana Sayfa](Home-TR) | [English](Getting-Started) | **Türkçe**

## Kurulum
Terminalle:
```bash
composer install
cp .env.example .env          # geliştirme için APP_ENV=local
php pika key:generate         # .env içine rastgele bir PIKA_LICENSE yazar
# .env içindeki MYSQL_* değerlerini düzenleyin, boş veritabanını oluşturun, sonra:
php pika migrate
php pika make:admin           # ilk yönetici hesabı
php pika serve                # http://127.0.0.1:8000  (yönetim: /admin/login)
```
Ya da sihirbaz hepsini yapsın: `php pika install`. Terminal yoksa web kurulumunu kullanın, bkz. [Yayına Alma](Deployment-TR).

`php pika doctor` komutunu istediğiniz zaman çalıştırın: yapılandırmayı, veritabanı bağlantısını, komut satırının kullandığı PHP'yi ve işlerinizi kontrol eder.

## Klasörler
```
public/        web kökü: index.php, install.php, assets/, uploads/
src/           Pika\ sınıfları (framework çekirdeği)
app/           sizin kodunuz: controllers/, middlewares/, models/, jobs/
routes/        web.php, admin.php, api.php (tüm dosyalar yüklenir)
includes/      loader, config, helpers.php, components/, error_pages/
content/       themes/, cache/, logs/
database/      migrations/NNN_ad.sql (+ NNN_ad.down.sql)
tests/         birim ve entegrasyon testleri
```
Web sunucusunu `public/` klasörüne yönlendirin. Geri kalan hiçbir şey web'den erişilebilir olmamalı.

## İlk sayfanız
1. `routes/web.php` içinde bir rota (GET rotaları HEAD'i de listelemeli):
```php
$router->add('GET|HEAD', 'about', 'About@index', ['name' => 'ABOUT']);
```
2. Bir controller: `php pika make:controller About` komutu `app/controllers/About.php` dosyasını oluşturur. Bir görünüm döndürmesini sağlayın:
```php
public function index(Request $request, Response $response) {
    return $response->setContent($this->view('about', ['title' => 'About']));
}
```
3. `content/themes/default/about.blade.php` görünümü:
```blade
@extends('layouts.site')
@section('title', $title)
@section('content')
    <div class="container"><h1>{{ $title }}</h1></div>
@endsection
```
`/about/` adresini açın. Her yerden `{{ route('ABOUT') }}` ile bağlantı verin.
