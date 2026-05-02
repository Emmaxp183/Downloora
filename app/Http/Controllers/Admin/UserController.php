<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserQuotaRequest;
use App\Models\User;
use App\Services\Storage\StorageQuota;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(StorageQuota $storageQuota): Response
    {
        return Inertia::render('Admin/Users/Index', [
            'users' => User::query()
                ->latest()
                ->get()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_admin' => $user->is_admin,
                    'email_verified_at' => $user->email_verified_at?->toISOString(),
                    'storage_quota_bytes' => $user->storage_quota_bytes,
                    'storage_used_bytes' => $storageQuota->usedBytes($user),
                ]),
        ]);
    }

    public function updateQuota(UpdateUserQuotaRequest $request, User $user): RedirectResponse
    {
        $user->forceFill([
            'storage_quota_bytes' => (int) $request->integer('storage_quota_mb') * 1024 * 1024,
        ])->save();

        return to_route('admin.users.index');
    }
}
