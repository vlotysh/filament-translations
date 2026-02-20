<?php

namespace Vlotysh\FilamentTranslations\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class PushTranslations extends Command
{
    protected $signature = 'translations:push {--lang= : Push only a specific language (e.g., uk)}';

    protected $description = 'Push translation files to S3 for syncing between environments';

    public function handle(): int
    {
        $localesPath = config('filament-translations.locales_path');
        $languages = config('filament-translations.languages', []);
        $disk = config('filament-translations.sync.disk', 's3');
        $syncPath = config('filament-translations.sync.path', 'translations');

        $storage = Storage::disk($disk);
        $langFilter = $this->option('lang');

        $pushedLanguages = [];

        foreach ($languages as $code => $lang) {
            if ($langFilter && $code !== $langFilter) {
                continue;
            }

            $file = $localesPath . '/' . $code . '.json';

            if (! File::exists($file)) {
                $this->warn("File not found: {$file}");
                continue;
            }

            $remotePath = $syncPath . '/' . $code . '.json';
            $storage->put($remotePath, File::get($file));

            $this->info("Pushed {$code}.json to {$disk}://{$remotePath}");
            $pushedLanguages[] = $code;
        }

        if (empty($pushedLanguages)) {
            $this->error('No translation files were pushed.');
            return 1;
        }

        // Read existing meta to increment version
        $metaPath = $syncPath . '/_meta.json';
        $version = 1;

        try {
            if ($storage->exists($metaPath)) {
                $existingMeta = json_decode($storage->get($metaPath), true);
                $version = ($existingMeta['version'] ?? 0) + 1;
            }
        } catch (\Throwable) {
            // _meta.json not available, start at version 1
        }

        $meta = [
            'pushed_at' => now()->toIso8601String(),
            'pushed_from' => config('app.env', 'unknown'),
            'version' => $version,
            'languages' => $pushedLanguages,
        ];

        try {
            $storage->put($metaPath, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info("Updated _meta.json (version {$version})");
        } catch (\Throwable $e) {
            $this->warn("Could not write _meta.json: {$e->getMessage()}");
        }

        $this->newLine();
        $this->info('Push completed successfully.');

        return 0;
    }
}
