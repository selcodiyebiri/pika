<?php
/**
 * CSRF check for every state-changing request (safe by default).
 * The token is accepted from the "token" form field or the X-CSRF-Token header.
 * Add paths that must stay open (webhooks, token-authenticated APIs) to $csrfExcludes.
 */
if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {

    $path = parse_url(currentUrl(), PHP_URL_PATH);

    $csrfExcludes = [
        // "/api/example/",
    ];

    if (!in_array($path, $csrfExcludes, true)) {
        $sent = $_POST['token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
            json_output([
                "status"  => false,
                "message" => "Access Denied!"
            ], 403);
        }
    }
}
