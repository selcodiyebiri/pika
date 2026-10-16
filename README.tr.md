# Pika

[English](README.md) | **Türkçe**

Kişisel projeler için küçük, güvenlik odaklı bir PHP framework altyapısı. MySQL (PDO), Blade görünümleri, küçük bir router.
Her işe yarayan bir framework değil, kopyalayıp kendinize göre şekillendireceğiniz bir başlangıç noktası: ORM, DI container ve kuyruk (queue) yok.

* PHP 8.2+, MySQL 5.7+/MariaDB, Composer, `pdo_mysql`, `openssl`, `mbstring`, `curl`, `fileinfo` eklentileri
* Kutudan çıktığı gibi güvenli varsayılanlar, bkz. [SECURITY.tr.md](SECURITY.tr.md)
* Ayrı temalara sahip site ve admin alanları, ortak giriş/şifre sıfırlama akışları, izinler, denetim (audit) logu
* Modern, mobil uyumlu (açık ve koyu temalı) arayüz, [Heroicons](https://heroicons.com) ikonları, dış istek yok, CSP uyumlu
* `php pika` komut satırı ve bir test paketi (birim + HTTP entegrasyon)

## Kurulum
```bash
composer install
cp .env.example .env          # geliştirme için APP_ENV=local
php pika key:generate         # .env dosyasına rastgele bir PIKA_LICENSE yazar
# .env içindeki MYSQL_* değerlerini düzenleyin, (boş) veritabanını oluşturun, sonra:
php pika migrate
php pika make:admin           # ilk admin hesabı
php pika serve                # http://127.0.0.1:8000  (admin: /admin/login)
php pika doctor               # yapılandırmayı ve veritabanı bağlantısını denetler
```
Ya da hepsini sihirbaza yaptırın: `composer install && php pika install` (site adresini, veritabanını ve admin hesabını sorar,
`.env` dosyasını yazar, migration'ları çalıştırır).

## Terminalsiz kurulum
Composer veya `php pika` çalıştıramadığınız paylaşımlı hosting için:
1. Deponun **Releases** sayfasından `pika-vX.Y.Z.zip` dosyasını indirin (içinde `vendor/` hazır gelir), açın ve belge kökü (document root)
   `public/` olacak şekilde yükleyin (bkz. *Canlıya alma*).
2. Hosting panelinizden boş bir MySQL veritabanı ve kullanıcı oluşturun.
3. Dosya yöneticisinde, kendi uydurduğunuz en az 16 karakterlik bir parola içeren **`content/install.token`** dosyasını oluşturun.
4. `https://alan.adiniz/install.php` adresini açın, jetonu girin ve formu doldurun (site adresi, veritabanı, admin hesabı, isteğe bağlı SMTP).
5. Kurulum sunucuyu kontrol eder, migration'ları çalıştırır, admini oluşturur, `.env` dosyasını yazar (0600 izniyle, yeni rastgele
   `PIKA_LICENSE` ile) ve **kendini kilitler**: jeton dosyası silinir ve `install.php` bundan sonra `404` verir. İsterseniz
   `public/install.php` dosyasını da silebilirsiniz.

Projede durması neden güvenli: jeton dosyası yokken sayfa mevcut değildir (404), yani bir yabancı "önce kurup sahip olamaz";
yanlış jetonlar sınırlanır (15 dakikada 5 deneme); yalnızca HTTPS'te (ya da localhost'ta) çalışır; içinde kullanıcı olan bir veritabanını
reddeder ve var olan bir `.env` dosyasının üzerine asla yazmaz. Proje klasörü yazılabilir değilse `.env` metnini bir kez gösterir, elle
oluşturursunuz. nginx kullanıyorsanız kurulum süresince PHP'nin `install.php` dosyasını çalıştırmasına da izin vermelisiniz (`location = /install.php { ... }`).

## Canlıya alma
1. Web sunucusunun **belge kökünü (document root) `public/`** klasörüne yönlendirin. Proje kökünü yayınlamayın.
2. `composer install --no-dev --optimize-autoloader`
3. `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `SITE_URL=https://alan.adiniz`, güçlü bir `PIKA_LICENSE`, `MYSQL_*`, `MAIL_*`.
   Bunlardan biri güvensiz ya da eksikse üretim modu **başlamayı reddeder** (HTTP 500, ayrıntılar logda).
