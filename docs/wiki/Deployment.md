# Deployment

[Home](Home) | **English** | [Türkçe](Deployment-TR)

## Production checklist
1. Point the web server's **document root at `public/`**. Do not serve the project root.
2. `composer install --no-dev --optimize-autoloader` (not needed with the release ZIP).
3. `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `SITE_URL=https://your.domain`, a strong `PIKA_LICENSE`, `MYSQL_*`, `MAIL_*`. Production refuses to start if any of that is unsafe or missing.
4. `php pika migrate`; make sure `content/cache`, `content/logs` and `public/uploads` are writable by PHP.
5. Behind a proxy or CDN, list every hop in `TRUSTED_PROXIES`.
6. Check that `https://your.domain/.env` and `/vendor/autoload.php` return **404**, then run `php pika doctor`.

## cPanel
Use a domain or sub-domain whose *Document Root* is `.../public`. If you cannot change the document root of a main domain, keep the project outside `public_html` and symlink `public_html` to `public/`. The root `.htaccess` (it rewrites everything into `public/`) is only a fallback, for example for MAMP serving `http://localhost:8888/pika/`.

## nginx
```nginx
root /var/www/pika/public;
location / { try_files $uri /index.php?$query_string; }
location ~ ^/(index|install)\.php$ { include fastcgi_params; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; fastcgi_pass unix:/run/php/php8.2-fpm.sock; }
location ^~ /uploads/ { location ~ \.php$ { return 403; } add_header Content-Security-Policy "default-src 'none'; sandbox"; }
location ~ /\. { deny all; }
```

## Release ZIP and web installer (no terminal)
1. Download `pika-vX.Y.Z.zip` from the repository's Releases (it already contains `vendor/`), unzip it and upload it with the document root on `public/`.
2. Create an empty MySQL database and user in your hosting panel.
3. In the file manager create `content/install.token` containing a secret of 16+ characters you make up.
4. Open `https://your.domain/install.php`, enter the token and fill in the form (site address, database, admin account, optional SMTP).
5. The installer checks the server, runs the migrations, creates the admin, writes `.env` (mode 0600, fresh `PIKA_LICENSE`) and locks itself: the token file is deleted and `install.php` answers `404`. You may delete `public/install.php`.

Without the token file the page does not exist, wrong tokens are throttled, it runs only over HTTPS (or on localhost) and refuses a database that already has users. nginx users must allow `install.php` for the duration of the install.

## Updating
Upload the new files (keep `.env` and `content/`), run `php pika migrate`, then `php pika doctor`. Read the [changelog](https://github.com/selcodiyebiri/pika/blob/main/CHANGELOG.md) first.
