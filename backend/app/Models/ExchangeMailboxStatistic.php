<?php

declare(strict_types=1);

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
     * 
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    /**
     * Get the integration that synced this statistic
     * 
     * @return BelongsTo<Integration, $this>
     */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class, 'integration_id', 'integration_id');
    }

    /**
     * Get the customer this statistic belongs to
     * 
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    /**
     * Get the mailbox these statistics belong to
     * 
     * @return BelongsTo<ExchangeMailbox, $this>
     */
    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(ExchangeMailbox::class, 'mailbox_id');
    }

    /**
     * Scope: Latest statistics only
     * 
     * @param \Illuminate\Database\Eloquent\Builder<$this> $query
     * @return \Illuminate\Database\Eloquent\Builder<$this>
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('statistics_timestamp', 'desc');
    }

    /**
     * Scope: Statistics within date range
     * 
     * @param \Illuminate\Database\Eloquent\Builder<$this> $query
     * @param string|\Carbon\Carbon $startDate
     * @param string|\Carbon\Carbon $endDate
     * @return \Illuminate\Database\Eloquent\Builder<$this>
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('statistics_timestamp', [$startDate, $endDate]);
    }

    /**
     * Scope: Statistics for a specific database
     * 
     * @param \Illuminate\Database\Eloquent\Builder<$this> $query
     * @param string $database
     * @return \Illuminate\Database\Eloquent\Builder<$this>
     */
    public function scopeForDatabase($query, string $database)
    {
        return $query->where('database_name', $database);
    }
}
