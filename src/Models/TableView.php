<?php

namespace Sehsah\FilamentRememberTable\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $user_type
 * @property int|string $user_id
 * @property ?string $tenant_id
 * @property string $table_key
 * @property string $name
 * @property ?string $icon
 * @property array<string, mixed> $state
 * @property bool $is_default
 * @property bool $is_public
 * @property int $sort_order
 */
class TableView extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'state' => 'array',
        'is_default' => 'boolean',
        'is_public' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function getTable(): string
    {
        return config('remember-table.table_name', parent::getTable());
    }

    public function user(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForTable(Builder $query, string $tableKey): Builder
    {
        return $query->where('table_key', $tableKey);
    }

    public function scopeForTenant(Builder $query, int | string | null $tenantId): Builder
    {
        return $tenantId === null
            ? $query->whereNull('tenant_id')
            : $query->where('tenant_id', (string) $tenantId);
    }

    public function scopeOwnedBy(Builder $query, Model $user): Builder
    {
        return $query
            ->where('user_type', $user->getMorphClass())
            ->where('user_id', $user->getKey());
    }

    /**
     * Views the user owns, plus views other users made public.
     */
    public function scopeVisibleTo(Builder $query, Model $user): Builder
    {
        return $query->where(function (Builder $query) use ($user): void {
            $query
                ->where(fn (Builder $query) => $query->ownedBy($user))
                ->when(
                    config('remember-table.allow_public_views', true),
                    fn (Builder $query) => $query->orWhere('is_public', true),
                );
        });
    }

    public function isOwnedBy(?Model $user): bool
    {
        return $user !== null
            && $this->user_type === $user->getMorphClass()
            && (string) $this->user_id === (string) $user->getKey();
    }
}
