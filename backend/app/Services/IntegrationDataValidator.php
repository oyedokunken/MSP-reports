<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class IntegrationDataValidator
{
    /**
     * Validate and sanitize integration data payload
     */
    public function validate(string $dataType, array $payload): array
    {
        // Remove potentially dangerous fields
        $sanitized = $this->sanitizePayload($payload);
        
        // Validate based on data type
        return match($dataType) {
            'ticket' => $this->validateTicket($sanitized),
            'time_entry' => $this->validateTimeEntry($sanitized),
            'device_status' => $this->validateDevice($sanitized),
            'alert' => $this->validateAlert($sanitized),
            'ssl_certificate' => $this->validateSslCert($sanitized),
            'domain' => $this->validateDomain($sanitized),
            default => $sanitized,
        };
    }

    /**
     * Sanitize payload to remove dangerous content
     */
    private function sanitizePayload(array $payload): array
    {
        $sanitized = [];
        
        foreach ($payload as $key => $value) {
            // Skip script tags, eval, etc.
            if (is_string($value)) {
                $value = strip_tags($value);
                $value = preg_replace('/javascript:/i', '', $value);
            }
            
            // Recursively sanitize nested arrays
            if (is_array($value)) {
                $value = $this->sanitizePayload($value);
            }
            
            $sanitized[$key] = $value;
        }
        
        return $sanitized;
    }

    /**
     * Validate ticket data
     */
    private function validateTicket(array $data): array
    {
        return array_filter($data, function($key) {
            return in_array($key, [
                'ticket_id', 'ticket_number', 'title', 'description',
                'status', 'priority', 'created_date', 'completed_date',
                'assigned_to', 'company_id', 'contact_id'
            ]);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Validate time entry data
     */
    private function validateTimeEntry(array $data): array
    {
        return array_filter($data, function($key) {
            return in_array($key, [
                'time_entry_id', 'ticket_id', 'hours', 'date',
                'resource_id', 'resource_name', 'work_type',
                'notes', 'billable'
            ]);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Validate device data
     */
    private function validateDevice(array $data): array
    {
        return array_filter($data, function($key) {
            return in_array($key, [
                'device_uid', 'hostname', 'type', 'os', 'ip_address',
                'status', 'last_seen', 'model', 'manufacturer'
            ]);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Validate alert data
     */
    private function validateAlert(array $data): array
    {
        return array_filter($data, function($key) {
            return in_array($key, [
                'alert_uid', 'device_uid', 'device_name', 'priority',
                'description', 'source', 'event_type', 'resolved',
                'resolved_at', 'muted', 'ticket_number'
            ]);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Validate SSL certificate data
     */
    private function validateSslCert(array $data): array
    {
        return array_filter($data, function($key) {
            return in_array($key, [
                'id', 'name', 'domain', 'issuer', 'expiry_date',
                'status', 'organization_id'
            ]);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Validate domain data
     */
    private function validateDomain(array $data): array
    {
        return array_filter($data, function($key) {
            return in_array($key, [
                'id', 'name', 'registrar', 'expiry_date',
                'status', 'organization_id', 'nameservers'
            ]);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Sanitize data for logging (remove PII)
     */
    public function sanitizeForLogging(array $data): array
    {
        $sanitized = $data;
        
        // Remove sensitive fields
        $sensitiveFields = [
            'password', 'api_key', 'secret', 'token', 'credentials',
            'email', 'phone', 'ssn', 'credit_card', 'api_secret'
        ];
        
        foreach ($sensitiveFields as $field) {
            if (isset($sanitized[$field])) {
                $sanitized[$field] = '[REDACTED]';
            }
        }
        
        // Recursively sanitize nested arrays
        foreach ($sanitized as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeForLogging($value);
            }
        }
        
        return $sanitized;
    }
}
