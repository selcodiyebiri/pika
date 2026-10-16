<?php
/**
 * Pika web installer (for hosting without a terminal).
 *
 * It does not exist until you create the file content/install.token containing a secret of 16+ characters
 * (File Manager / FTP). After a successful installation, or whenever .env exists, it answers 404 forever.
 * You may delete this file after installing. CLI alternative: php pika install
 */
define('BASEPATH', dirname(__DIR__));
foreach (['PasswordPolicy', 'InstallException', 'Migrator', 'Installer', 'Icons'] as $class) {
    require BASEPATH . "/src/$class.php";
}

use Pika\Icons;
use Pika\Installer;
use Pika\InstallException;

$installer = new Installer(BASEPATH);

function not_found(): never {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Not Found\n");
}

// Behave as if this file did not exist unless the token file is in place and nothing is installed yet.
if ($installer->isLocked() || !$installer->tokenEnabled()) {
    not_found();
}

/* ---------- headers (CSS and JS are inlined with a nonce, so the page works even if asset URLs do not) ---------- */
$nonce = base64_encode(random_bytes(16));
header_remove('X-Powered-By');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'nonce-$nonce'; script-src 'nonce-$nonce'; img-src data:; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
$host = preg_match('/^[a-z0-9.\-\[\]:]+$/i', $_SERVER['HTTP_HOST'] ?? '') ? $_SERVER['HTTP_HOST'] : 'localhost';

function h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function icon(string $name, string $class = 'icon'): string { return Icons::svg($name, $class); }

function alert(string $type, string $message, string $icon): string {
    return '<div class="alert alert-' . $type . '" role="' . ($type === 'error' ? 'alert' : 'status') . '">' . icon($icon) . '<div>' . $message . '</div></div>';
}

/** Render a full page. $step: 1 unlock, 2 configure, 3 done. */
function page(string $title, string $body, int $status = 200, int $step = 1): never {
    global $nonce;
    http_response_code($status);

    $css = is_file(__DIR__ . '/assets/css/pika.css') ? (string) file_get_contents(__DIR__ . '/assets/css/pika.css') : 'body{font-family:system-ui,sans-serif;max-width:44rem;margin:2rem auto}';
    $js = is_file(__DIR__ . '/assets/js/pika.js') ? (string) file_get_contents(__DIR__ . '/assets/js/pika.js') : '';

    $steps = '';
    foreach (['Unlock', 'Configure', 'Done'] as $i => $label) {
        $n = $i + 1;
        $state = $n < $step ? 'done' : ($n === $step ? 'current' : '');
        $steps .= '<li class="step ' . $state . '"><b>' . ($n < $step ? icon('check', 'icon icon-sm') : $n) . '</b>' . h($label) . '</li>';
    }

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<meta name="color-scheme" content="light dark"><meta name="robots" content="noindex"><title>' . h($title) . ' &middot; Pika installer</title>'
       . '<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\'%3E%3Crect width=\'24\' height=\'24\' rx=\'6\' fill=\'%234f46e5\'/%3E%3C/svg%3E">'
       . '<style nonce="' . h($nonce) . '">' . $css . '</style>'
       . ($js !== '' ? '<script nonce="' . h($nonce) . '">' . $js . '</script>' : '')
       . '</head><body class="wizard-body"><div class="wizard">'
       . '<div class="wizard-head"><span class="brand"><span class="brand-mark">' . icon('bolt') . '</span> Pika installer</span><ol class="steps">' . $steps . '</ol></div>'
       . $body
       . '<p class="auth-foot">Pika will lock this installer as soon as it has finished.</p>'
       . '</div></body></html>';
    exit;
}

if (!Installer::transportOk($https, $host)) {
    page('HTTPS required', '<div class="card"><div class="card-body"><h1>HTTPS required</h1>'
        . alert('error', 'The installer sends passwords, so it only runs over HTTPS (or on localhost). Open this page with <code>https://</code>.', 'lock')
        . '</div></div>', 403);
}

/* ---------- session and CSRF ---------- */
session_name('__PikaInstaller');
ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict']);
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$csrf = '<input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '">';

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && !hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
    page('Request rejected', '<div class="card"><div class="card-body"><h1>Request rejected</h1>'
        . alert('error', 'Invalid form token. Go back and reload the page.', 'error') . '</div></div>', 403);
}

