<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['person_id', 'type', 'number', 'issuing_authority', 'issuing_state'])]
class PersonDocument extends Model
{
    protected function casts(): array
    {
        return [
            'number' => 'encrypted',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
