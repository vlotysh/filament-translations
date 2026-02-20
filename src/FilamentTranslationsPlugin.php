<?php

namespace Vlotysh\FilamentTranslations;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Vlotysh\FilamentTranslations\Pages\TranslationsManager;

class FilamentTranslationsPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'filament-translations';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            TranslationsManager::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
