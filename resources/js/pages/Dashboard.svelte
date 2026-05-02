<script module lang="ts">
    import { dashboard } from '@/routes';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    };
</script>

<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import FileRow from '@/components/files/FileRow.svelte';
    import TorrentProgress from '@/components/torrents/TorrentProgress.svelte';
    import TorrentSubmitForm from '@/components/torrents/TorrentSubmitForm.svelte';

    type Torrent = {
        id: number;
        name: string | null;
        status: string;
        progress: number;
        total_size_bytes: number | null;
        downloaded_bytes: number;
        error_message: string | null;
    };

    type StoredFile = {
        id: number;
        name: string;
        original_path: string;
        mime_type: string | null;
        size_bytes: number;
        download_url: string;
        stream_url: string;
    };

    let {
        quota,
        activeTorrent,
        recentTorrents,
        recentFiles,
    }: {
        quota: {
            used_bytes: number;
            quota_bytes: number;
            remaining_bytes: number;
        };
        activeTorrent: Torrent | null;
        recentTorrents: Torrent[];
        recentFiles: StoredFile[];
    } = $props();

    const formatBytes = (bytes: number | null): string => {
        if (!bytes) {
            return '0 MB';
        }

        return `${(bytes / 1024 / 1024).toFixed(2)} MB`;
    };

    const quotaPercent = $derived(
        quota.quota_bytes > 0
            ? Math.round((quota.used_bytes / quota.quota_bytes) * 100)
            : 0,
    );
</script>

<AppHead title="Dashboard" />

<div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
    <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-lg border p-4">
            <p class="text-sm text-muted-foreground">Used</p>
            <p class="mt-2 text-2xl font-semibold">
                {formatBytes(quota.used_bytes)}
            </p>
        </div>
        <div class="rounded-lg border p-4">
            <p class="text-sm text-muted-foreground">Remaining</p>
            <p class="mt-2 text-2xl font-semibold">
                {formatBytes(quota.remaining_bytes)}
            </p>
        </div>
        <div class="rounded-lg border p-4">
            <p class="text-sm text-muted-foreground">Quota</p>
            <p class="mt-2 text-2xl font-semibold">{quotaPercent}%</p>
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <div class="space-y-4">
            <TorrentSubmitForm disabled={activeTorrent !== null} />

            {#if activeTorrent}
                <TorrentProgress torrent={activeTorrent} />
            {/if}

            <div class="rounded-lg border">
                <div class="border-b px-4 py-3">
                    <h2 class="font-medium">Recent torrents</h2>
                </div>
                <div class="divide-y">
                    {#each recentTorrents as torrent (torrent.id)}
                        <div class="px-4 py-3">
                            <div class="flex items-center justify-between gap-4">
                                <p class="truncate text-sm font-medium">
                                    {torrent.name ?? 'Inspecting torrent'}
                                </p>
                                <span class="text-sm tabular-nums"
                                    >{torrent.progress}%</span
                                >
                            </div>
                            <p class="text-xs capitalize text-muted-foreground">
                                {torrent.status.replaceAll('_', ' ')}
                            </p>
                        </div>
                    {:else}
                        <div class="px-4 py-10 text-center text-sm text-muted-foreground">
                            No torrents yet.
                        </div>
                    {/each}
                </div>
            </div>
        </div>

        <div class="rounded-lg border">
            <div class="border-b px-4 py-3">
                <h2 class="font-medium">Recent files</h2>
            </div>
            {#each recentFiles as file (file.id)}
                <FileRow {file} />
            {:else}
                <div class="px-4 py-10 text-center text-sm text-muted-foreground">
                    No completed files yet.
                </div>
            {/each}
        </div>
    </div>
</div>
