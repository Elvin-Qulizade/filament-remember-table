<?php

use Sehsah\FilamentRememberTable\PresetView;
use Sehsah\FilamentRememberTable\Support\TableState;

it('builds a state array', function () {
    $state = PresetView::make('Pending orders')
        ->filters(['status' => ['value' => 'pending']])
        ->sort('created_at', 'DESC')
        ->grouping('customer.name')
        ->hiddenColumns(['notes'])
        ->visibleColumns(['total'])
        ->toState();

    expect($state)
        ->v->toBe(TableState::VERSION)
        ->filters->toBe(['status' => ['value' => 'pending']])
        ->sort->toBe('created_at:desc')
        ->grouping->toBe('customer.name:asc')
        ->columns->toBe([
            ['name' => 'notes', 'toggled' => false],
            ['name' => 'total', 'toggled' => true],
        ])
        ->not->toHaveKey('tab')
        ->not->toHaveKey('perPage')
        ->not->toHaveKey('columnOrder');
});

it('includes tab and per page only when set', function () {
    expect(PresetView::make('x')->tab('active')->perPage(50)->toState())
        ->tab->toBe('active')
        ->perPage->toBe(50);
});

it('makes a url-safe key from the label', function () {
    expect(PresetView::make('Pending Orders!')->getKey())->toBe('preset-pending-orders')
        ->and(PresetView::make('طلبات معلقة')->getKey())->toBe('preset-طلبات-معلقة')
        ->and(PresetView::make('x')->key('My Key')->getKey())->toBe('preset-my-key');
});

it('can be marked as default', function () {
    expect(PresetView::make('x')->isDefault())->toBeFalse()
        ->and(PresetView::make('x')->default()->isDefault())->toBeTrue();
});
