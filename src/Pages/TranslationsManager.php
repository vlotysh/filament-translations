<?php

namespace Vlotysh\FilamentTranslations\Pages;

use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class TranslationsManager extends Page implements HasForms
{
    use InteractsWithForms;

    public string $search = '';

    public bool $showOnlyMissing = false;

    public array $translations = [];

    public array $expandedGroups = [];

    public string $newKey = '';

    public array $newValues = [];

    protected static string $view = 'filament-translations::translations-manager';

    public static function getNavigationGroup(): ?string
    {
        return config('filament-translations.navigation.group', 'Settings');
    }

    public static function getNavigationIcon(): ?string
    {
        return config('filament-translations.navigation.icon', 'heroicon-o-language');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-translations.navigation.sort', 50);
    }

    public static function getNavigationLabel(): string
    {
        return config('filament-translations.navigation.label', 'Translations');
    }

    public function getTitle(): string
    {
        return config('filament-translations.navigation.label', 'Translations');
    }

    public function mount(): void
    {
        $this->initNewValues();
        $this->loadTranslations();
    }

    protected function initNewValues(): void
    {
        foreach ($this->getLanguages() as $code => $lang) {
            $this->newValues[$code] = '';
        }
    }

    public function getLanguages(): array
    {
        return config('filament-translations.languages', [
            'en' => ['name' => 'English', 'flag' => '🇬🇧'],
        ]);
    }

    public function getLocalesPath(): string
    {
        return config('filament-translations.locales_path', resource_path('lang'));
    }

    public function getFeature(string $feature): bool
    {
        return config("filament-translations.features.{$feature}", true);
    }

    public function getFilteredTranslations(): array
    {
        $filtered = $this->translations;

        // Filter by missing only
        if ($this->showOnlyMissing) {
            $filtered = array_filter($filtered, function (array $item): bool {
                foreach ($this->getLanguages() as $code => $lang) {
                    if (empty($item[$code])) {
                        return true;
                    }
                }
                return false;
            });
        }

        // Filter by search
        if ($this->search !== '' && $this->search !== '0') {
            $search = mb_strtolower($this->search);
            $filtered = array_filter($filtered, function (array $item, $key) use ($search): bool {
                if (str_contains(mb_strtolower((string) $key), $search)) {
                    return true;
                }
                foreach ($this->getLanguages() as $code => $lang) {
                    if (str_contains(mb_strtolower($item[$code] ?? ''), $search)) {
                        return true;
                    }
                }
                return false;
            }, ARRAY_FILTER_USE_BOTH);
        }

        return $filtered;
    }

    public function getGroupedTranslations(): array
    {
        $filtered = $this->getFilteredTranslations();
        $grouped = [];

        foreach ($filtered as $key => $item) {
            $group = $item['group'] ?? explode('.', (string) $key)[0] ?? 'other';
            if (! isset($grouped[$group])) {
                $grouped[$group] = [];
            }

            $item['key'] = $key;
            $item['group'] = $group;
            $grouped[$group][$key] = $item;
        }

        ksort($grouped);

        return $grouped;
    }

    public function getGroups(): array
    {
        $groups = [];
        foreach ($this->translations as $key => $item) {
            $groups[] = $item['group'] ?? explode('.', (string) $key)[0] ?? 'other';
        }

        $groups = array_unique($groups);
        sort($groups);

        return $groups;
    }

    public function getGroupStats(): array
    {
        $stats = [];
        $languages = $this->getLanguages();

        foreach ($this->translations as $key => $item) {
            $group = $item['group'] ?? explode('.', (string) $key)[0] ?? 'other';
            if (! isset($stats[$group])) {
                $stats[$group] = ['total' => 0];
                foreach ($languages as $code => $lang) {
                    $stats[$group]["missing_{$code}"] = 0;
                }
            }

            $stats[$group]['total']++;
            foreach ($languages as $code => $lang) {
                if (empty($item[$code])) {
                    $stats[$group]["missing_{$code}"]++;
                }
            }
        }

        return $stats;
    }

    public function getStats(): array
    {
        $total = count($this->translations);
        $languages = $this->getLanguages();
        $stats = ['total' => $total];

        foreach ($languages as $code => $lang) {
            $stats["missing_{$code}"] = count(array_filter(
                $this->translations,
                fn(array $t): bool => empty($t[$code])
            ));
        }

        return $stats;
    }

    public function toggleGroup(string $group): void
    {
        if (in_array($group, $this->expandedGroups)) {
            $this->expandedGroups = array_diff($this->expandedGroups, [$group]);
        } else {
            $this->expandedGroups[] = $group;
        }
    }

    public function expandAll(): void
    {
        $this->expandedGroups = $this->getGroups();
    }

    public function collapseAll(): void
    {
        $this->expandedGroups = [];
    }

    public function toggleShowOnlyMissing(): void
    {
        $this->showOnlyMissing = ! $this->showOnlyMissing;

        if ($this->showOnlyMissing) {
            $this->expandedGroups = $this->getGroups();
        }
    }

    public function saveTranslationDirect(string $key, ...$values): void
    {
        $languages = array_keys($this->getLanguages());

        foreach ($languages as $index => $code) {
            $this->translations[$key][$code] = $values[$index] ?? '';
        }

        $this->translations[$key]['key'] = $key;
        $this->translations[$key]['group'] = explode('.', $key)[0] ?? 'other';

        $this->saveAllTranslations();
    }

    public function syncTranslations(): void
    {
        $scanPaths = config('filament-translations.scan.paths', []);

        if (empty($scanPaths)) {
            Notification::make()
                ->warning()
                ->title('Scan paths not configured')
                ->body('Please configure scan.paths in filament-translations config.')
                ->send();
            return;
        }

        $exitCode = Artisan::call('translations:sync');
        $output = Artisan::output();

        $this->loadTranslations();

        preg_match('/Added (\d+) missing keys/', $output, $matches);
        $addedCount = $matches[1] ?? 0;

        if ($addedCount > 0) {
            Notification::make()
                ->success()
                ->title('Sync completed')
                ->body("Added {$addedCount} missing translation keys.")
                ->send();

            $this->showOnlyMissing = true;
            $this->expandedGroups = $this->getGroups();
        } else {
            Notification::make()
                ->info()
                ->title('All translations are up to date')
                ->body('No missing keys found.')
                ->send();
        }
    }

    public function addNewKey(): void
    {
        $this->dispatch('open-modal', id: 'add-translation');
    }

    public function createTranslation(): void
    {
        if ($this->newKey === '' || $this->newKey === '0') {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('Key is required')
                ->send();
            return;
        }

        if (isset($this->translations[$this->newKey])) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('Key already exists')
                ->send();
            return;
        }

        $this->translations[$this->newKey] = [
            'key' => $this->newKey,
            'group' => explode('.', $this->newKey)[0] ?? 'other',
        ];

        foreach ($this->getLanguages() as $code => $lang) {
            $this->translations[$this->newKey][$code] = $this->newValues[$code] ?? '';
        }

        ksort($this->translations);
        $this->saveAllTranslations();

        $this->newKey = '';
        $this->initNewValues();

        $this->dispatch('close-modal', id: 'add-translation');

        Notification::make()
            ->success()
            ->title('Translation added')
            ->send();
    }

    public function deleteTranslation(string $key): void
    {
        unset($this->translations[$key]);
        $this->saveAllTranslations();

        Notification::make()
            ->success()
            ->title('Translation deleted')
            ->body('Key: ' . $key)
            ->send();
    }

    protected function loadTranslations(): void
    {
        $localesPath = $this->getLocalesPath();
        $languages = $this->getLanguages();
        $allData = [];

        // Load each language file
        foreach ($languages as $code => $lang) {
            $file = $localesPath . '/' . $code . '.json';
            if (File::exists($file)) {
                $content = json_decode(File::get($file), true) ?? [];
                $allData[$code] = Arr::dot($content);
            } else {
                $allData[$code] = [];
            }
        }

        // Merge all keys
        $allKeys = [];
        foreach ($allData as $code => $data) {
            $allKeys = array_merge($allKeys, array_keys($data));
        }
        $allKeys = array_unique($allKeys);
        sort($allKeys);

        // Build translations array
        $this->translations = [];
        foreach ($allKeys as $key) {
            $this->translations[$key] = [
                'key' => $key,
                'group' => explode('.', (string) $key)[0] ?? 'other',
            ];
            foreach ($languages as $code => $lang) {
                $this->translations[$key][$code] = $allData[$code][$key] ?? '';
            }
        }
    }

    protected function saveAllTranslations(): void
    {
        $localesPath = $this->getLocalesPath();
        $languages = $this->getLanguages();

        foreach ($languages as $code => $lang) {
            $data = [];
            foreach ($this->translations as $key => $item) {
                Arr::set($data, $key, $item[$code] ?? '');
            }

            $file = $localesPath . '/' . $code . '.json';
            File::put(
                $file,
                json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n"
            );
        }
    }
}
