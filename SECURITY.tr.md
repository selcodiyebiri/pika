# Güvenlik politikası

[English](SECURITY.md) | **Türkçe**

## Güvenlik açığı bildirme
Güvenlik sorunları için lütfen herkese açık bir issue **açmayın**. Bu depoda GitHub'ın
[özel güvenlik açığı bildirimini](../../security/advisories/new) kullanın. Etkilenen sürümü/commit'i, tekrarlama adımlarını ve
etkisini yazın. Birkaç gün içinde yanıt bekleyebilirsiniz; bu küçük bir kişisel proje olduğu için resmi bir yanıt süresi (SLA) yoktur.

Yalnızca varsayılan daldaki en son commit desteklenir.

## Pika sizin için neler yapar
- **Oturumlar:** `HttpOnly`, `SameSite=Lax`, HTTPS'te `Secure`, katı mod, girişte/çıkışta oturum kimliği yenilenir, boşta kalma ve mutlak
  zaman aşımları, önbelleğe alınmayan yanıtlar, "her yerden çıkış", parola değişince oturumlar sona erer.
- **CSRF:** her POST/PUT/PATCH/DELETE istek için oturum jetonu gerekir (form alanı ya da `X-CSRF-Token`), sabit zamanlı karşılaştırılır.
- **Parolalar:** `password_hash`, girişte yeniden hash, 10+ karakter kuralı, IP ve kullanıcı adı başına sınırlama, tek kullanımlık hash'li
  jetonlarla parola sıfırlama, sıfırlama formunda hesap varlığını sızdırmama.
- **Başlıklar:** istek başına nonce'lu sıkı CSP, `X-Frame-Options`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, HTTPS'te HSTS.
- **Veritabanı:** gerçek hazırlanmış sorgularla ve istisnalarla PDO; sorgu oluşturucu her değeri bağlar ve tanımlayıcıları doğrular.
- **Yüklemeler:** uzantı beyaz listesi, gerçek MIME kontrolü, görüntü çözme kontrolü, rastgele adlar, `public/uploads` içinde betik çalıştırma kapalı.
- **Güvenli başarısızlık:** `APP_ENV=production`, debug açıkken, zayıf bir `PIKA_LICENSE` ile ya da HTTPS olmayan bir `SITE_URL` ile başlamayı reddeder.
- **Cron:** HTTP cron uç noktası, `CRON_TOKEN` (32+ karakter) `X-Cron-Token` başlığıyla gönderilmedikçe 404 verir (sabit zamanlı karşılaştırma, URL'den asla okunmaz); işler `app/jobs/` altında sade sınıflardır ve üst üste binmez.
- **Araçlar:** `php pika scan:secrets`, `php pika doctor`, CI'da `composer audit`.

## Web kurulumu (`public/install.php`)
Terminali olmayan hosting için tasarlandı; `content/install.token` dosyasını oluşturmadıkça sayfa mevcut değildir.
- **Kapı:** jeton dosyasını oluşturmak dosya erişimi gerektirir, bu yüzden bir ziyaretçi "önce kurup sahip olamaz". Jeton sabit zamanlı
  karşılaştırılır; 5 yanlış jeton kapıyı 15 dakika kilitler (doğru jeton dahil, herkes için).
- **Kilit:** `.env` ya da `content/install.lock` varsa sayfa 404 verir ve jetonu yeniden oluşturmakla yeniden açılamaz.
- **Taşıma:** localhost dışında düz HTTP'yi reddeder; CSRF korumalı; sıkı CSP; `no-store`, `noindex`.
- **Güvenli varsayılanlar:** `.env` dosyasının üzerine asla yazmaz, 0600 izniyle yazar, içinde kullanıcı olan bir veritabanını reddeder, aynı anda tek kurulum.
- **İhtiyacınız yoksa kaldırın:** kurulumdan sonra `public/install.php` dosyasını silin (zaten etkisizdir).
- **Sınırlar:** jeton düz bir dosyada durur (diğer sunucu dosyaları gibi koruyun, kurulumdan sonra silinir); bir yabancı yanlış tahminlerle
  15 dakikalık kilidi tetikleyebilir ama kurulum yapamaz. Sunucuda zaten dosya yazabilen biri her şeyi yapabilir.

## Sizin yapmanız gerekenler
- Web sunucusunun **belge kökünü (document root) `public/`** klasörüne yönlendirin. Proje kökünü asla yayınlamayın.
- `.env` dosyasını sürüm kontrolünün ve web kökünün dışında tutun; `php pika key:generate` komutunu bir kez çalıştırın.
- `SITE_URL` değerini HTTPS adresinize ayarlayın, yalnızca HTTPS üzerinden yayın yapın ve ters proxy'leri `TRUSTED_PROXIES` içine yazın.
- Bağımlılıkları güncel tutun (`composer update`, `composer audit`).
- CSRF'den muaf yol eklemeden önce (webhook'lar, jetonla doğrulanan API'ler) `includes/components/post_security.php` dosyasını gözden geçirin.

## Bilinen sınırlar
- İstek sınırlama IP başınadır (`RateLimit` middleware'i) ve giriş/sıfırlama içindir; gerçek DoS koruması için önüne bir WAF ya da ters proxy sınırı koyun.
- Oturumlar PHP'nin varsayılan dosya depolamasını kullanır; paylaşımlı hostingte `session.save_path` yolunun hesabınıza özel olduğundan emin olun.
- Parola sıfırlama çalışan bir SMTP gerektirir (`MAIL_*`); `log` e-posta sürücüsü `APP_ENV=local` dışında reddedilir.
- Parola sıfırlama isteğinde, bilinen ve bilinmeyen adresler arasında küçük bir zamanlama farkı vardır (e-posta gönderimi).
