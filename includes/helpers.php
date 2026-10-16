<?php
use Pika\DB;

/* ---------- OPTIONS (cached per request) ---------- */

function &option_cache(): array {
    static $cache = [];
    return $cache;
}

function get_option($option_name, $entity = false) {
    $cache = &option_cache();
    if (!array_key_exists($option_name, $cache)) {
        $cache[$option_name] = DB::getVar("SELECT option_value FROM options WHERE option_key = ?", [$option_name]);
    }
    $value = $cache[$option_name];

    if (empty($value))
        return false;

    return $entity
        ? htmlentities($value, ENT_QUOTES)
        : stripcslashes(html_entity_decode($value));
}

function add_option($option_name, $option_value) {
    $cache = &option_cache();
    unset($cache[$option_name]);
    return DB::insert(
        "INSERT INTO options (option_key, option_value, created_time, updated_time) VALUES (?, ?, ?, ?)",
        [$option_name, $option_value, time(), time()]
    );
}

function update_option($option_name, $option_value) {
    $cache = &option_cache();
    unset($cache[$option_name]);
    return DB::exec(
        "INSERT INTO options (option_key, option_value, created_time, updated_time) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE option_value = VALUES(option_value), updated_time = VALUES(updated_time)",
        [$option_name, $option_value, time(), time()]
    ) !== false;
}

/* ---------- OUTPUT / DATA ---------- */

/* Escape for HTML output. Blade's {{ }} already does this; use esc() in plain PHP views. */
function esc($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function get_nested_value(array $data, string $path) {
    foreach (explode('.', $path) as $key) {
        if (!isset($data[$key])) {
            return null;
        }
        $data = $data[$key];
    }
    return $data;
}

function isValidJson(string $string): bool {
    json_decode($string);
    return json_last_error() === JSON_ERROR_NONE;
}

function generateSeoURL($text) {
    $text = strtr((string) $text, [
        'Ğ' => 'G', 'Ü' => 'U', 'Ş' => 'S', 'İ' => 'I', 'Ö' => 'O', 'Ç' => 'C',
        'ğ' => 'g', 'ü' => 'u', 'ş' => 's', 'ı' => 'i', 'ö' => 'o', 'ç' => 'c',
    ]);
    return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), '-');
}

function format_number($number) {
    return number_format($number, 2, ',', '.');
}

function global_time() {
    return time();
}

function time_difference($timestamp1, $timestamp2) {
    $interval = (new DateTime("@$timestamp1"))->diff(new DateTime("@$timestamp2"));

    $result = [];
    foreach (['y' => 'y', 'm' => 'mo', 'd' => 'd', 'h' => 'h', 'i' => 'min', 's' => 's'] as $k => $label) {
        if ($interval->$k) $result[] = $interval->$k . $label;
    }
    return implode(' ', $result);
}

/* ---------- RANDOM / IDS ---------- */

function hash_generator($length = 64) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $max = strlen($characters) - 1;
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $max)];
    }
    return $randomString;
}

function generate_random_string($length = 16) {
    return hash_generator($length);
}

function uuidv4() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // version 4
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variant
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/* Unique id of this request (also sent as X-Request-Id and written to every log line) */
function request_id(): string {
    return defined('REQUEST_ID') ? REQUEST_ID : '-';
}

/* Per-request nonce for inline <style>/<script> (see components/security_headers.php) */
function csp_nonce(): string {
    return defined('CSP_NONCE') ? CSP_NONCE : '';
}

/* ---------- REQUEST / URL ---------- */

