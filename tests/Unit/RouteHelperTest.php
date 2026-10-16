<?php
use PHPUnit\Framework\TestCase;
use Pika\Controller;

class RouteHelperTest extends TestCase
{
    private const THEME = 'zz_route_theme';
    private mixed $previousRouter;

    public static function setUpBeforeClass(): void
    {
        defined('SITE_THEME_PATH_MAIN') || define('SITE_THEME_PATH_MAIN', BASEPATH . '/content/themes');
        defined('SITE_SHARED_THEME_PATH') || define('SITE_SHARED_THEME_PATH', SITE_THEME_PATH_MAIN . '/shared');
        defined('SITE_CACHE_PATH') || define('SITE_CACHE_PATH', sys_get_temp_dir());
    }

    protected function setUp(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SESSION = [];

        $this->previousRouter = $GLOBALS['router'] ?? null;
        $GLOBALS['router'] = new class {
            public function getRoutes(): array
            {
                return [
                    ['route' => '/', 'name' => 'HOME'],
                    ['route' => '/login', 'name' => 'LOGIN'],
                    ['route' => '/admin/login', 'name' => 'ADMIN_LOGIN'],
                    ['route' => '/reset/:any', 'name' => 'RESET'],
                    ['route' => '/posts/:id/comments/:slug', 'name' => 'COMMENTS'],
                    ['route' => '/a&b', 'name' => 'AMP'],
                    ['route' => '/unnamed'],
                ];
            }
        };
    }

    protected function tearDown(): void
    {
        $GLOBALS['router'] = $this->previousRouter;
        $dir = BASEPATH . '/content/themes/' . self::THEME;
        foreach (glob("$dir/*") ?: [] as $f) unlink($f);
        @rmdir($dir);
    }

    public function testBuildsUrlsWithoutDoubleSlashes(): void
    {
        $this->assertSame('http://example.com/', route('HOME'));
        $this->assertSame('http://example.com/login/', route('LOGIN'));
        $this->assertSame('http://example.com/admin/login/', route('ADMIN_LOGIN'));
    }

    public function testRespectsTheSubFolderTheAppIsMountedIn(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/pika/public/index.php';
        $this->assertSame('http://example.com/pika/', route('HOME'));
        $this->assertSame('http://example.com/pika/login/', route('LOGIN'));
    }

    public function testFillsParameters(): void
    {
        $this->assertSame('http://example.com/reset/abc123/', route('RESET', ['any' => 'abc123']));
        $this->assertSame('http://example.com/posts/7/comments/hello-world/', route('COMMENTS', ['id' => 7, 'slug' => 'hello-world']));
    }

    public function testParameterValuesAreUrlEncoded(): void
    {
        $this->assertSame('http://example.com/reset/a%20b%2Fc/', route('RESET', ['any' => 'a b/c']));

        $url = route('RESET', ['any' => '"><script>alert(1)</script>']);
        $this->assertStringNotContainsString('<script>', $url);
        $this->assertStringNotContainsString('"', $url);

        // regex back-references and backslashes in a value are plain text, not replacement syntax
        $this->assertSame('http://example.com/reset/%241%5C0/', route('RESET', ['any' => '$1\\0']));
    }

    public function testMissingParametersAndUnknownNamesReturnNullAndAreLogged(): void
    {
        $log = BASEPATH . '/content/logs/pika-' . date('Y-m-d') . '.log';
        $before = is_file($log) ? filesize($log) : 0;

        $this->assertNull(route('RESET'));
        $this->assertNull(route('COMMENTS', ['id' => 7]));
        $this->assertNull(route('DOES_NOT_EXIST'));
        $this->assertNull(route('unnamed'));

        clearstatcache();
        $written = substr((string) file_get_contents($log), $before);
        $this->assertStringContainsString("route('RESET') is missing a parameter", $written);
        $this->assertStringContainsString("route('DOES_NOT_EXIST') does not exist", $written);
    }

    public function testCalledBeforeTheRouterExistsItReturnsNullInsteadOfCrashing(): void
    {
        $GLOBALS['router'] = null;
        $this->assertNull(route('LOGIN'));
        unset($GLOBALS['router']);
        $this->assertNull(route('LOGIN'));
    }

    public function testNamesAreCaseSensitive(): void
    {
        $this->assertNull(route('login'));
    }

    private function render(string $template, array $data = []): string
    {
        $dir = BASEPATH . '/content/themes/' . self::THEME;
        @mkdir($dir);
        $view = 'probe_' . bin2hex(random_bytes(4));   // a new name each time: Blade caches compiled views by file
        file_put_contents("$dir/$view.blade.php", $template);

        return (new class extends Controller { protected string $theme = 'zz_route_theme'; })->view($view, $data);
    }

    public function testBladeDirectiveAndFunctionAgree(): void
    {
        $html = $this->render("A=@route('LOGIN')|B={{ route('LOGIN') }}|C=@route('RESET', ['any' => 'x1'])|D={{ route('RESET', ['any' => 'x1']) }}");
        $this->assertSame('A=http://example.com/login/|B=http://example.com/login/|C=http://example.com/reset/x1/|D=http://example.com/reset/x1/', trim($html));
    }

    public function testBladeDirectiveEscapesHtmlAndHandlesNull(): void
    {
        $this->assertSame('X=http://example.com/a&amp;b/|', trim($this->render("X=@route('AMP')|")), 'escaped like {{ }}');
        $this->assertSame('Y=|', trim($this->render("Y=@route('DOES_NOT_EXIST')|")), 'unknown name prints nothing');

        $html = $this->render("<a href=\"@route('RESET', ['any' => \$token])\">go</a>", ['token' => 'a b']);
        $this->assertStringContainsString('href="http://example.com/reset/a%20b/"', $html);
    }
}
