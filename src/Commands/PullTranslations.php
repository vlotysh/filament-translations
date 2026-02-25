<?php

namespace Vlotysh\FilamentTranslations\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class PullTranslations extends Command
{
    protected $signature = 'translations:pull {--force : Overwrite local files completely (no merge)} {--overwrite : Overwrite existing keys with remote values} {--lang= : Pull only a specific language}';

    protected $description = 'Pull translation files from S3 and merge with local translations';

    public function handle(): int
    {
        $localesPath = config('filament-translations.locales_path');
        $languages = config('filament-translations.languages', []);
        $disk = config('filament-translations.sync.disk', 's3');
        $syncPath = config('filament-translations.sync.path', 'translations');

        $storage = Storage::disk($disk);
        $langFilter = $this->option('lang');
        $force = $this->option('force');
        $overwrite = $this->option('overwrite');

        // Show meta info if available
        $metaPath = $syncPath . '/_meta.json';
        try {
            if ($storage->exists($metaPath)) {
                $meta = json_decode($storage->get($metaPath), true);
                $this->info("Remote: version {$meta['version']}, pushed from {$meta['pushed_from']} at {$meta['pushed_at']}");
                $this->newLine();
            }
        } catch (\Throwable) {
            // _meta.json not available, continue without it
        }

        $pulledCount = 0;

        foreach ($languages as $code => $lang) {
            if ($langFilter && $code !== $langFilter) {
                continue;
            }

            $remotePath = $syncPath . '/' . $code . '.json';

            try {
                $remoteRaw = $storage->get($remotePath);
            } catch (\Throwable $e) {
                $this->warn("Remote file not available: {$code}.json ({$e->getMessage()})");
                continue;
            }

            if ($remoteRaw === null) {
                $this->warn("Remote file not found: {$remotePath}");
                continue;
            }

            $remoteContent = json_decode($remoteRaw, true);

            if ($remoteContent === null) {
                $this->error("Invalid JSON in remote {$code}.json");
                continue;
            }

            $localFile = $localesPath . '/' . $code . '.json';

            if ($force || ! File::exists($localFile)) {
                // Full overwrite
                File::put(
                    $localFile,
                    json_encode($remoteContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n"
                );
                $this->info("Pulled {$code}.json (full overwrite)");
            } else {
                // Merge translations
                $localContent = json_decode(File::get($localFile), true) ?? [];

                $localFlat = Arr::dot($localContent);
                $remoteFlat = Arr::dot($remoteContent);

                $added = 0;
                $overwritten = 0;

                foreach ($remoteFlat as $key => $value) {
                    if (! array_key_exists($key, $localFlat)) {
                        $localFlat[$key] = $value;
                        $added++;
                    } elseif ($overwrite && $localFlat[$key] !== $value) {
                        $localFlat[$key] = $value;
                        $overwritten++;
                    }
                }

                // Rebuild nested and sort
                ksort($localFlat);
                $nested = [];
                foreach ($localFlat as $key => $value) {
                    Arr::set($nested, $key, $value);
                }

                $this->sortArrayRecursive($nested);

                File::put(
                    $localFile,
                    json_encode($nested, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n"
                );

                if ($overwrite) {
                    $this->info("Pulled {$code}.json (merged, {$added} new keys added, {$overwritten} keys overwritten)");
                } else {
                    $this->info("Pulled {$code}.json (merged, {$added} new keys added)");
                }
            }

            $pulledCount++;
        }

        if ($pulledCount === 0) {
            $this->error('No translation files were pulled.');
            return 1;
        }

        $this->newLine();
        $this->info('Pull completed successfully.');

        return 0;
    }

    private function sortArrayRecursive(array &$array): void
    {
        ksort($array);
        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->sortArrayRecursive($value);
            }
        }
    }
}
