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
    private const MAX_USER_ATTEMPTS = 10;     // failed attempts per username in WINDOW (any IP), not applied to the account's last login IP
    private const WINDOW = 900; // seconds

    /**
     * Hash verified against when the username does not exist, so timing does not reveal valid usernames.
     * It must have the same cost as real hashes (PASSWORD_DEFAULT changes between PHP versions), so it is created
     * once with the current default and kept in content/cache.
     */
    protected static function dummyHash(): string
    {
        static $hash = null;
        if ($hash !== null) return $hash;

        $file = BASEPATH . '/content/cache/dummy.hash';
        $hash = is_file($file) ? trim((string) file_get_contents($file)) : '';
        if ($hash === '' || password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            @file_put_contents($file, $hash, LOCK_EX);
        }
        return $hash;
    }

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

        $user = $username === '' ? false : DB::getRow(
            "SELECT * FROM users WHERE username = ? AND status = 1" . $this->accountCondition(),
            [$username]
        );

        // Record the attempt first, then count (it includes this one): parallel requests cannot slip past the limit together.
        $name = mb_substr($username, 0, 191);
        $since = time() - self::WINDOW;
        DB::insert("INSERT INTO login_attempts (ip, username, time) VALUES (?, ?, ?)", [$ip, $name, time()]);

        $ipBlocked = DB::getVar("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND time > ?", [$ip, $since]) > self::MAX_ATTEMPTS;
        // The username limit stops distributed guessing, but must not let a stranger lock the owner out:
        // it does not apply to the IP the account last signed in from.
        $knownIp = $user && $user->last_ip === $ip;
        $userBlocked = !$knownIp && $username !== ''
            && DB::getVar("SELECT COUNT(*) FROM login_attempts WHERE username = ? AND time > ?", [$name, $since]) > self::MAX_USER_ATTEMPTS;
        if ($ipBlocked || $userBlocked) {
            $response->setStatusCode(429);
            return $this->form($response, 'Too many failed attempts. Try again later.');
        }

        if (password_verify($password, $user ? $user->password : static::dummyHash()) && $user) {
            $this->succeed($request, $user, $password, $ip);
        }

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
        // forget only the attempts for this account from this IP; other usernames keep counting
        DB::exec("DELETE FROM login_attempts WHERE ip = ? AND username = ?", [$ip, mb_substr((string) $user->username, 0, 191)]);

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
        remember_token_issue((int) $user->id);
    }
}
