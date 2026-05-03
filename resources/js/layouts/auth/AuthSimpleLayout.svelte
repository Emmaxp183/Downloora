<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import type { Snippet } from 'svelte';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';
    import { home } from '@/routes';

    let {
        title = '',
        description = '',
        children,
    }: {
        title?: string;
        description?: string;
        children?: Snippet;
    } = $props();
</script>

<div
    class="seedr-page relative flex min-h-svh flex-col items-center justify-center gap-6 overflow-hidden p-6 md:p-10"
>
    <div
        class="seedr-dots pointer-events-none absolute inset-0 opacity-[0.055]"
    ></div>

    <div class="relative z-10 w-full max-w-md">
        <div class="seedr-panel flex flex-col gap-8 p-6 sm:p-8">
            <div class="flex flex-col items-center gap-4">
                <Link
                    href={home()}
                    class="flex flex-col items-center gap-2 font-medium"
                >
                    <div
                        class="mb-1 flex size-14 items-center justify-center rounded-full border-2 border-foreground bg-[var(--seedr-lime)] text-[var(--seedr-ink)] shadow-[3px_3px_0_0_var(--foreground)]"
                    >
                        <AppLogoIcon class="size-8 fill-current" />
                    </div>
                    <span class="sr-only">{title}</span>
                </Link>
                <div class="space-y-2 text-center">
                    <h1 class="text-2xl font-black tracking-tight">{title}</h1>
                    <p
                        class="text-center text-sm font-medium text-muted-foreground"
                    >
                        {description}
                    </p>
                </div>
            </div>
            {@render children?.()}
        </div>
    </div>
</div>
