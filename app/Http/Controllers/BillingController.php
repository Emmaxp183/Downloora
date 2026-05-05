<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\BillingPlans;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Stripe\Exception\InvalidRequestException;
use Symfony\Component\HttpFoundation\Response;

class BillingController extends Controller
{
    public function checkout(Request $request, string $plan, BillingPlans $plans): Response
    {
        $selectedPlan = $plans->find($plan);

        if (! $selectedPlan) {
            throw ValidationException::withMessages([
                'plan' => 'Please choose a valid Downloora plan.',
            ]);
        }

        if (! $selectedPlan['stripe_price_id']) {
            throw ValidationException::withMessages([
                'plan' => 'This plan is missing a Stripe price ID. Add it to your environment before checkout.',
            ]);
        }

        $user = $request->user();

        if ($user->subscribed($plans->subscriptionType())) {
            return Inertia::location($user->billingPortalUrl(route('dashboard')));
        }

        try {
            return $this->startCheckout($user, $selectedPlan, $plans);
        } catch (InvalidRequestException $exception) {
            if (! $this->isCurrencyLockedCustomerError($exception) || ! app()->environment(['local', 'testing'])) {
                throw $exception;
            }

            $user->forceFill([
                'stripe_id' => null,
                'pm_type' => null,
                'pm_last_four' => null,
                'trial_ends_at' => null,
            ])->save();

            return $this->startCheckout($user->refresh(), $selectedPlan, $plans);
        }
    }

    private function startCheckout(User $user, array $selectedPlan, BillingPlans $plans): Response
    {
        $checkout = $user
            ->newSubscription($plans->subscriptionType(), $selectedPlan['stripe_price_id'])
            ->checkout([
                'success_url' => route('billing.success').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('billing.cancel'),
                'payment_method_types' => ['card', 'ideal'],
                'allow_promotion_codes' => true,
                'metadata' => [
                    'downloora_plan' => $selectedPlan['id'],
                ],
                'subscription_data' => [
                    'metadata' => [
                        'downloora_plan' => $selectedPlan['id'],
                    ],
                ],
            ]);

        return Inertia::location($checkout->url);
    }

    private function isCurrencyLockedCustomerError(InvalidRequestException $exception): bool
    {
        return str_contains($exception->getMessage(), 'You cannot combine currencies on a single customer');
    }

    public function portal(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasStripeId()) {
            return redirect()
                ->route('dashboard')
                ->with('status', 'Choose a plan before opening billing settings.');
        }

        return Inertia::location($user->billingPortalUrl(route('dashboard')));
    }

    public function success(): RedirectResponse
    {
        return redirect()
            ->route('dashboard')
            ->with('status', 'Payment received. Your subscription will update as soon as Stripe confirms it.');
    }

    public function cancel(): RedirectResponse
    {
        return redirect()
            ->route('dashboard')
            ->with('status', 'Checkout cancelled. Your current plan was not changed.');
    }
}
