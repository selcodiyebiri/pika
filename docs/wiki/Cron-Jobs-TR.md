# Cron İşleri

[Ana Sayfa](Home-TR) | [English](Cron-Jobs) | **Türkçe**

**Cron işi**, bir zamanlamaya göre kendi kendine çalışan bir görevdir: günlük rapor göndermek, eski satırları silmek, her 5 dakikada veri çekmek. Pika'da bir iş, `app/jobs/` içindeki küçük bir PHP sınıfıdır. Aynı sınıfı sunucunun cron'u (komut satırı, uzun işler için en iyisi) ya da bir URL (komut satırı cron'u olmayan hosting için) başlatabilir. Bu sayfadaki dosyalar projenin test takımı tarafından çalıştırılır.

## 1. Bir iş oluşturun
```bash
php pika make:job reports/daily
```
Bu komut `app/jobs/Reports/Daily.php` dosyasını oluşturur. Adlar küçük harf, rakam, `-` ve `_` içerir; `/` alt klasör yapar; `mail-send` adı `MailSend` sınıfı, `reports/daily` adı `App\Jobs\Reports\Daily` olur. İşi `handle()` içine yazın. Bir şey ters giderse istisna fırlatın: çalışma başarısız olarak kaydedilir.

**Dosya:** `app/jobs/Reports/Daily.php`
```php
<?php
namespace App\Jobs\Reports;

use Pika\DB;
use Pika\Job;
use Pika\Mail;

class Daily extends Job
{
    public function handle(): void
    {
        $users = (int) DB::getVar("SELECT COUNT(*) FROM users");

        if (!Mail::send('you@example.com', 'Daily report', "Users: $users")) {
            throw new \RuntimeException('The report could not be mailed');
        }
    }
}
```
Bir iş tarayıcı olmadan çalışır: giriş yapmış kullanıcı ve `$_POST` yoktur. Geri kalan her şeyi kullanabilirsiniz (`DB`, `Mail`, modeller, `log_message()`, `get_option()`).

## 2. Büyük bir yığını küçük adımlarla işleyen iş
Çok uzun süren bir çalışma hosting tarafından kesilebilir. Her çalışmada sınırlı bir parça işleyin ve nerede kaldığınızı hatırlayın; sonraki çalışma oradan devam eder. Bu örnek denetim kaydını 500'er satır okur ve konumunu `options` tablosunda tutar.

**Dosya:** `app/jobs/Reports/Archive.php`
```php
<?php
namespace App\Jobs\Reports;

use Pika\DB;
use Pika\Job;

class Archive extends Job
{
    private const BATCH = 500;

    public function handle(): void
    {
        $lastId = (int) get_option('archive_last_id');   // 0 on the very first run
        $rows = DB::table('user_logs')->where('id', '>', $lastId)->orderBy('id')->limit(self::BATCH)->get();

        foreach ($rows as $row) {
            // process one row here: copy it, send it somewhere, calculate with it
            $lastId = (int) $row->id;
        }

        update_option('archive_last_id', (string) $lastId);   // the next run continues here
        log_message('info', 'archive: ' . count($rows) . " rows, now at id $lastId");
    }
}
```

## 3. Elle deneyin
```bash
php pika job:run reports/daily
```
`reports/daily: ok (0.01s)` yazar. `failed` hatayı gösterir; `skipped`, önceki çalışmanın sürdüğü anlamına gelir. `php pika doctor` işlerinizi ve her birinin son çalışma zamanını listeler. Sonuçlar ayrıca `content/logs` içine yazılır.

## 4. Cron ile zamanlayın
Bir zamanlamanın beş alanı vardır: **dakika, saat, ayın günü, ay, haftanın günü**. `*` "her" demektir, `*/5` "her 5" demektir.

| Zamanlama | Anlamı |
|---|---|
| `* * * * *` | her dakika |
| `*/5 * * * *` | her 5 dakikada |
| `0 * * * *` | her saat başı |
| `30 3 * * *` | her gün 03:30'da |
| `0 9 * * 1` | her Pazartesi 09:00'da |
| `0 0 1 * *` | her ayın 1'inde gece yarısı |

### Komut satırında (tercih edilen)
İş başına bir cron satırı. Cron her satırı ayrı süreç olarak başlatır; işler paralel çalışır ve yavaş bir iş diğerlerini bekletmez. Komut satırında zaman sınırı yoktur. cPanel'de **Cron Jobs** bölümünü açın, zamanlamayı seçin ve komutu girin:
```bash
30 3 * * * /usr/local/bin/php -q /home/user/public_html/pika/pika job:run reports/daily
*/5 * * * * /usr/local/bin/php -q /home/user/public_html/pika/pika job:run reports/archive
```
* `/usr/local/bin/php` PHP programıdır, `/home/user/public_html/pika/pika` proje klasörünüzdeki `pika` dosyasıdır (önce klasör adı, sonra dosya; çalıştırma izni gerekmez), `job:run reports/daily` işin kendisidir.
* PHP 8.2+ bir program kullanın. Hosting'lerde çoğu zaman birkaç tane bulunur; cPanel'inkiler `/opt/cpanel/ea-php82/root/usr/bin/php` gibi yollardadır. `php pika doctor`, çalıştığı PHP'nin yolunu ve sürümünü, proje klasörünü ve işleri yazar; böylece doğru değerleri kopyalayabilirsiniz.
* Hataları görmek için satırın sonuna `>> /home/user/cron.log 2>&1` ekleyin.
* Bir iş kendi üzerine binmez: önceki çalışma sürüyorsa yenisi atlanır (çıkış kodu 0). Hatalar 1 koduyla çıkar.

### HTTP üzerinden (komut satırı cron'u yoksa)
`.env` içinde `CRON_TOKEN` değerini 32+ karakterlik rastgele bir sırra ayarlayın (`openssl rand -hex 32`), sonra işin URL'sini jetonu bir başlıkta göndererek çağırın:
```bash
30 3 * * * wget -q -O /dev/null --header="X-Cron-Token: YOUR_TOKEN" https://your.domain/api/cron/reports/daily
```
Önce bir terminalden deneyin:
```bash
curl -H "X-Cron-Token: YOUR_TOKEN" http://127.0.0.1:8000/api/cron/reports/daily
```
Yanıt `{"status":"ok","seconds":0.01}` gibi bir JSON'dur. Eksik ya da yanlış jeton, bilinmeyen bir iş ve boş `CRON_TOKEN` bilerek `404` verir. URL'deki jeton (`?token=`) yok sayılır, çünkü URL'ler sunucu loglarına düşer. PHP ve web sunucusu zaman sınırları ile IP başına `RateLimit` (`RATE_LIMIT`) geçerlidir; bu yüzden buraya kısa işleri koyun.

## 5. İşlerinizi izleyin
* Yönetim alanında bir **İşler** sayfası vardır (`/admin/jobs/`): son çalışma, sonuç, süre ve üst üste hata sayısı.
* Bir iş başarısız olmaya başladığında tek bir e-posta almak için `.env` içinde `CRON_ALERT_EMAIL` ayarlayın (dakikada bir değil). Çalışan `MAIL_*` ayarları gerekir.

## 6. Çalışmadığında
| Belirti | Kontrol |
|---|---|
| Hiçbir şey olmuyor | Komutun aynısını bir terminalde çalıştırın. `>> /home/user/cron.log 2>&1` ekleyin ve dosyayı okuyun. |
| "Could not open input file" | `pika` dosyasının yolu yanlış (`public_html/pika/pika` mi?). |
| Bir PHP sürümü hatası | Cron eski bir PHP kullanıyor. PHP 8.2+ programın tam yolunu kullanın. |
| `Unknown job` | Ad, `app/jobs/` içindeki bir dosyayla eşleşmiyor (`reports/daily`, `Reports/Daily.php` demektir). |
| Hep `skipped` | Önceki bir çalışma takılıyor ya da aralıktan uzun sürüyor. `content/logs` içine bakın. |
| HTTP `404` veriyor | `CRON_TOKEN` boş ya da 32 karakterden kısa, başlık eksik ya da ad yanlış. |
| Yanlış saat | Zamanlamalar sunucunun saatini kullanır; uygulamanın saat dilimi `APP_TIMEZONE`'dur. |
