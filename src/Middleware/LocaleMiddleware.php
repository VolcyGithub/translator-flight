<?php

namespace Volcy\Translator\Flight\Middleware;

use flight\Engine;

/**
 * Lightweight Locale Router Middleware.
 * 
 * This middleware has been refactored from runtime HTML parsing to a simple
 * locale router. It only determines the user's active locale and sets the
 * global framework locale environment before running the route.
 * 
 * The actual translation is now handled at compile-time by TrackedBladeOne,
 * which injects translations during template compilation rather than parsing
 * HTML on every request. This achieves zero runtime overhead.
 * 
 * Attach it explicitly wherever translation should apply - a route group,
 * not globally - same reasoning as the Laravel bridge: which routes get
 * translated is the app's decision, not this package's.
 *
 *   Flight::group('/', function () { ... }, [$localeMiddleware]);
 */
class LocaleMiddleware
{
    public function __construct(
        protected Engine $app,
        protected \Closure $localeResolver,
        protected string $fallbackLocale = 'en',
    ) {
    }

    public function before(): void
    {
        // Resolve the current locale
        $locale = ($this->localeResolver)();
        
        // Set the locale globally for the request
        // This makes the locale available to TrackedBladeOne for compile-time path resolution
        $this->app->set('locale', $locale);
        
        // Store as a global constant or variable for BladeOne to access
        if (! defined('APP_LOCALE')) {
            define('APP_LOCALE', $locale);
        }
        
        // Store in Flight's app container for easy access
        $this->app->set('translator.locale', $locale);
        $this->app->set('translator.fallback_locale', $this->fallbackLocale);
    }
}
