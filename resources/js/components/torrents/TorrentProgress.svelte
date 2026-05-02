<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import Activity from 'lucide-svelte/icons/activity';
    import X from 'lucide-svelte/icons/x';
    import { destroy } from '@/actions/App/Http/Controllers/TorrentController';

    type Torrent = {
        id: number;
        name: string | null;
        status: string;
        progress: number;
        total_size_bytes: number | null;
        downloaded_bytes: number;
        error_message: string | null;
    };

    let { torrent }: { torrent: Torrent } = $props();

    const name = $derived(torrent.name ?? 'Inspecting torrent');
    const progress = $derived(Math.min(100, Math.max(0, torrent.progress)));
    const status = $derived(torrent.status.replaceAll('_', ' '));

    const confirmCancel = (event: MouseEvent): void => {
        if (!confirm(`Cancel ${name}?`)) {
            event.preventDefault();
        }
    };
</script>

<div
    class="grid min-h-20 grid-cols-[minmax(0,1fr)_7rem] items-center gap-4 border-b border-zinc-100 bg-white px-4 dark:border-zinc-800 dark:bg-zinc-950"
>
    <div class="flex min-w-0 items-center gap-4">
        <span
            class="flex size-10 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-indigo-500 dark:bg-indigo-500/10"
        >
            <Activity class="size-5" />
        </span>
        <div class="min-w-0 flex-1">
            <div class="flex items-center justify-between gap-4">
                <p class="truncate text-base font-medium">{name}</p>
                <span class="text-sm tabular-nums text-zinc-500"
                    >{progress}%</span
                >
            </div>
            <p class="mt-1 text-xs capitalize text-zinc-500">{status}</p>
            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-zinc-100">
                <div
                    class="h-full rounded-full bg-indigo-400 transition-[width]"
                    style={`width: ${progress}%`}
                ></div>
            </div>
            {#if torrent.error_message}
                <p class="mt-2 text-sm text-red-500">{torrent.error_message}</p>
            {/if}
        </div>
    </div>
    <div class="flex justify-end">
        <Link
            href={destroy(torrent.id)}
            as="button"
            type="button"
            preserveScroll
            onclick={confirmCancel}
            class="flex size-9 items-center justify-center rounded-full bg-zinc-100 text-zinc-500 transition hover:bg-rose-50 hover:text-rose-500 dark:bg-zinc-900 dark:hover:bg-rose-950"
            title="Cancel download"
        >
            <X class="size-4" />
        </Link>
    </div>
</div>
