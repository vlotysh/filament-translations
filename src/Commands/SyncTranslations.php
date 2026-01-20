<?php

namespace Vlotysh\FilamentTranslations\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

class SyncTranslations extends Command
{
    protected $signature = 'translations:sync {--dry-run : Show what would be added without making changes}';

    protected $description = 'Scan source files for translation keys and add missing ones to locale files';

    private array $translations = [];
    private array $addedKeys = [];

    public function handle(): int
    {
        $localesPath = config('filament-translations.locales_path');
        $scanPaths = config('filament-translations.scan.paths', []);
        $extensions = config('filament-translations.scan.extensions', ['js', 'jsx', 'ts', 'tsx', 'vue']);
        $patterns = config('filament-translations.scan.patterns', [
            '/(?:^|[^a-zA-Z\$])t\(\s*[\'"]([a-zA-Z0-9_.]+)[\'"]\s*(?:,|\))/m',
        ]);
        $languages = config('filament-translations.languages', ['en' => ['name' => 'English']]);

        if (empty($scanPaths)) {
            $this->error('No scan paths configured. Please set filament-translations.scan.paths in config.');
            return 1;
        }

        // Validate paths
        foreach ($scanPaths as $path) {
            if (! File::isDirectory($path)) {
                $this->error("Scan path not found: {$path}");
                return 1;
            }
        }

        // Load existing translations
        foreach ($languages as $code => $lang) {
            $file = $localesPath . '/' . $code . '.json';
            if (File::exists($file)) {
                $content = json_decode(File::get($file), true) ?? [];
                $this->translations[$code] = Arr::dot($content);
            } else {
                $this->translations[$code] = [];
            }
        }

        // Find all translation keys in source files
        $foundKeys = $this->scanSourceFiles($scanPaths, $extensions, $patterns);

        $this->info('Found ' . count($foundKeys) . ' unique translation keys in source files');

        // Find missing keys
        $missingKeys = [];
        foreach ($foundKeys as $key) {
            $missing = [];
            foreach ($languages as $code => $lang) {
                if (! isset($this->translations[$code][$key]) || $this->translations[$code][$key] === null) {
                    $missing[$code] = true;
                }
            }
            if (! empty($missing)) {
                $missingKeys[] = [
                    'key' => $key,
                    'missing' => $missing,
                ];
            }
        }

        if (empty($missingKeys)) {
            $this->info('All translation keys are present in all locale files!');
            return 0;
        }

        $this->warn('Found ' . count($missingKeys) . ' missing translation keys:');
        $this->newLine();

        // Build table headers
        $headers = ['Key'];
        foreach ($languages as $code => $lang) {
            $headers[] = "Missing {$code}";
        }

        // Build table rows
        $rows = array_map(function ($item) use ($languages) {
            $row = [$item['key']];
            foreach ($languages as $code => $lang) {
                $row[] = isset($item['missing'][$code]) ? 'Yes' : 'No';
            }
            return $row;
        }, $missingKeys);

        $this->table($headers, $rows);

        if ($this->option('dry-run')) {
            $this->info('Dry run mode - no changes made.');
            return 0;
        }

        // Add missing keys
        $addedCount = 0;
        foreach ($missingKeys as $item) {
            foreach ($languages as $code => $lang) {
                if (isset($item['missing'][$code])) {
                    $this->translations[$code][$item['key']] = '';
                    $addedCount++;
                }
            }
        }

        // Save updated translations
        foreach ($languages as $code => $lang) {
            $this->saveTranslations($code, $localesPath);
        }

        $this->newLine();
        $this->info("Added {$addedCount} missing keys with empty values.");
        $this->info('You can now edit them in Admin Panel -> Translations');

        return 0;
    }

    private function scanSourceFiles(array $paths, array $extensions, array $patterns): array
    {
        $keys = [];

        foreach ($paths as $basePath) {
            $files = File::allFiles($basePath);

            foreach ($files as $file) {
                $extension = $file->getExtension();
                if (! in_array($extension, $extensions)) {
                    continue;
                }

                $content = File::get($file->getPathname());

                foreach ($patterns as $pattern) {
                    preg_match_all($pattern, $content, $matches);
                    if (! empty($matches[1])) {
                        foreach ($matches[1] as $key) {
                            $keys[$key] = true;
                        }
                    }
                }
            }
        }

        $result = array_keys($keys);
        sort($result);

        return $result;
    }

    private function saveTranslations(string $code, string $localesPath): void
    {
        $data = [];

        // Sort keys
        ksort($this->translations[$code]);

        // Convert flat array to nested
        foreach ($this->translations[$code] as $key => $value) {
            Arr::set($data, $key, $value);
        }

        // Sort recursively
        $this->sortArrayRecursive($data);

        $file = $localesPath . '/' . $code . '.json';
        File::put(
            $file,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
        );
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
