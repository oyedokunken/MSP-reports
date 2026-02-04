<?php

declare(strict_types=1);

namespace App\Actions\Report;

use App\Models\ReportSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class GetScheduledReportsAction
{
    /**
     * @return Collection<int, ReportSchedule>
     */
    public function execute(User $authUser, bool $includeInactive): Collection
    {
        $query = ReportSchedule::where('company_id', $authUser->company_id);
        
        if (!$includeInactive) {
            $query->where('active', true);
        }

        return $query->with('customer:customer_id,canonical_name,report_destination,report_configuration')
            ->orderBy('next_scheduled_at', 'asc')
            ->get();
    }
}
