<?php

namespace Volcy\Translator\Flight\Middleware;

use flight\Engine;
use Volcy\Translator\RenderedViewsRegistry;
use Volcy\Translator\TranslationCatalog;

/**
 * Flight middleware, following the documented convention of a plain
 * class with a before()/after() method (see Flight's own MinifyMiddleware
 * example). Attach it explicitly wherever translation should apply -
 * a route group, not globally - same reasoning as the Laravel bridge:
 * which routes get translated is the app's decision, not this package's.
 *
 *   Flight::group('/', function () { ... }, [$translateMiddleware]);
 */
class TranslateMiddleware
{
    public function __construct(
        protected Engine $app,
        protected TranslationCatalog $catalog,
        protected RenderedViewsRegistry $registry,
        protected \Closure $localeResolver,
        protected string $fallbackLocale,
    ) {
    }

    public function before(): void
    {
        $catalog = $this->catalog;
        $registry = $this->registry;
        $localeResolver = $this->localeResolver;
        $fallbackLocale = $this->fallbackLocale;

        $this->app->response()->addResponseBodyCallback(
            function (string $body) use ($catalog, $registry, $localeResolver, $fallbackLocale): string {
                $locale = $localeResolver();

                if ($locale === $fallbackLocale) {
                    return $body;
                }

                $dictionary = $catalog->forViewsAndLocale($registry->all(), $locale);

                if (empty($dictionary)) {
                    return $body;
                }

                return $catalog->applyToHtml($body, $dictionary);
            }
        );
    }
}
