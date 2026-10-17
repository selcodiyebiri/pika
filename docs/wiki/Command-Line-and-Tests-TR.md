# Komut Satırı ve Testler

[Ana Sayfa](Home-TR) | [English](Command-Line-and-Tests) | **Türkçe**

## `php pika`
| Komut | Ne yapar |
|---|---|
| `install` | ilk çalıştırma sihirbazı: `.env`, veritabanı, migration'lar, yönetici hesabı |
| `serve [port]` | geliştirme sunucusu (varsayılan port 8000) |
| `migrate` / `migrate:rollback` | bekleyen migration'ları çalıştırır / son grubu geri alır |
| `make:migration <name>` | `NNN_ad.sql` ve `.down.sql` oluşturur |
| `make:controller <Name> [--admin]` | bir controller oluşturur |
| `make:job <name>` | `app/jobs/` içinde bir iş oluşturur |
| `job:run <name>` | bir işi şimdi çalıştırır |
| `make:admin [user] [email]` | bir yönetici oluşturur (parola sorusu ya da `PIKA_ADMIN_PASSWORD`) |
| `key:generate [--force]` | `.env` içine rastgele bir `PIKA_LICENSE` yazar |
| `log:clear [days]` | eski logları ve süresi dolmuş satırları siler (varsayılan 14 gün) |
| `scan:secrets` | commit edilmiş jeton, anahtar ve parolaları arar |
| `doctor` | yapılandırmayı, veritabanını, PHP'yi, işleri kontrol eder |

`scan:secrets`, `pika-secret-scan: ignore` içeren bir satırı atlar (örnekler ve test verisi için).

## Testler
```bash
vendor/bin/phpunit                       # birim + entegrasyon
vendor/bin/phpunit --testsuite unit      # veritabanı gerekmez
```
Entegrasyon testleri gerçek uygulamayı PHP'nin yerleşik sunucusuyla `pika_framework_test` veritabanına karşı başlatır (otomatik oluşturulur ve migrate edilir; adı `_test` ile bitmeyen bir veritabanına asla dokunulmaz). Erişilebilir bir MySQL yoksa atlanırlar. CI, iki seti de PHP 8.2, 8.3 ve 8.4'te bir MySQL servisiyle çalıştırır; ayrıca `composer audit` ve gizli anahtar taraması yapar.

## Bir düzeltme katkısı
Önce başarısız olan bir test yazın, geçmesini sağlayın, tüm seti ve `php pika scan:secrets` komutunu çalıştırın.
