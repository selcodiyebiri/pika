# Kimlik Doğrulama ve Güvenlik

[Ana Sayfa](Home-TR) | [English](Authentication-and-Security) | **Türkçe**

Korumaların ve bilinen sınırların tam listesi [güvenlik politikasında](https://github.com/selcodiyebiri/pika/blob/main/SECURITY.tr.md) vardır. Bu sayfa onların nasıl kullanılacağını gösterir.

## Kullanıcılar ve giriş
Site girişi `app/controllers/Login.php`, yönetim girişi `app/controllers/Admin/Login.php` dosyasındadır; ikisi de sınırlamayı, parola kontrolünü, yeniden hash'lemeyi, loglamayı ve oturumu yapan `Pika\Auth\BaseLogin` sınıfından türer. Yeni bir giriş alanı yalnızca kimin girebileceğini ve nereye gideceğini söyler (bu iki sınıfa bakın).

Yardımcılar: `is_logged_in()`, `currentUser()`, `is_admin_logged_in()`, `currentAdminUser()`, `logout_everywhere($userId)`, `set_user_password($userId, $plain)` (kullanıcının diğer oturumlarını da bitirir), `password_policy_errors($password, $username)`.

Rotaları `User` ya da `Admin` middleware'iyle koruyun (bkz. [Rotalar ve Controller'lar](Routing-and-Controllers-TR)). İlk yöneticiyi `php pika make:admin` ile oluşturun.

## Yetkiler ve denetim kaydı
```php
if (can('posts.edit')) { /* ... */ }
$this->authorize('posts.edit');   // bir controller içinde: izin yoksa 403
audit_log('post_deleted', "id $id");
```
Yöneticiler (`users.admin = 1`) her şeyi yapabilir; diğer kullanıcıların `user_permissions` içinde `(user_id, permission)` satırı olmalıdır. Denetim kaydı `user_logs` tablosudur ve yönetim panelinde gösterilir.

## Girişte neler olur
* 15 dakikada IP başına 5 ve kullanıcı adı başına 10 başarısız deneme sonraki denemeleri kilitler (HTTP 429). Kullanıcı adı sınırı, hesabın en son giriş yaptığı IP için geçerli değildir.
* Oturum kimliği yenilenir, boşta kalma ve mutlak zaman aşımları uygulanır, parola değişikliği her oturumu bitirir.
* "Beni hatırla" yalnızca rastgele bir jetonun hash'ini saklar; 30 gün sonra sona erer ve her kullanıldığında yenilenir.

## Parola sıfırlama
`/forgot` tek kullanımlık bir bağlantı gönderir (1 saat geçerli, hash'li saklanır). Yanıt bilinen ve bilinmeyen adresler için aynıdır; bir hesap 2 dakikada en fazla bir e-posta alır. Çalışan SMTP gerekir (`MAIL_*`).

## Başlıklar ve CSRF
Yanıtlar, istek başına nonce'lu sıkı bir CSP, `X-Frame-Options`, `nosniff`, `Referrer-Policy`, `Permissions-Policy` ve HTTPS'te HSTS taşır. Durumu değiştiren tüm istekler CSRF jetonu ister. Blade içinde: `{!! csrf_field() !!}`; JavaScript'te `X-CSRF-Token` gönderin.

## Pratik kurallar
* Bağlı değerler (`?`) kullanın, girdiyi asla SQL'e eklemeyin.
* Çıktıyı `{{ }}` ile kaçırın.
* Yalnızca `redirect_to()` ya da `route()` ile yönlendirin; `safe_redirect_url()` başka sunucuları engeller.
* Sırları `.env` içinde tutun; commit etmeden önce `php pika scan:secrets` çalıştırın.
