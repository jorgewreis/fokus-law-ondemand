<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['actor_user_id', 'event', 'subject_type', 'subject_id', 'module', 'action', 'outcome', 'created_at'])]
class AccountAuditEvent extends Model
{
    // Keep audit records limited to approved metadata; do not add free-form payload/context fields.
    public $timestamps = false;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
