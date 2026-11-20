<?php

namespace App\Services;

use App\Models\Person;
use App\Models\User;

class PersonAccessPolicy
{
    public function __construct(private AccountAccess $access) {}

    public function canRead(?User $user): bool
    {
        return $user !== null && $this->access->allows($user, 'people', 'read');
    }

    public function canCreate(?User $user): bool
    {
        return $user !== null && $this->canRead($user) && $this->access->allows($user, 'people', 'create');
    }

    public function canUpdate(?User $user, Person $person): bool
    {
        if ($user === null || ! $this->canRead($user)) {
            return false;
        }

        return (int) $person->created_by_user_id === (int) $user->getKey()
            || $this->access->allows($user, 'people', 'update');
    }

    public function canDelete(?User $user, Person $person): bool
    {
        return $user !== null
            && $user->is_synthetic_admin
            && $this->canRead($user)
            && $this->access->allows($user, 'people', 'delete')
            && $person->user_id === null
            && ! $person->processLinks()->exists();
    }
}
