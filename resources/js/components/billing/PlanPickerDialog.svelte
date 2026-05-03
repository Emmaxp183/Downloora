<script lang="ts">
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
    }: {
        open?: boolean;
    } = $props();

    let selectedPlan = $state<PlanId>('pro');
    let billYearly = $state(false);

    const plans: Plan[] = [
        {
            id: 'basic',
            name: 'Basic',
            quota: '30 GB',
            description: 'Perfect for steady weekend use',
            price: '6.95',
            icon: Flame,
            iconClass: 'text-orange-400',
        },
        {
            id: 'pro',
            name: 'Pro',
            quota: '100 GB',
            description: 'Perfect for large libraries & advanced networks',
            price: '9.95',
            icon: Sprout,
            iconClass: 'text-emerald-400',
            popular: true,
        },
        {
            id: 'master',
            name: 'Master',
            quota: '1 TB',
            description: 'Perfect for power users & hoarders',
            price: '19.95',
            icon: Cloud,
            iconClass: 'text-violet-400',
        },
    ];
</script>

<Dialog bind:open>
    <DialogContent
        class="max-h-[92vh] max-w-3xl overflow-y-auto rounded-3xl border-zinc-200 bg-white p-0 text-[#142b65] shadow-2xl dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-50"
    >
        <div
            class="flex items-center justify-between border-b border-zinc-200 px-6 py-5 dark:border-zinc-800 sm:px-8"
        >
            <div>
                <DialogTitle
                    class="text-3xl font-medium tracking-normal sm:text-4xl"
                >
                    Choose your plan
                </DialogTitle>
                <DialogDescription class="sr-only">
                    Select a storage plan for your Seedr Drive account.
                </DialogDescription>
            </div>
            <button
                type="button"
                onclick={() => (open = false)}
                class="flex size-10 items-center justify-center rounded-full bg-zinc-100 text-zinc-500 transition hover:bg-zinc-200 hover:text-zinc-900 dark:bg-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white"
                title="Close"
            >
                <X class="size-5" />
            </button>
        </div>

        <div class="space-y-6 px-6 py-6 sm:px-8">
            <p
                class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-zinc-400"
            >
                Available plans
            </p>

            <div class="space-y-4">
                {#each plans as plan (plan.id)}
                    <button
                        type="button"
                        onclick={() => (selectedPlan = plan.id)}
                        class={cn(
                            'relative grid w-full grid-cols-[4.5rem_minmax(0,1fr)] items-center gap-4 rounded-2xl border-2 border-zinc-200 bg-white p-4 text-left transition hover:border-blue-300 dark:border-zinc-800 dark:bg-zinc-950 dark:hover:border-blue-500/60 sm:grid-cols-[5rem_minmax(0,1fr)_8rem]',
                            selectedPlan === plan.id &&
                                'border-blue-500 bg-blue-50/60 shadow-[0_18px_48px_rgba(37,99,235,0.13)] dark:border-blue-400 dark:bg-blue-950/20',
                        )}
                    >
                        {#if plan.popular}
                            <span
                                class="absolute -top-4 left-6 rounded-full bg-violet-600 px-4 py-1 text-xs font-semibold uppercase tracking-[0.08em] text-white"
                            >
                                Most popular
                            </span>
                        {/if}

                        <span
                            class="flex size-16 items-center justify-center rounded-2xl bg-blue-50 ring-1 ring-blue-100 dark:bg-zinc-900 dark:ring-zinc-800"
                        >
                            <plan.icon
                                class={`size-8 stroke-[1.7] ${plan.iconClass}`}
                            />
                        </span>

                        <span class="min-w-0">
                            <span class="flex flex-wrap items-center gap-3">
                                <span class="text-2xl font-medium"
                                    >{plan.name}</span
                                >
                                <span
                                    class={cn(
                                        'rounded-full px-3 py-1 text-sm font-medium',
                                        plan.id === 'pro'
                                            ? 'bg-blue-500 text-white'
                                            : 'bg-zinc-100 text-slate-600 dark:bg-zinc-800 dark:text-zinc-200',
                                    )}
                                >
                                    {plan.quota}
                                </span>
                            </span>
                            <span
                                class="mt-2 block truncate text-base text-slate-500 dark:text-zinc-400"
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
                                class="block text-sm text-slate-500 dark:text-zinc-400"
                            >
                                per month
                            </span>
                        </span>
                    </button>
                {/each}
            </div>

            <button
                type="button"
                onclick={() => (billYearly = !billYearly)}
                class="flex w-full items-center justify-between gap-4 rounded-2xl border-2 border-zinc-200 bg-white p-4 text-left dark:border-zinc-800 dark:bg-zinc-950"
                aria-pressed={billYearly}
            >
                <span>
                    <span class="block text-xl font-medium">Bill yearly</span>
                    <span
                        class="mt-1 flex items-center gap-2 text-base font-medium text-emerald-500"
                    >
                        <Check class="size-5" />
                        Save €19.90 per year
                    </span>
                </span>
                <span
                    class={cn(
                        'flex h-9 w-16 items-center rounded-full p-1 transition',
                        billYearly
                            ? 'justify-end bg-blue-500'
                            : 'justify-start bg-slate-300 dark:bg-zinc-700',
                    )}
                >
                    <span class="size-7 rounded-full bg-white shadow-sm"></span>
                </span>
            </button>

            <div
                class="flex flex-wrap items-center justify-center gap-3 text-sm text-slate-500 dark:text-zinc-400"
            >
                <Star class="size-5 fill-amber-400 text-amber-400" />
                <span>Risk-Free</span>
                <span class="text-zinc-300 dark:text-zinc-700">|</span>
                <span>7-Day or 20GB</span>
                <span class="text-zinc-300 dark:text-zinc-700">|</span>
                <span>Money-Back Guarantee</span>
            </div>

            <button
                type="button"
                onclick={() => (open = false)}
                class="flex min-h-14 w-full items-center justify-center gap-3 rounded-full bg-blue-600 px-6 text-xl font-medium text-white shadow-[0_18px_42px_rgba(37,99,235,0.24)] transition hover:bg-blue-700"
            >
                Continue
                <ArrowRight class="size-6" />
            </button>

            <p
                class="text-center text-sm leading-6 text-slate-500 dark:text-zinc-400"
            >
                By clicking continue you agree to Seedr's
                <span class="text-blue-500">Terms of Service</span>
                and
                <span class="text-blue-500">Privacy Policy</span>.
            </p>
        </div>
    </DialogContent>
</Dialog>
