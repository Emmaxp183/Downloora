<?php

namespace App\Models;

use Database\Factories\StoredFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'user_id',
    'torrent_id',
    'media_import_id',
    's3_disk',
    's3_bucket',
    's3_key',
    'original_path',
    'name',
    'mime_type',
    'size_bytes',
    'adaptive_stream_status',
    'adaptive_stream_disk',
    'adaptive_stream_bucket',
    'adaptive_stream_playlist_key',
    'adaptive_stream_variants',
    'adaptive_stream_error',
    'adaptive_stream_generated_at',
])]
class StoredFile extends Model
{
    /** @use HasFactory<StoredFileFactory> */
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
            'adaptive_stream_variants' => 'array',
            'adaptive_stream_generated_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns this stored file.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the torrent that produced this stored file.
     */
    public function torrent(): BelongsTo
    {
        return $this->belongsTo(Torrent::class);
    }

    /**
     * Get the media import that produced this stored file.
     */
    public function mediaImport(): BelongsTo
    {
        return $this->belongsTo(MediaImport::class);
    }

    /**
     * Get the configured filesystem disk for the stored file.
     */
    public function s3Disk(): FilesystemAdapter
    {
        return Storage::disk($this->s3_disk);
    }

    /**
     * Get the configured filesystem disk for adaptive HLS assets.
     */
    public function adaptiveStreamDisk(): FilesystemAdapter
    {
        return Storage::disk($this->adaptive_stream_disk ?? $this->s3_disk);
    }
}
