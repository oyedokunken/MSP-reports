<?php

declare(strict_types=1);

namespace App\Integrations\SAT;

final class SATMapper
{
    /**
     * Map SAT training completion into a standardized IntegrationData payload.
     * Currently placeholder only.
     * 
     * @param array<string, mixed> $completion
     * @return array<string, mixed>
     */
    public static function mapTrainingCompletion(array $completion): array
    {
        return [
            // No real schema yet; filled in once SAT API is defined
            'integration_customer_id' => null,
            'data_type' => 'sat_training_completion',
            'timestamp' => now(),
            'payload' => $completion,
        ];
    }

    /**
     * Map SAT phishing simulation into a standardized IntegrationData payload.
     * Currently placeholder only.
     * 
     * @param array<string, mixed> $simulation
     * @return array<string, mixed>
     */
    public static function mapPhishingSimulation(array $simulation): array
    {
        return [
            // No real schema yet; filled in once SAT API is defined
            'integration_customer_id' => null,
            'data_type' => 'sat_phishing_simulation',
            'timestamp' => now(),
            'payload' => $simulation,
        ];
    }
}