function getIP(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (is_trusted_proxy()) {
        // Every hop appends the address it saw, so only the right-hand entries come from our own proxies; the left-hand
        // ones are whatever the client claimed. Walk from the right and stop at the first address that is not a trusted proxy.
        $hops = array_reverse(array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')));
        foreach ($hops as $hop) {
            if (!filter_var($hop, FILTER_VALIDATE_IP)) break;
            $ip = $hop;
            if (!in_array($hop, trusted_proxies(), true)) break;
        }
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
}

/* Same /16 (IPv4) or /64 (IPv6) network */
function is_ip_similar($savedIp, $currentIp): bool {
    $a = @inet_pton((string) $savedIp);
    $b = @inet_pton((string) $currentIp);
    if ($a === false || $b === false || strlen($a) !== strlen($b)) return false;
    $len = strlen($a) === 4 ? 2 : 8;
    return substr($a, 0, $len) === substr($b, 0, $len);
}

/* Scheme + host of the current request (Host header validated). */
function request_origin() {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (!preg_match('/^[a-z0-9.\-]+(:\d{1,5})?$/i', $host)) $host = 'localhost';
    return (is_https() ? 'https' : 'http') . '://' . $host;
}

/* URL path the app is mounted under: '' at a domain root, '/pika' when served from a sub-folder.
   Derived from the front controller location; a trailing /public is stripped (root .htaccess rewrite). */
function base_path() {
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return str_ends_with($dir, '/public') ? substr($dir, 0, -7) : $dir;
}

/* Set SITE_URL in .env for production; otherwise it is built from the (validated) request. */
function currentSiteUrl() {
    if ($url = pika_env('SITE_URL')) return rtrim($url, '/');
    return request_origin() . base_path();
}

function currentAdminUrl() {
    return currentSiteUrl() . "/" . pika_env('ADMIN_URL', 'admin');
}

function currentUrl() {
    return request_origin() . ($_SERVER['REQUEST_URI'] ?? '/');
}

function asset($file) {
    return (defined('SITE_ASSET_URI') ? SITE_ASSET_URI : currentSiteUrl() . '/assets') . "/{$file}";
}

function admin_asset($file) {
    return (defined('SITE_ADMIN_ASSET_URI') ? SITE_ADMIN_ASSET_URI : currentSiteUrl() . '/assets/admin') . "/{$file}";
}

/* Heroicons as inline SVG: {!! icon('shield') !!}. Names: see src/Icons.php. */
function icon(string $name, string $class = 'icon'): string {
    return Pika\Icons::svg($name, $class);
}

/* Only same-site targets are allowed (no open redirects); anything else falls back to the site root. */
function safe_redirect_url(string $url): string {
    $url = str_replace(["\r", "\n", "\0"], '', trim($url));
    $site = currentSiteUrl();

    // Browsers read "\" as "/" and "user@host" sends the visitor to host: parse_url and the browser would disagree.
    if (str_contains($url, '\\') || parse_url($url, PHP_URL_USER) !== null) return $site . '/';

    if ($url !== '' && $url[0] === '/' && !preg_match('#^/[/\\\\]#', $url)) {
        return $site . $url;                                   // relative to the site
    }
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    $allowed = [strtolower((string) parse_url($site, PHP_URL_HOST)), strtolower((string) parse_url(request_origin(), PHP_URL_HOST))];
    return ($host !== '' && in_array($host, $allowed, true) && preg_match('#^https?://#i', $url)) ? $url : $site . '/';
}

function redirect_to(string $url, int $status = 303): void {
    header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
    header('Location: ' . safe_redirect_url($url), true, $status);
    exit;
}

function redirect($type = 'admin') {
    redirect_to($type === 'admin' ? currentAdminUrl() . "/login/" : currentSiteUrl() . "/login/");
}

/**
 * URL of a named route: route('RESET', ['any' => $token]). In Blade: {{ route('LOGIN') }} or @route('LOGIN').
 * Name a route with ['name' => 'LOGIN']. Parameter values are URL-encoded. Returns null (and logs a warning)
 * for an unknown name or a missing parameter.
 */
function route(string $name, array $params = []): ?string {
    global $router;
    if (!is_object($router)) {   // helpers load before the router exists; only call route() while handling a request
        log_message('warning', "route('$name') was called before the router was created");
        return null;
    }
    foreach ($router->getRoutes() as $route) {
        if (($route['name'] ?? null) !== $name) continue;

        $path = (string) $route['route'];
        foreach ($params as $key => $value) {
            $path = preg_replace_callback('/:' . preg_quote((string) $key, '/') . '\b/', fn() => rawurlencode((string) $value), $path);
        }
        if (preg_match('/:\w+/', $path)) {
            log_message('warning', "route('$name') is missing a parameter: $path");
            return null;
        }

        $path = trim($path, '/');
        return currentSiteUrl() . '/' . ($path === '' ? '' : $path . '/');
    }

    log_message('warning', "route('$name') does not exist");
    return null;
}

/**
 * Run $fn after the response has been sent to the visitor (PHP-FPM and LiteSpeed); elsewhere it simply runs at the end
 * of the request. Used for slow work whose duration must not show in the response time (sending mail).
 */
function after_response(callable $fn): void {
    register_shutdown_function(function () use ($fn) {
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
        elseif (function_exists('litespeed_finish_request')) litespeed_finish_request();
        try { $fn(); } catch (\Throwable $e) { log_message('error', 'after_response failed: ' . $e->getMessage()); }
    });
}

function cURL(array $params) {
    $ch = curl_init();
    $options = [
        CURLOPT_URL => $params['url'],
        CURLOPT_HEADER => false,
        CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'] ?? 'Pika',
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_SSL_VERIFYPEER => true,
    ];
    foreach (($params['options'] ?? []) as $option => $value) {
        $options[$option] = $value;
    }
    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}

/* ---------- RESPONSES / ERRORS / LOG ---------- */

function json_output($msg, $status = 200, $exit = true, $extras = null) {
    if ($status !== 200)
        http_response_code($status);
    header("Content-Type: application/json");
    $array = is_array($msg) ? $msg : ["message" => [$msg]];
    $json = json_encode($array, $extras ?? 0);
    if ($exit) exit($json);
    echo $json;
}

function abort($status = 404) {
    http_response_code($status);
    $file_path = BASEPATH . "/includes/error_pages/" . (int) $status . ".php";
    if (file_exists($file_path)) {
        require $file_path;
    }
    exit;
}

function log_message(string $level, string $message): void {
    $dir = defined('SITE_LOG_PATH') ? SITE_LOG_PATH : BASEPATH . '/content/logs';
    $line = sprintf("[%s] [%s] %s: %s\n", date('Y-m-d H:i:s'), request_id(), strtoupper($level), $message);
    @file_put_contents($dir . '/pika-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
}

function errorHandling($errNo = null, string $errMsg = '', $errorFile = null, $errLine = null) {
    // known harmless notice: izniburak/router calls ob_end_clean() after Response::send() already closed the buffers (HEAD requests)
    if (str_starts_with($errMsg, 'ob_end_clean(): Failed to delete buffer') && str_contains((string) $errorFile, '/izniburak/router/')) {
        return true;
    }
    log_message('error', "[$errNo] $errMsg in $errorFile:$errLine | " . currentUrl() . " | " . getIP());
    return !APP_DEBUG; // in debug mode PHP's own handler also displays the error
}

/* Log an uncaught exception, then show the debug page (APP_DEBUG) or the generic error page. Never returns. */
function handle_exception(Throwable $e, int $status = 500) {
    log_message('error', get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    if (APP_DEBUG) {
        http_response_code($status);
        $n = esc(csp_nonce());
        echo "<!DOCTYPE html><html lang=\"en\"><head><meta charset=\"UTF-8\"><title>" . esc(get_class($e)) . "</title>"
           . "<style nonce=\"$n\">body{font-family:system-ui,sans-serif;margin:2rem;color:#212529}pre{background:#f1f3f5;padding:1rem;overflow:auto}</style></head><body>"
           . "<h1>" . esc(get_class($e)) . "</h1><p><strong>" . esc($e->getMessage()) . "</strong></p>"
           . "<p>" . esc($e->getFile()) . ":" . (int) $e->getLine() . "</p>"
           . "<pre>" . esc($e->getTraceAsString()) . "</pre><p>Request ID: " . esc(request_id()) . "</p></body></html>";
        exit;
    }
    abort($status);
}

/* ---------- FLASH MESSAGES / OLD INPUT ----------
   flash() shows on the NEXT request (use before a redirect); flash_now() on the current one. */

function flash_rotate(): void {
    $_SESSION['_flash'] = $_SESSION['_flash_next'] ?? [];
    $_SESSION['_old'] = $_SESSION['_old_next'] ?? [];
    unset($_SESSION['_flash_next'], $_SESSION['_old_next']);
}

function flash(string $key, string $message): void     { $_SESSION['_flash_next'][$key] = $message; }
function flash_now(string $key, string $message): void { $_SESSION['_flash'][$key] = $message; }
function flash_get(string $key, $default = null)       { return $_SESSION['_flash'][$key] ?? $default; }

function is_sensitive_field(string $name): bool {
    return (bool) preg_match('/pass|token|secret|card|cvv|pin/i', $name);
}

/* Keep submitted input for the next request (never password/token-like fields). */
function keep_old(array $input): void {
    $_SESSION['_old_next'] = array_filter($input, fn($v, $k) => is_scalar($v) && !is_sensitive_field((string) $k), ARRAY_FILTER_USE_BOTH);
}

/* Previously submitted value of a field, for refilling forms. */
function old(string $field, $default = '') {
    if (is_sensitive_field($field)) return $default;
    $value = $_SESSION['_old'][$field] ?? $_POST[$field] ?? $default;
    return is_scalar($value) ? $value : $default;
}

/* ---------- CSRF ---------- */

function csrf_token() {
    if (!isset($_SESSION['csrf_token']))
        $_SESSION['csrf_token'] = hash_generator();
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

/* ---------- AUTH (users table; "user" and "admin" use separate session keys) ---------- */

/* Session stores a hash of the password hash so a password change invalidates the session. */
function session_password_hash($password): string {
    return hash('sha256', (string) $password);
}

function session_account(string $prefix, bool $admin = false, bool $refresh = false) {
    static $cache = [];
    if ($refresh) unset($cache[$prefix]);
    if (array_key_exists($prefix, $cache)) return $cache[$prefix];

    $row = false;
    if (!empty($_SESSION["{$prefix}_logged_in"]) && isset($_SESSION["{$prefix}_logged_id"], $_SESSION["{$prefix}_logged_password"])) {
        $row = DB::getRow(
            "SELECT * FROM users WHERE id = ? AND status = 1" . ($admin ? " AND admin = 1" : ""),
            [$_SESSION["{$prefix}_logged_id"]]
        );
        // the session ends when the password changed or "log out everywhere" was used (session_version)
        if (!$row
            || !hash_equals(session_password_hash($row->password), (string) $_SESSION["{$prefix}_logged_password"])
            || (int) $row->session_version !== (int) ($_SESSION["{$prefix}_logged_ver"] ?? 0)) {
            $row = false;
        }
    }
    return $cache[$prefix] = $row;
}

function currentUser()      { return session_account('user'); }
function currentAdminUser() { return session_account('admin', true); }

function is_logged_in() {
    $u = currentUser();
    return $u ? (string) $u->id : false;
}

function is_admin_logged_in() {
    $u = currentAdminUser();
    return $u ? (string) $u->id : false;
}

function session_login(string $prefix, bool $admin, $user): bool {
    session_regenerate_id(true);
    $_SESSION["{$prefix}_logged_in"] = true;
    $_SESSION["{$prefix}_logged_id"] = (string) $user->id;
    $_SESSION["{$prefix}_logged_password"] = session_password_hash($user->password);
    $_SESSION["{$prefix}_logged_ver"] = (int) $user->session_version;
    session_account($prefix, $admin, true);
    return true;
}

function user_session_set($user)  { return session_login('user', false, $user); }
function admin_session_set($user) { return session_login('admin', true, $user); }

function session_logout(string $prefix): void {
    unset($_SESSION["{$prefix}_logged_in"], $_SESSION["{$prefix}_logged_id"], $_SESSION["{$prefix}_logged_password"], $_SESSION["{$prefix}_logged_ver"]);
    session_regenerate_id(true);
    session_account($prefix, $prefix === 'admin', true);
}

/* Issue a remember-me cookie. The database keeps only the sha256 hash and the issue time (tokens expire after 30 days). */
function remember_token_issue(int $userId): void {
    $token = hash_generator(64);
    DB::exec("UPDATE users SET remember_token = ?, remember_issued = ? WHERE id = ?", [hash('sha256', $token), time(), $userId]);
    setcookie('remember_token', $token, [
        'expires' => time() + REMEMBER_TTL, 'path' => '/',
        'secure' => is_https(), 'httponly' => true, 'samesite' => 'Strict',
    ]);
}

/* Invalidate every session and remember-me token of a user ("log out everywhere"). */
function logout_everywhere($userId): void {
    DB::exec("UPDATE users SET session_version = session_version + 1, remember_token = NULL WHERE id = ?", [$userId]);
}

/* Problems with a candidate password (empty array = acceptable). bcrypt only uses the first 72 bytes. */
function password_policy_errors(string $password, string $username = ''): array {
    return Pika\PasswordPolicy::errors($password, $username);
}

/* Change a password and end all other sessions / remember-me tokens of that user. */
function set_user_password($userId, string $plain): void {
    DB::exec(
        "UPDATE users SET password = ?, session_version = session_version + 1, remember_token = NULL WHERE id = ?",
        [password_hash($plain, PASSWORD_DEFAULT), $userId]
    );
}

function only_admin() {
    if (!is_admin_logged_in()) redirect('admin');
}

function user_session_verify($current_user) {
    return hash_equals(hash('sha256', (string) $current_user->user_agent), hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''))
        && is_ip_similar($current_user->last_ip, getIP());
}

/* ---------- PERMISSIONS (admin = 1 may do everything; others need a row in user_permissions) ---------- */

function can(string $permission, $user = null): bool {
    static $cache = [];
    $user = $user ?: (currentAdminUser() ?: currentUser());
    if (!$user) return false;
    if ((int) $user->admin === 1) return true;

    $cache[$user->id] ??= array_column(DB::get("SELECT permission FROM user_permissions WHERE user_id = ?", [$user->id]), 'permission');
    return in_array($permission, $cache[$user->id], true);
}

/* ---------- LOGS ---------- */

function user_log_add($log) {
    foreach (['user_id', 'action', 'description', 'ip', 'user_agent'] as $required) {
        if (!isset($log[$required])) return false;
    }
    return DB::insert(
        "INSERT INTO user_logs (user_id, action, description, ip, user_agent, time) VALUES (?, ?, ?, ?, ?, ?)",
        [$log['user_id'], $log['action'], $log['description'], $log['ip'], $log['user_agent'], $log['time'] ?? time()]
    );
}

/* Audit trail entry for the current account (admin first, then user; user_id 0 = anonymous). */
function audit_log(string $action, string $description = '', $userId = null) {
    $userId ??= (currentAdminUser() ?: currentUser())->id ?? 0;
    return user_log_add([
        'user_id' => $userId, 'action' => $action, 'description' => mb_substr($description, 0, 1000),
        'ip' => getIP(), 'user_agent' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);
}
