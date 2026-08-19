<?php

namespace Volcy\Translator\Flight;

use flight\Engine;
use Volcy\Translator\BuildRunner;
use Volcy\Translator\Contracts\IdStrategy;
use Volcy\Translator\Drivers\BladeDriver;
use Volcy\Translator\Filesystem\NativeFilesystem;
use Volcy\Translator\Flight\Middleware\LocaleMiddleware;
use Volcy\Translator\IdStrategyResolver;
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
 * 
 * The library now uses compile-time translation injection, eliminating
 * runtime HTML parsing overhead. Templates are compiled separately for each
 * locale with translations inlined directly into the PHP cache.
 */
class TranslatorBootstrap
{
    protected TranslationCatalog $catalog;
    protected RenderedViewsRegistry $registry;
    protected ScanRunner $scanRunner;
    protected BuildRunner $buildRunner;
    protected LocaleMiddleware $middleware;

    protected function __construct(
        protected Engine $app,
        protected array $config,
    ) {
        $filesystem = new NativeFilesystem();
        $resolver = new ViewIndexPathResolver();
        $idStrategyResolver = new IdStrategyResolver($config);
        $idStrategy = $idStrategyResolver->strategy();

        $this->registry = new RenderedViewsRegistry();
        $this->catalog = new TranslationCatalog($filesystem, $resolver, $config['index_path']);
        $this->scanRunner = new ScanRunner(new BladeDriver($idStrategy), $filesystem, $resolver, $idStrategy);
        $this->buildRunner = new BuildRunner($filesystem, $resolver, new TranslationDriverResolver($config));

        $localeResolver = $config['locale_resolver'] ?? static fn () => $config['source_locale'] ?? 'en';
        $fallbackLocale = $config['fallback_locale'] ?? ($config['source_locale'] ?? 'en');

        $this->middleware = new LocaleMiddleware(
            $app,
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

    public function middleware(): LocaleMiddleware
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
     * 
     * The compile path is now locale-specific for compile-time translation.
     * Templates are compiled separately for each locale to enable zero
     * runtime overhead translation.
     * 
     * Note: The locale is resolved at runtime during request processing,
     * not at blade instantiation time. The middleware sets the locale,
     * and the blade instance uses it to determine the correct compilation path.
     */
    public function blade(string $viewsPath, string $compilePath, $mode = null): TrackedBladeOne
    {
        $blade = new TrackedBladeOne($viewsPath, $compilePath, $mode);
        $blade->setRegistry($this->registry);
        $blade->setTranslationCatalog($this->catalog);
        $blade->setIndexPathResolver(new ViewIndexPathResolver());
        $blade->setIndexPath($this->config['index_path']);
        $blade->setCompilePath($compilePath);
        $blade->setLocaleResolver($this->config['locale_resolver'] ?? static fn () => $this->config['source_locale'] ?? 'en');

        return $blade;
    }
}
