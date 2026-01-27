# Filament Translations Manager

Excel-like translations manager for Filament 3 with auto-sync from source files.

## Features

- Excel-like interface with inline editing
- Auto-save on blur or Enter key
- Visual indicators for missing translations (red exclamation mark)
- Unsaved changes indicator (orange dot)
- Group-based organization with collapsible sections
- Search across keys and values
- Filter to show only missing translations
- Sync translations from source code (`t('key')` function calls)
- Configurable languages with flag emojis
- Add/delete translation keys from UI
- Statistics dashboard
- **S3 sync** — push/pull translations between environments via S3

## Requirements

- PHP 8.1+
- Laravel 10.0+ or 11.0+
- Filament 3.0+

## Installation

### Via Composer (Packagist)

```bash
composer require vlotysh/filament-translations
```

### Local Development

Add the package to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "packages/vlotysh/filament-translations"
        }
    ],
    "require": {
        "vlotysh/filament-translations": "@dev"
    }
}
```

Then run:

```bash
composer update vlotysh/filament-translations
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=filament-translations-config
```

This will create `config/filament-translations.php`:

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Locales Path
    |--------------------------------------------------------------------------
    |
    | Path to the directory containing your JSON translation files.
    | Each language should have its own file (e.g., en.json, uk.json).
    |
    */
    'locales_path' => env('TRANSLATIONS_PATH', resource_path('lang')),

    /*
    |--------------------------------------------------------------------------
    | Languages
    |--------------------------------------------------------------------------
    |
    | Define the languages your application supports.
    | Key is the locale code, value contains display name and optional flag.
    |
    */
    'languages' => [
        'en' => [
            'name' => 'English',
            'flag' => '🇬🇧',
        ],
        'uk' => [
            'name' => 'Ukrainian',
            'flag' => '🇺🇦',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Source Scan Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for scanning source files to find translation keys.
    | Used by the translations:sync command.
    |
    */
    'scan' => [
        // Paths to scan for translation function calls
        'paths' => [
            // resource_path('js'),
            // base_path('frontend/src'),
        ],

        // File extensions to scan
        'extensions' => ['js', 'jsx', 'ts', 'tsx', 'vue'],

        // Regex patterns to match translation function calls
        'patterns' => [
            '/(?:^|[^a-zA-Z\$])t\(\s*[\'"]([a-zA-Z0-9_.]+)[\'"]\s*(?:,|\))/m',
            '/\$t\(\s*[\'"]([a-zA-Z0-9_.]+)[\'"]\s*(?:,|\))/m',
            '/__\(\s*[\'"]([a-zA-Z0-9_.]+)[\'"]\s*(?:,|\))/m',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    |
    | Customize how the translations manager appears in Filament navigation.
    |
    */
    'navigation' => [
        'group' => 'Settings',
        'icon' => 'heroicon-o-language',
        'sort' => 50,
        'label' => 'Translations',
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | Enable or disable specific features.
    |
    */
    /*
    |--------------------------------------------------------------------------
    | S3 Sync Settings
    |--------------------------------------------------------------------------
    */
    'sync' => [
        'disk' => env('TRANSLATIONS_SYNC_DISK', 's3-translations'),
        'path' => env('TRANSLATIONS_SYNC_PATH', 'translations-sync'),
    ],

    'features' => [
        'sync_button' => true,    // Show sync button
        'show_stats' => true,     // Show statistics cards
        'allow_add' => true,      // Allow adding new keys
        'allow_delete' => true,   // Allow deleting keys
    ],
];
```

## Usage

### Register the Page in Filament Panel

Add the `TranslationsManager` page to your Filament panel provider:

```php
// app/Providers/Filament/AdminPanelProvider.php

use Vlotysh\FilamentTranslations\Pages\TranslationsManager;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->pages([
                Dashboard::class,
                TranslationsManager::class,
            ])
            // ... other configuration
    }
}
```

### Translation Files Format

The package works with JSON translation files. Each language has its own file:

**en.json:**
```json
{
    "nav": {
        "home": "Home",
        "catalog": "Catalog"
    },
    "product": {
        "addToCart": "Add to Cart",
        "price": "Price"
    }
}
```

**uk.json:**
```json
{
    "nav": {
        "home": "Головна",
        "catalog": "Каталог"
    },
    "product": {
        "addToCart": "Додати в кошик",
        "price": "Ціна"
    }
}
```

