<?php

namespace App\Models\Concerns;

use App\Models\Scopes\DummyVisibilityScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * The `is_dummy` filters without a global scope.
 *
 * User uses this rather than `HasDummyVisibility`: the auth guard resolves a
 * token by looking up its user, and a global scope on User would filter the
 * review account out of that lookup and make it impossible to log in. So User
 * queries opt in to the rule explicitly with `dummyVisible()`.
 */
trait HasDummyFlag
{
    /**
     * Apply the same rule the global scope would: the review account sees its
     * own records, everyone else sees real ones.
     */
    public function scopeDummyVisible(Builder $query): Builder
    {
        return DummyVisibilityScope::constrain($query, $query->getModel()->getTable());
    }

    /**
     * Real rows only, whoever is looking.
     */
    public function scopeRealOnly(Builder $query): Builder
    {
        return $query->where($query->getModel()->getTable() . '.is_dummy', false);
    }
}
