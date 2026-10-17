# Helpers and Utilities

[Home](Home) | **English** | [Türkçe](Helpers-and-Utilities-TR)

## Validation
```php
$v = Pika\Validator::make($_POST, [
    'email'    => 'required|email|max:250|unique:users,email',
    'password' => 'required|min:10|confirmed',      // needs password_confirmation
    'role'     => 'in:admin,user',
], ['email' => 'E-mail address']);
if ($v->fails()) { flash_now('error', $v->first('email') ?? 'Please check the form'); }
$data = $v->validated();   // only the fields that have rules
```
Rules: `required`, `min:n`, `max:n`, `email`, `numeric`, `in:a,b`, `confirmed`, `unique:table,column[,ignoreId]`. `$v->errors()` returns every message per field.

## Pagination
```php
$p = new Pika\Pagination($total, 20);
$rows = DB::get("SELECT * FROM posts LIMIT {$p->limit} OFFSET {$p->offset}");
echo $p->render();   // Bootstrap-style page links, keeps other query parameters
```

## Uploads
```php
$saved = Pika\Upload::save($_FILES['avatar'], ['dir' => 'avatars', 'max_size' => 1_000_000]);
// ['path' => 'avatars/ab12….png', 'name' => '…', 'size' => 1234, 'mime' => 'image/png']
```
Allowed by default: jpg, jpeg, png, gif, webp, pdf (SVG is excluded). The extension, the real MIME type and the image contents are checked, the file gets a random name, and `public/uploads` cannot run scripts. A problem throws `Pika\UploadException` with a message you can show.

## Mail
```php
Pika\Mail::send('you@example.com', 'Subject', "Plain text body");   // true or false
```
Driver and SMTP settings come from `MAIL_*`. In `APP_ENV=local` set `MAIL_DRIVER=log` to write mails to `content/logs`.

## Encryption
```php
$secret = Pika\SecureString::encode('text');     // AES-256-GCM, key derived from PIKA_LICENSE
$plain  = Pika\SecureString::decode($secret);    // false if tampered with or the key changed
```

## Rate limiting
```php
$r = Pika\RateLimiter::hit('export:' . getIP(), 10, 3600);   // 10 per hour
if (!$r['allowed']) { /* $r['retry_after'] seconds */ }
```
Or put `['before' => RateLimit::class]` on a route group.

## Other helpers
`esc()`, `json_output($data, $status)`, `abort(404)`, `log_message($level, $text)`, `getIP()`, `currentSiteUrl()`, `uuidv4()`, `hash_generator($length)`, `generateSeoURL($text)`, `cURL([...])` (TLS verification on), `after_response(callable)` (run slow work after the response on PHP-FPM and LiteSpeed). Logs are in `content/logs/pika-YYYY-MM-DD.log`; `php pika log:clear` removes old ones.
