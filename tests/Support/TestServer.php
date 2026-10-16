<?php
namespace Tests\Support;

/** Runs the real app with PHP's built-in server for the duration of a test class. */
class TestServer
{
    private $process;
    public string $url;
    public int $port;

    /** Environment variables that configure the app; a "clean" server drops them so only its own .env counts. */
    private const CONFIG_KEYS = ['APP_ENV', 'APP_DEBUG', 'PIKA_LICENSE', 'SITE_URL', 'ADMIN_URL', 'APP_TIMEZONE', 'MYSQL_HOST', 'MYSQL_PORT',
        'MYSQL_SOCKET', 'MYSQL_DB', 'MYSQL_USER', 'MYSQL_PASS', 'TRUSTED_PROXIES', 'SESSION_IDLE', 'SESSION_ABSOLUTE', 'RATE_LIMIT',
        'RATE_WINDOW', 'MAIL_DRIVER', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_USER', 'MAIL_PASS', 'MAIL_ENCRYPTION', 'MAIL_FROM', 'MAIL_FROM_NAME', 'CSP'];

    /**
     * @param string|null $root project folder to serve (default: this project)
     * @param bool $clean start without any configuration variables, so the app reads only the .env inside $root
     */
    public function __construct(array $env = [], ?string $root = null, bool $clean = false)
    {
        $root ??= BASEPATH;
        $sock = stream_socket_server('tcp://127.0.0.1:0');
        $this->port = (int) explode(':', stream_socket_get_name($sock, false))[1];
        fclose($sock);
        $this->url = "http://127.0.0.1:{$this->port}";

        $base = array_filter(array_merge(getenv(), $_ENV), 'is_string');
        if ($clean) $base = array_diff_key($base, array_flip(self::CONFIG_KEYS));
        $this->process = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:{$this->port}", '-t', $root . '/public', $root . '/includes/dev_router.php'],
            [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
            $root,
            array_merge($base, $clean ? [] : ['TRUSTED_PROXIES' => '127.0.0.1'], $env)
        );

        for ($i = 0; $i < 50; $i++) {
            if (@fsockopen('127.0.0.1', $this->port, $errno, $errstr, 0.1)) return;
            usleep(100_000);
        }
        throw new \RuntimeException('Test server did not start.');
    }

    public function client(string $ip = '10.1.1.1'): HttpClient
    {
        $c = new HttpClient($this->url);
        $c->defaultHeaders['X-Forwarded-For'] = $ip;   // the server trusts 127.0.0.1 as a proxy, so tests control the client IP
        return $c;
    }

    public function stop(): void
    {
        if ($this->process) { proc_terminate($this->process); proc_close($this->process); $this->process = null; }
    }
}
