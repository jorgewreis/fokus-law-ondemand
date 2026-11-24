<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['person_id', 'type', 'value', 'user_key', 'phone_id', 'is_primary'])]
class PersonWhatsappIdentifier extends Model
{
    protected function casts(): array
    {
        return [
            'value' => 'encrypted',
            'user_key' => 'encrypted',
            'is_primary' => 'boolean',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function phone(): BelongsTo
    {
        return $this->belongsTo(PersonPhone::class, 'phone_id');
    }
}
