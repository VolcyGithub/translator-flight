# volcy/translator-flight

[![Latest Stable Version](https://img.shields.io/packagist/v/volcy/translator-flight)](https://packagist.org/packages/volcy/translator-flight)
[![Total Downloads](https://img.shields.io/packagist/dt/volcy/translator-flight)](https://packagist.org/packages/volcy/translator-flight)
[![License](https://img.shields.io/packagist/l/volcy/translator-flight)](https://packagist.org/packages/volcy/translator-flight)
[![PHP Version](https://img.shields.io/packagist/php-v/volcy/translator-flight)](https://packagist.org/packages/volcy/translator-flight)

FlightPHP bridge for `volcy/translator-core`: compile-time template translation with zero runtime overhead.

## Architecture

This library uses a **compile-time translation architecture** for maximum performance:

- **Compile-Time Injection**: Translations are embedded directly into compiled templates, eliminating runtime processing
- **Locale-Specific Caching**: Each locale maintains its own compiled template cache for instant rendering
- **Instant Language Switching**: Delivers fully translated HTML from the first byte
- **Lightweight Middleware**: Only handles locale resolution; all translation work happens during compilation

## Requirements
- PHP 8.0+
- volcy/translator-core ^1.0
- flightphp/core ^3.0
- eftec/bladeone ^4.0

## Installation

Install via Composer:

```bash
composer require volcy/translator-flight
```

If you want to use the included CLI commands, also install `flightphp/runway` (recommended in `require-dev`):

```bash
composer require --dev flightphp/runway
```

## Quick start

1. Configure a `translator` section in your Flight app config (example keys used by the bootstrap):

```php
// app/config/config.php
return [
    'translator' => [
        'index_path'      => __DIR__ . '/../storage/translator/indexes',
        'views_path'      => __DIR__ . '/../app/views',
        'source_locale'   => 'en',
        'fallback_locale' => 'en',
        // Optional: ID strategy for generating translation IDs
        // Options: 'hash' (default), 'tag_path', 'explicit'
        'id_strategy'     => 'hash',
        // Optional: callable returning the current locale
        'locale_resolver' => fn () => $_SESSION['locale'] ?? 'en',
    ],
];
```

2. Register the translator in your bootstrap code and create a `TrackedBladeOne` instance:

```php
use Volcy\Translator\Flight\TranslatorBootstrap;
use Flight;

$translator = TranslatorBootstrap::register(Flight::app(), [
    'index_path' => __DIR__ . '/../storage/translator/indexes',
    'views_path' => __DIR__ . '/../app/views',
    'source_locale' => 'en',
    'fallback_locale' => 'en',
    'id_strategy' => 'hash', // 'hash', 'tag_path', or 'explicit'
    'locale_resolver' => fn () => $_SESSION['locale'] ?? 'en',
]);

$blade = $translator->blade(__DIR__ . '/../app/views', __DIR__ . '/../storage/views_compiled');

// Use $blade as you would a normal BladeOne instance
echo $blade->run('pages.home', ['user' => $user]);
```

3. Attach the middleware to route groups where translations should be applied:

```php
Flight::group('/', function () use ($blade) {
    // routes
}, [$translator->middleware()]);
```

The `LocaleMiddleware` is lightweight and only resolves the current locale. The actual translation happens during template compilation by `TrackedBladeOne`, which injects translations directly into the compiled PHP files for each locale.

## CLI: scanning and building locale indexes

Two Runway commands are provided:

- `translator:scan` — scans Blade views and writes the source-locale index.
- `translator:build <locale>` — fills in a target-locale index from the source locale.

Usage (cross-platform examples):

```bash
# *nix
vendor/bin/runway translator:scan --path=app/views
vendor/bin/runway translator:build fr --source=en

# Windows (composer-installed binaries create .bat wrappers)
vendor/bin/runway.bat translator:scan --path=app/views
vendor/bin/runway.bat translator:build fr --source=en
```

See `src/commands/ScanViewsCommand.php` and `src/commands/BuildLocaleIndexCommand.php` for details and available options.

## Configuration

Additional configuration options for translation drivers:

```php
'translator' => [
    // ... other config ...
    'translation_driver' => 'groq', // 'groq', 'google', or 'cerebras'
    'drivers' => [
        'groq' => [
            'key' => 'your-groq-api-key',
            'model' => 'llama-3.1-8b-instant',
        ],
        'google' => [
            'key' => 'your-google-translate-key',
        ],
        'cerebras' => [
            'key' => 'your-cerebras-api-key',
            'model' => 'llama-3.3-70b',
        ],
    ],
],
```

## API overview

- `TranslatorBootstrap::register(Engine $app, array $config): TranslatorBootstrap` — registers services and returns an instance.
- `TranslatorBootstrap::blade(string $viewsPath, string $compilePath): TrackedBladeOne` — convenience factory that wires the rendered-views registry.
- `TranslatorBootstrap::middleware(): TranslateMiddleware` — Flight middleware to attach to groups that should be translated.

## ID Strategies

This package supports all ID strategies from translator-core:
- `hash` (default): Content-based SHA1 hashes
- `tag_path`: HTML tag path + attribute aware IDs  
- `explicit`: Manual control via data-i18n attributes with hash fallback

For detailed information about ID strategies, see the [translator-core documentation](https://github.com/VolcyGithub/translator-core#id-strategies).

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

For issues and questions, please use the [GitHub issue tracker](https://github.com/VolcyGithub/translator-flight/issues).

For security issues, please see [SECURITY.md](SECURITY.md).
