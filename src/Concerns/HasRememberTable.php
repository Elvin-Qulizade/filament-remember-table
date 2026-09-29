<?php

namespace Sehsah\FilamentRememberTable\Concerns;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;
use Sehsah\FilamentRememberTable\Models\TableView;
use Sehsah\FilamentRememberTable\PresetView;
use Sehsah\FilamentRememberTable\Support\TableState;

/**
 * Add to any Filament page that uses a table (e.g. a resource's ListRecords page),
 * then spread `...$this->getTableViewActions()` into getHeaderActions().
 *
 * @mixin \Filament\Tables\Concerns\InteractsWithTable
 */
trait HasRememberTable
{
    /**
     * Key of the view currently applied: a database id, or "preset-..." for a preset.
     */
    #[Url(as: 'view', except: '')]
    public string $activeTableView = '';

    protected bool $shouldApplyInitialTableView = false;

    /** @var Collection<int, TableView>|null */
    protected ?Collection $cachedTableViews = null;

    /*
    |--------------------------------------------------------------------------
    | Overridable hooks
    |--------------------------------------------------------------------------
    */

    /**
     * Views defined in code. Users can apply them but not change them.
     *
     * @return array<PresetView>
     */
    public function getPresetTableViews(): array
    {
        return [];
    }

    /**
     * Identifies which table the saved views belong to.
     */
    public function getTableViewsKey(): string
    {
        return static::class;
    }

    /*
    |--------------------------------------------------------------------------
    | Livewire lifecycle
    |--------------------------------------------------------------------------
    */

    public function mountHasRememberTable(): void
    {
        $this->shouldApplyInitialTableView = true;
    }

