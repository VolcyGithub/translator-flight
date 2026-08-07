<?php

declare(strict_types=1);

namespace Volcy\Translator\Flight\Commands;

use flight\commands\AbstractBaseCommand;
use Volcy\Translator\Drivers\BladeDriver;
use Volcy\Translator\Filesystem\NativeFilesystem;
use Volcy\Translator\ScanRunner;
use Volcy\Translator\ViewIndexPathResolver;

class ScanViewsCommand extends AbstractBaseCommand
{
    /**
     * @param array<string, mixed> $config Config from app/config/config.php
     */
    public function __construct(array $config)
    {
        parent::__construct('translator:scan', 'Scan Blade views and write the source-locale translation index', $config);

        // Single-word option names used deliberately: adhocore/cli's
        // magic-property key conversion for multi-word/dashed option
        // names isn't fully documented, so we read everything back via
        // the explicitly-documented values() array instead of guessing
        // at a property name.
        $this->option('--path [path]', 'Views path to scan (defaults to translator.views_path in config)');
        $this->option('--source [locale]', 'Source locale to write (defaults to translator.source_locale in config)');
    }

    public function execute(): void
    {
        $io = $this->app()->io();
        $values = $this->values();

        $translatorConfig = $this->config['translator'] ?? [];

        $viewsPath = $values['path'] ?? ($translatorConfig['views_path'] ?? null);
        $indexPath = $translatorConfig['index_path'] ?? null;
        $sourceLocale = $values['source'] ?? ($translatorConfig['source_locale'] ?? 'en');

        if (! $viewsPath || ! is_dir($viewsPath)) {
            $io->error("Views path not found: {$viewsPath}");

            return;
        }

        if (! $indexPath) {
            $io->error('translator.index_path is not configured.');

            return;
        }

        $runner = new ScanRunner(new BladeDriver(), new NativeFilesystem(), new ViewIndexPathResolver());
        $result = $runner->run($viewsPath, $indexPath, $sourceLocale);

        $io->ok("Scanned {$result['written']} file(s) into locale [{$sourceLocale}].");
    }
}
