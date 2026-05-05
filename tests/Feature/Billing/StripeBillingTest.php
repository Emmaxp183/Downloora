<?php

use App\Listeners\SyncStripeSubscriptionQuota;
use App\Models\User;
use Laravel\Cashier\Events\WebhookHandled;

test('checkout requires a configured stripe price id', function () {
    config(['billing.plans.basic.stripe_price_id' => null]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('billing.checkout', 'basic'))
        ->assertSessionHasErrors('plan');
});

test('subscription webhooks update the user storage quota', function () {
    config([
        'billing.plans.pro.stripe_price_id' => 'price_downloora_pro_monthly',
        'billing.plans.pro.quota_bytes' => 100 * 1024 * 1024 * 1024,
    ]);

    $user = User::factory()->create([
        'stripe_id' => 'cus_downloora_test',
        'storage_quota_bytes' => 734003200,
    ]);

    $user->subscriptions()->create([
        'type' => config('billing.subscription_type'),
        'stripe_id' => 'sub_downloora_test',
        'stripe_status' => 'active',
        'stripe_price' => 'price_downloora_pro_monthly',
        'quantity' => 1,
    ]);

    app(SyncStripeSubscriptionQuota::class)->handle(new WebhookHandled([
        'type' => 'customer.subscription.updated',
        'data' => [
            'object' => [
                'customer' => 'cus_downloora_test',
            ],
        ],
    ]));

    expect($user->refresh()->storage_quota_bytes)->toBe(100 * 1024 * 1024 * 1024);
});

test('cancelled subscription webhooks reset the user storage quota', function () {
    config([
        'billing.default_quota_bytes' => 734003200,
        'billing.plans.pro.stripe_price_id' => 'price_downloora_pro_monthly',
    ]);

    $user = User::factory()->create([
        'stripe_id' => 'cus_downloora_cancelled',
        'storage_quota_bytes' => 100 * 1024 * 1024 * 1024,
    ]);

    $user->subscriptions()->create([
        'type' => config('billing.subscription_type'),
        'stripe_id' => 'sub_downloora_cancelled',
        'stripe_status' => 'canceled',
        'stripe_price' => 'price_downloora_pro_monthly',
        'quantity' => 1,
        'ends_at' => now()->subMinute(),
    ]);

    app(SyncStripeSubscriptionQuota::class)->handle(new WebhookHandled([
        'type' => 'customer.subscription.deleted',
        'data' => [
            'object' => [
                'customer' => 'cus_downloora_cancelled',
            ],
        ],
    ]));

    expect($user->refresh()->storage_quota_bytes)->toBe(734003200);
});
