<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeEmailAlias extends Model
{
    protected $fillable = [
        'company_id',
        'integration_id',
        'customer_id',
        'mailbox_id',
        'email_address',
        'address_type',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    /**
     * Get the company that owns this alias
     * 
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'company_id');
    }

    /**
     * Get the integration that synced this alias
     * 
     * @return BelongsTo<Integration, $this>
     */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class, 'integration_id', 'integration_id');
    }

    /**
     * Get the customer this alias belongs to
     * 
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    /**
     * Get the mailbox this alias belongs to
     * 
     * @return BelongsTo<ExchangeMailbox, $this>
     */
    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(ExchangeMailbox::class, 'mailbox_id');
    }

    /**
     * Scope: Primary email addresses only
     * 
     * @param \Illuminate\Database\Eloquent\Builder<$this> $query
     * @return \Illuminate\Database\Eloquent\Builder<$this>
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope: SMTP addresses only
     * 
     * @param \Illuminate\Database\Eloquent\Builder<$this> $query
     * @return \Illuminate\Database\Eloquent\Builder<$this>
     */
    public function scopeSmtp($query)
    {
        return $query->where('address_type', 'SMTP');
    }
}