4. `php pika migrate`; `content/cache`, `content/logs` ve `public/uploads` klasörlerinin PHP tarafından yazılabildiğinden emin olun.
5. Ters proxy / CDN arkasındaysanız, istemci IP'lerinin ve HTTPS'in doğru algılanması için IP'lerini `TRUSTED_PROXIES` içine yazın.
6. `https://alan.adiniz/.env` ve `/vendor/autoload.php` adreslerinin **404** verdiğini kontrol edin.

**Apache / cPanel:** *Document Root*'u `.../public` olan bir alan adı ya da alt alan adı kullanın. Yönlendiremediğiniz bir ana alan adında
projeyi `public_html` dışına koyun ve `public_html` yerine `public/` klasörüne sembolik bağ verin. Kökteki `.htaccess` (her şeyi
`public/` içine yönlendirir) yalnızca yedek içindir, örneğin MAMP'ta `http://localhost:8888/pika/` adresinden çalıştırmak için.

**nginx:**
```nginx
root /var/www/pika/public;
location / { try_files $uri /index.php?$query_string; }
location ~ ^/(index|install)\.php$ { include fastcgi_params; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; fastcgi_pass unix:/run/php/php8.2-fpm.sock; }
location ^~ /uploads/ { location ~ \.php$ { return 403; } add_header Content-Security-Policy "default-src 'none'; sandbox"; }
location ~ /\. { deny all; }
```

## Yapılandırma (`.env`)
| Anahtar | Amaç |
|---|---|
| `APP_ENV` | `local` ya da `production` (varsayılan). Üretim modu yukarıdaki denetimleri zorunlu kılar. |
| `APP_DEBUG` | `true` hataları ve yığın izli bir hata ayıklama sayfasını gösterir. Üretimde asla. |
| `PIKA_LICENSE` | `SecureString`'in (AES-256-GCM) arkasındaki gizli anahtar. Üretimde 32+ karakter. Değiştirmek eski şifreli verileri okunamaz yapar. |
| `SITE_URL`, `ADMIN_URL` | Esas adres (üretimde zorunlu) ve admin URL öneki (varsayılan `admin`). |
| `MYSQL_HOST/PORT/SOCKET/DB/USER/PASS` | Veritabanı. `MYSQL_SOCKET` (örn. MAMP) host/port'u geçersiz kılar. |
| `TRUSTED_PROXIES` | `X-Forwarded-*` başlıklarına güvenilen proxy IP'leri (virgülle ayrılmış). |
| `SESSION_IDLE`, `SESSION_ABSOLUTE` | Saniye cinsinden oturum zaman aşımları (1800 / 28800). |
| `RATE_LIMIT`, `RATE_WINDOW` | `RateLimit` middleware'i: IP başına pencere başına istek sayısı (60 / 60 sn). |
| `CRON_ALERT_EMAIL` | Bir iş başarısız olmaya başladığında tek bir e-posta alacak adres (çalışan `MAIL_*` gerekir). Boşsa kapalıdır. |
| `CRON_TOKEN` | HTTP cron uç noktası `/api/cron/<job>` için sır (32+ karakter, `X-Cron-Token` başlığıyla gönderilir). Boşsa kapalıdır. |
| `MAIL_*` | Şifre sıfırlama için SMTP (`MAIL_DRIVER=log` yalnızca `APP_ENV=local` ile). |
| `CSP` | Varsayılan Content-Security-Policy'yi değiştirir (`{nonce}` yer tutucusu). |

Gerçek ortam değişkenleri `.env` dosyasının önüne geçer.

