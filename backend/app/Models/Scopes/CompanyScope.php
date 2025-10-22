<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        
        if (auth()->check()) {
            $companyId = auth()->user()->company_id;
            
            if ($companyId) {
                $builder->where($model->getTable() . '.company_id', $companyId);
            }
        }
    }

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
