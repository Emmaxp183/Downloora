<?php

namespace App\Services\Billing;

use App\Models\User;
use App\Support\BillingPlans;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\SubscriptionItem;

class StripeBillingStateResetter
{
    public function __construct(private BillingPlans $plans) {}

    public function reset(User $user): User
    {
        return DB::transaction(function () use ($user): User {
            $subscriptionIds = $user->subscriptions()->pluck('id');

            if ($subscriptionIds->isNotEmpty()) {
                SubscriptionItem::query()
                    ->whereIn('subscription_id', $subscriptionIds)
                    ->delete();
            }

            $user->subscriptions()->delete();

            $user->forceFill([
                'stripe_id' => null,
                'pm_type' => null,
                'pm_last_four' => null,
                'trial_ends_at' => null,
                'storage_quota_bytes' => $this->plans->defaultQuotaBytes(),
            ])->save();

            return $user->refresh();
        });
    }
}
