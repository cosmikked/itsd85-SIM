<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait Sortable
{
    /**
     * Apply sorting to the query builder based on the request's 'sort' parameter.
     */
    public function applySorting(Builder $query, Request $request): Builder
    {
        if ($request->has('sort')) {
            $sorts = explode(',', $request->query('sort'));

            foreach ($sorts as $sortColumn) {
                $direction = 'asc';

                if (str_starts_with($sortColumn, '-')) {
                    $direction = 'desc';
                    $sortColumn = ltrim($sortColumn, '-');
                }

                $query->orderBy($sortColumn, $direction);
            }
        }

        return $query;
    }
}
