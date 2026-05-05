<?php

use App\Enums\MediaImportStatus;
use App\Jobs\DownloadMediaImport;
use App\Jobs\InspectMediaImport;
use App\Models\MediaImport;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Support\Facades\Bus;

test('verified users can submit a public media url through the shared input', function () {
    Bus::fake();

    $user = User::factory()->create([
        'storage_quota_bytes' => 10 * 1024 * 1024,
    ]);

    $this->actingAs($user)
        ->post('/torrents', [
            'url' => 'https://www.youtube.com/watch?v=example',
        ])
        ->assertRedirect(route('dashboard', absolute: false))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('media_imports', [
        'user_id' => $user->id,
        'source_url' => 'https://www.youtube.com/watch?v=example',
        'source_domain' => 'www.youtube.com',
        'status' => MediaImportStatus::Inspecting->value,
    ]);

    Bus::assertDispatched(InspectMediaImport::class);
});

test('users with an active media import save submitted media urls to wishlist', function () {
    Bus::fake();

    $user = User::factory()->create([
        'storage_quota_bytes' => 10 * 1024 * 1024,
    ]);

    MediaImport::factory()->for($user)->create([
        'status' => MediaImportStatus::Downloading,
    ]);

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->post('/torrents', [
            'url' => 'https://www.youtube.com/watch?v=another',
        ])
        ->assertRedirect(route('dashboard', absolute: false))
        ->assertSessionHasNoErrors();

    expect($user->mediaImports()->count())->toBe(1);
    $this->assertDatabaseHas('wishlist_items', [
        'user_id' => $user->id,
        'url' => 'https://www.youtube.com/watch?v=another',
        'source_type' => 'media',
        'source_domain' => 'www.youtube.com',
    ]);
    expect(WishlistItem::query()->count())->toBe(1);
    Bus::assertNotDispatched(InspectMediaImport::class);
});

test('ready media imports can queue a selected format', function () {
    Bus::fake();

    $user = User::factory()->create([
        'storage_quota_bytes' => 10 * 1024 * 1024,
    ]);
    $mediaImport = MediaImport::factory()->for($user)->create([
        'status' => MediaImportStatus::Ready,
        'formats' => [
            [
                'id' => 'best',
                'selector' => 'bestvideo*+bestaudio/best',
                'type' => 'video',
                'extension' => 'mp4',
                'quality' => 'Best available',
                'duration_seconds' => 120,
                'size_bytes' => 1024,
                'source' => 'YouTube',
            ],
        ],
    ]);

    $this->actingAs($user)
        ->post("/media-imports/{$mediaImport->id}/formats", [
            'format_id' => 'best',
        ])
        ->assertRedirect(route('dashboard', absolute: false))
        ->assertSessionHasNoErrors();

    $mediaImport->refresh();

    expect($mediaImport->status)->toBe(MediaImportStatus::Queued)
        ->and($mediaImport->selected_format['id'])->toBe('best');

    Bus::assertDispatched(DownloadMediaImport::class);
});
