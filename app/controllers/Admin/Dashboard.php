<?php
namespace App\Controllers\Admin;

use Pika\AdminController,
    Pika\DB,
    Symfony\Component\HttpFoundation\Request,
    Symfony\Component\HttpFoundation\Response;

class Dashboard extends AdminController
{
    /** Badge colour per audit action. */
    private const TONES = [
        'login' => 'badge-success', 'admin_login' => 'badge-success', 'auto_login' => 'badge-success',
        'permission_denied' => 'badge-danger',
        'password_reset_requested' => 'badge-warn', 'password_reset_completed' => 'badge-warn', 'admin_request' => 'badge-warn',
    ];

    public function index(Request $request, Response $response)
    {
        $since = time() - 86400;

        $stats = [
            'users'      => (int) DB::getVar("SELECT COUNT(*) FROM users"),
            'admins'     => (int) DB::getVar("SELECT COUNT(*) FROM users WHERE admin = 1"),
            'logins'     => (int) DB::getVar("SELECT COUNT(*) FROM user_logs WHERE action IN ('login', 'admin_login', 'auto_login') AND time > ?", [$since]),
            'failed'     => (int) DB::getVar("SELECT COUNT(*) FROM login_attempts WHERE time > ?", [$since]),
            'migrations' => (int) DB::getVar("SELECT COUNT(*) FROM migrations"),
        ];

        $activity = array_map(fn($r) => [
            'username' => $r->username,
            'action'   => $r->action,
            'tone'     => self::TONES[$r->action] ?? '',
            'ip'       => $r->ip,
            'time'     => date('Y-m-d H:i', (int) $r->time),
        ], DB::get(
            "SELECT l.action, l.ip, l.time, COALESCE(u.username, 'guest') AS username
             FROM user_logs l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.id DESC LIMIT 8"
        ));

        $system = [
            'php'      => PHP_VERSION,
            'mysql'    => (string) DB::getVar("SELECT VERSION()"),
            'env'      => APP_ENV,
            'debug'    => APP_DEBUG,
            'timezone' => date_default_timezone_get(),
            'time'     => date('Y-m-d H:i'),
        ];

        return $response->setContent($this->view('dashboard', [
            'title' => 'Dashboard', 'stats' => $stats, 'activity' => $activity, 'system' => $system,
        ]));
    }
}
