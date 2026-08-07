<?php

namespace Volcy\Translator\Flight;

use flight\Engine;
use Volcy\Translator\BuildRunner;
use Volcy\Translator\Drivers\BladeDriver;
use Volcy\Translator\Filesystem\NativeFilesystem;
use Volcy\Translator\Flight\Middleware\TranslateMiddleware;
use Volcy\Translator\RenderedViewsRegistry;
use Volcy\Translator\ScanRunner;
use Volcy\Translator\TranslationCatalog;
use Volcy\Translator\TranslationDriverResolver;
use Volcy\Translator\ViewIndexPathResolver;

/**
 * Flight has no service-provider/auto-registration system, so this is
 * called explicitly once from the app's own bootstrap (index.php or
 * app/config/config.php), the same place BladeOne itself is normally
 * instantiated:
 *
 *   $translator = TranslatorBootstrap::register($app, [
 *       'index_path'      => __DIR__ . '/../storage/translator/indexes',
 *       'views_path'      => __DIR__ . '/../app/views',
 *       'source_locale'   => 'en',
 *       'fallback_locale' => 'en',
 *       'locale_resolver' => fn () => Session::get('locale', 'en'),
 *   ]);
 *
 *   $blade = $translator->blade($viewsPath, $compilePath);
 *
 *   Flight::group('/', function () { ... }, [$translator->middleware()]);
 */
class TranslatorBootstrap
{
    protected TranslationCatalog $catalog;
    protected RenderedViewsRegistry $registry;
    protected ScanRunner $scanRunner;
    protected BuildRunner $buildRunner;
    protected TranslateMiddleware $middleware;

    protected function __construct(
        protected Engine $app,
        protected array $config,
    ) {
        $filesystem = new NativeFilesystem();
        $resolver = new ViewIndexPathResolver();

        $this->registry = new RenderedViewsRegistry();
        $this->catalog = new TranslationCatalog($filesystem, $resolver, $config['index_path']);
        $this->scanRunner = new ScanRunner(new BladeDriver(), $filesystem, $resolver);
        $this->buildRunner = new BuildRunner($filesystem, $resolver, new TranslationDriverResolver($config));

        $localeResolver = $config['locale_resolver'] ?? static fn () => $config['source_locale'] ?? 'en';
        $fallbackLocale = $config['fallback_locale'] ?? ($config['source_locale'] ?? 'en');

        $this->middleware = new TranslateMiddleware(
            $app,
            $this->catalog,
            $this->registry,
            $localeResolver instanceof \Closure ? $localeResolver : \Closure::fromCallable($localeResolver),
            $fallbackLocale
        );

        // Available to app code and to Runway commands via Flight::get(...)
        $app->set('translator.registry', $this->registry);
        $app->set('translator.catalog', $this->catalog);
        $app->set('translator.scan_runner', $this->scanRunner);
        $app->set('translator.build_runner', $this->buildRunner);
        $app->set('translator.config', $config);
    }

    public static function register(Engine $app, array $config): self
    {
        return new self($app, $config);
    }

    public function middleware(): TranslateMiddleware
    {
        return $this->middleware;
    }

    public function registry(): RenderedViewsRegistry
    {
        return $this->registry;
    }

    public function catalog(): TranslationCatalog
    {
        return $this->catalog;
    }

    public function scanRunner(): ScanRunner
    {
        return $this->scanRunner;
    }

    public function buildRunner(): BuildRunner
    {
        return $this->buildRunner;
    }

    /**
     * Convenience factory for a TrackedBladeOne instance already wired
     * to this bootstrap's registry, so view names get recorded
     * automatically as soon as the app renders with it.
     */
    public function blade(string $viewsPath, string $compilePath, $mode = null): TrackedBladeOne
    {
        $blade = new TrackedBladeOne($viewsPath, $compilePath, $mode);
        $blade->setRegistry($this->registry);

        return $blade;
    }
}
