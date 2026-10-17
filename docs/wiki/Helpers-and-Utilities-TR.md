# Yardımcılar

[Ana Sayfa](Home-TR) | [English](Helpers-and-Utilities) | **Türkçe**

## Doğrulama
```php
$v = Pika\Validator::make($_POST, [
    'email'    => 'required|email|max:250|unique:users,email',
    'password' => 'required|min:10|confirmed',      // password_confirmation gerekir
    'role'     => 'in:admin,user',
], ['email' => 'E-mail address']);
if ($v->fails()) { flash_now('error', $v->first('email') ?? 'Please check the form'); }
$data = $v->validated();   // yalnızca kuralı olan alanlar
```
Kurallar: `required`, `min:n`, `max:n`, `email`, `numeric`, `in:a,b`, `confirmed`, `unique:tablo,sütun[,ignoreId]`. `$v->errors()` her alan için tüm mesajları döndürür.

## Sayfalama
```php
$p = new Pika\Pagination($total, 20);
$rows = DB::get("SELECT * FROM posts LIMIT {$p->limit} OFFSET {$p->offset}");
echo $p->render();   // Bootstrap tarzı sayfa bağlantıları, diğer sorgu parametrelerini korur
```

## Yüklemeler
```php
$saved = Pika\Upload::save($_FILES['avatar'], ['dir' => 'avatars', 'max_size' => 1_000_000]);
// ['path' => 'avatars/ab12….png', 'name' => '…', 'size' => 1234, 'mime' => 'image/png']
```
Varsayılan izinli türler: jpg, jpeg, png, gif, webp, pdf (SVG hariç). Uzantı, gerçek MIME türü ve görüntü içeriği kontrol edilir, dosya rastgele bir ad alır ve `public/uploads` betik çalıştıramaz. Bir sorun, gösterebileceğiniz bir mesajla `Pika\UploadException` fırlatır.

## E-posta
```php
Pika\Mail::send('you@example.com', 'Subject', "Plain text body");   // true ya da false
```
Sürücü ve SMTP ayarları `MAIL_*` içinden gelir. `APP_ENV=local` içinde e-postaları `content/logs` içine yazmak için `MAIL_DRIVER=log` ayarlayın.

## Şifreleme
```php
$secret = Pika\SecureString::encode('text');     // AES-256-GCM, anahtar PIKA_LICENSE'tan türetilir
$plain  = Pika\SecureString::decode($secret);    // kurcalanmışsa ya da anahtar değiştiyse false
```

## Hız sınırlama
```php
$r = Pika\RateLimiter::hit('export:' . getIP(), 10, 3600);   // saatte 10
if (!$r['allowed']) { /* $r['retry_after'] saniye */ }
```
Ya da bir rota grubuna `['before' => RateLimit::class]` ekleyin.

## Diğer yardımcılar
`esc()`, `json_output($data, $status)`, `abort(404)`, `log_message($level, $text)`, `getIP()`, `currentSiteUrl()`, `uuidv4()`, `hash_generator($length)`, `generateSeoURL($text)`, `cURL([...])` (TLS doğrulaması açık), `after_response(callable)` (yavaş işi PHP-FPM ve LiteSpeed'de yanıttan sonra çalıştırır). Loglar `content/logs/pika-YYYY-MM-DD.log` içindedir; `php pika log:clear` eskileri siler.
