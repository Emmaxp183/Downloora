<script lang="ts">
    import { router } from '@inertiajs/svelte';
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

    const cancelDownload = (): void => {
        if (confirm(`Cancel ${name}?`)) {
            router.delete(destroy.url(torrent.id), {
                preserveScroll: true,
            });
        }
    };
</script>

<div
    class="seedr-row grid min-h-20 grid-cols-[minmax(0,1fr)_7rem] items-center gap-4 px-4"
>
    <div class="flex min-w-0 items-center gap-4">
        <span
            class="flex size-11 shrink-0 items-center justify-center rounded-full border-2 border-foreground bg-[var(--seedr-purple)] text-[var(--seedr-paper)]"
        >
            <Activity class="size-5" />
        </span>
        <div class="min-w-0 flex-1">
            <div class="flex items-center justify-between gap-4">
                <p class="truncate text-base font-medium">{name}</p>
                <span
                    class="text-sm font-bold tabular-nums text-muted-foreground"
                    >{progress}%</span
                >
            </div>
            <p
                class="mt-1 text-xs font-medium capitalize text-muted-foreground"
            >
                {status}
            </p>
            <div class="seedr-progress mt-2">
                <div
                    class="seedr-progress-fill"
                    style={`width: ${progress}%`}
                ></div>
            </div>
            {#if torrent.error_message}
                <p class="mt-2 text-sm font-semibold text-destructive">
                    {torrent.error_message}
                </p>
            {/if}
        </div>
    </div>
    <div class="flex justify-end">
        <button
            type="button"
            onclick={cancelDownload}
            class="seedr-icon-button seedr-danger"
            title="Cancel download"
        >
            <X class="size-4" />
        </button>
    </div>
</div>
