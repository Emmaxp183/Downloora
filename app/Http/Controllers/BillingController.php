<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Billing\StripeBillingStateResetter;
use App\Services\Billing\StripeSubscriptionSyncer;
use App\Support\BillingPlans;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Stripe\Exception\InvalidRequestException;
use Symfony\Component\HttpFoundation\Response;

class BillingController extends Controller
{
    public function checkout(
        Request $request,
        string $plan,
        BillingPlans $plans,
        StripeBillingStateResetter $billingStateResetter,
    ): Response {
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
            try {
                return Inertia::location($user->billingPortalUrl(route('dashboard')));
            } catch (InvalidRequestException $exception) {
                if (! $this->isMissingStripeCustomerError($exception)) {
                    throw $exception;
                }

                $user = $billingStateResetter->reset($user);
            }
        }

        try {
            return $this->startCheckout($user, $selectedPlan, $plans);
        } catch (InvalidRequestException $exception) {
            if ($this->isMissingStripeCustomerError($exception)) {
                return $this->startCheckout(
                    $billingStateResetter->reset($user),
                    $selectedPlan,
                    $plans,
                );
            }

            if (! $this->isCurrencyLockedCustomerError($exception) || ! app()->environment(['local', 'testing'])) {
                throw $exception;
            }

            return $this->startCheckout($billingStateResetter->reset($user), $selectedPlan, $plans);
        }
    }

    private function startCheckout(User $user, array $selectedPlan, BillingPlans $plans): Response
    {
        $checkout = $user
            ->newSubscription($plans->subscriptionType(), $selectedPlan['stripe_price_id'])
            ->checkout([
                'success_url' => route('billing.success').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('billing.cancel'),
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

    private function isMissingStripeCustomerError(InvalidRequestException $exception): bool
    {
        return $exception->getStripeCode() === 'resource_missing'
            && $exception->getStripeParam() === 'customer'
            && str_contains($exception->getMessage(), 'No such customer');
    }

    public function portal(Request $request, StripeBillingStateResetter $billingStateResetter): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasStripeId()) {
            return redirect()
                ->route('dashboard')
                ->with('status', 'Choose a plan before opening billing settings.');
        }

        try {
            return Inertia::location($user->billingPortalUrl(route('dashboard')));
        } catch (InvalidRequestException $exception) {
            if (! $this->isMissingStripeCustomerError($exception)) {
                throw $exception;
            }

            $billingStateResetter->reset($user);

            return redirect()
                ->route('dashboard')
                ->with('status', 'Your previous billing profile was from sandbox mode. Choose a plan to start live billing.');
        }
    }

    public function success(Request $request, StripeSubscriptionSyncer $stripeSubscriptionSyncer): RedirectResponse
    {
        if ($request->filled('session_id')) {
            $stripeSubscriptionSyncer->syncFromCheckoutSession(
                $request->user(),
                $request->string('session_id')->toString(),
            );
        }

        return redirect()
            ->route('dashboard')
            ->with('status', 'Payment received. Your plan is now active.');
    }

    public function cancel(): RedirectResponse
    {
        return redirect()
            ->route('dashboard')
            ->with('status', 'Checkout cancelled. Your current plan was not changed.');
    }
}
