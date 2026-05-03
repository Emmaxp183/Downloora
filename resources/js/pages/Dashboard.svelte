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
    import { page, usePoll } from '@inertiajs/svelte';
    import Clock3 from 'lucide-svelte/icons/clock-3';
    import Search from 'lucide-svelte/icons/search';
    import AppHead from '@/components/AppHead.svelte';
    import PlanPickerDialog from '@/components/billing/PlanPickerDialog.svelte';
    import FileFolderRow from '@/components/files/FileFolderRow.svelte';
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
        updated_at?: string | null;
    };

    type FileFolder = {
        id: string;
        torrent_id: number | null;
        name: string;
        download_url: string | null;
        size_bytes: number;
        updated_at?: string | null;
        files: StoredFile[];
    };

    let {
        quota,
        activeTorrent,
        recentFileFolders,
    }: {
        quota: {
            used_bytes: number;
            quota_bytes: number;
            remaining_bytes: number;
        };
        activeTorrent: Torrent | null;
        recentFileFolders: FileFolder[];
    } = $props();

    const user = $derived(page.props.auth.user);
    let planDialogOpen = $state(false);

    const formatBytes = (bytes: number | null): string => {
        if (!bytes) {
            return '0 MB';
        }

        if (bytes >= 1024 * 1024 * 1024) {
            return `${(bytes / 1024 / 1024 / 1024).toFixed(2)} GB`;
        }

        return `${(bytes / 1024 / 1024).toFixed(2)} MB`;
    };

    const quotaPercent = $derived(
        quota.quota_bytes > 0
            ? Math.min(
                  100,
                  Math.round((quota.used_bytes / quota.quota_bytes) * 100),
              )
            : 0,
    );

    const { start: startPolling, stop: stopPolling } = usePoll(
        2000,
        {
            only: [
                'quota',
                'activeTorrent',
                'recentTorrents',
                'recentFileFolders',
            ],
        },
        {
            autoStart: false,
        },
    );

    $effect(() => {
        if (activeTorrent) {
            startPolling();
        } else {
            stopPolling();
        }
    });
</script>

<AppHead title="Dashboard" />

<div class="space-y-8">
    <section class="grid gap-6 lg:grid-cols-[22rem_minmax(0,1fr)]">
        <div
            class="seedr-card flex min-w-0 items-center gap-4 bg-[var(--seedr-paper)] p-5"
        >
            <div
                class="flex size-20 shrink-0 items-center justify-center rounded-full border-2 border-foreground bg-[var(--seedr-lime)] text-xl font-black text-[var(--seedr-ink)] shadow-[4px_4px_0_0_var(--foreground)]"
            >
                {user.name.slice(0, 2).toUpperCase()}
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex items-center justify-between gap-3">
                    <p class="truncate text-lg font-black uppercase">
                        Non-premium
                    </p>
                    <button
                        type="button"
                        onclick={() => (planDialogOpen = true)}
                        class="text-sm font-black uppercase text-[var(--seedr-orange)] underline decoration-2 underline-offset-4 hover:text-[var(--seedr-green)]"
                    >
                        Get more
                    </button>
                </div>
                <div class="seedr-progress mt-3 bg-background">
                    <div
                        class="seedr-progress-fill"
                        style={`width: ${quotaPercent}%`}
                    ></div>
                </div>
                <p class="mt-2 text-right text-sm font-semibold">
                    <span class="text-[var(--seedr-green)]"
                        >{formatBytes(quota.used_bytes)}</span
                    >
                    <span class="text-muted-foreground">
                        / {formatBytes(quota.quota_bytes)}</span
                    >
                </p>
            </div>
        </div>

        <div
            class="seedr-card flex min-w-0 items-center bg-[var(--seedr-paper)] p-4"
        >
            <TorrentSubmitForm disabled={activeTorrent !== null} />
        </div>
    </section>

    <section class="space-y-4 overflow-hidden">
        <div
            class="seedr-table-head grid min-h-14 grid-cols-[3.5rem_minmax(0,1fr)_7rem] items-center gap-4 px-4 text-sm font-black uppercase sm:grid-cols-[3.5rem_minmax(0,1fr)_8rem_9rem_12rem]"
        >
            <div class="flex items-center justify-center">
                <span
                    class="size-5 rounded border-2 border-[var(--seedr-ink)] bg-[var(--seedr-paper)]"
                ></span>
            </div>
            <div class="flex items-center gap-6">
                <span>Name</span>
                <label
                    class="hidden h-10 w-full max-w-72 items-center gap-2 rounded-full border-2 border-[var(--seedr-ink)] bg-[var(--seedr-paper)] px-3 text-[var(--seedr-ink)] sm:flex"
                >
                    <input
                        placeholder="Search your files"
                        class="min-w-0 flex-1 bg-transparent text-sm normal-case outline-none placeholder:text-[var(--seedr-ink)]/50"
                    />
                    <Search class="size-4" />
                </label>
            </div>
            <div class="text-right sm:text-center"></div>
            <div class="hidden sm:block">Size</div>
            <div class="hidden sm:flex sm:items-center sm:gap-2">
                <Clock3 class="size-4" />
                Last changed
            </div>
        </div>

        {#if activeTorrent}
            <TorrentProgress torrent={activeTorrent} />
        {/if}

        {#each recentFileFolders as folder (folder.id)}
            <FileFolderRow {folder} />
        {:else}
            <div class="px-4 py-16 text-center text-sm text-zinc-500">
                No completed files yet.
            </div>
        {/each}
    </section>
</div>

<PlanPickerDialog bind:open={planDialogOpen} />
