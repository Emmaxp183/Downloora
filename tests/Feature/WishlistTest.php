<?php

use App\Models\User;
use App\Models\WishlistItem;

test('users can save a magnet link to their wishlist', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->post(route('wishlist.store'), [
            'url' => 'magnet:?xt=urn:btih:1234567890abcdef',
        ])
        ->assertRedirect(route('dashboard', absolute: false))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('wishlist_items', [
        'user_id' => $user->id,
        'url' => 'magnet:?xt=urn:btih:1234567890abcdef',
        'source_type' => 'magnet',
        'source_domain' => null,
    ]);
});

test('users can save a media url to their wishlist', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('wishlist.store'), [
            'url' => 'https://example.com/watch/video',
        ])
        ->assertRedirect(route('dashboard', absolute: false))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('wishlist_items', [
        'user_id' => $user->id,
        'url' => 'https://example.com/watch/video',
        'source_type' => 'media',
        'source_domain' => 'example.com',
        'title' => 'example.com',
    ]);
});

test('saving the same wishlist url keeps one item', function () {
    $user = User::factory()->create();
    $url = 'https://example.com/watch/video';

    $this->actingAs($user)->post(route('wishlist.store'), ['url' => $url]);
    $this->actingAs($user)->post(route('wishlist.store'), ['url' => $url]);

    expect($user->wishlistItems()->count())->toBe(1);
});

test('wishlist rejects invalid urls', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->post(route('wishlist.store'), [
            'url' => 'javascript:alert(1)',
        ])
        ->assertRedirect(route('dashboard', absolute: false))
        ->assertSessionHasErrors('url');

    expect(WishlistItem::query()->count())->toBe(0);
});

test('users can delete their own wishlist items', function () {
    $user = User::factory()->create();
    $item = WishlistItem::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('wishlist.destroy', $item))
        ->assertRedirect(route('dashboard', absolute: false));

    expect(WishlistItem::query()->count())->toBe(0);
});

test('users cannot delete another users wishlist items', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $item = WishlistItem::factory()->for($otherUser)->create();

    $this->actingAs($user)
        ->delete(route('wishlist.destroy', $item))
        ->assertForbidden();

    expect($item->fresh())->not->toBeNull();
});
