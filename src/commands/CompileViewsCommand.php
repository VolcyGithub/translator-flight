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

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                // 1. Get real full path and normalize Windows backslashes
                $fullPath = str_replace('\\', '/', $file->getRealPath());
                $normalizedViewsPath = str_replace('\\', '/', realpath($viewsPath));

                // 2. Extract relative view path cleanly
                $relativePath = ltrim(str_replace($normalizedViewsPath, '', $fullPath), '/');

                // 3. Convert relative path to standard Blade view notation (e.g., "admin.components.cards.member")
                $viewName = str_replace(['.blade.php', '/'], ['', '.'], $relativePath);

                // 4. Set current view so TrackedBladeOne loads the dictionary index
                $blade->setCurrentView($viewName);

                // 5. Force compilation using the clean view dot-notation name instead of raw path
                $blade->compile($viewName, true);
            }
        }
    }
}
