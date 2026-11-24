<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'created_by_user_id', 'person_type', 'name', 'postal_code', 'street',
    'address_number', 'city', 'state', 'district', 'complement',
])]
class Person extends Model
{
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PersonDocument::class);
    }

    public function phones(): HasMany
    {
        return $this->hasMany(PersonPhone::class);
    }

    public function whatsappIdentifiers(): HasMany
    {
        return $this->hasMany(PersonWhatsappIdentifier::class);
    }

    public function processLinks(): HasMany
    {
        return $this->hasMany(PersonProcess::class, 'person_id');
    }
}
