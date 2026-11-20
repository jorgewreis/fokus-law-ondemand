<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public function grants(): HasMany
    {
        return $this->hasMany(UnitGrant::class, 'unit_id');
    }
}
