<?php

declare(strict_types=1);

namespace App\Reports\SendmarcSummary;

use App\Models\Customer;
use App\Reports\Contracts\BaseReport;
use App\Models\IntegrationData;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SendmarcSummary extends BaseReport
{
    protected SendmarcDataGatherer $dataGatherer;

    public function __construct(int $companyId)
    {
        parent::__construct($companyId);
        $this->dataGatherer = new SendmarcDataGatherer();
    }

    public function getRequiredDataTypes(): array
    {
        return ['sender_statistics', 'volume_totals', 'volume_timeline', 'ip_address_statistics'];
    }

    public function getRequiredHistoricalRange(): int
    {
        return 31; // monthly
    }

    public function validate(Customer $customer, Carbon $startDate, Carbon $endDate): bool
    {
        $hasSendmarc = $customer->integrationCustomers()
            ->whereHas('integration', function ($q) {
                $q->where('integration_type', 'Sendmarc')->whereIn('status', ['active', 'importing_historical', 'syncing']);
            })
            ->exists();
        if (!$hasSendmarc) {
            \Log::info('Sendmarc Monthly Summary validation - No Sendmarc integration', [
                'customer_id' => $customer->customer_id,
            ]);
            return false;
        }

        $integrationCustomerIds = $customer->integrationCustomers()
            ->whereHas('integration', function ($q) {
                $q->where('integration_type', 'Sendmarc')->whereIn('status', ['active', 'importing_historical', 'syncing']);
            })
            ->pluck('integration_customers.integration_customer_id')
            ->toArray();

        if (empty($integrationCustomerIds)) {
            return false;
        }

        $hasData = IntegrationData::where('company_id', $customer->company_id)
            ->whereIn('data_type', $this->getRequiredDataTypes())
            ->whereIn('integration_customer_id', $integrationCustomerIds)
            ->exists();

        return $hasData;
    }

    public function generate(Customer $customer, Carbon $startDate, Carbon $endDate, array $options = []): array
    {
        $integrationCustomerIds = $customer->integrationCustomers()
            ->whereHas('integration', function ($q) {
                $q->where('integration_type', 'Sendmarc')->whereIn('status', ['active', 'importing_historical', 'syncing']);
            })
            ->pluck('integration_customers.integration_customer_id')
            ->toArray();

        // Check for included sections
        $includedSections = $options['included_sections'] ?? $options['data_types'] ?? ['all'];
        $sectionsToRender = count($includedSections) > 0 && !in_array('all', $includedSections) ? $includedSections : ['all'];

        // Determine timezone for date display in report
        $timezone = $options['timezone'] ?? $customer->reportSchedules()->where('report_type', 'sendmarc_summary')->first()?->timezone ?? config('app.timezone');
        $displayStart = $startDate->copy()->setTimezone($timezone);
        $displayEnd = $endDate->copy()->setTimezone($timezone);

        // Gather (Sendmarc data currently stored as snapshot timestamps)
        // Apply date filtering, but if it yields no results and we have data,
        // fall back to using the available snapshot so the report still renders useful information.
        
        $senders = collect();
        if (in_array('all', $sectionsToRender) || in_array('compliance', $sectionsToRender) || in_array('senders', $sectionsToRender) || in_array('recommendations', $sectionsToRender)) {
            $rawSenders = $this->dataGatherer->gatherSenderStatistics($customer, $integrationCustomerIds);
            $senders = $rawSenders->filter(fn ($row) => $this->inRange($row['timestamp'], $startDate, $endDate));
            if ($senders->isEmpty() && $rawSenders->isNotEmpty()) {
                $senders = $rawSenders;
            }
        }

        $volumeTotals = collect();
        if (in_array('all', $sectionsToRender) || in_array('executive_summary', $sectionsToRender) || in_array('recommendations', $sectionsToRender)) {
            // Prefer timeline data for totals if available as it is more granular and accurate than aggregate totals
            $rawTimeline = $this->dataGatherer->gatherVolumeTimeline($customer, $integrationCustomerIds);
            $volumeTotals = $rawTimeline->filter(fn ($row) => $this->inRange($row['timestamp'], $startDate, $endDate));
            
            if ($volumeTotals->isEmpty()) {
                $rawVolumeTotals = $this->dataGatherer->gatherVolumeTotals($customer, $integrationCustomerIds);
                $volumeTotals = $rawVolumeTotals->filter(fn ($row) => $this->inRange($row['timestamp'], $startDate, $endDate));
                if ($volumeTotals->isEmpty() && $rawVolumeTotals->isNotEmpty()) {
                    $volumeTotals = $rawVolumeTotals;
                }
            }
        }

        $timeline = collect();
        if (in_array('all', $sectionsToRender) || in_array('volume_timeline', $sectionsToRender)) {
            // Reuse the already gathered and filtered timeline if possible
            if ($volumeTotals->isNotEmpty() && isset($volumeTotals->first()['payload']['index'])) {
                $timeline = $volumeTotals;
            } else {
                $rawTimeline = $this->dataGatherer->gatherVolumeTimeline($customer, $integrationCustomerIds);
                $timeline = $rawTimeline->filter(fn ($row) => $this->inRange($row['timestamp'], $startDate, $endDate));
                if ($timeline->isEmpty() && $rawTimeline->isNotEmpty()) {
                    $timeline = $rawTimeline;
                }
            }
        }

        $ipStats = collect();
        if (in_array('all', $sectionsToRender) || in_array('ip_sources', $sectionsToRender)) {
            $rawIpStats = $this->dataGatherer->gatherIpAddressStatistics($customer, $integrationCustomerIds);
            $ipStats = $rawIpStats->filter(fn ($row) => $this->inRange($row['timestamp'], $startDate, $endDate));
            if ($ipStats->isEmpty() && $rawIpStats->isNotEmpty()) {
                $ipStats = $rawIpStats;
            }
        }

        // Summaries
        $totals = $this->aggregateTotals($volumeTotals);
        $senderSummary = $this->summarizeSenders($senders);
        $ipSummary = $this->summarizeIpSources($ipStats);
        $compliance = $this->calculateCompliance($totals);

        $executiveSummary = [
            'total_emails' => $totals['total_incidents'] ?? 0,
            'complete_passes' => $totals['total_complete_passes'] ?? 0,
            'partial_passes' => ($totals['total_dkim_fails_spf_passes'] ?? 0) + ($totals['total_spf_fails_dkim_passes'] ?? 0),
            'complete_failures' => $totals['total_complete_failures'] ?? 0,
            'compliance_rate' => $compliance['compliance_rate'],
            'authorized_senders' => $senderSummary['authorized_senders'],
            'unauthorized_senders' => $senderSummary['unauthorized_senders'],
            'top_sender' => $senderSummary['top_sender'],
        ];

        $recommendations = $this->buildRecommendations($totals, $senderSummary, $ipSummary);

        return [
            'generated_at' => now()->toDateTimeString(),
            'customer' => $customer,
            'customer_data' => [
                'id' => $customer->customer_id,
                'name' => $customer->canonical_name,
            ],
            'period' => [
                'start' => $this->formatDate($displayStart),
                'end' => $this->formatDate($displayEnd),
                'formatted' => $this->formatDateRange($displayStart, $displayEnd),
            ],
            'executive_summary' => $executiveSummary,
            'totals' => $totals,
            'compliance' => $compliance,
            'top_senders' => $senderSummary['top_senders'],
            'ip_sources' => $ipSummary['top_ips'],
            'alerts' => $recommendations,
            'volume_timeline' => $timeline->map(fn($row) => [
                'date' => Carbon::parse($row['timestamp'])->setTimezone($timezone)->format('Y-m-d'),
                'total' => $row['payload']['total_incidents'] ?? 0,
                'passes' => $row['payload']['total_complete_passes'] ?? 0,
                'failures' => $row['payload']['total_complete_failures'] ?? 0,
            ])->sortBy('date')->values()->toArray(),
            'sections_to_render' => $sectionsToRender,
            'options' => $options,
        ];
    }

    public function getTemplate(): string
    {
        return resource_path('views/Reports/SendmarcSummary/template.blade.php');
    }

    public function getMetadata(): array
    {
        return [
            'id' => 'sendmarc_summary',
            'name' => 'Sendmarc DMARC Summary',
            'description' => 'Email authentication and DMARC compliance analysis identifying authorized vs unauthorized senders',
            'category' => 'security',
            'data_types' => $this->getRequiredDataTypes(),
            'frequencies' => ['monthly', 'weekly', 'on-demand'],
            'formats' => ['pdf', 'html'],
            'supports_scheduling' => true,
            'supports_email_delivery' => true,
            'supports_api_delivery' => true,
            'estimated_generation_time' => 60,
        ];
    }

    private function inRange($timestamp, Carbon $start, Carbon $end): bool
    {
        if (!$timestamp) return false;
        $ts = $timestamp instanceof Carbon ? $timestamp : Carbon::parse($timestamp);
        return $ts->between($start, $end);
    }

    /**
     * @param Collection<int, array<string, mixed>> $volumeTotals
     * @return array<string, int>
     */
    private function aggregateTotals(Collection $volumeTotals): array
    {
        $fields = [
            'total_incidents', 'total_complete_passes', 'total_dkim_fails_spf_passes',
            'total_spf_fails_dkim_passes', 'total_complete_failures', 'total_overrides_none',
            'total_overrides_quarantine', 'total_overrides_reject', 'total_sources'
        ];
        $totals = array_fill_keys($fields, 0);
        foreach ($volumeTotals as $row) {
            $payload = $row['payload'] ?? [];
            foreach ($fields as $f) {
                $totals[$f] += (int)($payload[$f] ?? 0);
            }
        }
        return $totals;
    }

    /**
     * @param Collection<int, array<string, mixed>> $senders
     * @return array{top_senders: array<int, array<string, mixed>>, top_sender: string|null, authorized_senders: int, unauthorized_senders: int}
     */
    private function summarizeSenders(Collection $senders): array
    {
        $grouped = $senders->groupBy(function (array $row): string {
            $p = $row['payload'] ?? [];
            return (string)($p['organisation'] ?? $p['service'] ?? 'unknown');
        });
        $summary = $grouped->map(function ($rows, $key) {
            $total = $rows->sum(fn ($r) => (int)($r['payload']['total_incidents'] ?? 0));
            $passes = $rows->sum(fn ($r) => (int)($r['payload']['total_complete_passes'] ?? 0));
            $fails = $rows->sum(fn ($r) => (int)($r['payload']['total_complete_failures'] ?? 0));
            $blacklisted = $rows->sum(fn ($r) => (int)($r['payload']['total_blacklisted_incidents'] ?? 0));
            $category = optional($rows->first())['payload']['category'] ?? 'unknown';
            return [
                'sender' => $key,
                'category' => $category,
                'total' => $total,
                'passes' => $passes,
                'failures' => $fails,
                'blacklisted' => $blacklisted,
            ];
        })->values()->sortByDesc('total')->values();

        $authorized = $summary->where('category', 'authorised compliant')->count();
        $unauthorized = $summary->where('category', '!=', 'authorised compliant')->count();

        return [
            'top_senders' => $summary->take(10)->toArray(),
            'top_sender' => optional($summary->first())['sender'] ?? null,
            'authorized_senders' => $authorized,
            'unauthorized_senders' => $unauthorized,
        ];
    }

    /**
     * @param Collection<int, array<string, mixed>> $ipStats
     * @return array{top_ips: array<int, array<string, mixed>>}
     */
    private function summarizeIpSources(Collection $ipStats): array
    {
        $grouped = $ipStats->groupBy(fn (array $row): string => (string)($row['payload']['ip_address'] ?? 'unknown'));
        $summary = $grouped->map(function ($rows, $ip) {
            $total = $rows->sum(fn ($r) => (int)($r['payload']['total_incidents'] ?? 0));
            $passes = $rows->sum(fn ($r) => (int)($r['payload']['total_complete_passes'] ?? 0));
            $fails = $rows->sum(fn ($r) => (int)($r['payload']['total_complete_failures'] ?? 0));
            $org = optional($rows->first())['payload']['organisation'] ?? null;
            $service = optional($rows->first())['payload']['service'] ?? null;
            return [
                'ip' => $ip,
                'organisation' => $org,
                'service' => $service,
                'total' => $total,
                'passes' => $passes,
                'failures' => $fails,
            ];
        })->values()->sortByDesc('total')->values();

        return [
            'top_ips' => $summary->take(10)->toArray(),
        ];
    }

    /**
     * @param array<string, int> $totals
     * @return array{total_messages: int, compliance_rate: float}
     */
    private function calculateCompliance(array $totals): array
    {
        $total = ($totals['total_complete_passes'] ?? 0)
            + ($totals['total_dkim_fails_spf_passes'] ?? 0)
            + ($totals['total_spf_fails_dkim_passes'] ?? 0)
            + ($totals['total_complete_failures'] ?? 0);
        $rate = $total > 0 ? round((($totals['total_complete_passes'] ?? 0) / $total) * 100, 2) : 0;
        return [
            'total_messages' => $total,
            'compliance_rate' => $rate,
        ];
    }

    /**
     * @param array<string, int> $totals
     * @param array<string, mixed> $senderSummary
     * @param array<string, mixed> $ipSummary
     * @return array<int, string>
     */
    private function buildRecommendations(array $totals, array $senderSummary, array $ipSummary): array
    {
        $recs = [];
        if (($totals['total_blacklisted_incidents'] ?? 0) > 0) {
            $recs[] = 'Investigate blacklisted senders and block at gateway/DMARC policy.';
        }
        if (($totals['total_complete_failures'] ?? 0) > 0) {
            $recs[] = 'High DMARC failures detected. Review SPF/DKIM alignment and sender authorization.';
        }
        if (($totals['total_overrides_none'] ?? 0) > 0) {
            $recs[] = 'Some emails bypassed DMARC enforcement (none). Consider quarantine/reject policies.';
        }
        if (empty($recs)) {
            $recs[] = 'DMARC posture looks healthy. Maintain current configuration and monitor new senders.';
        }
        return $recs;
    }
}
