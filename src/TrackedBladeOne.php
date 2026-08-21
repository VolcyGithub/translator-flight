<?php

namespace Volcy\Translator\Flight;

use eftec\bladeone\BladeOne;
use Volcy\Translator\RenderedViewsRegistry;
use Volcy\Translator\TranslationCatalog;
use Volcy\Translator\ViewIndexPathResolver;
use Volcy\Translator\BalancedElementExtractor;

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

    public function __construct($templatePath = null, $compiledPath = null, $mode = 0)
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

    public function lockLocale(string $locale): void
    {
        $this->locale = $locale;
        $this->localeResolver = null; // CLI compile: explicit locale wins, no dynamic override
    }
    public function setCurrentView(string $viewName): self
    {
        $this->currentView = $viewName;
        return $this;
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
        // First try explicit locale setting (for CLI compilation)
        if ($this->locale !== 'en') {
            return $this->locale;
        }
        
        // Then try locale resolver
        if ($this->localeResolver !== null) {
            $resolver = $this->localeResolver;
            $resolved = $resolver instanceof \Closure ? $resolver() : $resolver;
            if ($resolved !== 'en') {
                $this->locale = $resolved;
                return $this->locale;
            }
        }

        // Also check Flight app container if available
        if (class_exists('flight\Engine') && isset($GLOBALS['flight'])) {
            $flightLocale = $GLOBALS['flight']->get('translator.locale') ?? 'en';
            if ($flightLocale !== 'en') {
                $this->locale = $flightLocale;
                return $this->locale;
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

    public function setView($view): BladeOne
    {
        $this->currentView = $view;

        return parent::setView($view);
    }

   // TrackedBladeOne.php
protected function applyLocaleCompilePath(): void
{
    $localePath = $this->getLocaleCompilePath();

    if (property_exists($this, 'compiledPath')) {
        $this->compiledPath = $localePath;
    } elseif (property_exists($this, 'pathCache')) {
        $this->pathCache = $localePath;
    } elseif (isset($this->compilePath)) {
        $this->compilePath = $localePath;
    }
}
/**
 * Set the locale-specific compile path before compiling/writing to disk.
 */
public function compile($value = null, $forced = false)
{
    $this->applyLocaleCompilePath();
    return parent::compile($value, $forced);
}

public function run($view = null, $variables = [], $mergeData = null): string
{
    $this->applyLocaleCompilePath();

    $resolvedView = $view ?? $this->currentView;
    if ($resolvedView !== null && $this->registry !== null) {
        $this->registry->add($resolvedView);
    }

    return parent::run($view, $variables, $mergeData);
}

   // TrackedBladeOne.php
public function compileString($value): string
{
    // Inject compile-time translations BEFORE BladeOne compilation
    if ($this->catalog !== null && $this->resolver !== null && $this->resolveLocale() !== 'fr') {
        $value = $this->injectCompileTimeTranslations($value);
    }
    return parent::compileString($value);
}

protected function injectCompileTimeTranslations(string $rawSource): string
{
    $viewName = $this->currentView ?: $this->view;
    
    if (empty($viewName)) {
        return $rawSource;
    }

    try {
        $dictionary = $this->catalog->forViewsAndLocale([$viewName], $this->resolveLocale());
    } catch (\Exception $e) {
        return $rawSource;
    }

    if (empty($dictionary)) {
        return $rawSource;
    }

    $content = $rawSource;

    foreach ($dictionary as $id => $translatedText) {
        foreach (['"', "'"] as $q) {
            $extracted = BalancedElementExtractor::extractByAttribute($content, "data-i18n={$q}{$id}{$q}");
            if ($extracted !== null) {
                $content = substr_replace(
                    $content,
                    $translatedText,
                    $extracted['inner_start'],
                    $extracted['inner_end'] - $extracted['inner_start']
                );
                break;
            }
        }
    }

    // Handle custom data-i18n-* attributes (data-i18n-loading, data-i18n-text, etc.)
    // Replace data-i18n-* attribute values with translations
    $content = preg_replace_callback(
        '/data-i18n-([a-zA-Z0-9_-]+)\s*=\s*["\']([^"\']+)["\']/i',
        function ($matches) use ($dictionary) {
            $attrName = $matches[1]; // e.g., 'loading', 'text'
            $attrValue = $matches[2]; // the original text/ID
            
            // Check if this is a translation ID in our dictionary
            if (isset($dictionary[$attrValue])) {
                $translatedText = htmlspecialchars($dictionary[$attrValue], ENT_QUOTES, 'UTF-8');
                return "data-i18n-{$attrName}=\"{$translatedText}\"";
            }
            
            // If not found in dictionary, keep original
            return $matches[0];
        },
        $content
    );

    // Handle paired attributes (data-i18n-* with corresponding attribute replacement)
    // e.g., data-i18n-placeholder="id" + placeholder="original" -> placeholder="translated"
    return preg_replace_callback(
        '/data-i18n-([a-z-]+)\s*=\s*["\']([^"\']+)["\'](?<between>[^>]*?)\b\1\s*=\s*(["\'])[^"\']*\4/is',
        function ($m) use ($dictionary) {
            $id = $m[2];
            if (!isset($dictionary[$id])) {
                return $m[0];
            }
            $val = htmlspecialchars($dictionary[$id], ENT_QUOTES, 'UTF-8');
            return "data-i18n-{$m[1]}=\"{$id}\"{$m['between']}{$m[1]}=\"{$val}\"";
        },
        $content
    );
}

   
}
