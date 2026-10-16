<?php
/**
 * Security headers for every response. Inline <style>/<script> in views must carry nonce="{{ csp_nonce() }}".
 * Override the whole policy with CSP in .env (use {nonce} as placeholder).
 */
header_remove('X-Powered-By');
header('X-Request-Id: ' . REQUEST_ID);
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
header('Cross-Origin-Opener-Policy: same-origin');

$csp = pika_env('CSP') ?: "default-src 'self'; script-src 'self' 'nonce-{nonce}'; style-src 'self' 'nonce-{nonce}'; "
     . "img-src 'self' data:; font-src 'self'; connect-src 'self'; object-src 'none'; "
     . "base-uri 'self'; form-action 'self'; frame-ancestors 'none'";
header('Content-Security-Policy: ' . str_replace('{nonce}', CSP_NONCE, $csp));

if (is_https()) {
    header('Strict-Transport-Security: max-age=15552000');
}
