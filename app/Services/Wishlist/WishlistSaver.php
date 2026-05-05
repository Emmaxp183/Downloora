<?php

namespace App\Services\Wishlist;

use App\Models\User;
use App\Models\WishlistItem;

class WishlistSaver
{
    public function save(User $user, string $url): WishlistItem
    {
        $sourceType = str_starts_with($url, 'magnet:?') ? 'magnet' : 'media';
        $sourceDomain = $sourceType === 'media' ? parse_url($url, PHP_URL_HOST) : null;

        return $user->wishlistItems()->updateOrCreate(
            ['url_hash' => hash('sha256', $url)],
            [
                'url' => $url,
                'source_type' => $sourceType,
                'source_domain' => $sourceDomain,
                'title' => $sourceDomain ?: 'Magnet link',
            ],
        );
    }
}
