<?php

namespace App\Support;

class BillingPlans
{
    /**
     * @return array<string, array{id: string, name: string, stripe_price_id: ?string, quota_bytes: int}>
     */
    public function all(): array
    {
        return config('billing.plans', []);
    }

    /**
     * @return array{id: string, name: string, stripe_price_id: ?string, quota_bytes: int}|null
     */
    public function find(string $plan): ?array
    {
        return $this->all()[$plan] ?? null;
    }

    /**
     * @return array{id: string, name: string, stripe_price_id: ?string, quota_bytes: int}|null
     */
    public function findByPriceId(?string $priceId): ?array
    {
        if (! $priceId) {
            return null;
        }

        foreach ($this->all() as $plan) {
            if ($plan['stripe_price_id'] === $priceId) {
                return $plan;
            }
        }

        return null;
    }

    public function subscriptionType(): string
    {
        return config('billing.subscription_type', 'default');
    }

    public function defaultQuotaBytes(): int
    {
        return (int) config('billing.default_quota_bytes');
    }
}
