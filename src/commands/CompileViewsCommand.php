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

    $excludedFolders = array_map(
        fn($f) => trim(str_replace('\\', '/', $f), '/'),
        $config['excluded_folders'] ?? []
    );

    $targetLocales = array_map('trim', explode(',', $this->values()['locales'] ?? 'fr'));

    $translator = \Volcy\Translator\Flight\TranslatorBootstrap::register(Flight::app(), $config);
    $blade = $translator->blade($viewsPath, $compilePath);

    foreach ($targetLocales as $locale) {
        $blade->lockLocale($locale);

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($viewsPath));

        foreach ($iterator as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $relativePath = ltrim(str_replace($viewsPath, '', $file->getPathname()), '/\\');
            $normalized = str_replace('\\', '/', $relativePath);

            if ($this->isExcluded($normalized, $excludedFolders)) {
                continue;
            }

            $viewName = str_replace(['.blade.php', '/', '\\'], ['', '.', '.'], $relativePath);

            $blade->setCurrentView($viewName);
            $blade->compile($viewName, true);
        }

        $io->ok("Successfully pre-compiled views for locale [{$locale}].");
    }
}

private function isExcluded(string $relativePath, array $excludedFolders): bool
{
    foreach ($excludedFolders as $folder) {
        if ($folder === '') {
            continue;
        }
        if ($relativePath === $folder || str_starts_with($relativePath, $folder . '/')) {
            return true;
        }
    }

    return false;
}
}