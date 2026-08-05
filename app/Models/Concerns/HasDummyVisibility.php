<?php

namespace App\Models\Concerns;

use App\Models\Scopes\DummyVisibilityScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Adds the review-account visibility rule as a global scope.
 *
 * Not used on User — see `HasDummyFlag`, which User uses instead so that
 * authentication can still resolve the account.
 */
trait HasDummyVisibility
{
    use HasDummyFlag;

    public static function bootHasDummyVisibility(): void
    {
        static::addGlobalScope(new DummyVisibilityScope);
    }

    /**
     * Query real and review rows together, whoever is looking.
     *
     * For work that must be deterministic rather than viewer-dependent —
     * ID sequences, seeding, maintenance jobs.
     */
    public function scopeWithDummy(Builder $query): Builder
    {
        return $query->withoutGlobalScope(DummyVisibilityScope::class);
    }

    /**
     * Real rows only, whoever is looking.
     *
     * For inventory math, money totals and public feeds — anywhere a review
     * record would distort a real number.
     */
    public function scopeRealOnly(Builder $query): Builder
    {
        return $query->withoutGlobalScope(DummyVisibilityScope::class)
            ->where($query->getModel()->getTable() . '.is_dummy', false);
    }
}
