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
    | Key is the locale code, value is the display name.
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
        // Default matches: t('key'), $t('key'), i18n.t('key'), __('key')
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
    |
    | Configuration for syncing translations via S3 between environments.
    | Used by translations:push and translations:pull commands.
    |
    */
    'sync' => [
        'disk' => env('TRANSLATIONS_SYNC_DISK', 's3'),
        'path' => 'translations-sync',
    ],

    'features' => [
        // Show sync button (requires scan.paths to be configured)
        'sync_button' => true,

        // Show statistics cards
        'show_stats' => true,

        // Allow adding new keys from UI
        'allow_add' => true,

        // Allow deleting keys from UI
        'allow_delete' => true,
    ],
];
