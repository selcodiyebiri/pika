<?php
namespace App\Middlewares;

use Buki\Router\Http\Middleware,
    Pika\RateLimiter,
    Symfony\Component\HttpFoundation\Request,
    Symfony\Component\HttpFoundation\Response;

/** Per-IP request limit (RATE_LIMIT requests per RATE_WINDOW seconds, set in .env). */
class RateLimit extends Middleware
{
    public function handle(Request $request, Response $response)
    {
        $result = RateLimiter::hit('ip:' . getIP(), RATE_LIMIT, RATE_WINDOW);

        header('X-RateLimit-Limit: ' . RATE_LIMIT);
        header('X-RateLimit-Remaining: ' . $result['remaining']);

        if ($result['allowed']) {
            return true;
        }

        $response->setStatusCode(429);
        $response->headers->set('Retry-After', (string) $result['retry_after']);
        $response->headers->set('Content-Type', 'application/json');
        return $response->setContent(json_encode(['status' => false, 'message' => 'Too many requests']));
    }
}
