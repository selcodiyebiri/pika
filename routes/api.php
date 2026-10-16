<?php
// API / cron routes. RateLimit applies RATE_LIMIT requests per RATE_WINDOW seconds per IP (.env).
// API clients that send no CSRF token must be listed in includes/components/post_security.php.
$router->group('api', function($router) {
    $router->add('GET|HEAD', 'health', function () {
        json_output(['status' => true]);
    });

    // HTTP cron for hosts without CLI cron: wget -q -O /dev/null --header="X-Cron-Token: ..." https://site/api/cron/reports/daily
    // Short jobs only (PHP / web server time limits apply); long ones belong in `php pika job:run`.
    $router->add('GET', 'cron/:all', function ($name) {
        $token = (string) pika_env('CRON_TOKEN', '');
        $sent = $_SERVER['HTTP_X_CRON_TOKEN'] ?? '';
        if (strlen($token) < 32 || !is_string($sent) || !hash_equals($token, $sent)) abort(404);

        try {
            $r = Pika\JobRunner::forApp()->run($name);
        } catch (InvalidArgumentException $e) {
            abort(404);
        }
        json_output(['status' => $r['status'], 'seconds' => $r['seconds']], $r['status'] === 'failed' ? 500 : 200);
    });
}, ['before' => RateLimit::class]);
