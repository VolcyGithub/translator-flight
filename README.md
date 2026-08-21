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
- **Dynamic Content Support**: Handles JavaScript framework content via custom data attributes

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

### 1. Install Dependencies

```bash
composer require volcy/translator-core volcy/translator-flight eftec/bladeone
composer require --dev flightphp/runway
```

### 2. Configure Translation Settings

Add translator configuration to your Flight app config:

```php
// app/config/config.php
return [
    'translator' => [
        'index_path'      => PROJECT_ROOT . '/storage/translator/indexes',
        'views_path'      => PROJECT_ROOT . '/app/views',
        'compile_path'    => PROJECT_ROOT . '/storage/views_compiled',
        'source_locale'   => 'en', // Your original template language
        'fallback_locale' => 'en',
        'id_strategy'     => 'hash', // 'hash', 'tag_path', or 'explicit'
        'locale_resolver' => fn () => $_GET['lang'] ?? $_SESSION['locale'] ?? 'en',
        'excluded_folders' => ['/admin'], // Optional: folders to exclude from scanning
        'translation_driver' => 'groq', // 'groq', 'google', or 'cerebras'
        'drivers' => [
            'groq' => [
                'key' => $_ENV['GROQ_API_KEY'] ?? null,
                'model' => 'llama-3.1-8b-instant',
            ],
            'cerebras' => [
                'key' => $_ENV['CEREBRAS_API_KEY'] ?? null,
                'model' => 'gemma-4-31b',
            ],
        ],
    ],
];
```

### 3. Register Translator Service

Create a service registration file:

```php
// app/config/services/translator.php
use Volcy\Translator\Flight\TranslatorBootstrap;

$translator = TranslatorBootstrap::register($app, $config['translator']);
$app->set('translator', $translator);
```

### 4. Configure BladeOne View Service

Update your view service configuration to use TrackedBladeOne:

```php
// app/config/services/view.php
use Volcy\Translator\Flight\TrackedBladeOne as BladeOne;

$app->register(
    'view',
    BladeOne::class,
    [BladeOne::MODE_FAST], // Use MODE_FAST for production, MODE_DEBUG for development
    function (BladeOne $blade) use ($ds, $app, $config) {
        $views = realpath(PROJECT_ROOT . '/app/views');
        $cache = realpath(PROJECT_ROOT . '/storage/views_compiled');
        $blade->setPath($views, $cache);
        $blade->setRegistry($app->get('translator')->registry());
    }
);

// Create a custom render function that uses the translator-aware BladeOne
$app->map(
    'render',
    function (string $template, array $data = []) use ($app, $config): void {
        $blade = $app->get('translator')->blade(
            $config['translator']['views_path'],
            $config['translator']['compile_path']
        );
        echo $blade->run($template, $data);
    }
);
```

### 5. Attach Locale Middleware

Add the locale middleware to your routes:

```php
// app/config/routes/web.php
Flight::group('/', function () {
    // Your routes here
}, [$app->get('translator')->middleware()]);
```

### 6. Add Translatable Attributes to Templates

Use `data-i18n` attributes in your Blade templates:

```html
<!-- Static content -->
<h1 data-i18n="welcome">Welcome</h1>
<p data-i18n="description">This is the home page</p>

<!-- Attributes -->
<input data-i18n-placeholder="email_placeholder" placeholder="Enter email">
<img data-i18n-alt="logo_alt" alt="Company Logo">

<!-- Dynamic content (Alpine.js, etc.) -->
<button data-i18n-loading="Loading..." data-i18n-text="Submit"
        x-text="loading ? $el.dataset.i18nLoading : $el.dataset.i18nText">
    Submit
</button>
```

The `LocaleMiddleware` is lightweight and only resolves the current locale. The actual translation happens during template compilation by `TrackedBladeOne`, which injects translations directly into the compiled PHP files for each locale.

## CLI Workflow

### 1. Scan Views

