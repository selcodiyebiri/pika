<?php
namespace App\Controllers;

use Pika\Controller,
    Pika\DB,
    Pika\Mail,
    Pika\RateLimiter,
    Symfony\Component\HttpFoundation\Request,
    Symfony\Component\HttpFoundation\Response;

/**
 * Password reset by e-mail. Tokens are random, stored only as sha256, valid for 1 hour and single use.
 * The "forgot" page answers identically whether or not the address exists (no account enumeration).
 */
class PasswordReset extends Controller
{
    private const TTL = 3600;

    public function forgot(Request $request, Response $response)
    {
        if ($request->getMethod() !== 'POST') {
            return $response->setContent($this->view('forgot', ['title' => 'Forgot password']));
        }

        if (!RateLimiter::hit('forgot:' . getIP(), 5, 3600)['allowed']) {
            $response->setStatusCode(429);
            flash_now('error', 'Too many requests. Try again later.');
            return $response->setContent($this->view('forgot', ['title' => 'Forgot password']));
        }

        $email = trim((string) $request->request->get('email', ''));
        $user = filter_var($email, FILTER_VALIDATE_EMAIL)
            ? DB::getRow("SELECT id, username FROM users WHERE email = ? AND status = 1", [$email])
            : false;

        if ($user) {
            $token = hash_generator(64);
            DB::exec("UPDATE password_resets SET used_at = ? WHERE user_id = ? AND used_at IS NULL", [time(), $user->id]);
            DB::insert(
                "INSERT INTO password_resets (user_id, token_hash, expires_at, ip, created_at) VALUES (?, ?, ?, ?, ?)",
                [$user->id, hash('sha256', $token), time() + self::TTL, getIP(), time()]
            );
            Mail::send($email, 'Reset your password', "Use this link within 1 hour to choose a new password:\n\n"
                . route('RESET', ['any' => $token]) . "\n\nIf you did not ask for this, ignore this message.\n");
            audit_log('password_reset_requested', '', $user->id);
        }

        flash_now('status', 'If that address belongs to an account, a reset link has been sent.');
        return $response->setContent($this->view('forgot', ['title' => 'Forgot password']));
    }

    public function reset($token, Request $request, Response $response)
    {
        $reset = $this->findValid((string) $token);
        if (!$reset) {
            $response->setStatusCode(400);
            return $response->setContent($this->view('reset', ['title' => 'Reset password', 'invalid' => true]));
        }

        if ($request->getMethod() !== 'POST') {
            return $response->setContent($this->view('reset', ['title' => 'Reset password', 'invalid' => false]));
        }

        if (!RateLimiter::hit('reset:' . getIP(), 10, 3600)['allowed']) {
            $response->setStatusCode(429);
            flash_now('error', 'Too many requests. Try again later.');
            return $response->setContent($this->view('reset', ['title' => 'Reset password', 'invalid' => false]));
        }

        $password = (string) $request->request->get('password', '');
        $errors = password_policy_errors($password, $reset->username);
        if (!hash_equals($password, (string) $request->request->get('password_confirmation', ''))) {
            $errors[] = 'Password confirmation does not match.';
        }
        if ($errors) {
            $response->setStatusCode(422);
            flash_now('error', implode(' ', $errors));
            return $response->setContent($this->view('reset', ['title' => 'Reset password', 'invalid' => false]));
        }

        DB::transaction(function () use ($reset, $password) {
            set_user_password($reset->user_id, $password);   // also ends every session of that user
            DB::exec("UPDATE password_resets SET used_at = ? WHERE user_id = ? AND used_at IS NULL", [time(), $reset->user_id]);
        });
        audit_log('password_reset_completed', '', $reset->user_id);

        flash('status', 'Password updated. Please log in.');
        redirect_to(route('LOGIN'));
    }

    private function findValid(string $token): object|false
    {
        if (!preg_match('/^[A-Za-z0-9]{64}$/', $token)) return false;

        return DB::getRow(
            "SELECT r.id, r.user_id, u.username FROM password_resets r JOIN users u ON u.id = r.user_id
             WHERE r.token_hash = ? AND r.used_at IS NULL AND r.expires_at > ? AND u.status = 1",
            [hash('sha256', $token), time()]
        );
    }
}
