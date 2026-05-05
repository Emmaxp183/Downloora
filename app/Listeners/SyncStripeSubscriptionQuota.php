<?php

namespace App\Listeners;

use App\Support\BillingPlans;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookHandled;

class SyncStripeSubscriptionQuota
{
    public function __construct(private readonly BillingPlans $plans) {}

    public function handle(WebhookHandled $event): void
    {
        if (! in_array($event->payload['type'] ?? null, [
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted',
            'customer.deleted',
        ], true)) {
            return;
        }

        $customer = $event->payload['data']['object']['customer'] ?? $event->payload['data']['object']['id'] ?? null;
        $user = Cashier::findBillable($customer);

        if (! $user) {
            return;
        }

        $subscription = $user->subscription($this->plans->subscriptionType());
        $plan = $subscription?->valid()
            ? $this->plans->findByPriceId($subscription->stripe_price)
            : null;

        $user->forceFill([
            'storage_quota_bytes' => $plan['quota_bytes'] ?? $this->plans->defaultQuotaBytes(),
        ])->save();
    }
}
