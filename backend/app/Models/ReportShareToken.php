<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportShareToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'generated_report_id',
        'company_id',
        'token',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Get the generated report that this token belongs to.
     */
    public function generatedReport(): BelongsTo
    {
        return $this->belongsTo(GeneratedReport::class, 'generated_report_id', 'generated_report_id');
    }

    /**
     * Get the company that this token belongs to.
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
     */
    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', now());
    }
}
