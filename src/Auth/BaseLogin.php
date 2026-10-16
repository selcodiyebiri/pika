<?php
namespace Pika\Auth;

use Pika\Controller,
    Pika\DB,
    Symfony\Component\HttpFoundation\Request,
    Symfony\Component\HttpFoundation\Response;

/**
 * Shared login flow (throttling, password check, rehash, logging).
 * Subclasses (site Login, Admin\Login) only say who may log in and what happens around the session.
 */
abstract class BaseLogin extends Controller
{
    private const MAX_ATTEMPTS = 5;           // failed attempts per IP in WINDOW
    private const MAX_USER_ATTEMPTS = 10;     // failed attempts per username in WINDOW (any IP)
    private const WINDOW = 900; // seconds
    // Verified against when the username does not exist, so timing does not reveal valid usernames
    private const DUMMY_HASH = '$2y$12$3EZzsI3k4.Lmd.VNhPjCfO0FbtGO8HctaRu372Cmj.xxQnB5Gu84a';

    /** Show the "remember me" checkbox on the form. */
    protected bool $remember = false;
    /** Route name of the "forgot password" page; null hides the link. */
    protected ?string $forgotRoute = null;
    /** Small label above the form heading, e.g. "Administration". */
    protected ?string $badge = null;

    abstract protected function isLoggedIn(): bool;
    /** Extra SQL appended to the user lookup, e.g. " AND admin = 1". */
    abstract protected function accountCondition(): string;
    abstract protected function startSession(object $user, Request $request): void;
    abstract protected function endSession(): void;
    abstract protected function homeUrl(): string;
    abstract protected function loginUrl(): string;
    /** Value stored in user_logs.action. */
    abstract protected function logAction(): string;

    public function login(Request $request, Response $response)
    {
        if ($this->isLoggedIn()) {
            redirect_to($this->homeUrl());
        }

        if ($request->getMethod() !== 'POST') {
            return $this->form($response);
        }

        $ip = getIP();
        $username = trim((string) $request->request->get('username', ''));
        $password = (string) $request->request->get('password', '');

        $since = time() - self::WINDOW;
        if (DB::getVar("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND time > ?", [$ip, $since]) >= self::MAX_ATTEMPTS
            || ($username !== '' && DB::getVar("SELECT COUNT(*) FROM login_attempts WHERE username = ? AND time > ?", [mb_substr($username, 0, 191), $since]) >= self::MAX_USER_ATTEMPTS)) {
            $response->setStatusCode(429);
            return $this->form($response, 'Too many failed attempts. Try again later.');
        }

        $user = $username === '' ? false : DB::getRow(
            "SELECT * FROM users WHERE username = ? AND status = 1" . $this->accountCondition(),
            [$username]
        );

        if (password_verify($password, $user ? $user->password : self::DUMMY_HASH) && $user) {
            $this->succeed($request, $user, $password, $ip);
        }

        DB::insert("INSERT INTO login_attempts (ip, username, time) VALUES (?, ?, ?)", [$ip, mb_substr($username, 0, 191), time()]);
        $response->setStatusCode(401);
        return $this->form($response, 'Invalid username or password.');
    }

    public function logout()
    {
        audit_log('logout');
        $this->endSession();
        redirect_to($this->loginUrl());
    }

    private function form(Response $response, ?string $error = null)
    {
        return $response->setContent($this->view('login', [
            'title'     => 'Login',
            'error'     => $error,
            'remember'  => $this->remember,
            'forgotUrl' => $this->forgotRoute ? route($this->forgotRoute) : null,
            'badge'     => $this->badge,
        ]));
    }

    private function succeed(Request $request, object $user, string $password, string $ip): void
    {
        DB::exec("DELETE FROM login_attempts WHERE ip = ?", [$ip]);

        $agent = $request->headers->get('User-Agent', '');
        DB::exec("UPDATE users SET last_ip = ?, user_agent = ? WHERE id = ?", [$ip, mb_substr($agent, 0, 255), $user->id]);
        if (password_needs_rehash($user->password, PASSWORD_DEFAULT)) {
            DB::exec("UPDATE users SET password = ? WHERE id = ?", [password_hash($password, PASSWORD_DEFAULT), $user->id]);
        }

        // reload so the session fingerprint uses the final password hash
        $user = DB::getRow("SELECT * FROM users WHERE id = ?", [$user->id]);
        $this->startSession($user, $request);

        user_log_add([
            'user_id' => $user->id, 'action' => $this->logAction(),
            'description' => 'successful login', 'ip' => $ip, 'user_agent' => $agent,
        ]);

        redirect_to($this->homeUrl());
    }

    /** Issue a remember-me cookie; the database keeps only its sha256 hash. */
    protected function issueRememberToken(object $user): void
    {
        $token = hash_generator(64);
        DB::exec("UPDATE users SET remember_token = ? WHERE id = ?", [hash('sha256', $token), $user->id]);
        setcookie('remember_token', $token, [
            'expires' => time() + 30 * 86400, 'path' => '/',
            'secure' => is_https(), 'httponly' => true, 'samesite' => 'Strict',
        ]);
    }
}
