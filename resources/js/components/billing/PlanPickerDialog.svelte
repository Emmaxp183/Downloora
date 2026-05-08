<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import ArrowRight from 'lucide-svelte/icons/arrow-right';
    import Check from 'lucide-svelte/icons/check';
    import Cloud from 'lucide-svelte/icons/cloud';
    import Flame from 'lucide-svelte/icons/flame';
    import Sprout from 'lucide-svelte/icons/sprout';
    import Star from 'lucide-svelte/icons/star';
    import X from 'lucide-svelte/icons/x';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { cn } from '@/lib/utils';
    import { checkout } from '@/routes/billing';

    type PlanId = 'basic' | 'pro' | 'master';

    type Plan = {
        id: PlanId;
        name: string;
        quota: string;
        description: string;
        price: string;
        icon: typeof Flame;
        iconClass: string;
        popular?: boolean;
    };

    let {
        open = $bindable(false),
        currentPlanId = 'free',
    }: {
        open?: boolean;
        currentPlanId?: PlanId | 'free';
    } = $props();

    let selectedPlan = $state<PlanId>('pro');
    let processing = $state(false);
    let planError = $state<string | null>(null);

    const plans: Plan[] = [
        {
            id: 'basic',
            name: 'Basic',
            quota: '50 GB',
            description: 'Perfect for steady weekend use',
            price: '5.99',
            icon: Flame,
            iconClass:
                'bg-[var(--downloora-orange)] text-[var(--downloora-ink)]',
        },
        {
            id: 'pro',
            name: 'Pro',
            quota: '100 GB',
            description: 'Perfect for large libraries & advanced networks',
            price: '10',
            icon: Sprout,
            iconClass: 'bg-[var(--downloora-lime)] text-[var(--downloora-ink)]',
            popular: true,
        },
        {
            id: 'master',
            name: 'Master',
            quota: '1 TB',
            description: 'Perfect for power users & hoarders',
            price: '20',
            icon: Cloud,
            iconClass:
                'bg-[var(--downloora-purple)] text-[var(--downloora-paper)]',
        },
    ];

    const startCheckout = (): void => {
        planError = null;
        processing = true;

        router.post(
            checkout.url(selectedPlan),
            {},
            {
                preserveScroll: true,
                onError: (errors) => {
                    planError =
                        typeof errors.plan === 'string'
                            ? errors.plan
                            : 'Unable to start Stripe Checkout. Please try again.';
                },
                onFinish: () => {
                    processing = false;
                },
            },
        );
    };

    $effect(() => {
        if (open) {
            selectedPlan = currentPlanId === 'free' ? 'pro' : currentPlanId;
            planError = null;
        }
    });
</script>

