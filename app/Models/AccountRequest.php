<?php

namespace App\Models;

use App\AccountRequestStatus;
use Database\Factories\AccountRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['display_name', 'email', 'status', 'email_confirmed_at', 'submitted_at', 'expires_at', 'reviewed_by_user_id', 'linked_user_id', 'reviewed_at'])]
class AccountRequest extends Model
{
    /** @use HasFactory<AccountRequestFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AccountRequestStatus::class,
            'email_confirmed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_user_id');
    }
}