## Klasör yapısı
```
public/        web kökü: index.php, install.php (web kurulumu), assets/ (css, js), uploads/ (betik çalıştırma kapalı)
src/           Pika\ (PSR-4): DB, Query, Model, Controller, AdminController, Auth\BaseLogin, Validator, Pagination,
               SecureString, Upload, Mail, RateLimiter, Icons, Installer, Migrator, Console\Commands
app/           controllers/ (+ Admin/), middlewares/ (Admin, User, RateLimit), models/ (App\Models), jobs/ (App\Jobs)
routes/        *.php, hepsi otomatik yüklenir (web, admin, api)
includes/      loader, config (.env + açılış denetimleri), helpers.php, components/, error_pages/
content/       themes/<tema>/ + themes/shared/, cache/, logs/
database/      migrations/NNN_ad.sql (+ NNN_ad.down.sql)
tests/         Unit/ ve Integration/ (PHPUnit)
```

## Günlük kullanım
```php
// routes/web.php            GET rotaları HEAD'i de listelemeli
$router->add('GET|HEAD', 'posts', 'Posts@index', ['name' => 'POSTS']);

// app/controllers/Posts.php  (php pika make:controller Posts)
class Posts extends Controller {
    public function index(Request $request, Response $response) {
        $posts = DB::table('posts')->where('status', 1)->orderBy('id', 'desc')->limit(20)->get();
        return $response->setContent($this->view('posts', ['posts' => $posts]));   // content/themes/default/posts.blade.php
    }
}
```
* **Temalar:** bir controller'ın `$theme` özelliği `content/themes/<tema>` klasörünü seçer; görünümler bulunamazsa `content/themes/shared` içine bakılır.
  `Controller` `default`, `AdminController` `admin` temasını kullanır. Admin controller'ları `app/controllers/Admin/` içindedir (`'Admin.Ad@metot'`).
* **Kimlik doğrulama:** `is_logged_in()`, `currentUser()`, `is_admin_logged_in()`, `User` / `Admin` middleware'leri, `can('izin')` ve
  `$this->authorize('izin')` (adminler her şeyi yapabilir; diğerleri için `user_permissions` tablosunda satır gerekir),
  `audit_log('eylem', 'ayrıntı')`, `logout_everywhere($userId)`, `set_user_password($userId, $plain)`.
* **Formlar:** `{!! csrf_field() !!}`, `Validator::make($_POST, ['email' => 'required|email|unique:users,email'])`,
  `flash('status', '...')` / `flash_now()`, `old('alan')`.
* **Görünümler:** `{{ }}` çıktıyı kaçışlar. Satır içi `<style>`/`<script>` için `nonce="{{ csp_nonce() }}"` gerekir (aksi hâlde CSP satır içi kodu engeller);
  stilleri bunun yerine `public/assets/css/pika.css` dosyasına koyun. Yerleşimler: `shared/layouts/base` ve `auth` (giriş, şifremi unuttum, sıfırlama),
  `default/layouts/site`, `admin/layouts/admin`. İkonlar: `{!! icon('shield') !!}` (Heroicons, adlar için `src/Icons.php`).
* **Adlandırılmış rotalar:** bir rotaya ad verin (`['name' => 'LOGIN']`), sonra görünümlerde `{{ route('LOGIN') }}`, `{{ route('RESET', ['any' => $token]) }}`
  ya da `@route('LOGIN')`, PHP'de `route('LOGIN')` kullanın. Değerler URL kodlanır ve sonuç HTML kaçışlanır; bilinmeyen bir ad ya da eksik
  parametre `null` verir (görünümde boş) ve loga uyarı yazılır. `route()` `includes/helpers.php` içindedir ve yönlendiriciye ihtiyaç duyar, bu yüzden
  bir istek işlenirken çağırın (yönlendirici oluşturulmadan önce çalışan koddan çağırmayın).
* **Modeller/sorgular:** `DB::table()` oluşturucusu (yalnızca bağlanmış değerler), `Pika\Model` alt sınıfları (`$fillable` toplu atamayı sınırlar),
  ham `DB::get/getRow/getVar/exec/insert`, `DB::transaction(fn)`.
* **Yüklemeler:** `Upload::save($_FILES['f'], ['dir' => 'avatars', 'max_size' => 1_000_000])`.
* **API:** rotaları `['before' => RateLimit::class]` ile gruplayın; jetonla doğrulanan uç noktalar CSRF'den muaf olmak için
  `includes/components/post_security.php` içinde listelenmelidir.

