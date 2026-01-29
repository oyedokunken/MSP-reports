<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ScopeCustomerAccess
{
    /**
     * Handle an incoming request.
     *
     * Apply customer-level scoping to all queries if user has scoped_customers defined.
     * This ensures users can only access data for their assigned customers.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // If user is authenticated and has customer scoping defined
        if ($user && !empty($user->scoped_customers)) {
            // Store scoped customer IDs in request for use in controllers
            $request->attributes->set('scoped_customers', $user->scoped_customers);

            // Apply global query scope for this request
            // This will automatically filter all Customer queries
            \App\Models\Customer::addGlobalScope('user_customer_scope', function ($query) use ($user) {
                $query->whereIn('customer_id', $user->scoped_customers);
            });

            // Also scope IntegrationCustomer queries (for customer mapping)
            \App\Models\IntegrationCustomer::addGlobalScope('user_customer_scope', function ($query) use ($user) {
                $query->whereIn('customer_id', $user->scoped_customers);
            });

            // Scope report-related queries
            if (class_exists(\App\Models\ReportSchedule::class)) {
                \App\Models\ReportSchedule::addGlobalScope('user_customer_scope', function ($query) use ($user) {
                    $query->whereIn('customer_id', $user->scoped_customers);
                });
            }

            if (class_exists(\App\Models\GeneratedReport::class)) {
                \App\Models\GeneratedReport::addGlobalScope('user_customer_scope', function ($query) use ($user) {
                    $query->whereIn('customer_id', $user->scoped_customers);
                });
            }
        }

        return $next($request);
    }
}