    /**
     * Runs after the table has booted and loaded its own session/URL state.
     */
    public function bootedHasRememberTable(): void
    {
        if (! $this->shouldApplyInitialTableView) {
            return;
        }

        $this->shouldApplyInitialTableView = false;

        if ($this->getTableViewsUser() === null) {
            $this->activeTableView = '';

            return;
        }

        // A shared link: ?view=12 or ?view=preset-pending
        if (filled($this->activeTableView)) {
            if (! $this->applyTableView($this->activeTableView, notify: false)) {
                $this->activeTableView = '';
            }

            return;
        }

        if (! config('remember-table.apply_default_view_on_load', true) || $this->requestHasTableState()) {
            return;
        }

        if ($defaultKey = $this->getDefaultTableViewKey()) {
            $this->applyTableView($defaultKey, notify: false);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Actions
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<Action | ActionGroup>
     */
    public function getTableViewActions(): array
    {
        if ($this->getTableViewsUser() === null) {
            return [];
        }

        $groups = array_filter([
            $this->makeTableViewItemsGroup($this->getPresetTableViews()),
            $this->makeTableViewItemsGroup($this->getTableViews()->all()),
            ActionGroup::make([
                $this->saveTableViewAction(),
                $this->updateTableViewAction(),
                $this->manageTableViewsAction(),
                $this->resetTableViewAction(),
            ])->dropdown(false),
        ]);

        return [
            ActionGroup::make(array_values($groups))
                ->label(fn (): string => $this->getTableViewsButtonLabel())
                ->icon('heroicon-o-bookmark')
                ->color('gray')
                ->button()
                ->dropdownPlacement('bottom-end'),
        ];
    }

    public function saveTableViewAction(): Action
    {
        return Action::make('saveTableView')
            ->label(__('filament-remember-table::remember-table.actions.save'))
            ->icon('heroicon-o-plus')
            ->modalHeading(__('filament-remember-table::remember-table.modals.save.heading'))
            ->modalDescription(__('filament-remember-table::remember-table.modals.save.description'))
            ->modalSubmitActionLabel(__('filament-remember-table::remember-table.actions.save_submit'))
            ->modalWidth('md')
            ->schema([
                TextInput::make('name')
                    ->label(__('filament-remember-table::remember-table.fields.name'))
                    ->required()
                    ->maxLength(100)
                    ->autofocus(),
                Toggle::make('is_default')
                    ->label(__('filament-remember-table::remember-table.fields.is_default')),
                Toggle::make('is_public')
                    ->label(__('filament-remember-table::remember-table.fields.is_public'))
                    ->helperText(__('filament-remember-table::remember-table.fields.is_public_help'))
                    ->visible(fn (): bool => (bool) config('remember-table.allow_public_views', true)),
            ])
            ->action(function (array $data, Action $action): void {
                $user = $this->getTableViewsUser();
                $limit = config('remember-table.max_views_per_table');

                if ($limit !== null && $this->ownedTableViewsQuery($user)->count() >= $limit) {
                    Notification::make()
                        ->title(__('filament-remember-table::remember-table.notifications.limit_reached', ['limit' => $limit]))
                        ->danger()
                        ->send();

                    $action->halt();
                }

                $view = $this->newTableViewModel()->fill([
                    'user_type' => $user->getMorphClass(),
                    'user_id' => $user->getKey(),
                    'tenant_id' => $this->getTableViewsTenantId(),
                    'table_key' => $this->getTableViewsKey(),
                    'name' => $data['name'],
                    'state' => $this->captureTableViewState(),
                    'is_default' => (bool) ($data['is_default'] ?? false),
                    'is_public' => (bool) ($data['is_public'] ?? false),
                    'sort_order' => (int) $this->ownedTableViewsQuery($user)->max('sort_order') + 1,
                ]);

                $view->save();

                if ($view->is_default) {
                    $this->clearOtherDefaultTableViews($view);
                }

                $this->activeTableView = (string) $view->getKey();
                $this->refreshTableViewActions();

                Notification::make()
                    ->title(__('filament-remember-table::remember-table.notifications.saved', ['name' => $view->name]))
                    ->success()
                    ->send();
            });
    }

    public function updateTableViewAction(): Action
    {
        return Action::make('updateTableView')
            ->label(__('filament-remember-table::remember-table.actions.update'))
            ->icon('heroicon-o-arrow-path')
            ->requiresConfirmation()
            ->modalHeading(fn (): string => __('filament-remember-table::remember-table.modals.update.heading', [
                'name' => $this->getActiveDatabaseTableView()?->name,
            ]))
            ->modalDescription(__('filament-remember-table::remember-table.modals.update.description'))
            ->visible(fn (): bool => $this->getActiveDatabaseTableView()?->isOwnedBy($this->getTableViewsUser())
                && $this->isActiveTableViewModified())
            ->action(function (): void {
                $view = $this->getActiveDatabaseTableView();

                if (! $view?->isOwnedBy($this->getTableViewsUser())) {
                    return;
                }

                $view->update(['state' => $this->captureTableViewState()]);
                $this->cachedTableViews = null;

                Notification::make()
                    ->title(__('filament-remember-table::remember-table.notifications.updated', ['name' => $view->name]))
                    ->success()
                    ->send();
            });
    }

    public function manageTableViewsAction(): Action
    {
        return Action::make('manageTableViews')
            ->label(__('filament-remember-table::remember-table.actions.manage'))
            ->icon('heroicon-o-cog-6-tooth')
            ->modalHeading(__('filament-remember-table::remember-table.modals.manage.heading'))
            ->modalSubmitActionLabel(__('filament-remember-table::remember-table.actions.manage_submit'))
            ->modalWidth('2xl')
            ->visible(fn (): bool => $this->ownedTableViewsQuery($this->getTableViewsUser())->exists())
            ->fillForm(fn (): array => [
                'views' => $this->ownedTableViewsQuery($this->getTableViewsUser())
                    ->orderBy('sort_order')
                    ->get()
                    ->map(fn (TableView $view): array => [
                        'id' => $view->getKey(),
                        'name' => $view->name,
                        'is_default' => $view->is_default,
                        'is_public' => $view->is_public,
                    ])
                    ->all(),
            ])
            ->schema([
                Repeater::make('views')
                    ->hiddenLabel()
                    ->schema([
                        Hidden::make('id'),
                        TextInput::make('name')
                            ->label(__('filament-remember-table::remember-table.fields.name'))
                            ->required()
                            ->maxLength(100)
                            ->columnSpan(2),
                        Toggle::make('is_default')
                            ->label(__('filament-remember-table::remember-table.fields.is_default'))
                            ->inline(false),
                        Toggle::make('is_public')
                            ->label(__('filament-remember-table::remember-table.fields.is_public'))
                            ->inline(false)
                            ->visible(fn (): bool => (bool) config('remember-table.allow_public_views', true)),
                    ])
                    ->columns(4)
                    ->addable(false)
                    ->reorderable()
                    ->deletable(),
            ])
            ->action(function (array $data): void {
                $this->syncManagedTableViews($data['views'] ?? []);

                Notification::make()
                    ->title(__('filament-remember-table::remember-table.notifications.managed'))
                    ->success()
                    ->send();
            });
    }

    public function resetTableViewAction(): Action
    {
        return Action::make('resetTableView')
            ->label(__('filament-remember-table::remember-table.actions.reset'))
            ->icon('heroicon-o-x-mark')
            ->color('gray')
            ->action(fn () => $this->resetTableToDefaultState());
    }

    /**
     * Livewire endpoint used by the dropdown items; also callable from custom UIs.
     */
    public function applyTableView(string $key, bool $notify = true): bool
    {
        $view = $this->findTableView($key);

        if ($view === null) {
            return false;
        }

        [$name, $state] = $view instanceof PresetView
            ? [$view->getLabel(), $view->toState()]
            : [$view->name, $view->state ?? []];

        $this->applyTableViewState($state);
        $this->activeTableView = $key;

        if ($notify) {
            Notification::make()
                ->title(__('filament-remember-table::remember-table.notifications.applied', ['name' => $name]))
                ->success()
                ->send();
        }

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Reading and writing table state
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, mixed>
     */
    protected function captureTableViewState(): array
    {
        $toggles = [];
        $order = [];

        foreach ($this->tableColumns ?? [] as $item) {
            $order[] = $item['name'];

            $columns = ($item['type'] ?? null) === 'group' ? ($item['columns'] ?? []) : [$item];

            foreach ($columns as $column) {
                if ($column['isToggleable'] ?? false) {
                    $toggles[] = ['name' => $column['name'], 'toggled' => (bool) $column['isToggled']];
                }
            }
        }

        $state = [
            'v' => TableState::VERSION,
            'filters' => $this->tableFilters ?? [],
            'search' => (string) $this->tableSearch,
            'columnSearches' => $this->tableColumnSearches ?? [],
            'sort' => $this->tableSort,
            'grouping' => $this->tableGrouping,
            'columns' => $toggles,
            'columnOrder' => $order,
            'perPage' => $this->tableRecordsPerPage,
            'tab' => property_exists($this, 'activeTab') ? $this->activeTab : null,
        ];

        // Only store what the config says to remember.
        foreach (TableState::PARTS as $key => $configKey) {
            if (! $this->remembersTablePart($configKey)) {
                unset($state[$key]);
            }
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected function applyTableViewState(array $state): void
    {
        $table = $this->getTable();

        if (array_key_exists('tab', $state) && $this->remembersTablePart('tab') && method_exists($this, 'getCachedTabs')) {
            $tab = $state['tab'];
            $this->activeTab = ($tab !== null && array_key_exists($tab, $this->getCachedTabs()))
                ? (string) $tab
                : $this->getDefaultActiveTab();
            $this->updatedActiveTab();
        }

        if (array_key_exists('filters', $state) && $this->remembersTablePart('filters')) {
            $filters = array_intersect_key(
                is_array($state['filters']) ? $state['filters'] : [],
                $table->getFilters(),
            );

            // Filling the form resets filters that aren't in the view back to their defaults.
            $this->getTableFiltersForm()->fill($filters);

            if ($table->hasDeferredFilters()) {
                $this->tableFilters = $this->tableDeferredFilters;
            }

            $this->handleTableFilterUpdates();
        }

        if (array_key_exists('search', $state) && $this->remembersTablePart('search')) {
            $this->tableSearch = (string) $state['search'];
            $this->updatedTableSearch();
        }

        if (array_key_exists('columnSearches', $state) && $this->remembersTablePart('column_searches')) {
            $this->tableColumnSearches = array_intersect_key(
                is_array($state['columnSearches']) ? $state['columnSearches'] : [],
                $table->getColumns(),
            );
            $this->updatedTableColumnSearches();
        }

        if (array_key_exists('sort', $state) && $this->remembersTablePart('sort')) {
            $sort = is_string($state['sort']) ? $state['sort'] : null;
            $column = $sort !== null ? (string) str($sort)->before(':') : null;

            $this->tableSort = ($column !== null && $table->getColumn($column) !== null) ? $sort : null;
            $this->updatedTableSort();
        }

        if (array_key_exists('grouping', $state) && $this->remembersTablePart('grouping')) {
            $grouping = is_string($state['grouping']) ? $state['grouping'] : null;
            $group = $grouping !== null ? (string) str($grouping)->before(':') : null;

            $this->tableGrouping = ($group !== null && $table->getGroup($group) !== null) ? $grouping : null;
            $this->updatedTableGrouping();
        }

        if (array_key_exists('columns', $state) && $this->remembersTablePart('columns') && ! $table->hasColumnsLayout()) {
            $this->applyTableViewColumns(
                is_array($state['columns']) ? $state['columns'] : [],
                is_array($state['columnOrder'] ?? null) ? $state['columnOrder'] : null,
            );
        }

        if (array_key_exists('perPage', $state) && $this->remembersTablePart('per_page') && $table->isPaginated()) {
            $perPage = $state['perPage'];

            if ($perPage !== null && in_array($perPage, $table->getPaginationPageOptions())) {
                $this->tableRecordsPerPage = $perPage;
                $this->updatedTableRecordsPerPage();
            }
        }

        $this->resetPage();
        $this->flushCachedTableRecords();
    }

    /**
     * Start from the table's default column state (so columns added after the view
     * was saved still appear), then apply the saved toggles and order.
     *
     * @param  array<int, array{name?: string, toggled?: bool}>  $toggles
     * @param  array<int, string>|null  $order
     */
    protected function applyTableViewColumns(array $toggles, ?array $order): void
    {
        $toggleMap = [];

        foreach ($toggles as $toggle) {
            if (isset($toggle['name'])) {
                $toggleMap[$toggle['name']] = (bool) ($toggle['toggled'] ?? true);
            }
        }

        $applyToggle = function (array $column) use ($toggleMap): array {
            if (($column['isToggleable'] ?? false) && array_key_exists($column['name'], $toggleMap)) {
                $column['isToggled'] = $toggleMap[$column['name']];
            }

            return $column;
        };

        $columns = [];

        foreach ($this->getDefaultTableColumnState() as $item) {
            if (($item['type'] ?? null) === 'group') {
                $item['columns'] = array_map($applyToggle, $item['columns'] ?? []);
                $item['isToggled'] = collect($item['columns'])->contains('isToggled', true);
            } else {
                $item = $applyToggle($item);
            }

            $columns[$item['name']] = $item;
        }

        $defaultOrder = array_keys($columns);
        $wasReordered = false;

        if ($order !== null) {
            $position = array_flip(array_values($order));

            uksort($columns, fn (string $a, string $b): int => ($position[$a] ?? PHP_INT_MAX) <=> ($position[$b] ?? PHP_INT_MAX)
                ?: array_search($a, $defaultOrder) <=> array_search($b, $defaultOrder));

            $wasReordered = array_keys($columns) !== $defaultOrder;
        }

        $this->applyTableColumnManager(array_values($columns), $wasReordered);
    }

    public function resetTableToDefaultState(): void
    {
        $table = $this->getTable();

        if (property_exists($this, 'activeTab') && method_exists($this, 'getDefaultActiveTab')) {
            $this->activeTab = $this->getDefaultActiveTab();
            $this->updatedActiveTab();
        }

        $this->getTableFiltersForm()->fill();

        if ($table->hasDeferredFilters()) {
            $this->tableFilters = $this->tableDeferredFilters;
        }

        $this->handleTableFilterUpdates();

        $this->tableSearch = '';
        $this->updatedTableSearch();

        $this->tableColumnSearches = [];
        $this->updatedTableColumnSearches();

        $this->tableSort = null;
        $this->updatedTableSort();

        $this->tableGrouping = null;
        $this->updatedTableGrouping();

        if (! $table->hasColumnsLayout()) {
            $this->resetTableColumnManager();
        }

        $this->activeTableView = '';
        $this->resetPage();
        $this->flushCachedTableRecords();
    }

    /*
    |--------------------------------------------------------------------------
    | Queries
    |--------------------------------------------------------------------------
    */

    /**
     * The user's own views plus public views, for this table.
     *
     * @return Collection<int, TableView>
     */
    public function getTableViews(): Collection
    {
        $user = $this->getTableViewsUser();

        if ($user === null) {
            return new Collection;
        }

        return $this->cachedTableViews ??= $this->tableViewsQuery()
            ->visibleTo($user)
            ->orderByRaw('CASE WHEN user_type = ? AND user_id = ? THEN 0 ELSE 1 END', [$user->getMorphClass(), $user->getKey()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    protected function tableViewsQuery(): Builder
    {
        return $this->newTableViewModel()->newQuery()
            ->forTable($this->getTableViewsKey())
            ->when(
                config('remember-table.scope_to_tenant', true),
                fn (Builder $query) => $query->forTenant($this->getTableViewsTenantId()),
            );
    }

    protected function ownedTableViewsQuery(?Model $user): Builder
    {
        return $user === null
            ? $this->tableViewsQuery()->whereRaw('1 = 0')
            : $this->tableViewsQuery()->ownedBy($user);
    }

    protected function findTableView(string $key): TableView | PresetView | null
    {
        if (str_starts_with($key, 'preset-')) {
            foreach ($this->getPresetTableViews() as $preset) {
                if ($preset->getKey() === $key) {
                    return $preset;
                }
            }

            return null;
        }

        if (! ctype_digit($key)) {
            return null;
        }

        return $this->getTableViews()->firstWhere(fn (TableView $view): bool => (string) $view->getKey() === $key);
    }

    protected function getActiveDatabaseTableView(): ?TableView
    {
        $view = filled($this->activeTableView) ? $this->findTableView($this->activeTableView) : null;

        return $view instanceof TableView ? $view : null;
    }

    protected function getDefaultTableViewKey(): ?string
    {
        $own = $this->ownedTableViewsQuery($this->getTableViewsUser())
            ->where('is_default', true)
            ->value('id');

        if ($own !== null) {
            return (string) $own;
        }

        foreach ($this->getPresetTableViews() as $preset) {
            if ($preset->isDefault()) {
                return $preset->getKey();
            }
        }

        return null;
    }

    protected function clearOtherDefaultTableViews(TableView $view): void
    {
        $this->ownedTableViewsQuery($this->getTableViewsUser())
            ->whereKeyNot($view->getKey())
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }

    /**
     * @param  array<int|string, array{id?: int|string, name?: string, is_default?: bool, is_public?: bool}>  $items
     */
    protected function syncManagedTableViews(array $items): void
    {
        $user = $this->getTableViewsUser();
        $owned = $this->ownedTableViewsQuery($user)->get()->keyBy(fn (TableView $view): string => (string) $view->getKey());
        $keep = [];
        $hasDefault = false;
        $order = 0;

        foreach ($items as $item) {
            $view = $owned->get((string) ($item['id'] ?? ''));

            if ($view === null) {
                continue;
            }

            $isDefault = (bool) ($item['is_default'] ?? false) && ! $hasDefault;
            $hasDefault = $hasDefault || $isDefault;

            $view->update([
                'name' => $item['name'] ?? $view->name,
                'is_default' => $isDefault,
                'is_public' => config('remember-table.allow_public_views', true) && (bool) ($item['is_public'] ?? false),
                'sort_order' => $order++,
            ]);

            $keep[] = (string) $view->getKey();
        }

        $deleted = $owned->keys()->diff($keep);

        if ($deleted->isNotEmpty()) {
            $this->ownedTableViewsQuery($user)->whereKey($deleted->all())->delete();

            if ($deleted->contains($this->activeTableView)) {
                $this->activeTableView = '';
            }
        }

        $this->refreshTableViewActions();
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<TableView | PresetView>  $views
     */
    protected function makeTableViewItemsGroup(array $views): ?ActionGroup
    {
        if ($views === []) {
            return null;
        }

        $user = $this->getTableViewsUser();

        $actions = array_map(function (TableView | PresetView $view) use ($user): Action {
            $key = $view instanceof PresetView ? $view->getKey() : (string) $view->getKey();
            $label = $view instanceof PresetView ? $view->getLabel() : $view->name;
            $icon = $view instanceof PresetView ? $view->getIcon() : $view->icon;
            $isShared = $view instanceof TableView && ! $view->isOwnedBy($user);
            $isDefault = $view instanceof PresetView
                ? $view->isDefault()
                : ($view->is_default && ! $isShared);

            return Action::make('applyTableView_' . md5($key))
                ->label($label)
                ->icon(fn (): string => $this->activeTableView === $key ? 'heroicon-m-check' : ($icon ?? 'heroicon-o-eye'))
                ->color(fn (): string => $this->activeTableView === $key ? 'primary' : 'gray')
                ->badge(match (true) {
                    $isDefault => __('filament-remember-table::remember-table.badges.default'),
                    $isShared => __('filament-remember-table::remember-table.badges.shared'),
                    $view instanceof PresetView => __('filament-remember-table::remember-table.badges.preset'),
                    default => null,
                })
                ->action(fn () => $this->applyTableView($key));
        }, $views);

        return ActionGroup::make($actions)->dropdown(false);
    }

    protected function getTableViewsButtonLabel(): string
    {
        $view = filled($this->activeTableView) ? $this->findTableView($this->activeTableView) : null;

        if ($view === null) {
            return __('filament-remember-table::remember-table.button.label');
        }

        $name = $view instanceof PresetView ? $view->getLabel() : $view->name;

        return $this->isActiveTableViewModified()
            ? __('filament-remember-table::remember-table.button.modified', ['name' => $name])
            : $name;
    }

    public function isActiveTableViewModified(): bool
    {
        $view = filled($this->activeTableView) ? $this->findTableView($this->activeTableView) : null;

        if ($view === null) {
            return false;
        }

        $saved = $view instanceof PresetView ? $view->toState() : ($view->state ?? []);

        return ! TableState::equals(
            array_intersect_key($this->captureTableViewState(), $saved),
            $saved,
            config('remember-table.remember', []),
        );
    }

    protected function remembersTablePart(string $configKey): bool
    {
        return (bool) config("remember-table.remember.{$configKey}", true);
    }

    protected function requestHasTableState(): bool
    {
        $keys = ['filters', 'search', 'sort', 'grouping', 'tab', 'tableFilters', 'tableSearch', 'tableSort', 'tableGrouping'];

        return collect(request()->query())->keys()->intersect($keys)->isNotEmpty();
    }

    /**
     * Header actions are cached per request; rebuild them after views change
     * so the dropdown shows the new list straight away.
     */
    protected function refreshTableViewActions(): void
    {
        $this->cachedTableViews = null;

        if (property_exists($this, 'cachedHeaderActions') && method_exists($this, 'cacheInteractsWithHeaderActions')) {
            $this->cachedHeaderActions = [];
            $this->cacheInteractsWithHeaderActions();
        }
    }

    protected function getTableViewsUser(): ?Model
    {
        $user = class_exists(Filament::class) ? Filament::auth()->user() : auth()->user();

        return $user instanceof Model ? $user : null;
    }

    protected function getTableViewsTenantId(): int | string | null
    {
        if (! class_exists(Filament::class)) {
            return null;
        }

        return Filament::getTenant()?->getKey();
    }

    protected function newTableViewModel(): TableView
    {
        $model = config('remember-table.model', TableView::class);

        return new $model;
    }
}
