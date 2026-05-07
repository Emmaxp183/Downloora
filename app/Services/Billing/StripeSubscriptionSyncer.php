<?php

namespace App\Services\Billing;

use App\Models\User;
use App\Support\BillingPlans;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Cashier;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Subscription as StripeSubscription;

class StripeSubscriptionSyncer
{
    public function __construct(private readonly BillingPlans $plans) {}

    public function syncFromCheckoutSession(User $user, string $sessionId): bool
    {
        if ($sessionId === '' || ! $user->stripe_id) {
            return false;
        }

        $session = Cashier::stripe()->checkout->sessions->retrieve($sessionId, [
            'expand' => ['subscription.items.data.price'],
        ]);

        if (! $this->isOwnedCompletedSubscriptionSession($user, $session)) {
            return false;
        }

        $subscription = $session->subscription;

        if (is_string($subscription)) {
            $subscription = Cashier::stripe()->subscriptions->retrieve($subscription, [
                'expand' => ['items.data.price'],
            ]);
        }

        return $subscription instanceof StripeSubscription
            && $this->syncStripeSubscription($user, $subscription);
    }

    public function syncLatestActiveForUser(User $user): bool
    {
        if (! $user->stripe_id) {
            return false;
        }

        $subscriptions = Cashier::stripe()->subscriptions->all([
            'customer' => $user->stripe_id,
            'limit' => 5,
            'status' => 'all',
            'expand' => ['data.items.data.price'],
        ]);

        foreach ($subscriptions->data as $subscription) {
            if (in_array($subscription->status, ['active', 'trialing'], true)) {
                return $this->syncStripeSubscription($user, $subscription);
            }
        }

        return false;
    }

    public function syncStripeSubscription(User $user, StripeSubscription $stripeSubscription): bool
    {
        if ($this->stripeId($stripeSubscription->customer) !== $user->stripe_id) {
            return false;
        }

        $items = $stripeSubscription->items->data ?? [];

        if ($items === []) {
            return false;
        }

        $firstItem = $items[0];
        $isSinglePrice = count($items) === 1;
        $stripePrice = $isSinglePrice ? $this->stripeId($firstItem->price) : null;

        $subscription = $user->subscriptions()->updateOrCreate([
            'stripe_id' => $stripeSubscription->id,
        ], [
            'type' => $this->plans->subscriptionType(),
            'stripe_status' => $stripeSubscription->status,
            'stripe_price' => $stripePrice,
            'quantity' => $isSinglePrice ? ($firstItem->quantity ?? null) : null,
            'trial_ends_at' => $this->timestamp($stripeSubscription->trial_end ?? null),
            'ends_at' => $this->endsAt($stripeSubscription),
        ]);

        $subscriptionItemIds = [];

        foreach ($items as $item) {
            $subscriptionItemIds[] = $item->id;

            $subscription->items()->updateOrCreate([
                'stripe_id' => $item->id,
            ], [
                'stripe_product' => $this->stripeId($item->price->product),
                'stripe_price' => $this->stripeId($item->price),
                'quantity' => $item->quantity ?? null,
            ]);
        }

        $subscription->items()
            ->whereNotIn('stripe_id', $subscriptionItemIds)
            ->delete();

        $plan = $subscription->valid()
            ? $this->plans->findByPriceId($subscription->stripe_price)
            : null;

        $user->forceFill([
            'storage_quota_bytes' => $plan['quota_bytes'] ?? $this->plans->defaultQuotaBytes(),
        ])->save();

        return true;
    }

    private function isOwnedCompletedSubscriptionSession(User $user, StripeCheckoutSession $session): bool
    {
        return $this->stripeId($session->customer) === $user->stripe_id
            && $session->mode === 'subscription'
            && $session->status === 'complete';
    }

    private function endsAt(StripeSubscription $subscription): ?Carbon
    {
        if ($subscription->cancel_at_period_end ?? false) {
            return $this->timestamp($subscription->current_period_end ?? null);
        }

        return $this->timestamp($subscription->cancel_at ?? $subscription->canceled_at ?? null);
    }

    private function timestamp(mixed $timestamp): ?Carbon
    {
        return $timestamp ? Carbon::createFromTimestamp($timestamp) : null;
    }

    private function stripeId(mixed $value): ?string
    {
        return is_object($value) && isset($value->id) ? $value->id : $value;
    }
}
