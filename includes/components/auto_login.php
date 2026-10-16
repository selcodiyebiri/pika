<?php
use Pika\DB;

/* Remember-me: the cookie holds a random token, the database only its sha256 hash. */
function remember_cookie_clear(string $name): void {
    setcookie($name, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

if (!empty($_COOKIE['remember_token']) && is_string($_COOKIE['remember_token']) && !is_logged_in()) {

    $search_user = DB::getRow(
        "SELECT * FROM users WHERE remember_token = ? AND status = 1",
        [hash('sha256', $_COOKIE['remember_token'])]
    );

    // legacy tokens without an issue time count as expired
    $fresh = $search_user && $search_user->remember_issued && time() - (int) $search_user->remember_issued < REMEMBER_TTL;

    if ($fresh && user_session_verify($search_user)) {
        user_session_set($search_user);
        remember_token_issue((int) $search_user->id);   // rotate: a stolen cookie stops working after the owner's next visit
        user_log_add([
            'user_id'    => $search_user->id,
            'action'     => 'auto_login',
            'description'=> 'user logged in automatically with remember-me cookie',
            'ip'         => getIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);
    } else {
        remember_cookie_clear('remember_token');
    }
}
