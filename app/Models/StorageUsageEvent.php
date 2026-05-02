<?php

namespace App\Models;

use Database\Factories\StorageUsageEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'stored_file_id', 'delta_bytes', 'reason', 'metadata'])]
class StorageUsageEvent extends Model
{
    /** @use HasFactory<StorageUsageEventFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'delta_bytes' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the user that owns this storage usage event.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
