<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['person_id', 'number', 'category', 'is_primary'])]
class PersonPhone extends Model
{
    protected function casts(): array
    {
        return [
            'number' => 'encrypted',
            'is_primary' => 'boolean',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function whatsappIdentifiers(): HasMany
    {
        return $this->hasMany(PersonWhatsappIdentifier::class, 'phone_id');
    }
}
