<x-filament-panels::page>
    @php
        $stats = $this->getStats();
        $groups = $this->getGroups();
        $groupedTranslations = $this->getGroupedTranslations();
        $groupStats = $this->getGroupStats();
        $languages = $this->getLanguages();
        $showStats = $this->getFeature('show_stats');
        $allowAdd = $this->getFeature('allow_add');
        $allowDelete = $this->getFeature('allow_delete');
        $showSyncButton = $this->getFeature('sync_button') && !empty(config('filament-translations.scan.paths'));
    @endphp

    {{-- Stats --}}
    @if($showStats)
        <div class="grid grid-cols-2 md:grid-cols-{{ 2 + count($languages) }} gap-4 mb-6">
            <x-filament::section>
                <div class="text-center">
                    <div class="text-3xl font-bold text-primary-600">{{ $stats['total'] }}</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Total Keys</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-3xl font-bold text-success-600">{{ count($groups) }}</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Groups</div>
                </div>
            </x-filament::section>

            @foreach($languages as $code => $lang)
                <x-filament::section>
                    <div class="text-center">
                        <div class="text-3xl font-bold {{ ($stats["missing_{$code}"] ?? 0) > 0 ? 'text-danger-600' : 'text-success-600' }}">
                            {{ $stats["missing_{$code}"] ?? 0 }}
                        </div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Missing {{ $lang['flag'] ?? '' }} {{ strtoupper($code) }}</div>
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    @endif

    {{-- Toolbar --}}
    <x-filament::section class="mb-6">
        <div class="flex flex-col md:flex-row gap-4">
            {{-- Search --}}
            <div class="flex-1">
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="text"
                        wire:model.live.debounce.500ms="search"
                        placeholder="Search by key or value..."
                    />
                </x-filament::input.wrapper>
            </div>

            {{-- Filter & Expand/Collapse --}}
            <div class="flex gap-2 flex-wrap">
                <x-filament::button
                    wire:click="toggleShowOnlyMissing"
                    :color="$showOnlyMissing ? 'danger' : 'gray'"
                    size="sm"
                    :icon="$showOnlyMissing ? 'heroicon-s-funnel' : 'heroicon-o-funnel'"
                >
                    {{ $showOnlyMissing ? 'Missing Only' : 'Show Missing' }}
                </x-filament::button>
                <x-filament::button
                    wire:click="expandAll"
                    color="gray"
                    size="sm"
                    icon="heroicon-o-chevron-double-down"
                >
                    Expand
                </x-filament::button>
                <x-filament::button
                    wire:click="collapseAll"
                    color="gray"
                    size="sm"
                    icon="heroicon-o-chevron-double-up"
                >
                    Collapse
                </x-filament::button>

                @if($showSyncButton)
                    <x-filament::button
                        wire:click="syncTranslations"
                        wire:loading.attr="disabled"
                        color="warning"
                        size="sm"
                        icon="heroicon-o-arrow-path"
                    >
                        <span wire:loading.remove wire:target="syncTranslations">Sync</span>
                        <span wire:loading wire:target="syncTranslations">Syncing...</span>
                    </x-filament::button>
                @endif

                @if($allowAdd)
                    <x-filament::button
                        wire:click="addNewKey"
                        size="sm"
                        icon="heroicon-o-plus"
                    >
                        Add Key
                    </x-filament::button>
                @endif
            </div>
        </div>
    </x-filament::section>

    {{-- Excel-like Table --}}
    <div class="space-y-4">
        @forelse($groupedTranslations as $groupName => $groupItems)
            @php
                $gStats = $groupStats[$groupName] ?? ['total' => 0];
                $isExpanded = in_array($groupName, $expandedGroups) || !empty($search);
                $hasIssues = false;
                foreach ($languages as $code => $lang) {
                    if (($gStats["missing_{$code}"] ?? 0) > 0) {
                        $hasIssues = true;
                        break;
                    }
                }
            @endphp

            <div
                class="border rounded-xl overflow-hidden {{ $hasIssues ? 'border-warning-300 dark:border-warning-700' : 'border-gray-200 dark:border-gray-700' }}"
                wire:key="group-{{ $groupName }}"
            >
                {{-- Group Header --}}
                <button
                    wire:click="toggleGroup('{{ $groupName }}')"
                    class="w-full flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                >
                    <div class="flex items-center gap-3">
                        <x-filament::icon
                            :icon="$isExpanded ? 'heroicon-o-chevron-down' : 'heroicon-o-chevron-right'"
                            class="w-5 h-5 text-gray-500"
                        />
                        <span class="font-semibold text-gray-900 dark:text-white text-lg">
                            {{ ucfirst($groupName) }}
                        </span>
                        <span class="px-2 py-0.5 text-xs rounded-full bg-primary-100 dark:bg-primary-900 text-primary-700 dark:text-primary-300">
                            {{ $gStats['total'] }} keys
                        </span>
                    </div>

                    <div class="flex items-center gap-2 flex-wrap justify-end">
                        @foreach($languages as $code => $lang)
                            @if(($gStats["missing_{$code}"] ?? 0) > 0)
                                <span class="px-2 py-0.5 text-xs rounded-full bg-danger-100 dark:bg-danger-900 text-danger-700 dark:text-danger-300">
                                    {{ $gStats["missing_{$code}"] }} {{ $lang['flag'] ?? strtoupper($code) }}
                                </span>
                            @endif
                        @endforeach
                        @if(!$hasIssues)
                            <span class="px-2 py-0.5 text-xs rounded-full bg-success-100 dark:bg-success-900 text-success-700 dark:text-success-300">
                                Complete
                            </span>
                        @endif
                    </div>
                </button>

                {{-- Group Content --}}
                @if($isExpanded)
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm border-collapse">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50">
                                    <th class="text-left py-2 px-3 font-medium text-gray-600 dark:text-gray-400 w-[20%]">Key</th>
                                    @foreach($languages as $code => $lang)
                                        <th class="text-left py-2 px-3 font-medium text-gray-600 dark:text-gray-400">
                                            <span class="inline-flex items-center gap-1">
                                                {{ $lang['flag'] ?? '' }} {{ $lang['name'] }}
                                            </span>
                                        </th>
                                    @endforeach
                                    @if($allowDelete)
                                        <th class="w-10"></th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($groupItems as $key => $item)
                                    @php
                                        $langCodes = array_keys($languages);

                                        // Build Alpine.js data as PHP
                                        $alpineProps = [];
                                        $checkParts = [];
                                        $saveParts = [];
                                        $resetParts = [];

                                        foreach ($langCodes as $code) {
                                            $value = $item[$code] ?? '';
                                            $jsValue = json_encode($value, JSON_UNESCAPED_UNICODE);
                                            $alpineProps[] = "{$code}: {$jsValue}";
                                            $alpineProps[] = "original_{$code}: {$jsValue}";
                                            $checkParts[] = "(this.{$code} !== this.original_{$code})";
                                            $saveParts[] = "this.{$code}";
                                            $resetParts[] = "this.original_{$code} = this.{$code};";
                                        }

                                        $escapedKey = addslashes($key);
                                        $alpineJs = '{ '
                                            . implode(', ', $alpineProps) . ', '
                                            . 'changed: false, saving: false, '
                                            . 'checkChanged() { this.changed = ' . implode(' || ', $checkParts) . '; }, '
                                            . 'async save() { '
                                                . 'if (!this.changed) return; '
                                                . 'this.saving = true; '
                                                . 'await $wire.saveTranslationDirect("' . $escapedKey . '", ' . implode(', ', $saveParts) . '); '
                                                . implode(' ', $resetParts) . ' '
                                                . 'this.changed = false; this.saving = false; '
                                            . '} '
                                        . '}';

                                        $missingCheck = implode(' || ', array_map(fn($c) => "({$c} === '')", $langCodes));
                                    @endphp
                                    <tr
                                        class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50/50 dark:hover:bg-gray-800/30 group"
                                        wire:key="row-{{ $key }}"
                                        x-data="{{ $alpineJs }}"
                                    >
                                        {{-- Key --}}
                                        <td class="py-1 px-3">
                                            <div class="flex items-center gap-2">
                                                <template x-if="{{ $missingCheck }}">
                                                    <x-filament::icon
                                                        icon="heroicon-s-exclamation-circle"
                                                        class="w-4 h-4 text-danger-500 flex-shrink-0"
                                                    />
                                                </template>
                                                <code class="text-xs bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded text-primary-600 dark:text-primary-400 break-all">
                                                    {{ Str::after($item['key'], $groupName . '.') }}
                                                </code>
                                                <span
                                                    x-show="changed"
                                                    x-cloak
                                                    class="w-2 h-2 rounded-full bg-warning-500"
                                                    title="Unsaved changes"
                                                ></span>
                                            </div>
                                        </td>

                                        {{-- Language inputs --}}
                                        @foreach($langCodes as $code)
                                            <td class="py-1 px-1">
                                                <input
                                                    type="text"
                                                    x-model="{{ $code }}"
                                                    x-on:input="checkChanged()"
                                                    x-on:blur="save()"
                                                    x-on:keydown.enter="$el.blur()"
                                                    x-bind:class="{
                                                        'border-danger-300 dark:border-danger-700 bg-danger-50 dark:bg-danger-950': {{ $code }} === '',
                                                        'border-gray-200 dark:border-gray-700': {{ $code }} !== ''
                                                    }"
                                                    class="w-full px-2 py-1.5 text-sm rounded border bg-white dark:bg-gray-900 dark:text-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none transition-colors"
                                                    placeholder="{{ $languages[$code]['name'] }}..."
                                                />
                                            </td>
                                        @endforeach

                                        {{-- Delete --}}
                                        @if($allowDelete)
                                            <td class="py-1 px-2">
                                                <button
                                                    type="button"
                                                    wire:click="deleteTranslation('{{ $key }}')"
                                                    wire:confirm="Delete '{{ $key }}'?"
                                                    class="p-1 rounded opacity-30 hover:opacity-100 hover:bg-danger-50 dark:hover:bg-danger-950 text-danger-500 transition-opacity"
                                                    title="Delete"
                                                >
                                                    <x-filament::icon icon="heroicon-o-trash" class="w-4 h-4" />
                                                </button>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @empty
            <x-filament::section>
                <div class="py-8 text-center text-gray-500 dark:text-gray-400">
                    No translations found
                </div>
            </x-filament::section>
        @endforelse
    </div>

    <div class="mt-4 text-sm text-gray-500 dark:text-gray-400">
        Total: {{ $stats['total'] }} translations in {{ count($groups) }} groups
        <span class="mx-2">•</span>
        <span class="text-xs">Auto-saves on blur or Enter</span>
    </div>

    {{-- Add Translation Modal --}}
    @if($allowAdd)
        <x-filament::modal id="add-translation" width="lg">
            <x-slot name="heading">
                Add New Translation
            </x-slot>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Key</label>
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="text"
                            wire:model="newKey"
                            placeholder="e.g., checkout.steps.confirm"
                        />
                    </x-filament::input.wrapper>
                    <p class="mt-1 text-xs text-gray-500">Use dot notation for nested keys</p>
                </div>

                @foreach($languages as $code => $lang)
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ $lang['flag'] ?? '' }} {{ $lang['name'] }}
                        </label>
                        <x-filament::input.wrapper>
                            <x-filament::input
                                type="text"
                                wire:model="newValues.{{ $code }}"
                                placeholder="{{ $lang['name'] }} translation..."
                            />
                        </x-filament::input.wrapper>
                    </div>
                @endforeach
            </div>

            <x-slot name="footerActions">
                <x-filament::button
                    wire:click="createTranslation"
                    color="primary"
                >
                    Create
                </x-filament::button>
            </x-slot>
        </x-filament::modal>
    @endif

    <style>
        [x-cloak] { display: none !important; }
    </style>
</x-filament-panels::page>
