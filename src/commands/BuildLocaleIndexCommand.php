<?php

declare(strict_types=1);

namespace Volcy\Translator\Flight\Commands;

use flight\commands\AbstractBaseCommand;
use Volcy\Translator\BuildRunner;
use Volcy\Translator\Filesystem\NativeFilesystem;
use Volcy\Translator\TranslationDriverResolver;
use Volcy\Translator\ViewIndexPathResolver;

class BuildLocaleIndexCommand extends AbstractBaseCommand
{
    /**
     * @param array<string, mixed> $config Config from app/config/config.php
     */
    public function __construct(array $config)
    {
        parent::__construct('translator:build', 'Fill in a target-locale index from the source-locale index', $config);

        $this->argument('<locale>', 'Target locale to build (e.g. fr)');
        $this->option('--source [locale]', 'Source locale to translate from (defaults to translator.source_locale in config)');
        $this->option('--dry', 'Skip actual translation calls, just report counts');
    }

    public function execute(): void
    {
        $io = $this->app()->io();
        $values = $this->values();

        $translatorConfig = $this->config['translator'] ?? [];

        $locale = $values['locale'] ?? null;
        $sourceLocale = $values['source'] ?? ($translatorConfig['source_locale'] ?? 'en');
        $indexPath = $translatorConfig['index_path'] ?? null;
        $dryRun = (bool) ($values['dry'] ?? false);

        if (! $locale) {
            $io->error('A target locale is required, e.g. translator:build fr');

            return;
        }

        if (! $indexPath) {
            $io->error('translator.index_path is not configured.');

            return;
        }

        $sourceRoot = rtrim($indexPath, '/\\') . DIRECTORY_SEPARATOR . $sourceLocale;

        if (! is_dir($sourceRoot)) {
            $io->error("No source index found for [{$sourceLocale}]. Run translator:scan first.");

            return;
        }

        $runner = new BuildRunner(
            new NativeFilesystem(),
            new ViewIndexPathResolver(),
            new TranslationDriverResolver($translatorConfig)
        );

        $result = $runner->run($indexPath, $locale, $sourceLocale, $dryRun);

        $io->ok("Locale [{$locale}]: translated {$result['translated']} new string(s), reused {$result['reused']}.");
    }
}
