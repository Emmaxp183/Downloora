<?php

namespace App\Policies;

use App\Models\StoredFile;
use App\Models\User;

class StoredFilePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, StoredFile $storedFile): bool
    {
        return $storedFile->user()->is($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, StoredFile $storedFile): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, StoredFile $storedFile): bool
    {
        return $storedFile->user()->is($user);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, StoredFile $storedFile): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, StoredFile $storedFile): bool
    {
        return false;
    }
}
