<?php

namespace Vlotysh\FilamentTranslations;

use Illuminate\Support\ServiceProvider;
use Vlotysh\FilamentTranslations\Commands\SyncTranslations;

class FilamentTranslationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/filament-translations.php',
            'filament-translations'
        );
    }

    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/filament-translations.php' => config_path('filament-translations.php'),
        ], 'filament-translations-config');

        // Publish views
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/filament-translations'),
        ], 'filament-translations-views');

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'filament-translations');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncTranslations::class,
            ]);
        }
    }
}
