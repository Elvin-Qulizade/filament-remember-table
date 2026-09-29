<?php

namespace Sehsah\FilamentRememberTable\Support;

/**
 * Helpers for comparing stored table states. Framework-free so it is easy to test.
 */
class TableState
{
    /**
     * Bump when the shape of the stored state changes, so old views can be migrated on load.
     */
    public const VERSION = 1;

    /**
     * Map of state keys to the config keys in `remember-table.remember`.
     *
     * @var array<string, string>
     */
    public const PARTS = [
        'filters' => 'filters',
        'search' => 'search',
        'columnSearches' => 'column_searches',
        'sort' => 'sort',
        'grouping' => 'grouping',
        'columns' => 'columns',
        'columnOrder' => 'columns',
        'perPage' => 'per_page',
        'tab' => 'tab',
    ];

    /**
     * Keep only the enabled parts, and put values in a canonical form so two
     * states that mean the same thing compare as equal.
     *
     * @param  array<string, mixed>  $state
     * @param  array<string, bool>  $enabled  config('remember-table.remember')
     * @return array<string, mixed>
     */
    public static function normalize(array $state, array $enabled): array
    {
        $normalized = [];

        foreach (static::PARTS as $key => $configKey) {
            if (! ($enabled[$configKey] ?? true)) {
                continue;
            }

            $value = $state[$key] ?? null;

            $normalized[$key] = match ($key) {
                'search' => trim((string) $value),
                'sort', 'grouping' => is_string($value) && trim($value) !== '' ? $value : null,
                'perPage', 'tab' => $value === null || $value === '' ? null : (string) $value,
                'columns' => static::normalizeColumns(is_array($value) ? $value : []),
                'columnOrder' => is_array($value) ? array_values($value) : null,
                default => static::sortRecursive(static::withoutEmpty(is_array($value) ? $value : [])),
            };
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     * @param  array<string, bool>  $enabled
     */
    public static function equals(array $a, array $b, array $enabled): bool
    {
        $a = static::normalize($a, $enabled);
        $b = static::normalize($b, $enabled);

        // A view without a saved column order (e.g. a preset) doesn't care about order.
        if (($a['columnOrder'] ?? null) === null || ($b['columnOrder'] ?? null) === null) {
            unset($a['columnOrder'], $b['columnOrder']);
        }

        // A view that only mentions some columns only cares about those.
        if (isset($a['columns'], $b['columns'])) {
            $a['columns'] = array_intersect_key($a['columns'], $b['columns']);
            $b['columns'] = array_intersect_key($b['columns'], $a['columns']);
        }

        return $a == $b;
    }

    /**
     * @param  array<int, array{name?: string, toggled?: bool}>  $columns
     * @return array<string, bool>
     */
    protected static function normalizeColumns(array $columns): array
    {
        $map = [];

        foreach ($columns as $column) {
            if (is_array($column) && isset($column['name'])) {
                $map[(string) $column['name']] = (bool) ($column['toggled'] ?? true);
            }
        }

        ksort($map);

        return $map;
    }

    /**
     * Drop null, empty-string and empty-array leaves so "no value" compares equal however it was stored.
     *
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    protected static function withoutEmpty(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $item = static::withoutEmpty($item);
            }

            if ($item === null || $item === '' || $item === []) {
                unset($value[$key]);

                continue;
            }

            $value[$key] = $item;
        }

        return $value;
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    protected static function sortRecursive(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = static::sortRecursive($item);
            }
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}

