<?php

namespace Sehsah\FilamentRememberTable;

use Sehsah\FilamentRememberTable\Support\TableState;

/**
 * A view defined in code by the developer. Users can apply it but not edit or delete it.
 */
class PresetView
{
    protected string $key;

    protected ?string $icon = null;

    protected bool $isDefault = false;

    /** @var array<string, mixed> */
    protected array $filters = [];

    protected ?string $search = null;

    protected ?string $sort = null;

    protected ?string $grouping = null;

    /** @var array<string, bool> */
    protected array $columnToggles = [];

    protected int | string | null $perPage = null;

    protected ?string $tab = null;

    final public function __construct(protected string $label)
    {
        $this->key = static::slug($label);
    }

    public static function make(string $label): static
    {
        return new static($label);
    }

    /**
     * A stable identifier used in the URL (?view=preset-...). Defaults to a slug of the label.
     */
    public function key(string $key): static
    {
        $this->key = static::slug($key);

        return $this;
    }

    public function icon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Apply this view when a user opens the table and has no default view of their own.
     */
    public function default(bool $condition = true): static
    {
        $this->isDefault = $condition;

        return $this;
    }

    /**
     * Raw filter form state, keyed by filter name.
     * Example: ['status' => ['value' => 'pending'], 'is_paid' => ['value' => true]]
     *
     * @param  array<string, mixed>  $filters
     */
    public function filters(array $filters): static
    {
        $this->filters = $filters;

        return $this;
    }

    public function search(?string $search): static
    {
        $this->search = $search;

        return $this;
    }

    public function sort(string $column, string $direction = 'asc'): static
    {
        $this->sort = $column . ':' . (strtolower($direction) === 'desc' ? 'desc' : 'asc');

        return $this;
    }

    public function grouping(string $group, string $direction = 'asc'): static
    {
        $this->grouping = $group . ':' . (strtolower($direction) === 'desc' ? 'desc' : 'asc');

        return $this;
    }

    /**
     * @param  array<string>  $columns  Toggleable columns to show.
     */
    public function visibleColumns(array $columns): static
    {
        foreach ($columns as $column) {
            $this->columnToggles[$column] = true;
        }

        return $this;
    }

    /**
     * @param  array<string>  $columns  Toggleable columns to hide.
     */
    public function hiddenColumns(array $columns): static
    {
        foreach ($columns as $column) {
            $this->columnToggles[$column] = false;
        }

        return $this;
    }

    public function perPage(int | string | null $perPage): static
    {
        $this->perPage = $perPage;

        return $this;
    }

    /**
     * The resource list page tab to activate (a key from getTabs()).
     */
    public function tab(?string $tab): static
    {
        $this->tab = $tab;

        return $this;
    }

    public function getKey(): string
    {
        return 'preset-' . $this->key;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    /**
     * @return array<string, mixed>
     */
    public function toState(): array
    {
        $columns = [];

        foreach ($this->columnToggles as $name => $isToggled) {
            $columns[] = ['name' => $name, 'toggled' => $isToggled];
        }

        $state = [
            'v' => TableState::VERSION,
            'filters' => $this->filters,
            'search' => (string) $this->search,
            'columnSearches' => [],
            'sort' => $this->sort,
            'grouping' => $this->grouping,
            'columns' => $columns,
            'columnOrder' => null,
            'perPage' => $this->perPage,
            'tab' => $this->tab,
        ];

        // Leave the page's tab and page size alone unless the preset sets them.
        return array_filter(
            $state,
            fn (mixed $value, string $key): bool => ! (in_array($key, ['perPage', 'tab', 'columnOrder'], true) && $value === null),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    protected static function slug(string $value): string
    {
        $slug = mb_strtolower(trim((string) preg_replace('/[^\p{L}\p{N}]+/u', '-', $value), '-'));

        return $slug !== '' ? $slug : substr(md5($value), 0, 8);
    }
}
