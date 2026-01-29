<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $generated_report_id
 * @property int $company_id
 * @property string $token
 * @property string|null $signature
 * @property \Carbon\Carbon $expires_at
 * @property-read \App\Models\GeneratedReport $generatedReport
 * @property-read \App\Models\Company $company
 */
class ReportShareToken extends Model
{

    protected $fillable = [
        'generated_report_id',
        'company_id',
        'token',
        'signature',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Generate a secure token with HMAC signature for tamper detection.
     * 
     * @param int $reportId
     * @param int $companyId
     * @return array{token: string, signature: string, expires_at: \Carbon\Carbon}
     */
    public static function generateSecureToken(int $reportId, int $companyId): array
    {
        $token = \Illuminate\Support\Str::random(64);
        $expiresAt = now()->addHour();
        
        // Create HMAC signature using app key for tamper detection
        $payload = "{$token}:{$reportId}:{$companyId}:{$expiresAt->timestamp}";
        $signature = hash_hmac('sha256', $payload, config('app.key'));
        
        return [
            'token' => $token,
            'signature' => $signature,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Verify the HMAC signature of a token.
     * 
     * @return bool
     */
    public function verifySignature(): bool
    {
        $payload = "{$this->token}:{$this->generated_report_id}:{$this->company_id}:{$this->expires_at->timestamp}";
        $expectedSignature = hash_hmac('sha256', $payload, config('app.key'));
        
        return hash_equals($expectedSignature, $this->signature ?? '');
    }

    /**
     * Get the generated report that this token belongs to.
     * 
     * @return BelongsTo<GeneratedReport, $this>
     */
    public function generatedReport(): BelongsTo
    {
        return $this->belongsTo(GeneratedReport::class, 'generated_report_id', 'generated_report_id');
    }

    /**
     * Get the company that this token belongs to.
     * 
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    /**
     * Check if the token is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Scope to get only valid (non-expired) tokens.
     * 
     * @param \Illuminate\Database\Eloquent\Builder<$this> $query
     * @return \Illuminate\Database\Eloquent\Builder<$this>
     */
    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', now());
    }
}
