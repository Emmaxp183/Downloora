<?php

namespace App\Models;

use App\Enums\TorrentSourceType;
use App\Enums\TorrentStatus;
use Database\Factories\TorrentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'source_type',
    'magnet_uri',
    'torrent_file_path',
    'info_hash',
    'name',
    'status',
    'progress',
    'total_size_bytes',
    'downloaded_bytes',
    'qbittorrent_hash',
    'error_message',
    'started_at',
    'completed_at',
])]
class Torrent extends Model
{
    /** @use HasFactory<TorrentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_type' => TorrentSourceType::class,
            'status' => TorrentStatus::class,
            'progress' => 'integer',
            'total_size_bytes' => 'integer',
            'downloaded_bytes' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns this torrent.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the files discovered inside this torrent.
     */
    public function files(): HasMany
    {
        return $this->hasMany(TorrentFile::class);
    }

    /**
     * Scope the query to torrents that are still active.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            TorrentStatus::PendingMetadata,
            TorrentStatus::Queued,
            TorrentStatus::Downloading,
            TorrentStatus::Importing,
        ]);
    }

    /**
     * Determine whether the torrent is active.
     */
    public function isActive(): bool
    {
        return in_array($this->status, [
            TorrentStatus::PendingMetadata,
            TorrentStatus::Queued,
            TorrentStatus::Downloading,
            TorrentStatus::Importing,
        ], true);
    }
}
