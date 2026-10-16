<?php
namespace Tests\Support;

/** Tiny cURL client with its own cookie jar. Never follows redirects. */
class HttpClient
{
    private string $jar;
    public array $defaultHeaders = [];

    public function __construct(private string $baseUrl)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'pika-jar-');
    }

    public function __destruct() { @unlink($this->jar); }

    /** @return object{status:int, headers:array<string,string>, body:string, location:?string}  (repeated headers are joined with ", ") */
    public function request(string $method, string $path, array $data = [], array $headers = []): object
    {
        $ch = curl_init($this->baseUrl . $path);
        $hdrs = [];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY         => $method === 'HEAD',
            CURLOPT_COOKIEJAR      => $this->jar,
            CURLOPT_COOKIEFILE     => $this->jar,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => array_map(fn($k, $v) => "$k: $v", array_keys($headers + $this->defaultHeaders), array_values($headers + $this->defaultHeaders)),
            CURLOPT_HEADERFUNCTION => function ($c, $line) use (&$hdrs) {
                if (str_contains($line, ':')) { [$k, $v] = explode(':', $line, 2); $k = strtolower(trim($k)); $hdrs[$k] = isset($hdrs[$k]) ? $hdrs[$k] . ', ' . trim($v) : trim($v); }
                return strlen($line);
            },
        ]);
        if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return (object) ['status' => $status, 'headers' => $hdrs, 'body' => (string) $body, 'location' => $hdrs['location'] ?? null];
    }

    public function get(string $path, array $headers = []): object { return $this->request('GET', $path, [], $headers); }
    public function post(string $path, array $data = [], array $headers = []): object { return $this->request('POST', $path, $data, $headers); }

    /** Value of a cookie from the jar (null if absent). */
    public function cookie(string $name): ?string
    {
        foreach (file($this->jar) as $line) {
            $line = preg_replace('/^#HttpOnly_/', '', trim($line));
            $f = explode("\t", $line);
            if (count($f) >= 7 && $f[5] === $name) return $f[6];
        }
        return null;
    }

    /** GET a page and return its hidden CSRF token. */
    public function csrf(string $path): string
    {
        preg_match('/name="token" value="([^"]+)"/', $this->get($path)->body, $m);
        return $m[1] ?? '';
    }
}