## Cron işleri
Bir iş, `app/jobs/` içindeki bir sınıftır: `php pika make:job reports/daily` komutu `app/jobs/Reports/Daily.php` dosyasını oluşturur
(`mail-send` adı `MailSend` olur). İşi `handle()` içine yazın; hata bildirmek için istisna fırlatın. Bitmeyen tek bir çalışma yerine
her seferinde sınırlı bir parça işleyin (örneğin 500 kayıt).

**Cron'dan (tercih edilen).** Her iş için bir cron satırı ekleyin. Cron her satırı ayrı süreç olarak başlatır; işler paralel çalışır ve
yavaş bir iş diğerlerini bekletmez. Komut satırında zaman sınırı yoktur, uzun işler için doğru yol budur. cPanel'de (Cron Jobs):
```bash
# proje public_html/pika içine yüklendiyse (public_html dışındaysa /home/user/pika/pika olurdu)
* * * * * /usr/local/bin/php -q /home/user/public_html/pika/pika job:run reports/daily
*/5 * * * * /usr/local/bin/php -q /home/user/public_html/pika/pika job:run mail-send
```
* `.../pika/pika`, proje klasöründeki `pika` dosyasıdır (önce klasör adı, sonra dosya); çalıştırma izni gerekmez.
* PHP 8.2+ kullanın. `php pika doctor` komut satırındaki PHP'nin yolunu ve sürümünü, proje klasörünü ve işleri son çalışma zamanlarıyla
  birlikte yazar. Hosting'lerde sık sık birden çok PHP sürümü bulunur (cPanel: `/opt/cpanel/ea-php82/root/usr/bin/php`).
* Yönetim alanında **İşler** sayfası (`/admin/jobs/`) her işin son çalışmasını, sonucunu, süresini ve üst üste hata sayısını gösterir. Aynı bilgi
  `php pika doctor` içinde de vardır. `CRON_ALERT_EMAIL` ayarlıysa, bir iş başarısız olmaya başladığında dakikada bir değil, tek bir e-posta alırsınız.
* Bir iş kendi üzerine binmez: önceki çalışma sürüyorsa yenisi atlanır (çıkış kodu 0, bir şey bozulmaz). Hatalar 1 koduyla çıkar ve
  `content/logs` içine yazılır.

**HTTP üzerinden** (CLI cron olmayan hosting). `CRON_TOKEN` değerini 32+ karakterlik rastgele bir sırra ayarlayın (`openssl rand -hex 32`), sonra:
```bash
* * * * * wget -q -O /dev/null --header="X-Cron-Token: YOUR_TOKEN" https://your.domain/api/cron/reports/daily
```
Jeton başlıkta gönderilmelidir; URL'deki jeton, URL'ler sunucu loglarına düştüğü için yok sayılır. Eksik ya da yanlış jeton, bilinmeyen bir iş
veya boş `CRON_TOKEN` hep `404` döndürür. İstek PHP ve web sunucusu zaman sınırlarına ve IP başına `RateLimit` bütçesine (`RATE_LIMIT`) tabidir;
bu yüzden buraya kısa işleri koyun, tek sunucu çok sayıda işi çağırıyorsa sınırı yükseltin.

## Komut satırı
`php pika` her şeyi listeler: `install`, `serve`, `migrate`, `migrate:rollback`, `make:migration`, `make:controller [--admin]`, `make:admin`,
`make:job <name>`, `job:run <name>`, `key:generate`, `log:clear [gün]`, `scan:secrets`, `doctor`.

## Testler
```bash
vendor/bin/phpunit                       # birim + entegrasyon
vendor/bin/phpunit --testsuite unit      # veritabanı gerekmez
```
Entegrasyon testleri gerçek uygulamayı PHP'nin yerleşik sunucusuyla `pika_framework_test` veritabanına karşı başlatır
(otomatik oluşturulur ve migrate edilir; adı `_test` ile bitmeyen hiçbir veritabanına asla dokunulmaz). Erişilebilir bir MySQL
yoksa atlanırlar. CI iki paketi de PHP 8.2-8.4 üzerinde bir MySQL servisiyle çalıştırır.

## Lisans
MIT, bkz. [LICENSE](LICENSE). İkonlar: Heroicons (MIT, Tailwind Labs), bkz. [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).