### Syncing Translations from Source Code

The package can scan your source files for translation function calls and add missing keys automatically.

#### Configure Scan Paths

```php
// config/filament-translations.php

'scan' => [
    'paths' => [
        base_path('frontend/src'),          // React/Vue frontend
        resource_path('js'),                // Laravel Mix/Vite JS
    ],
    'extensions' => ['js', 'jsx', 'ts', 'tsx', 'vue'],
    'patterns' => [
        // Matches: t('key'), $t('key'), __('key')
        '/(?:^|[^a-zA-Z\$])t\(\s*[\'"]([a-zA-Z0-9_.]+)[\'"]\s*(?:,|\))/m',
    ],
],
```

#### Using the Sync Command

```bash
# Preview what would be added
php artisan translations:sync --dry-run

# Add missing keys
php artisan translations:sync
```

#### Using the Sync Button

Click the "Sync" button in the Translations Manager UI to run the sync command directly from the admin panel.

### Syncing Translations via S3 (Push / Pull)

Push and pull translations between environments (e.g., local dev and production) using an S3 bucket.

#### Configuration

The `sync` section in the config controls the S3 disk and remote path:

```php
'sync' => [
    'disk' => env('TRANSLATIONS_SYNC_DISK', 's3-translations'),
    'path' => env('TRANSLATIONS_SYNC_PATH', 'translations-sync'),
],
```

The package expects an `s3-translations` disk in `config/filesystems.php`. Add it with a fallback to your main S3 credentials:

```php
// config/filesystems.php
's3-translations' => [
    'driver' => 's3',
    'key' => env('TRANSLATIONS_SYNC_AWS_KEY', env('AWS_ACCESS_KEY_ID')),
    'secret' => env('TRANSLATIONS_SYNC_AWS_SECRET', env('AWS_SECRET_ACCESS_KEY')),
    'region' => env('TRANSLATIONS_SYNC_AWS_REGION', env('AWS_DEFAULT_REGION')),
    'bucket' => env('TRANSLATIONS_SYNC_AWS_BUCKET', env('AWS_BUCKET')),
    'throw' => false,
],
```

By default it uses the same S3 bucket as your app. To use a separate bucket or AWS account, set the `TRANSLATIONS_SYNC_AWS_*` variables:

```env
# Optional — only set if you need a separate S3 config
TRANSLATIONS_SYNC_AWS_KEY=
TRANSLATIONS_SYNC_AWS_SECRET=
TRANSLATIONS_SYNC_AWS_REGION=
TRANSLATIONS_SYNC_AWS_BUCKET=

# Override disk or remote path if needed
TRANSLATIONS_SYNC_DISK=s3-translations
TRANSLATIONS_SYNC_PATH=translations-sync
```

#### Push Command

Upload local translation files to S3:

```bash
# Push all languages
php artisan translations:push

# Push only Ukrainian
php artisan translations:push --lang=uk
```

This uploads each locale JSON file to `translations-sync/{locale}.json` and creates a `_meta.json` with version, timestamp, and environment name.

#### Pull Command

Download translations from S3 and merge with local files:

```bash
# Merge: local values win, remote-only keys are added
php artisan translations:pull

# Full overwrite: replace local files with remote
php artisan translations:pull --force

# Pull only English
php artisan translations:pull --lang=en
```

**Merge strategy (default):**
- Key exists locally — keep local value
- Key exists only on remote — add it
- Key exists only locally — keep it

**Force mode (`--force`):**
- Remote file completely overwrites local file

#### UI Buttons

The Translations Manager toolbar includes **Push to S3** and **Pull from S3** buttons that trigger the same logic as the artisan commands.

#### `_meta.json`

Each push writes a metadata file to S3:

```json
{
    "pushed_at": "2026-01-27T22:00:00+00:00",
    "pushed_from": "production",
    "version": 5,
    "languages": ["uk", "en"]
}
```

The version auto-increments on each push. The pull command displays this info before downloading.

### Docker Setup

If your frontend source is in a separate container, mount it to make it accessible:

```yaml
# docker-compose.yml
services:
  backend:
    volumes:
      - ./frontend/src:/var/www/frontend-src:ro
      - ./frontend/src/i18n/locales:/var/www/frontend-locales
```

Then configure paths:

