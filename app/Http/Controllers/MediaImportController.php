<?php

namespace App\Http\Controllers;

use App\Enums\MediaImportStatus;
use App\Jobs\DownloadMediaImport;
use App\Models\MediaImport;
use App\Services\Storage\StorageQuota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MediaImportController extends Controller
{
    public function storeFormat(Request $request, MediaImport $mediaImport, StorageQuota $quota): RedirectResponse
    {
        if (! $mediaImport->user()->is($request->user())) {
            abort(403);
        }

        if ($mediaImport->status !== MediaImportStatus::Ready) {
            throw ValidationException::withMessages([
                'format_id' => __('This media URL is not ready for format selection.'),
            ]);
        }

        $validated = $request->validate([
            'format_id' => ['required', 'string', 'max:255'],
        ]);

        $format = collect($mediaImport->formats ?? [])
            ->first(fn (array $format): bool => ($format['id'] ?? null) === $validated['format_id']);

        if (! is_array($format)) {
            throw ValidationException::withMessages([
                'format_id' => __('Select one of the available media formats.'),
            ]);
        }

        if (isset($format['size_bytes']) && ! $quota->canStore($request->user(), (int) $format['size_bytes'])) {
            $mediaImport->forceFill([
                'status' => MediaImportStatus::QuotaExceeded,
                'error_message' => 'This media format exceeds your remaining storage quota.',
            ])->save();

            return to_route('dashboard');
        }

        $mediaImport->forceFill([
            'status' => MediaImportStatus::Queued,
            'selected_format' => $format,
            'estimated_size_bytes' => $format['size_bytes'] ?? $mediaImport->estimated_size_bytes,
            'progress' => 0,
            'error_message' => null,
        ])->save();

        DownloadMediaImport::dispatch($mediaImport);

        return to_route('dashboard');
    }

    public function destroy(Request $request, MediaImport $mediaImport): RedirectResponse
    {
        if (! $mediaImport->user()->is($request->user())) {
            abort(403);
        }

        if ($mediaImport->isActive()) {
            $mediaImport->forceFill([
                'status' => MediaImportStatus::Cancelled,
                'error_message' => null,
            ])->save();
        }

        return to_route('dashboard');
    }
}
