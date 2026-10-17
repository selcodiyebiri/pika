# Authentication and Security

[Home](Home) | **English** | [Türkçe](Authentication-and-Security-TR)

The full list of protections and known limits is in the [security policy](https://github.com/selcodiyebiri/pika/blob/main/SECURITY.md). This page shows how to use them.

## Users and login
Site login is `app/controllers/Login.php`, admin login is `app/controllers/Admin/Login.php`; both extend `Pika\Auth\BaseLogin`, which does throttling, the password check, rehashing, logging and the session. A new login area only says who may sign in and where to go (see those two classes).

Helpers: `is_logged_in()`, `currentUser()`, `is_admin_logged_in()`, `currentAdminUser()`, `logout_everywhere($userId)`, `set_user_password($userId, $plain)` (also ends the user's other sessions), `password_policy_errors($password, $username)`.

Protect routes with the middleware `User` or `Admin` (see [Routing and Controllers](Routing-and-Controllers)). Create the first admin with `php pika make:admin`.

## Permissions and audit log
```php
if (can('posts.edit')) { /* ... */ }
$this->authorize('posts.edit');   // in a controller: 403 if not allowed
audit_log('post_deleted', "id $id");
```
Admins (`users.admin = 1`) may do everything; other users need a row `(user_id, permission)` in `user_permissions`. The audit log is the `user_logs` table and is shown on the admin dashboard.

## What happens on login
* 5 failed attempts per IP and 10 per username in 15 minutes lock further tries (HTTP 429). The username limit does not apply to the IP the account last signed in from.
* The session id is regenerated, idle and absolute timeouts apply, and a password change ends every session.
* "Remember me" stores only a hash of a random token; it expires after 30 days and is replaced each time it is used.

## Password reset
`/forgot` mails a single-use link (valid for 1 hour, stored hashed). The answer is the same for known and unknown addresses; an account gets at most one mail every 2 minutes. It needs working SMTP (`MAIL_*`).

## Headers and CSRF
Responses carry a strict CSP with a per-request nonce, `X-Frame-Options`, `nosniff`, `Referrer-Policy`, `Permissions-Policy` and HSTS on HTTPS. All state-changing requests need the CSRF token. In Blade: `{!! csrf_field() !!}`; in JavaScript send `X-CSRF-Token`.

## Rules of thumb
* Use bound values (`?`), never concatenate input into SQL.
* Escape output with `{{ }}`.
* Redirect only with `redirect_to()` or `route()`; `safe_redirect_url()` blocks other hosts.
* Keep secrets in `.env`; run `php pika scan:secrets` before you commit.