```php
'locales_path' => '/var/www/frontend-locales',

'scan' => [
    'paths' => [
        '/var/www/frontend-src',
    ],
],
```

### Setting Up a Cron Job

To automatically sync translations, add to your scheduler:

```php
// app/Console/Kernel.php

protected function schedule(Schedule $schedule)
{
    $schedule->command('translations:sync')->daily();
}
```

## UI Overview

The Translations Manager provides:

1. **Statistics Dashboard** - Shows total keys, groups, and missing translations per language
2. **Toolbar** - Search, filter missing, expand/collapse all, sync button, add key
3. **Grouped Keys** - Translations organized by first segment (e.g., `nav.*`, `product.*`)
4. **Excel-like Grid** - Inline editing with auto-save

### Visual Indicators

- **Red exclamation mark** - Translation is missing (empty value)
- **Orange dot** - Unsaved changes (save by pressing Enter or clicking outside the field)
- **Red border** - Input field for missing translation
- **Danger badge** - Group has missing translations

## Customizing Views

Publish the views to customize the appearance:

```bash
php artisan vendor:publish --tag=filament-translations-views
```

Views will be published to `resources/views/vendor/filament-translations/`.

## API Reference

### TranslationsManager Page

| Method | Description |
|--------|-------------|
| `getLanguages()` | Returns configured languages |
| `getLocalesPath()` | Returns path to translation files |
| `getFeature($name)` | Check if feature is enabled |
| `syncTranslations()` | Run sync command |
| `pushToS3()` | Push translations to S3 |
| `pullFromS3()` | Pull translations from S3 (merge) |
| `saveTranslationDirect($key, ...$values)` | Save translation values |
| `deleteTranslation($key)` | Delete a translation key |
| `createTranslation()` | Create new translation from modal |

### Commands

#### `translations:sync`

```bash
php artisan translations:sync [--dry-run]
```

Scans source files for translation keys and adds missing ones.

Options:
- `--dry-run` - Show what would be added without making changes

#### `translations:push`

```bash
php artisan translations:push [--lang=CODE]
```

Uploads local translation files to S3.

Options:
- `--lang` - Push only a specific language (e.g., `--lang=uk`)

#### `translations:pull`

```bash
php artisan translations:pull [--force] [--lang=CODE]
```

Downloads translations from S3 and merges with local files.

Options:
- `--force` - Overwrite local files completely (no merge)
- `--lang` - Pull only a specific language

## Adding More Languages

Simply add to the `languages` config:

```php
'languages' => [
    'en' => ['name' => 'English', 'flag' => '🇬🇧'],
    'uk' => ['name' => 'Ukrainian', 'flag' => '🇺🇦'],
    'de' => ['name' => 'German', 'flag' => '🇩🇪'],
    'fr' => ['name' => 'French', 'flag' => '🇫🇷'],
],
```

Create the corresponding JSON files (`de.json`, `fr.json`) in your locales path.

## Custom Translation Function Patterns

If your project uses different translation functions, add custom regex patterns:

```php
'patterns' => [
    // Standard patterns
    '/(?:^|[^a-zA-Z\$])t\(\s*[\'"]([a-zA-Z0-9_.]+)[\'"]\s*(?:,|\))/m',

    // i18next useTranslation hook
    '/i18n\.t\(\s*[\'"]([a-zA-Z0-9_.]+)[\'"]\s*(?:,|\))/m',

    // Custom translate function
    '/translate\(\s*[\'"]([a-zA-Z0-9_.]+)[\'"]\s*(?:,|\))/m',
],
```

## Troubleshooting

### Focus Lost When Typing

The package uses Alpine.js for local state to prevent this issue. If you customize views, avoid Livewire `wire:model` on input fields.

### Sync Command Not Finding Keys

1. Verify scan paths exist and are readable
2. Check file extensions match your source files
3. Test your regex patterns against sample code
4. For Docker, ensure volumes are mounted correctly

### Translations Not Saving

1. Check file permissions on translation JSON files
2. Verify `locales_path` is correct and writable

## Contributing

Contributions are welcome! Please submit pull requests to the [GitHub repository](https://github.com/vlotysh/filament-translations).

## License

MIT License. See [LICENSE](LICENSE) for details.

## Author

**Vlad Lotysh**
- GitHub: [@vlotysh](https://github.com/vlotysh)
- Email: vlad@lotysh.dev
