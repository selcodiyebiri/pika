<?php
namespace App\Controllers\Admin;

use Pika\Auth\BaseLogin,
    Symfony\Component\HttpFoundation\Request;

/** Admin login: only users with admin = 1, rendered with the admin theme. */
class Login extends BaseLogin
{
    protected string $theme = 'admin';
    protected ?string $badge = 'Administration';

    protected function isLoggedIn(): bool          { return (bool) is_admin_logged_in(); }
    protected function accountCondition(): string  { return ' AND admin = 1'; }
    protected function homeUrl(): string           { return route('ADMIN_HOME'); }
    protected function loginUrl(): string          { return route('ADMIN_LOGIN'); }
    protected function logAction(): string         { return 'admin_login'; }
    protected function endSession(): void          { session_logout('admin'); }

    protected function startSession(object $user, Request $request): void
    {
        admin_session_set($user);
    }
}