Extract translatable strings from your Blade templates:

```bash
vendor/bin/runway translator:scan --path=app/views
```

This creates a source locale index in your configured `index_path`.

### 2. Build Translations

Generate translations for target locales:

```bash
vendor/bin/runway translator:build fr --source=en
```

This creates French translations from your English source.

### 3. Compile Views (Precompilation)

Compile views for all locales to avoid runtime compilation:

```bash
vendor/bin/runway translator:compile --locales=en,fr
```

This compiles your Blade templates with translations embedded for each locale.

**Note**: If you don't use the compile command, views will be compiled on first request for each locale.

### 4. Apply Translations

Translations are automatically applied via the locale middleware during web requests. The middleware sets the locale, and the appropriate compiled template is served.

## Usage Examples

### Static Content Translation

```html
<!-- In your Blade template -->
<h1 data-i18n="product_title">Product Title</h1>
<p data-i18n="product_description">This is a great product</p>
```

### Attribute Translation

```html
<input data-i18n-placeholder="search_placeholder" placeholder="Search products">
<img data-i18n-alt="image_alt" alt="Product image">
```

### Dynamic Content (Alpine.js)

```html
<!-- For dynamic JavaScript content -->
<button data-i18n-loading="Submitting..." data-i18n-text="Submit Order"
        x-text="loading ? $el.dataset.i18nLoading : $el.dataset.i18nText">
    Submit Order
</button>
```

### Locale Switching

Access different locales via URL parameter or session:

```php
// In your locale resolver
'locale_resolver' => fn () => $_GET['lang'] ?? $_SESSION['locale'] ?? 'en'

// Usage: /?lang=fr will serve French compiled templates
```

## CLI Commands

The library provides three Runway commands:

### `translator:scan`
Scans Blade views and creates the source locale index.

```bash
vendor/bin/runway translator:scan --path=app/views
```

**Options:**
- `--path`: Path to views directory (default: from config)
- `--exclude`: Comma-separated folders to exclude

### `translator:build`
Builds target locale indexes from the source locale.

```bash
vendor/bin/runway translator:build fr --source=en
```

**Options:**
- `<locale>`: Target locale (required)
- `--source`: Source locale (default: from config)

### `translator:compile`
Pre-compiles views for specific locales with translations embedded.

```bash
vendor/bin/runway translator:compile --locales=en,fr
```

**Options:**
- `--locales`: Comma-separated target locales (default: from config)
- `--clear`: Clear existing compiled views before recompiling

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
- `TranslatorBootstrap::blade(string $viewsPath, string $compilePath): TrackedBladeOne` — convenience factory that wires the rendered-views registry and translation catalog.
- `TranslatorBootstrap::middleware(): LocaleMiddleware` — Flight middleware to attach to groups that should be translated.
- `TrackedBladeOne::lockLocale(string $locale): void` — locks the locale for CLI compilation, bypassing runtime locale resolution.

## Dynamic Content Support

The library supports translation of dynamic JavaScript framework content through custom `data-i18n-*` attributes:

### Alpine.js Example

```html
<!-- Button with dynamic text -->
<button data-i18n-loading="Loading..." data-i18n-text="Submit"
        x-text="loading ? $el.dataset.i18nLoading : $el.dataset.i18nText">
    Submit
</button>
```

### Other Frameworks

This pattern works with any JavaScript framework that can access DOM attributes:

```html
<!-- Vue.js -->
<span data-i18n-success="Success!" data-i18n-error="Error"
      :text="success ? $el.dataset.i18nSuccess : $el.dataset.i18nError"></span>

<!-- React (via data attributes) -->
<button data-i18n-submit="Submit" 
        onClick={() => setText(button.dataset.i18nSubmit)}>
    Submit
</button>
```

The scanner automatically extracts values from `data-i18n-*` attributes, and the compile-time injection replaces them with translated values, enabling performance translation for dynamic content.

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
