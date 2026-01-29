<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Scopes\CompanyScope;
use App\Models\Scopes\CustomerScope;

/**
 * @property int $id
 * @property int $company_id
 * @property int $integration_id
 * @property int $customer_id
 * @property string $guid
 * @property string $identity
 * @property string|null $display_name
 * @property string|null $alias
 * @property string|null $primary_smtp_address
 * @property string|null $user_principal_name
 * @property string|null $sam_account_name
 * @property string|null $recipient_type
 * @property string|null $recipient_type_details
 * @property bool $is_shared
 * @property bool $is_resource
 * @property string|null $resource_type
 * @property bool $hidden_from_address_lists
 * @property string|null $external_directory_object_id
 * @property string|null $database_name
 * @property string|null $server_name
 * @property string|null $distinguished_name
 * @property string|null $license_type
 * @property array<string, mixed>|null $assigned_licenses
 * @property int|null $prohibit_send_quota_mb
 * @property int|null $prohibit_send_receive_quota_mb
 * @property int|null $issue_warning_quota_mb
 * @property bool $use_database_quota_defaults
 * @property int|null $total_item_size_mb
 * @property int|null $total_deleted_item_size_mb
 * @property int|null $item_count
 * @property int|null $deleted_item_count
 * @property \Carbon\Carbon|null $last_logon_time
 * @property \Carbon\Carbon|null $last_logoff_time
 * @property bool $archive_enabled
 * @property string|null $archive_guid
 * @property string|null $archive_name
 * @property string|null $archive_database
 * @property int|null $archive_quota_mb
 * @property int|null $archive_warning_quota_mb
 * @property int|null $archive_size_mb
 * @property bool $litigation_hold_enabled
 * @property \Carbon\Carbon|null $litigation_hold_date
 * @property string|null $litigation_hold_owner
 * @property string|null $retention_policy
 * @property string|null $retention_comment
 * @property string|null $retention_url
 * @property string|null $forwarding_address
 * @property string|null $forwarding_smtp_address
 * @property bool $deliver_to_mailbox_and_forward
 * @property string|null $organizational_unit
 * @property string|null $office
 * @property string|null $department
 * @property string|null $company_name
 * @property string|null $custom_attribute_1
 * @property string|null $custom_attribute_2
 * @property string|null $custom_attribute_3
 * @property \Carbon\Carbon|null $when_created
 * @property \Carbon\Carbon|null $when_changed
 * @property string|null $exchange_version
 * @property-read float|null $quota_usage_percentage
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\Integration $integration
 * @property-read \App\Models\Customer $customer
 * @property-read \App\Models\ActiveDirectoryUser|null $activeDirectoryUser
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ExchangeEmailAlias> $emailAliases
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ExchangeMailboxPermission> $permissions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ExchangeMailboxStatistic> $statistics
 */
class ExchangeMailbox extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
        static::addGlobalScope(new CustomerScope);
    }

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
     * 
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    /**
     * Get the integration that synced this mailbox
     * 
     * @return BelongsTo<Integration, $this>
     */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class, 'integration_id', 'integration_id');
    }

    /**
     * Get the customer this mailbox belongs to
     * 
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    /**
     * Get the AD user this mailbox is linked to (if sam_account_name matches)
     * 
     * @return BelongsTo<ActiveDirectoryUser, $this>
     */
    public function activeDirectoryUser(): BelongsTo
    {
        return $this->belongsTo(ActiveDirectoryUser::class, 'sam_account_name', 'sam_account_name')
            ->where('company_id', $this->company_id)
            ->where('customer_id', $this->customer_id);
    }

    /**
     * Get all email aliases for this mailbox
     * 
     * @return HasMany<ExchangeEmailAlias, $this>
     */
    public function emailAliases(): HasMany
    {
        return $this->hasMany(ExchangeEmailAlias::class, 'mailbox_id');
    }

    /**
     * Get all permissions for this mailbox
     * 
     * @return HasMany<ExchangeMailboxPermission, $this>
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(ExchangeMailboxPermission::class, 'mailbox_id');
    }

    /**
     * Get all statistics snapshots for this mailbox
     * 
     * @return HasMany<ExchangeMailboxStatistic, $this>
     */
    public function statistics(): HasMany
    {
        return $this->hasMany(ExchangeMailboxStatistic::class, 'mailbox_id');
    }

    /**
     * Get the latest statistics for this mailbox
     * 
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<\App\Models\ExchangeMailboxStatistic, $this>|null
     */
    public function latestStatistics()
    {
        return $this->hasOne(ExchangeMailboxStatistic::class, 'mailbox_id')
            ->latestOfMany('statistics_timestamp');
    }

    /**
     * Scope: User mailboxes only
     * 
     * @param \Illuminate\Database\Eloquent\Builder<$this> $query
     * @return \Illuminate\Database\Eloquent\Builder<$this>
     */
    public function scopeUserMailboxes($query)
    {
        return $query->where('recipient_type', 'UserMailbox')
            ->where('is_shared', false)
            ->where('is_resource', false);
    }

    /**
     * Scope: Shared mailboxes only
     * 
     * @param \Illuminate\Database\Eloquent\Builder<$this> $query
     * @return \Illuminate\Database\Eloquent\Builder<$this>
     */
    public function scopeSharedMailboxes($query)
    {
        return $query->where('is_shared', true);
    }

    /**
     * Scope: Resource mailboxes (rooms/equipment)
     * 
     * @param \Illuminate\Database\Eloquent\Builder<$this> $query
     * @return \Illuminate\Database\Eloquent\Builder<$this>
     */
    public function scopeResourceMailboxes($query)
    {
        return $query->where('is_resource', true);
    }

    /**
     * Scope: Mailboxes with archives enabled
     * 
     * @param \Illuminate\Database\Eloquent\Builder<$this> $query
     * @return \Illuminate\Database\Eloquent\Builder<$this>
     */
    public function scopeWithArchive($query)
    {
        return $query->where('archive_enabled', true);
    }

    /**
     * Scope: Mailboxes on litigation hold
     * 
     * @param \Illuminate\Database\Eloquent\Builder<$this> $query
     * @return \Illuminate\Database\Eloquent\Builder<$this>
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
