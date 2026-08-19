<?php

namespace Volcy\Translator\Flight;

use eftec\bladeone\BladeOne;
use Volcy\Translator\RenderedViewsRegistry;
use Volcy\Translator\TranslationCatalog;
use Volcy\Translator\ViewIndexPathResolver;

/**
 * Drop-in replacement for BladeOne that records every view name rendered
 * during the request and performs compile-time translation injection.
 * Covers both call styles BladeOne supports:
 *
 *   $blade->run('pages.example', [...]);
 *   $blade->setView('pages.example')->run();
 *
 * Deliberately left without parameter type hints on the overrides below,
 * so this stays compatible regardless of the exact signature in whatever
 * eftec/bladeone version is installed.
 */
class TrackedBladeOne extends BladeOne
{
    protected ?RenderedViewsRegistry $registry = null;
    protected string $currentView = "";
    protected string $locale = 'en';
    protected ?TranslationCatalog $catalog = null;
    protected ?ViewIndexPathResolver $resolver = null;
    protected string $indexPath = '';
    protected string $baseCompilePath = '';
    protected $localeResolver = null;
    
    /**
     * @var string|null Dynamic property for BladeOne compilation path compatibility
     */
    protected ?string $compilePath = null;
    
    /**
     * @var string|null Dynamic property for BladeOne view compatibility
     */
    protected ?string $view = null;
    
    /**
     * @var string|null Dynamic property for BladeOne compiledPath compatibility
     */
    protected ?string $compiledPath = null;
    
    /**
     * @var string|null Dynamic property for BladeOne pathCache compatibility
     */
    protected ?string $pathCache = null;

    public function __construct($templatePath = null, $compiledPath = null, $mode = null)
    {
        if ($compiledPath !== null) {
            $this->baseCompilePath = $compiledPath;
        }
        parent::__construct($templatePath, $compiledPath, $mode);
    }

    public function setRegistry(RenderedViewsRegistry $registry): void
    {
        $this->registry = $registry;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function setTranslationCatalog(TranslationCatalog $catalog): void
    {
        $this->catalog = $catalog;
    }

    public function setIndexPathResolver(ViewIndexPathResolver $resolver): void
    {
        $this->resolver = $resolver;
    }

    public function setIndexPath(string $indexPath): void
    {
        $this->indexPath = $indexPath;
    }

    public function setCompilePath(string $compilePath): void
    {
        $this->baseCompilePath = $compilePath;
    }

    public function setLocaleResolver($resolver): void
    {
        $this->localeResolver = $resolver;
    }

    /**
     * Get the current locale and update compilation path accordingly
     */
    protected function resolveLocale(): string
    {
        if ($this->localeResolver !== null) {
            $resolver = $this->localeResolver;
            $this->locale = $resolver instanceof \Closure ? $resolver() : $resolver;
        }
        
        // Also check Flight app container if available
        if (class_exists('flight\Engine') && isset($GLOBALS['flight'])) {
            $flightLocale = $GLOBALS['flight']->get('translator.locale') ?? 'en';
            if ($flightLocale !== 'en') {
                $this->locale = $flightLocale;
            }
        }
        
        return $this->locale;
    }

    /**
     * Get the locale-specific compilation path
     */
    protected function getLocaleCompilePath(): string
    {
        $locale = $this->resolveLocale();
        $localePath = rtrim($this->baseCompilePath, '/\\') . DIRECTORY_SEPARATOR . $locale;
        
        // Ensure the locale-specific directory exists
        if (! is_dir($localePath)) {
            @mkdir($localePath, 0755, true);
        }
        
        return $localePath;
    }

    public function setView($view):BladeOne
    {
        $this->currentView = $view;

        return parent::setView($view);
    }

    /**
     * Override compile to inject compile-time translations.
     * This intercepts the template compilation process and replaces
     * data-i18n attributes with their translated equivalents before
     * the template is cached as PHP.
     */
    public function compile( $value=null,$forced = false)
    {
        $compiled = parent::compile($value,$forced);
        
        // Only perform compile-time translation if we have the required dependencies
        if ($this->catalog !== null && $this->resolver !== null && $this->resolveLocale() !== 'en') {
            $compiled = $this->injectCompileTimeTranslations($compiled);
        }
        
        return $compiled;
    }

    /**
     * Inject translations into the compiled template during compilation.
     * This replaces data-i18n placeholders with actual translated strings,
     * achieving zero runtime overhead.
     */
    protected function injectCompileTimeTranslations(string $compiled): string
    {
        // Get the translation dictionary for the current view
        $viewName = $this->currentView ?: $this->view;
        
        if (empty($viewName)) {
            return $compiled;
        }

        try {
            $dictionary = $this->catalog->forViewsAndLocale([$viewName], $this->resolveLocale());
            
            if (empty($dictionary)) {
                return $compiled;
            }

            // Replace data-i18n attributes with their translations
            // Pattern matches: data-i18n="id" or data-i18n-attr="id"
            $compiled = preg_replace_callback(
                '/data-i18n(?:-[a-z-]+)?\s*=\s*["\']([^"\']+)["\']/',
                function ($matches) use ($dictionary) {
                    $id = trim($matches[1]);
                    if (isset($dictionary[$id])) {
                        // Return the translated string as a PHP echo statement
                        return '<?php echo \'' . addslashes($dictionary[$id]) . '\'; ?>';
                    }
                    return $matches[0]; // Keep original if no translation found
                },
                $compiled
            );

        } catch (\Exception $e) {
            // If translation fails, return original compiled template
            // This ensures the app doesn't break due to translation issues
        }

        return $compiled;
    }

    /**
     * Override run to set locale-specific compilation path before rendering
     */
    public function run($view = null, $variables = [], $mergeData = null): string
    {
        // Set locale-specific compilation path before rendering
        $localePath = $this->getLocaleCompilePath();
        
        // Try to set the compilation path using different property names
        // BladeOne may use different property names depending on version
        if (property_exists($this, 'compiledPath')) {
            $this->compiledPath = $localePath;
        } elseif (property_exists($this, 'pathCache')) {
            $this->pathCache = $localePath;
        } elseif (isset($this->compilePath)) {
            $this->compilePath = $localePath;
        }
        
        $resolvedView = $view ?? $this->currentView;

        if ($resolvedView !== null && $this->registry !== null) {
            $this->registry->add($resolvedView);
        }

        return parent::run($view, $variables, $mergeData);
    }
}
