<?php

namespace App\Models;

use Database\Factories\TorrentFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['torrent_id', 'path', 'size_bytes', 'selected', 'progress'])]
class TorrentFile extends Model
{
    /** @use HasFactory<TorrentFileFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'selected' => 'boolean',
            'progress' => 'integer',
        ];
    }

    /**
     * Get the torrent this file belongs to.
     */
    public function torrent(): BelongsTo
    {
        return $this->belongsTo(Torrent::class);
    }
}