<Dialog bind:open>
    <DialogContent class="max-h-[92vh] max-w-3xl overflow-y-auto p-0">
        <div
            class="flex items-center justify-between border-b-2 border-foreground px-6 py-5 sm:px-8"
        >
            <div>
                <DialogTitle
                    class="text-3xl font-black tracking-tight sm:text-4xl"
                >
                    Choose your plan
                </DialogTitle>
                <DialogDescription class="sr-only">
                    Select a storage plan for your Downloora account.
                </DialogDescription>
            </div>
            <button
                type="button"
                onclick={() => (open = false)}
                class="downloora-icon-button"
                title="Close"
            >
                <X class="size-5" />
            </button>
        </div>

        <div class="space-y-6 px-6 py-6 sm:px-8">
            <p
                class="text-sm font-black uppercase tracking-[0.18em] text-[var(--downloora-green)]"
            >
                Available plans
            </p>

            <div class="space-y-4">
                {#each plans as plan (plan.id)}
                    <button
                        type="button"
                        onclick={() => (selectedPlan = plan.id)}
                        class={cn(
                            'relative grid w-full grid-cols-[4.5rem_minmax(0,1fr)] items-center gap-4 rounded-[1.25rem] border-2 border-foreground bg-card p-4 text-left shadow-[3px_3px_0_0_var(--foreground)] transition hover:-translate-y-0.5 hover:bg-muted sm:grid-cols-[5rem_minmax(0,1fr)_8rem]',
                            selectedPlan === plan.id &&
                                'bg-[var(--downloora-lime)] text-[var(--downloora-ink)] shadow-[6px_6px_0_0_var(--foreground)]',
                        )}
                    >
                        {#if plan.popular}
                            <span
                                class="absolute -top-4 left-6 rounded-full border-2 border-foreground bg-[var(--downloora-purple)] px-4 py-1 text-xs font-black uppercase tracking-[0.08em] text-[var(--downloora-paper)]"
                            >
                                Most popular
                            </span>
                        {/if}

                        <span
                            class={`flex size-16 items-center justify-center rounded-2xl border-2 border-foreground shadow-[2px_2px_0_0_var(--foreground)] ${plan.iconClass}`}
                        >
                            <plan.icon class="size-8 stroke-[1.7]" />
                        </span>

                        <span class="min-w-0">
                            <span class="flex flex-wrap items-center gap-3">
                                <span class="text-2xl font-medium"
                                    >{plan.name}</span
                                >
                                {#if currentPlanId === plan.id}
                                    <span
                                        class="rounded-full border border-foreground bg-[var(--downloora-paper)] px-3 py-1 text-xs font-black uppercase"
                                    >
                                        Current
                                    </span>
                                {/if}
                                <span
                                    class="rounded-full border border-foreground bg-card px-3 py-1 text-sm font-bold"
                                >
                                    {plan.quota}
                                </span>
                            </span>
                            <span
                                class="mt-2 block truncate text-base font-medium text-muted-foreground"
                            >
                                {plan.description}
                            </span>
                        </span>

                        <span
                            class="col-span-2 flex items-end justify-between gap-2 sm:col-span-1 sm:block sm:text-right"
                        >
                            <span class="text-2xl font-medium tabular-nums">
                                <span class="align-super text-base">€</span
                                >{plan.price}
                            </span>
                            <span
                                class="block text-sm font-medium text-muted-foreground"
                            >
                                per month
                            </span>
                        </span>
                    </button>
                {/each}
            </div>

            <div
                class="rounded-[1.25rem] border-2 border-foreground bg-card p-4 text-sm font-semibold text-muted-foreground shadow-[3px_3px_0_0_var(--foreground)]"
            >
                <span
                    class="mr-2 inline-flex size-6 items-center justify-center rounded-full border-2 border-foreground bg-[var(--downloora-lime)] text-[var(--downloora-ink)]"
                >
                    <Check class="size-4" />
                </span>
                Monthly billing is handled securely by Stripe in EUR. You can pay
                with card or iDEAL and manage cards, invoices, and cancellation in
                Stripe Billing.
            </div>

            {#if planError}
                <p
                    class="rounded-[1.25rem] border-2 border-[var(--downloora-danger)] bg-[var(--downloora-danger)]/10 p-4 text-sm font-bold text-[var(--downloora-danger)]"
                >
                    {planError}
                </p>
            {/if}

            <div
                class="flex flex-wrap items-center justify-center gap-3 text-sm font-medium text-muted-foreground"
            >
                <Star
                    class="size-5 fill-[var(--downloora-orange)] text-[var(--downloora-orange)]"
                />
                <span>Risk-Free</span>
                <span>|</span>
                <span>7-Day or 20GB</span>
                <span>|</span>
                <span>Money-Back Guarantee</span>
            </div>

            <button
                type="button"
                onclick={startCheckout}
                disabled={processing}
                class="downloora-button flex min-h-14 w-full text-xl"
            >
                {processing ? 'Opening Stripe...' : 'Continue'}
                <ArrowRight class="size-6" />
            </button>

            <p class="text-center text-sm leading-6 text-muted-foreground">
                By clicking continue you agree to Downloora's
                <span class="font-semibold text-[var(--downloora-green)]"
                    >Terms of Service</span
                >
                and
                <span class="font-semibold text-[var(--downloora-green)]"
                    >Privacy Policy</span
                >.
            </p>
        </div>
    </DialogContent>
</Dialog>
