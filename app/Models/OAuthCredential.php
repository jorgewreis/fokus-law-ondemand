<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['credential_key', 'provider_key', 'access_token', 'refresh_token', 'expires_at', 'refreshed_at', 'revoked_at'])]
class OAuthCredential extends Model
{
    protected $table = 'oauth_credentials';

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'immutable_datetime',
            'refreshed_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }
}
