<?php
/* CONFIG */
require BASEPATH . "/includes/config.php";
define("REQUEST_ID", bin2hex(random_bytes(8)));
define("CSP_NONCE", base64_encode(random_bytes(16)));
pika_boot_enforce();

/* SESSION */
session_name("__RequestVerificationToken");
session_cache_limiter("nocache");
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.gc_maxlifetime', (string) max(SESSION_ABSOLUTE, SESSION_IDLE));
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => is_https(),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

/* Idle and absolute session timeouts */
$now = time();
if (isset($_SESSION['_started']) && ($now - $_SESSION['_last'] > SESSION_IDLE || $now - $_SESSION['_started'] > SESSION_ABSOLUTE)) {
    $_SESSION = [];
    session_regenerate_id(true);
}
$_SESSION['_started'] ??= $now;
$_SESSION['_last'] = $now;

/* AUTOLOAD (src/ classes + helpers) */
require BASEPATH . "/vendor/autoload.php";

flash_rotate();
require __DIR__ . "/components/security_headers.php";

/* COMPONENTS */
require __DIR__ . "/components/defines.php";
require __DIR__ . "/components/csrf.php";
require __DIR__ . "/components/post_security.php";
require __DIR__ . "/components/auto_login.php";
require __DIR__ . "/components/user_security_check.php";

set_error_handler("errorHandling", E_ALL & ~E_DEPRECATED);
set_exception_handler('handle_exception');

/* ROUTE -- do start */
use Buki\Router\Router;
$router = new Router([
    'base_folder' => BASE_FOLDER,
    'paths' => [
        // absolute: the router resolves these against the working directory, which differs under Apache
        'controllers' => BASEPATH . '/app/controllers',
        'middlewares' => BASEPATH . '/app/middlewares',
    ],
    'namespaces' => [
        'controllers' => 'App\Controllers',
        'middlewares' => 'App\Middlewares',
    ],
]);

/* Route files */
foreach (glob(BASEPATH . "/routes/*.php") as $router_file) {
    require $router_file;
}

$router->notFound(function() {
    abort(404);
});

$router->error(function ($request, $response, $e = null) {
    handle_exception($e ?? new RuntimeException('Router error'), 500);
});

/* ROUTER RUN */
$router->run();
