<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWishlistItemRequest;
use App\Models\WishlistItem;
use App\Services\Wishlist\WishlistSaver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WishlistItemController extends Controller
{
    public function store(StoreWishlistItemRequest $request, WishlistSaver $wishlistSaver): RedirectResponse
    {
        $wishlistSaver->save($request->user(), $request->wishlistUrl());

        return to_route('dashboard');
    }

    public function destroy(Request $request, WishlistItem $wishlistItem): RedirectResponse
    {
        abort_unless($wishlistItem->user_id === $request->user()->id, 403);

        $wishlistItem->delete();

        return to_route('dashboard');
    }
}
