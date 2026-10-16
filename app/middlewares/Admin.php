<?php
namespace App\Middlewares;

use Buki\Router\Http\Middleware,
    Symfony\Component\HttpFoundation\Request,
    Symfony\Component\HttpFoundation\Response;

class Admin extends Middleware
{
    public function handle(Request $request, Response $response)
    {
        if (is_admin_logged_in()) {
            // audit trail of every state-changing admin request
            if (!in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
                audit_log('admin_request', $request->getMethod() . ' ' . $request->getPathInfo());
            }
            return true;
        }

        if ($request->getMethod() === 'GET') {
            redirect('admin');
        }

        $response->setStatusCode(401);
        $response->headers->set('Content-Type', 'application/json');
        return $response->setContent(json_encode(['status' => false, 'message' => 'Unauthorized']));
    }
}