/* ---------- step 1: unlock with the token ---------- */
if (empty($_SESSION['unlocked'])) {
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (($wait = $installer->blockedFor()) > 0) {
            page('Too many attempts', '<div class="card"><div class="card-body"><h1>Too many attempts</h1>'
                . alert('error', 'Too many wrong tokens. Try again in ' . (int) ceil($wait / 60) . ' minute(s).', 'lock') . '</div></div>', 429);
        }
        if ($installer->checkToken((string) ($_POST['token'] ?? ''))) {
            session_regenerate_id(true);
            $_SESSION['unlocked'] = true;
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'), true, 303);
            exit;
        }
        $error = alert('error', 'That token is not correct.', 'error');
    }
    page('Unlock', '<div class="card"><div class="card-body">'
        . '<div class="success-mark">' . icon('lock') . '</div>'
        . '<h1>Unlock the installer</h1>'
        . '<p class="muted">Enter the token you saved in <code>content/install.token</code>. Only someone with access to the server files can do that.</p>'
        . $error
        . '<form method="post" autocomplete="off">' . $csrf
        . '<div class="field"><label for="token">Install token</label><div class="input-wrap"><input class="input" id="token" name="token" type="password" required autofocus>'
        . '<button type="button" class="reveal" data-toggle-password="token" aria-label="Show password" aria-pressed="false"><span class="eye-on">' . icon('eye') . '</span><span class="eye-off">' . icon('eye-slash') . '</span></button></div></div>'
        . '<button class="btn btn-primary btn-block btn-lg" type="submit">Continue</button></form>'
        . '</div></div>', $error ? 403 : 200, 1);
}

/* ---------- step 2: requirements and form ---------- */
$req = $installer->requirements();
$in = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === 'install' ? $_POST : [];
$errors = [];

if ($in && $req['ok']) {
    $errors = $installer->validate($in);
    if (!$errors) {
        try {
            $result = $installer->install($in);
            $_SESSION = [];
            session_destroy();

            $admin = rtrim($in['site_url'], '/') . '/admin/login/';
            $body = '<div class="card"><div class="card-body">'
                  . '<div class="success-mark">' . icon('check') . '</div>'
                  . '<h1>Installation complete</h1>'
                  . '<p class="muted">Pika is installed and this installer is now locked.</p>'
                  . '<p><a class="btn btn-primary btn-lg" href="' . h($admin) . '">Open the admin login ' . icon('arrow-right') . '</a></p>';
            if (!$result['env_written']) {
                $body .= alert('warn', '<strong>Action needed.</strong> The project folder is not writable, so <code>.env</code> was not created. '
                       . 'Create a file named <code>.env</code> in the project folder with exactly this content. It contains secrets and is shown only once.', 'alert')
                       . '<textarea class="env-box" readonly>' . h($result['env']) . '</textarea>';
            }
            $body .= '<h2>What to do next</h2><ul>'
                   . '<li>Sign in with the administrator account you just created.</li>'
                   . '<li>Delete <code>public/install.php</code> (optional, it already answers 404).</li>'
                   . '<li>Behind a reverse proxy or CDN? Add its IP to <code>TRUSTED_PROXIES</code> in <code>.env</code>.</li>'
                   . '<li>Fill in the <code>MAIL_*</code> settings in <code>.env</code> to enable password reset e-mails.</li></ul>'
                   . '</div></div>';
            page('Installation complete', $body, 200, 3);
        } catch (InstallException $e) {
            $errors['_'] = $e->getMessage();
        }
    }
}

$value = fn(string $k, string $d = '') => h(($in[$k] ?? '') !== '' ? $in[$k] : $d);   // passwords are never refilled
$fieldError = fn(string $k) => isset($errors[$k]) ? '<div class="field-error">' . h($errors[$k]) . '</div>' : '';

$base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
$base = str_ends_with($base, '/public') ? substr($base, 0, -7) : $base;
$defaultUrl = ($https ? 'https' : 'http') . '://' . $host . $base;

