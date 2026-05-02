<?php

namespace App\Services\Storage;

use App\Models\User;
use RuntimeException;

class StorageQuota
{
    /**
     * Get the number of bytes currently used by the user.
     */
    public function usedBytes(User $user): int
    {
        return (int) $user->storageUsageEvents()->sum('delta_bytes');
    }

    /**
     * Get the number of bytes the user may still store.
     */
    public function remainingBytes(User $user): int
    {
        return max(0, $user->storage_quota_bytes - $this->usedBytes($user));
    }

    /**
     * Determine whether the user can store the given number of bytes.
     */
    public function canStore(User $user, int $bytes): bool
    {
        return $bytes <= $this->remainingBytes($user);
    }

    /**
     * Assert that the user can store the given number of bytes.
     */
    public function assertCanStore(User $user, int $bytes): void
    {
        if (! $this->canStore($user, $bytes)) {
            throw new RuntimeException('The requested file size exceeds the remaining storage quota.');
        }
    }
}
