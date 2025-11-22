<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeMailboxStatistic extends Model
{
    protected $fillable = [
        'company_id',
        'integration_id',
        'customer_id',
        'mailbox_id',
        'total_item_size_mb',
        'total_deleted_item_size_mb',
        'item_count',
        'deleted_item_count',
        'associated_item_count',
        'folder_count',
        'database_name',
        'database_guid',
        'is_archival',
        'last_logon_time',
        'last_logoff_time',
        'last_logon_user_account',
        'last_logged_on_user_account',
        'statistics_timestamp',
    ];

    protected $casts = [
        'total_item_size_mb' => 'integer',
        'total_deleted_item_size_mb' => 'integer',
        'item_count' => 'integer',
        'deleted_item_count' => 'integer',
        'associated_item_count' => 'integer',
        'folder_count' => 'integer',
        'is_archival' => 'boolean',
        'last_logon_time' => 'datetime',
        'last_logoff_time' => 'datetime',
        'statistics_timestamp' => 'datetime',
    ];

    /**
     * Get the company that owns this statistic
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    /**
     * Get the integration that synced this statistic
     */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class, 'integration_id', 'integration_id');
    }

    /**
     * Get the customer this statistic belongs to
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    /**
     * Get the mailbox these statistics belong to
     */
    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(ExchangeMailbox::class, 'mailbox_id');
    }

    /**
     * Scope: Latest statistics only
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('statistics_timestamp', 'desc');
    }

    /**
     * Scope: Statistics within date range
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('statistics_timestamp', [$startDate, $endDate]);
    }

    /**
     * Scope: Statistics for a specific database
     */
    public function scopeForDatabase($query, string $database)
    {
        return $query->where('database_name', $database);
    }
}
