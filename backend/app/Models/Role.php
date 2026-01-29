<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Role extends Model
{
    protected $fillable = ['name'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<User, $this>
     */
    public function users(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(User::class, 'role_id', 'id');
    }
}
