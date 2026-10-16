<?php
namespace App\Middlewares;

use Buki\Router\Http\Middleware,
    Symfony\Component\HttpFoundation\Request,
    Symfony\Component\HttpFoundation\Response;

class User extends Middleware
{
    public function handle(Request $request, Response $response)
    {
        // Only serve the configured host
        $projectHost = mb_strtolower((string) parse_url(currentSiteUrl(), PHP_URL_HOST), 'UTF-8');
        if (mb_strtolower($request->getHost(), 'UTF-8') !== $projectHost) {
            $response->setStatusCode(400);
            $response->headers->set('Content-Type', 'application/json');
            return $response->setContent(json_encode(['status' => false, 'message' => 'Bad request']));
        }

        // Must be logged in
        $user = currentUser();
        if (!$user) {
            if ($request->getMethod() === 'GET') {
                redirect('user');
            }
            $response->setStatusCode(401);
            $response->headers->set('Content-Type', 'application/json');
            return $response->setContent(json_encode(['status' => false, 'message' => 'Unauthorized']));
        }

        // Optional IP allow-list (comma separated)
        if (!empty($user->allow_ips)) {
            $allowed = array_map('trim', explode(',', $user->allow_ips));
            if (!in_array(getIP(), $allowed, true)) {
                session_logout('user');
                redirect('user');
            }
        }

        // Session must come from the same browser that logged in
        if (!hash_equals(hash('sha256', (string) $user->user_agent), hash('sha256', $request->headers->get('User-Agent', '')))) {
            session_logout('user');
            redirect('user');
        }

        return true;
    }
}
