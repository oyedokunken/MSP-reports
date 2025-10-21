<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordResetToken extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'email',
        'token',
        'created_at',
        'expires_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Check if the token has expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Scope to get valid (non-expired) tokens
     */
    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', now());
    }
}
