<?php

namespace App\Models;

use App\Enums\MediaImportStatus;
use Database\Factories\MediaImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'stored_file_id',
    'source_url',
    'source_domain',
    'title',
    'thumbnail_url',
    'status',
    'progress',
    'duration_seconds',
    'estimated_size_bytes',
    'downloaded_bytes',
    'formats',
    'selected_format',
    'local_file_path',
    'error_message',
    'inspected_at',
    'started_at',
    'completed_at',
])]
class MediaImport extends Model
{
    /** @use HasFactory<MediaImportFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MediaImportStatus::class,
            'progress' => 'integer',
            'duration_seconds' => 'integer',
            'estimated_size_bytes' => 'integer',
            'downloaded_bytes' => 'integer',
            'formats' => 'array',
            'selected_format' => 'array',
            'inspected_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function storedFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class);
    }

    public function storedFiles(): HasMany
    {
        return $this->hasMany(StoredFile::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            MediaImportStatus::Inspecting,
            MediaImportStatus::Ready,
            MediaImportStatus::Queued,
            MediaImportStatus::Downloading,
            MediaImportStatus::Importing,
            MediaImportStatus::InspectionFailed,
            MediaImportStatus::QuotaExceeded,
            MediaImportStatus::DownloadFailed,
            MediaImportStatus::ImportFailed,
        ]);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [
            MediaImportStatus::Inspecting,
            MediaImportStatus::Ready,
            MediaImportStatus::Queued,
            MediaImportStatus::Downloading,
            MediaImportStatus::Importing,
            MediaImportStatus::InspectionFailed,
            MediaImportStatus::QuotaExceeded,
            MediaImportStatus::DownloadFailed,
            MediaImportStatus::ImportFailed,
        ], true);
    }
}
