<?php
use PHPUnit\Framework\TestCase;
use Pika\AdminController;
use Pika\Controller;

class ControllerThemeTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        defined('SITE_THEME_PATH_MAIN') || define('SITE_THEME_PATH_MAIN', BASEPATH . '/content/themes');
        defined('SITE_SHARED_THEME_PATH') || define('SITE_SHARED_THEME_PATH', SITE_THEME_PATH_MAIN . '/shared');
        defined('SITE_CACHE_PATH') || define('SITE_CACHE_PATH', sys_get_temp_dir());
    }

    protected function setUp(): void { $_SESSION = []; }

    public function testThemeSpecificViewIsRenderedFromItsOwnTheme(): void
    {
        $this->assertMatchesRegularExpression('#<h1[^>]*>Site</h1>#', (new Controller)->view('home', ['title' => 'Site']));
        $this->assertMatchesRegularExpression('#<h1[^>]*>Admin</h1>#', (new AdminController)->view('dashboard', ['title' => 'Admin']));
    }

    public function testSharedViewIsUsedByEveryTheme(): void
    {
        $data = ['title' => 'Login', 'error' => null, 'remember' => false];
        foreach ([new Controller, new AdminController] as $controller) {
            $html = $controller->view('login', $data);
            $this->assertStringContainsString('name="username"', $html);
            $this->assertStringContainsString('name="token"', $html);
        }
    }

    public function testRememberCheckboxFollowsFlagAndErrorIsEscaped(): void
    {
        $with = (new Controller)->view('login', ['title' => 'L', 'error' => '<b>x</b>', 'remember' => true]);
        $this->assertStringContainsString('name="remember"', $with);
        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $with);
        $this->assertStringNotContainsString('name="remember"', (new Controller)->view('login', ['title' => 'L', 'error' => null, 'remember' => false]));
    }

    public function testViewMissingFromThemeButPresentOnlyInAnotherThemeIsNotFound(): void
    {
        $this->expectException(InvalidArgumentException::class); // "View [dashboard] not found." (admin-only view)
        (new Controller)->view('dashboard', ['title' => 'x']);
    }

    public function testUnknownThemeThrows(): void
    {
        $controller = new class extends Controller { protected string $theme = 'nope'; };
        $this->expectException(RuntimeException::class);
        $controller->view('home');
    }
}
