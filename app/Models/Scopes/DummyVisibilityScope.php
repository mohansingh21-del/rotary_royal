<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Keeps the seeded review account's records apart from everyone else's.
 *
 * Applied as a global scope so a query leaks nothing by default — a new
 * endpoint has to opt out explicitly rather than remember to filter.
 *
 *   the review account → only its own records
 *   everyone else      → only real records, admins included
 *
 * Review bookings never touch inventory, so there is nothing for an admin to
 * reconcile and no reason to show them the rows.
 */
class DummyVisibilityScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        static::constrain($builder, $model->getTable());
    }

    /**
     * Shared by the global scope and by User's explicit `dummyVisible()` —
     * User can't carry a global scope, since the auth guard has to be able to
     * look up the token's own user unfiltered.
     */
    public static function constrain(Builder $builder, string $table): Builder
    {
        return $builder->where($table . '.is_dummy', static::actingUserIsDummy());
    }

    /**
     * Whether the review account is the one acting on this request.
     *
     * Several endpoints — booking and donation creation among them — are public
     * routes with no auth middleware, so `auth()->user()` is null there even
     * when the caller sent a valid token. Falling back to the sanctum guard
     * resolves the bearer token directly, which keeps a review booking from
     * being written as a real one.
     */
    public static function actingUserIsDummy(): bool
    {
        return (bool) static::actingUser()?->is_dummy;
    }

    public static function actingUser()
    {
        return auth()->user() ?? auth('sanctum')->user();
    }
}
