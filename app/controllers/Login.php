<?php
namespace App\Controllers;

use Pika\Auth\BaseLogin,
    Pika\DB,
    Symfony\Component\HttpFoundation\Request;

/** Site (front-end) login: any active user, with remember-me. */
class Login extends BaseLogin
{
    protected bool $remember = true;
    protected ?string $forgotRoute = 'FORGOT';

    protected function isLoggedIn(): bool          { return (bool) is_logged_in(); }
    protected function accountCondition(): string  { return ''; }
    protected function homeUrl(): string           { return route('HOME'); }
    protected function loginUrl(): string          { return route('LOGIN'); }
    protected function logAction(): string         { return 'login'; }

    protected function endSession(): void
    {
        // Revoke the remember-me token too, otherwise auto_login signs the user straight back in
        if ($user = currentUser()) {
            DB::exec("UPDATE users SET remember_token = NULL WHERE id = ?", [$user->id]);
        }
        remember_cookie_clear('remember_token');
        session_logout('user');
    }

    protected function startSession(object $user, Request $request): void
    {
        user_session_set($user);
        if ($request->request->get('remember')) {
            $this->issueRememberToken($user);
        }
    }
}
