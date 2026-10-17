# Rotalar ve Controller'lar

[Ana Sayfa](Home-TR) | [English](Routing-and-Controllers) | **Türkçe**

Yönlendirme `izniburak/router` paketini kullanır. `routes/` içindeki her dosya otomatik yüklenir.

## Rotalar
```php
// routes/web.php
$router->add('GET|HEAD', '/', 'Home@main', ['name' => 'HOME']);
$router->add('GET|HEAD|POST', 'login', 'Login@login', ['name' => 'LOGIN']);
$router->add('GET|HEAD|POST', 'reset/:any', 'PasswordReset@reset', ['name' => 'RESET']);
$router->add('GET|HEAD', 'ping', function () { json_output(['ok' => true]); });
```
* Üçüncü argüman `Controller@method` ya da bir closure'dır. Controller'lar `app/controllers/` içindedir; `app/controllers/Admin/Name.php` için `'Admin.Name@method'` kullanın.
* Parametreler: `:any` bir yol parçasını, `:all` geri kalanını yakalar. Değerler metodun ilk argümanları olarak gelir, sonra `Request` ve `Response` (Symfony HttpFoundation).
* Bir GET rotası, listelemezseniz HEAD'e cevap vermez: `'GET|HEAD'`.
* Controller `Response` döndürür (`$response->setContent(...)`); `send()` çağırmaz.

## Rota örnekleri
`routes/` içindeki her dosya yüklenir; bu yüzden bu sayfanın örnekleri kendi dosyasındadır. URL'leri tarayıcıda ya da `curl` ile açın; örnekler projenin test takımı tarafından çalıştırılır.

**Dosya:** `routes/demo.php`
```php
<?php
// 1. A closure: the quickest way to answer a request. Named, so route('DEMO_PING') gives its URL.
$router->add('GET|HEAD', 'demo/ping', function () {
    json_output(['status' => true]);
}, ['name' => 'DEMO_PING']);

// 2. A placeholder: its value arrives as the first argument. Escape it before printing.
$router->add('GET|HEAD', 'demo/hello/:any', function ($name) {
    echo 'Hello ' . esc($name);
});

// 3. Several placeholders arrive in order.
$router->add('GET|HEAD', 'demo/post/:any/comment/:any', function ($post, $comment) {
    echo 'Post ' . esc($post) . ', comment ' . esc($comment);
});

// 4. :all takes the rest of the path, slashes included.
$router->add('GET|HEAD', 'demo/files/:all', function ($path) {
    echo 'File ' . esc($path);
});

// 5. One URL, two methods. A POST needs the CSRF token (csrf_field() in the form).
$router->add('GET|POST', 'demo/form', function () {
    echo $_SERVER['REQUEST_METHOD'] === 'POST' ? 'Saved' : 'Show the form';
});

// 6. A group shares a URL prefix and a middleware (here: a per-IP request limit).
$router->group('demo/api', function ($router) {
    $router->add('GET|HEAD', 'time', function () {
        json_output(['time' => time()]);
    });
}, ['before' => RateLimit::class]);
```
| URL | Yanıt |
|---|---|
| `/demo/ping/` | `{"status":true}` |
| `/demo/hello/Ayse/` | `Hello Ayse` |
| `/demo/post/7/comment/3/` | `Post 7, comment 3` |
| `/demo/files/reports/2026/summary` | `File reports/2026/summary` |
| `/demo/api/time/` | JSON olarak zaman, IP başına sınırlı |

Gerçek bir projede rota bir closure yerine bir controller metoduna işaret eder: `$router->add('GET|HEAD', 'posts/:any', 'Posts@show', ['name' => 'POST'])`, `app/controllers/Posts.php` içindeki `show($id, Request $request, Response $response)` metodunu çağırır. [Not defteri öğreticisi](Tutorial-Notes-TR) başından sonuna bir tane kurar.

## İsimli rotalar
Bir rotaya isim verin, sonra URL'sini her yerde oluşturun:
```php
$url = route('RESET', ['any' => $token]);   // PHP içinde
```
Blade içinde: `{{ route('LOGIN') }}`, `{{ route('RESET', ['any' => $token]) }}` ya da `@route('LOGIN')`. Değerler URL kodlanır, sonuç HTML'e karşı kaçırılır. Bilinmeyen bir isim ya da eksik parametre `null` döndürür ve loga uyarı yazar. `route()` router'a ihtiyaç duyar, bu yüzden bir istek işlenirken çağırın.

## Gruplar ve middleware
```php
$router->group('admin', function ($router) {
    $router->add('GET|HEAD', '/', 'Admin.Dashboard@index', ['name' => 'ADMIN_HOME']);
}, ['before' => Admin::class]);
```
Middleware sınıfları `app/middlewares/` içindedir:
* `User`: site alanı, giriş yapmış kullanıcı ister (kullanıcı başına isteğe bağlı IP izin listesi).
* `Admin`: yönetim alanı, güvenli tarafta başarısız olur, GET olmayan her isteği denetim kaydına yazar.
* `RateLimit`: IP başına istek sınırı (`RATE_WINDOW` başına `RATE_LIMIT`), `Retry-After` ile 429 döndürür.

Middleware devam etmek için `true` döndürür; başka her değer yanıt olarak gönderilir.

## Controller'lar
`php pika make:controller Posts` (yönetim alanı için `--admin` ekleyin). Bir controller `Pika\Controller` (tema `default`) ya da `Pika\AdminController` (tema `admin`) sınıfından türer. İkisinde de `$this->authorize('permission')` vardır. Bkz. [Görünümler ve Temalar](Views-and-Themes-TR).

## CSRF
Her POST, PUT, PATCH ve DELETE oturum jetonunu ister (`token` form alanı ya da `X-CSRF-Token` başlığı). Jetonu gönderemeyen, jetonla doğrulanan uç noktalar (webhook'lar) `includes/components/post_security.php` içinde listelenmelidir; o listeyi dikkatle gözden geçirin.
