<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeMailboxPermission extends Model
{
    protected $fillable = [
        'company_id',
        'integration_id',
        'customer_id',
        'mailbox_id',
        'permission_type',
        'user_identity',
        'user_sam_account_name',
        'user_display_name',
        'access_rights',
        'inherited',
        'deny',
        'automapping',
    ];

    protected $casts = [
        'access_rights' => 'array',
        'inherited' => 'boolean',
        'deny' => 'boolean',
        'automapping' => 'boolean',
    ];

    /**
     * Get the company that owns this permission
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    /**
     * Get the integration that synced this permission
     */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class, 'integration_id', 'integration_id');
    }

    /**
     * Get the customer this permission belongs to
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    /**
     * Get the mailbox this permission is on
     */
    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(ExchangeMailbox::class, 'mailbox_id');
    }

    /**
     * Get the AD user who has this permission (if sam_account_name matches)
     */
    public function activeDirectoryUser(): BelongsTo
    {
        return $this->belongsTo(ActiveDirectoryUser::class, 'user_sam_account_name', 'sam_account_name')
            ->where('company_id', $this->company_id)
            ->where('customer_id', $this->customer_id);
    }

    /**
     * Scope: Full Access permissions only
     */
    public function scopeFullAccess($query)
    {
        return $query->where('permission_type', 'FullAccess');
    }

    /**
     * Scope: Send As permissions only
     */
    public function scopeSendAs($query)
    {
        return $query->where('permission_type', 'SendAs');
    }

    /**
     * Scope: Send On Behalf permissions only
     */
    public function scopeSendOnBehalf($query)
    {
        return $query->where('permission_type', 'SendOnBehalf');
    }

    /**
     * Scope: Non-inherited permissions only
     */
    public function scopeNotInherited($query)
    {
        return $query->where('inherited', false);
    }

    /**
     * Scope: Active permissions (not deny)
     */
    public function scopeActive($query)
    {
        return $query->where('deny', false);
    }
}
