<?php

use App\Models\StorageUsageEvent;
use App\Models\User;
use App\Services\Storage\StorageQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('it calculates used and remaining bytes from usage events', function () {
    $user = User::factory()->create(['storage_quota_bytes' => 1000]);

    StorageUsageEvent::factory()->for($user)->create(['delta_bytes' => 250]);
    StorageUsageEvent::factory()->for($user)->create(['delta_bytes' => -50]);

    $quota = app(StorageQuota::class);

    expect($quota->usedBytes($user))->toBe(200)
        ->and($quota->remainingBytes($user))->toBe(800)
        ->and($quota->canStore($user, 801))->toBeFalse()
        ->and($quota->canStore($user, 800))->toBeTrue();
});
