<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CompanyScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     * 
     * Automatically filters all queries to only return records
     * belonging to the authenticated user's company.
     * 
     * This is CRITICAL for multi-tenancy isolation.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Only apply scope if user is authenticated
        if (auth()->check()) {
            $companyId = auth()->user()->company_id;
            
            if ($companyId) {
                $builder->where($model->getTable() . '.company_id', $companyId);
            }
        }
    }

    /**
     * Extend the query builder with methods to bypass the scope when needed.
     * Use with extreme caution - only for admin operations.
     */
    public function extend(Builder $builder): void
    {
        $builder->macro('withoutCompanyScope', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });

        $builder->macro('forCompany', function (Builder $builder, int $companyId) {
            return $builder->withoutGlobalScope($this)
                          ->where($builder->getModel()->getTable() . '.company_id', $companyId);
        });
    }
}
