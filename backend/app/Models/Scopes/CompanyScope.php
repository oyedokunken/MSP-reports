<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CompanyScope implements Scope
{
    /**
     * @param Builder<Model> $builder
     * @param Model $model
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (auth()->check()) {
            $companyId = auth()->user()->company_id;
            
            if ($companyId) {
                $table = $model->getTable();
                
                // Handle tables without direct company_id column
                if ($table === 'integration_customers') {
                    // Filter through integration relationship
                    $builder->whereHas('integration', function ($query) use ($companyId) {
                        $query->where('integrations.company_id', $companyId);
                    });
                } elseif ($table === 'customer_mappings') {
                    // Filter through customer relationship
                    $builder->whereHas('customer', function ($query) use ($companyId) {
                        $query->where('customers.company_id', $companyId);
                    });
                } else {
                    // Standard company_id filtering
                    $builder->where($table . '.company_id', $companyId);
                }
            }
        }
    }

    /**
     * @param Builder<Model> $builder
     */
    public function extend(Builder $builder): void
    {
        $builder->macro('withoutCompanyScope', function (Builder $builder) {
            return $builder->withoutGlobalScope(CompanyScope::class);
        });

        $builder->macro('forCompany', function (Builder $builder, int $companyId) {
            return $builder->withoutGlobalScope(CompanyScope::class)
                          ->where($builder->getModel()->getTable() . '.company_id', $companyId);
        });
    }
}
