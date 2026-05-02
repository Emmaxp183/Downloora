<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import Link2 from 'lucide-svelte/icons/link-2';
    import Plus from 'lucide-svelte/icons/plus';
    import { store } from '@/actions/App/Http/Controllers/TorrentController';

    let {
        disabled = false,
    }: {
        disabled?: boolean;
    } = $props();
</script>

<Form {...store.form()} resetOnSuccess class="w-full">
    {#snippet children({ errors, processing })}
        <div
            class="group flex min-h-14 w-full overflow-hidden border border-zinc-100 bg-zinc-50 shadow-[0_16px_34px_rgba(24,24,27,0.04)] transition focus-within:border-indigo-200 focus-within:bg-white dark:border-zinc-800 dark:bg-zinc-900 dark:focus-within:border-indigo-500/40"
        >
            <div
                class="flex w-14 shrink-0 items-center justify-center text-zinc-400"
            >
                <Link2 class="size-5" />
            </div>
            <input
                id="magnet_uri"
                name="magnet_uri"
                placeholder="Paste magnet link URL here"
                disabled={disabled || processing}
                class="min-w-0 flex-1 bg-transparent text-base outline-none placeholder:text-zinc-400 disabled:cursor-not-allowed disabled:opacity-60"
                autocomplete="off"
            />
            <button
                type="submit"
                disabled={disabled || processing}
                class="flex w-16 shrink-0 items-center justify-center bg-zinc-100 text-zinc-950 transition hover:bg-zinc-200 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-zinc-800 dark:text-white dark:hover:bg-zinc-700"
                title="Add magnet"
            >
                <Plus class="size-7 stroke-[3]" />
            </button>
        </div>

        {#if errors.magnet_uri}
            <p class="mt-2 text-sm text-red-500">{errors.magnet_uri}</p>
        {:else if disabled}
            <p class="mt-2 text-sm text-zinc-500">
                Finish or cancel your active torrent before adding another.
            </p>
        {/if}
    {/snippet}
</Form>
