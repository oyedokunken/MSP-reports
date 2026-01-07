<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CustomerScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (auth()->check()) {
            $user = auth()->user();
            
            // If the user has scoped customers, restrict the query
            if ($user->scoped_customers && is_array($user->scoped_customers) && count($user->scoped_customers) > 0) {
                $column = $this->getScopeColumn($model);
                
                if ($column) {
                    $builder->whereIn($model->getTable() . '.' . $column, $user->scoped_customers);
                }
            }
        }
    }

    /**
     * Determine the column to use for scoping based on the model.
     */
    protected function getScopeColumn(Model $model): ?string
    {
        // For Customer model, use the primary key
        if ($model instanceof \App\Models\Customer) {
            return 'customer_id';
        }

        // For other models, check for customer_id column
        // This is a simple heuristic; can be made more explicit if needed
        $table = $model->getTable();
        
        // Models known to have customer_id
        $modelsWithCustomerId = [
            'report_schedules',
            'generated_reports',
            'customer_mappings',
            'active_directory_users',
            'active_directory_groups',
            'exchange_mailboxes',
            'integration_customers',
            'integration_data',
        ];

        if (in_array($table, $modelsWithCustomerId)) {
            return 'customer_id';
        }

        return null;
    }
}
