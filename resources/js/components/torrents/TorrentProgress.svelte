<script lang="ts">
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
</script>

<div class="rounded-lg border p-4">
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <p class="truncate text-sm font-medium">{name}</p>
            <p class="text-xs capitalize text-muted-foreground">
                {torrent.status.replaceAll('_', ' ')}
            </p>
        </div>
        <span class="text-sm tabular-nums">{progress}%</span>
    </div>

    <div class="mt-3 h-2 overflow-hidden rounded-full bg-muted">
        <div class="h-full bg-primary" style={`width: ${progress}%`}></div>
    </div>

    {#if torrent.error_message}
        <p class="mt-3 text-sm text-destructive">{torrent.error_message}</p>
    {/if}
</div>
