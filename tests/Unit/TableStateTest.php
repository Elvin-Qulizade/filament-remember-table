<?php

use Sehsah\FilamentRememberTable\Support\TableState;

$all = [
    'filters' => true, 'search' => true, 'column_searches' => true, 'sort' => true,
    'grouping' => true, 'columns' => true, 'per_page' => true, 'tab' => true,
];

it('treats empty filter values as equal however they are stored', function () use ($all) {
    $a = ['filters' => ['status' => ['value' => null], 'type' => ['values' => []]]];
    $b = ['filters' => []];

    expect(TableState::equals($a, $b, $all))->toBeTrue();
});

it('ignores filter key order', function () use ($all) {
    $a = ['filters' => ['a' => ['value' => 1], 'b' => ['value' => 2]]];
    $b = ['filters' => ['b' => ['value' => 2], 'a' => ['value' => 1]]];

    expect(TableState::equals($a, $b, $all))->toBeTrue();
});

it('detects a changed sort', function () use ($all) {
    expect(TableState::equals(['sort' => 'name:asc'], ['sort' => 'name:desc'], $all))->toBeFalse();
});

it('ignores parts that are disabled in config', function () use ($all) {
    $enabled = array_merge($all, ['search' => false]);

    expect(TableState::equals(['search' => 'foo'], ['search' => 'bar'], $enabled))->toBeTrue();
});

it('only compares the columns both states mention', function () use ($all) {
    $current = ['columns' => [['name' => 'email', 'toggled' => true], ['name' => 'phone', 'toggled' => false]]];
    $preset = ['columns' => [['name' => 'email', 'toggled' => true]]];

    expect(TableState::equals($current, $preset, $all))->toBeTrue();
});

it('ignores column order when one side has none', function () use ($all) {
    expect(TableState::equals(['columnOrder' => ['a', 'b']], ['columnOrder' => null], $all))->toBeTrue()
        ->and(TableState::equals(['columnOrder' => ['a', 'b']], ['columnOrder' => ['b', 'a']], $all))->toBeFalse();
});
