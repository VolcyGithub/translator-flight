<?php

namespace Volcy\Translator\Flight\Commands;

use Flight;
use flight\commands\AbstractBaseCommand;

class CompileViewsCommand extends AbstractBaseCommand
{
    public function __construct(array $config)
    {
        parent::__construct('translator:compile', 'Pre-compile views for all locales into static PHP cache files', $config);
        $this->option('--locales [locales]', 'Comma-separated target locales (e.g. fr,es)');
    }

    public function execute(): void
    {
        $io = $this->app()->io();
        $config = $this->config['translator'] ?? [];
        $viewsPath = $config['views_path'] ?? null;
        $compilePath = $config['compile_path'] ?? null;
        
        $targetLocales = array_map('trim', explode(',', $this->values()['locales'] ?? 'fr,es'));

        // Get Blade instance from Flight container or bootstrap
        $translator = \Volcy\Translator\Flight\TranslatorBootstrap::register(Flight::app(), $config);
        $blade = $translator->blade($viewsPath, $compilePath);

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($viewsPath));

        foreach ($targetLocales as $locale) {
            $blade->setLocale($locale);

            foreach ($iterator as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                    // Extract relative view path (e.g. "pages.home")
                    $relativePath = ltrim(str_replace($viewsPath, '', $file->getPathname()), '/\\');
                    $viewName = str_replace(['.blade.php', '/', '\\'], ['', '.', '.'], $relativePath);

                    // Tell blade which view is compiling so it loads the correct dictionary
                    $blade->setCurrentView($viewName);

                    // Compile the template (triggers injectCompileTimeTranslations!)
                    $blade->compile($file->getPathname(), true);
                }
            }
            $io->ok("Successfully pre-compiled views for locale [{$locale}].");
        }
    }
}