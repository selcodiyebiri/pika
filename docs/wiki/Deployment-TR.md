# Yayına Alma

[Ana Sayfa](Home-TR) | [English](Deployment) | **Türkçe**

## Canlı kontrol listesi
1. Web sunucusunun **belge kökünü `public/` klasörüne** yönlendirin. Proje kökünü yayınlamayın.
2. `composer install --no-dev --optimize-autoloader` (sürüm ZIP'iyle gerekmez).
3. `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `SITE_URL=https://your.domain`, güçlü bir `PIKA_LICENSE`, `MYSQL_*`, `MAIL_*`. Bunlardan biri güvensiz ya da eksikse canlı ortam başlamayı reddeder.
4. `php pika migrate`; `content/cache`, `content/logs` ve `public/uploads` klasörlerinin PHP tarafından yazılabilir olduğundan emin olun.
5. Proxy ya da CDN arkasında her adımı `TRUSTED_PROXIES` içine yazın.
6. `https://your.domain/.env` ve `/vendor/autoload.php` adreslerinin **404** döndürdüğünü kontrol edin, sonra `php pika doctor` çalıştırın.

## cPanel
*Document Root* değeri `.../public` olan bir alan adı ya da alt alan adı kullanın. Ana alan adının belge kökünü değiştiremiyorsanız projeyi `public_html` dışında tutun ve `public_html` klasörünü `public/` klasörüne sembolik bağlantı yapın. Kök `.htaccess` (her şeyi `public/` içine yönlendirir) yalnızca bir yedektir, örneğin `http://localhost:8888/pika/` adresini sunan MAMP için.

## nginx
```nginx
root /var/www/pika/public;
location / { try_files $uri /index.php?$query_string; }
location ~ ^/(index|install)\.php$ { include fastcgi_params; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; fastcgi_pass unix:/run/php/php8.2-fpm.sock; }
location ^~ /uploads/ { location ~ \.php$ { return 403; } add_header Content-Security-Policy "default-src 'none'; sandbox"; }
location ~ /\. { deny all; }
```

## Sürüm ZIP'i ve web kurulumu (terminalsiz)
1. Deponun Releases bölümünden `pika-vX.Y.Z.zip` dosyasını indirin (içinde `vendor/` hazırdır), açın ve belge kökü `public/` olacak şekilde yükleyin.
2. Hosting panelinizde boş bir MySQL veritabanı ve kullanıcı oluşturun.
3. Dosya yöneticisinde, kendi uydurduğunuz 16+ karakterlik bir sır içeren `content/install.token` dosyasını oluşturun.
4. `https://your.domain/install.php` adresini açın, jetonu girin ve formu doldurun (site adresi, veritabanı, yönetici hesabı, isteğe bağlı SMTP).
5. Kurulum sunucuyu kontrol eder, migration'ları çalıştırır, yöneticiyi oluşturur, `.env` dosyasını yazar (0600 izni, yeni `PIKA_LICENSE`) ve kendini kilitler: jeton dosyası silinir ve `install.php` `404` döndürür. `public/install.php` dosyasını silebilirsiniz.

Jeton dosyası olmadan sayfa yoktur, yanlış jetonlar sınırlanır, yalnızca HTTPS üzerinden (ya da localhost'ta) çalışır ve içinde kullanıcı olan bir veritabanını reddeder. nginx kullanıcıları kurulum süresince `install.php` dosyasına izin vermelidir.

## Güncelleme
Yeni dosyaları yükleyin (`.env` ve `content/` klasörünü koruyun), `php pika migrate`, sonra `php pika doctor` çalıştırın. Önce [değişiklik günlüğünü](https://github.com/selcodiyebiri/pika/blob/main/CHANGELOG.md) okuyun.
