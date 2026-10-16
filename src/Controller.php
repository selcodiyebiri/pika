<?php
namespace Pika;

use Illuminate\Container\Container as IlluminateContainer;
use Jenssegers\Blade\Blade;
use Jenssegers\Blade\Container;

/**
 * Base controller. A controller picks its theme with $theme (a folder in content/themes);
 * views are looked up in that theme first, then in content/themes/shared.
 */
class Controller
{
    protected string $theme = 'default';

    private static array $engines = [];

    public function view(string $view, array $data = []): string
    {
        if (!isset(self::$engines[$this->theme])) {
            $dir = SITE_THEME_PATH_MAIN . '/' . $this->theme;
            if (!is_dir($dir)) {
                throw new \RuntimeException("Theme not found: {$this->theme}");
            }

            $container = new Container;
            $blade = new Blade([$dir, SITE_SHARED_THEME_PATH], SITE_CACHE_PATH, $container);
            // @route('NAME', ['param' => 'value']) prints the escaped URL, like {{ route(...) }}
            $blade->directive('route', fn($expression) => "<?php echo htmlspecialchars((string) route($expression), ENT_QUOTES); ?>");
            self::$engines[$this->theme] = [$blade, $container];
        }

        [$blade, $container] = self::$engines[$this->theme];
        // Illuminate 11 resolves the Blade engine through the global container instance,
        // which jenssegers/blade 2.0 does not set. One container per theme, set before each render.
        IlluminateContainer::setInstance($container);

        return $blade->render($view, $data);
    }

    /** Stop with 403 (and an audit entry) unless the current account has the permission. */
    protected function authorize(string $permission): void
    {
        if (!can($permission)) {
            audit_log('permission_denied', $permission);
            abort(403);
        }
    }
}
