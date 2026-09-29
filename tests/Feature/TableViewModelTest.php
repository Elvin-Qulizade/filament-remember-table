<?php

use Sehsah\FilamentRememberTable\Models\TableView;
use Sehsah\FilamentRememberTable\Tests\Fixtures\User;

function makeView(User $user, array $attributes = []): TableView
{
    return TableView::create(array_merge([
        'user_type' => $user->getMorphClass(),
        'user_id' => $user->getKey(),
        'table_key' => 'orders',
        'name' => 'View',
        'state' => ['v' => 1, 'sort' => 'id:desc'],
    ], $attributes));
}

it('stores state as an array', function () {
    $view = makeView(User::create(['name' => 'A']));

    expect($view->fresh()->state)->toBe(['v' => 1, 'sort' => 'id:desc']);
});

it('shows a user their own views and public views only', function () {
    [$alice, $bob] = [User::create(['name' => 'Alice']), User::create(['name' => 'Bob'])];

    makeView($alice, ['name' => 'Alice private']);
    makeView($bob, ['name' => 'Bob private']);
    makeView($bob, ['name' => 'Bob public', 'is_public' => true]);

    expect(TableView::forTable('orders')->visibleTo($alice)->pluck('name')->sort()->values()->all())
        ->toBe(['Alice private', 'Bob public']);
});

it('hides public views of other users when sharing is disabled', function () {
    config()->set('remember-table.allow_public_views', false);

    [$alice, $bob] = [User::create(['name' => 'Alice']), User::create(['name' => 'Bob'])];
    makeView($bob, ['is_public' => true]);

    expect(TableView::visibleTo($alice)->count())->toBe(0);
});

it('scopes views by table and tenant', function () {
    $user = User::create(['name' => 'A']);

    makeView($user, ['tenant_id' => '1']);
    makeView($user, ['tenant_id' => '2']);
    makeView($user, ['table_key' => 'customers', 'tenant_id' => '1']);

    expect(TableView::forTable('orders')->forTenant(1)->count())->toBe(1)
        ->and(TableView::forTable('orders')->forTenant(null)->count())->toBe(0);
});

it('knows its owner', function () {
    [$alice, $bob] = [User::create(['name' => 'Alice']), User::create(['name' => 'Bob'])];
    $view = makeView($alice);

    expect($view->isOwnedBy($alice))->toBeTrue()
        ->and($view->isOwnedBy($bob))->toBeFalse()
        ->and($view->isOwnedBy(null))->toBeFalse();
});
