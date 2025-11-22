<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExchangeMailbox extends Model
{
    protected $fillable = [
        'company_id',
        'integration_id',
        'customer_id',
        'guid',
        'identity',
        'display_name',
        'alias',
        'primary_smtp_address',
        'user_principal_name',
        'sam_account_name',
        'recipient_type',
        'recipient_type_details',
        'is_shared',
        'is_resource',
        'resource_type',
        'hidden_from_address_lists',
        'external_directory_object_id',
        'database_name',
        'server_name',
        'distinguished_name',
        'license_type',
        'assigned_licenses',
        'prohibit_send_quota_mb',
        'prohibit_send_receive_quota_mb',
        'issue_warning_quota_mb',
        'use_database_quota_defaults',
        'total_item_size_mb',
        'total_deleted_item_size_mb',
        'item_count',
        'deleted_item_count',
        'last_logon_time',
        'last_logoff_time',
        'archive_enabled',
        'archive_guid',
        'archive_name',
        'archive_database',
        'archive_quota_mb',
        'archive_warning_quota_mb',
        'archive_size_mb',
        'litigation_hold_enabled',
        'litigation_hold_date',
        'litigation_hold_owner',
        'retention_policy',
        'retention_comment',
        'retention_url',
        'forwarding_address',
        'forwarding_smtp_address',
        'deliver_to_mailbox_and_forward',
        'organizational_unit',
        'office',
        'department',
        'company',
        'custom_attribute_1',
        'custom_attribute_2',
        'custom_attribute_3',
        'when_created',
        'when_changed',
        'exchange_version',
    ];

    protected $casts = [
        'is_shared' => 'boolean',
        'is_resource' => 'boolean',
        'hidden_from_address_lists' => 'boolean',
        'assigned_licenses' => 'array',
        'prohibit_send_quota_mb' => 'integer',
        'prohibit_send_receive_quota_mb' => 'integer',
        'issue_warning_quota_mb' => 'integer',
        'use_database_quota_defaults' => 'boolean',
        'total_item_size_mb' => 'integer',
        'total_deleted_item_size_mb' => 'integer',
        'item_count' => 'integer',
        'deleted_item_count' => 'integer',
        'last_logon_time' => 'datetime',
        'last_logoff_time' => 'datetime',
        'archive_enabled' => 'boolean',
        'archive_quota_mb' => 'integer',
        'archive_warning_quota_mb' => 'integer',
        'archive_size_mb' => 'integer',
        'litigation_hold_enabled' => 'boolean',
        'litigation_hold_date' => 'datetime',
        'deliver_to_mailbox_and_forward' => 'boolean',
        'when_created' => 'datetime',
        'when_changed' => 'datetime',
    ];

    /**
     * Get the company that owns this mailbox
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    /**
     * Get the integration that synced this mailbox
     */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class, 'integration_id', 'integration_id');
    }

    /**
     * Get the customer this mailbox belongs to
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    /**
     * Get the AD user this mailbox is linked to (if sam_account_name matches)
     */
    public function activeDirectoryUser(): BelongsTo
    {
        return $this->belongsTo(ActiveDirectoryUser::class, 'sam_account_name', 'sam_account_name')
            ->where('company_id', $this->company_id)
            ->where('customer_id', $this->customer_id);
    }

    /**
     * Get all email aliases for this mailbox
     */
    public function emailAliases(): HasMany
    {
        return $this->hasMany(ExchangeEmailAlias::class, 'mailbox_id');
    }

    /**
     * Get all permissions for this mailbox
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(ExchangeMailboxPermission::class, 'mailbox_id');
    }

    /**
     * Get all statistics snapshots for this mailbox
     */
    public function statistics(): HasMany
    {
        return $this->hasMany(ExchangeMailboxStatistic::class, 'mailbox_id');
    }

    /**
     * Get the latest statistics for this mailbox
     */
    public function latestStatistics()
    {
        return $this->hasOne(ExchangeMailboxStatistic::class, 'mailbox_id')
            ->latestOfMany('statistics_timestamp');
    }

    /**
     * Scope: User mailboxes only
     */
    public function scopeUserMailboxes($query)
    {
        return $query->where('recipient_type', 'UserMailbox')
            ->where('is_shared', false)
            ->where('is_resource', false);
    }

    /**
     * Scope: Shared mailboxes only
     */
    public function scopeSharedMailboxes($query)
    {
        return $query->where('is_shared', true);
    }

    /**
     * Scope: Resource mailboxes (rooms/equipment)
     */
    public function scopeResourceMailboxes($query)
    {
        return $query->where('is_resource', true);
    }

    /**
     * Scope: Mailboxes with archives enabled
     */
    public function scopeWithArchive($query)
    {
        return $query->where('archive_enabled', true);
    }

    /**
     * Scope: Mailboxes on litigation hold
     */
    public function scopeOnLitigationHold($query)
    {
        return $query->where('litigation_hold_enabled', true);
    }

    /**
     * Calculate quota usage percentage
     */
    public function getQuotaUsagePercentageAttribute(): ?float
    {
        if (!$this->prohibit_send_quota_mb || !$this->total_item_size_mb) {
            return null;
        }

        return round(($this->total_item_size_mb / $this->prohibit_send_quota_mb) * 100, 2);
    }

    /**
     * Check if mailbox is over quota
     */
    public function isOverQuota(): bool
    {
        if (!$this->prohibit_send_quota_mb || !$this->total_item_size_mb) {
            return false;
        }

        return $this->total_item_size_mb >= $this->prohibit_send_quota_mb;
    }

    /**
     * Check if mailbox is near quota (>80%)
     */
    public function isNearQuota(): bool
    {
        $usage = $this->quota_usage_percentage;
        return $usage !== null && $usage >= 80;
    }
}
