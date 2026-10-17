# Yapılandırma

[Ana Sayfa](Home-TR) | [English](Configuration) | **Türkçe**

Ayarlar `.env` içinde durur (`.env.example` dosyasını kopyalayın). Gerçek ortam değişkenleri `.env` değerini ezer. Canlı ortam (`APP_ENV=production`) güvensiz bir yapılandırmayla **başlamayı reddeder**; nedenler sunucu loguna yazılır.

## Ayarlar
| Anahtar | Amaç |
|---|---|
| `APP_ENV` | `local` ya da `production` (varsayılan). Canlı ortam güvenlik kontrollerini zorunlu kılar. |
| `APP_DEBUG` | `true` hataları ve bir hata ayıklama sayfası gösterir. Canlıda asla. |
| `PIKA_LICENSE` | `SecureString`'in (AES-256-GCM) arkasındaki sır. Canlıda 32+ karakter. Değiştirmek eski şifreli veriyi okunamaz yapar. |
| `SITE_URL`, `ADMIN_URL` | Kanonik adres (canlıda zorunlu, https) ve yönetim URL öneki (varsayılan `admin`). |
| `MYSQL_HOST/PORT/SOCKET/DB/USER/PASS` | Veritabanı. `MYSQL_SOCKET` (örneğin MAMP) host ve port yerine geçer. |
| `TRUSTED_PROXIES` | `X-Forwarded-*` başlıklarına güvenilen proxy ve CDN adreslerinin virgülle ayrılmış listesi. Her adımı yazın. |
| `SESSION_IDLE`, `SESSION_ABSOLUTE` | Saniye cinsinden oturum zaman aşımları (1800 / 28800). |
| `RATE_LIMIT`, `RATE_WINDOW` | `RateLimit` middleware'i: IP başına pencere başına istek (60 / 60 sn). |
| `MAIL_*` | Parola sıfırlama için SMTP. `MAIL_DRIVER=log` e-postaları loga yazar ve yalnızca `APP_ENV=local` ile izinlidir. |
| `CRON_TOKEN` | HTTP cron uç noktası için sır (32+ karakter). Boşsa kapalıdır. |
| `CRON_ALERT_EMAIL` | Bir iş başarısız olmaya başlayınca tek bir e-posta. Boşsa kapalıdır. |
| `CSP` | Varsayılan Content-Security-Policy'yi değiştirir (`{nonce}` her istekte değiştirilir). |

## Bilmekte fayda var
* `php pika key:generate`, `PIKA_LICENSE` değerini bir kez yazar; `--force` vermedikçe var olan anahtarı değiştirmeyi reddeder.
* Proxy ya da CDN arkasında `TRUSTED_PROXIES` ayarlayın, yoksa sınırlar ve loglar proxy'nin adresini görür ve `https` algılanmaz.
* `.env` içinde `#` ya da boşluk içeren değerleri tırnak içine alın.