/** One form field. $o: type, default, hint, span (full width), autocomplete. */
$field = function (string $name, string $label, array $o = []) use ($value, $fieldError): string {
    $type = $o['type'] ?? 'text';
    $input = $type === 'password'
        ? '<div class="input-wrap"><input class="input" id="' . $name . '" name="' . $name . '" type="password" autocomplete="' . h($o['autocomplete'] ?? 'new-password') . '">'
          . '<button type="button" class="reveal" data-toggle-password="' . $name . '" aria-label="Show password" aria-pressed="false"><span class="eye-on">' . icon('eye') . '</span><span class="eye-off">' . icon('eye-slash') . '</span></button></div>'
        : '<input class="input" id="' . $name . '" name="' . $name . '" type="' . $type . '" value="' . $value($name, $o['default'] ?? '') . '" autocomplete="off" spellcheck="false">';
    return '<div class="field' . (!empty($o['span']) ? ' span-2' : '') . '"><label for="' . $name . '">' . h($label) . '</label>' . $input
         . $fieldError($name) . (isset($o['hint']) ? '<div class="hint">' . $o['hint'] . '</div>' : '') . '</div>';
};

$section = fn(string $icon, string $title, string $sub, string $fields): string =>
    '<section class="card"><div class="card-body"><h2 class="section-title">' . icon($icon) . h($title) . '</h2><p class="section-sub">' . $sub . '</p><div class="grid-2">' . $fields . '</div></div></section>';

$list = '';
foreach ($req['checks'] as $c) {
    $cls = $c['ok'] ? 'ok' : ($c['required'] ? 'bad' : 'warn');
    $list .= '<li class="' . $cls . '"><span class="state">' . icon($c['ok'] ? 'check' : ($c['required'] ? 'x' : 'alert'), 'icon icon-sm') . '</span><span>' . h($c['label']) . '</span></li>';
}
if ($req['ok']) {
    // everything fine: one line, details on demand
    $checks = '<section class="card"><details class="checks"><summary><h2>Server checks</h2><span class="badge badge-success">'
            . icon('check', 'icon icon-sm') . ' all ' . count($req['checks']) . ' passed</span></summary><ul class="reqs">' . $list . '</ul></details></section>';
} else {
    $checks = '<section class="card"><div class="card-head"><h2>Server checks</h2><span class="badge badge-danger">action needed</span></div><ul class="reqs">' . $list . '</ul></section>';
}

$form = '';
if ($req['ok']) {
    $form = '<form method="post" autocomplete="off" class="stack">' . $csrf . '<input type="hidden" name="step" value="install">'
        . (isset($errors['_']) ? alert('error', h($errors['_']), 'error') : '')
        . $section('server', 'Site', 'The address people will use to open this site.',
            $field('site_url', 'Site address', ['default' => $defaultUrl, 'span' => true, 'hint' => 'Must start with <code>https://</code> (plain <code>http://</code> only works for localhost).']))
        . $section('database', 'Database', 'Create an <strong>empty</strong> MySQL database and user in your hosting panel first.',
            $field('db_host', 'Host', ['default' => 'localhost']) . $field('db_port', 'Port', ['default' => '3306'])
            . $field('db_name', 'Database name') . $field('db_user', 'User')
            . $field('db_pass', 'Password', ['type' => 'password', 'span' => true, 'autocomplete' => 'off'])
            . $field('db_socket', 'Socket path (optional)', ['span' => true, 'hint' => 'Replaces host and port. MAMP: <code>/Applications/MAMP/tmp/mysql/mysql.sock</code>']))
        . $section('user', 'Administrator', 'The first account. It can open the admin area.',
            $field('admin_user', 'Username') . $field('admin_email', 'E-mail', ['hint' => 'Needed for password reset.'])
            . $field('admin_pass', 'Password', ['type' => 'password', 'hint' => 'At least 10 characters.']) . $field('admin_pass2', 'Repeat password', ['type' => 'password']))
        . $section('mail', 'Mail (optional)', 'SMTP settings for password reset e-mails. You can also set them in <code>.env</code> later.',
            $field('mail_host', 'SMTP host') . $field('mail_port', 'SMTP port', ['default' => '587'])
            . $field('mail_user', 'SMTP user') . $field('mail_pass', 'SMTP password', ['type' => 'password', 'autocomplete' => 'off'])
            . $field('mail_from', 'Sender address', ['span' => true]))
        . '<button class="btn btn-primary btn-block btn-lg" type="submit">' . icon('rocket') . ' Install Pika</button></form>';
} else {
    $form = alert('error', 'Fix the items marked above, then reload this page.', 'alert');
}

page('Configure', '<div class="stack">' . $checks . $form . '</div>', 200, 2);
