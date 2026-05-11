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
    import { page, router, usePoll } from '@inertiajs/svelte';
    import Clock3 from 'lucide-svelte/icons/clock-3';
    import Cpu from 'lucide-svelte/icons/cpu';
    import Gauge from 'lucide-svelte/icons/gauge';
    import MemoryStick from 'lucide-svelte/icons/memory-stick';
    import Search from 'lucide-svelte/icons/search';
    import Wifi from 'lucide-svelte/icons/wifi';
    import AppHead from '@/components/AppHead.svelte';
    import PlanPickerDialog from '@/components/billing/PlanPickerDialog.svelte';
    import FileFolderRow from '@/components/files/FileFolderRow.svelte';
    import MediaImportProgress from '@/components/media/MediaImportProgress.svelte';
    import TorrentProgress from '@/components/torrents/TorrentProgress.svelte';
    import TorrentSubmitForm from '@/components/torrents/TorrentSubmitForm.svelte';
    import { store } from '@/routes/torrents';
    import { store as storeWishlist } from '@/routes/wishlist';

    type Torrent = {
        id: number;
        name: string | null;
        status: string;
        progress: number;
        total_size_bytes: number | null;
        downloaded_bytes: number;
        download_speed_bytes_per_second: number;
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

    type MediaFormat = {
        id: string;
        selector: string;
        type: 'video' | 'audio' | 'file';
        extension: string | null;
        quality: string;
        duration_seconds: number | null;
        size_bytes: number | null;
        source: string | null;
    };

    type MediaImport = {
        id: number;
        title: string | null;
        source_url: string;
        source_domain: string | null;
        thumbnail_url: string | null;
        status: string;
        progress: number;
        duration_seconds: number | null;
        estimated_size_bytes: number | null;
        downloaded_bytes: number;
        download_speed_bytes_per_second: number;
        formats: MediaFormat[];
        selected_format: MediaFormat | null;
        error_message: string | null;
    };

    type FileFolder = {
        id: string;
        torrent_id: number | null;
        media_import_id: number | null;
        name: string;
        download_url: string | null;
        size_bytes: number;
        updated_at?: string | null;
        files: StoredFile[];
    };

    type WishlistItem = {
        id: number;
        url: string;
        source_type: string;
        source_domain: string | null;
        title: string | null;
        created_at?: string | null;
    };

    type SystemMetrics = {
        cpu: {
            usage_percent: number | null;
            cores: number;
        };
        memory: {
            used_bytes: number | null;
            total_bytes: number | null;
            usage_percent: number | null;
        };
        network: {
            received_bytes_per_second: number;
            transmitted_bytes_per_second: number;
            total_bytes_per_second: number;
        };
        sampled_at: string;
    };

    let {
        quota,
        activeTorrent,
        activeMediaImport,
        prefillUrl,
        prefillAutoSubmit,
        prefillWishlistSave,
        wishlistItems,
        recentFileFolders,
        billing,
        systemMetrics,
    }: {
        quota: {
            used_bytes: number;
            quota_bytes: number;
            remaining_bytes: number;
        };
        billing: {
            current_plan_id: 'free' | 'basic' | 'pro' | 'master';
            current_plan_name: string;
            has_stripe_customer: boolean;
            status: string | null;
        };
        activeTorrent: Torrent | null;
        activeMediaImport: MediaImport | null;
        prefillUrl: string | null;
        prefillAutoSubmit: boolean;
        prefillWishlistSave: boolean;
        wishlistItems: WishlistItem[];
        recentFileFolders: FileFolder[];
        systemMetrics: SystemMetrics;
    } = $props();

    const user = $derived(page.props.auth.user);
    let planDialogOpen = $state(false);
    let prefillSubmitted = $state(false);
    let wishlistPrefillSaved = $state(false);

    const clearPrefillQuery = (): void => {
        const url = new URL(window.location.href);
        url.searchParams.delete('url');
        url.searchParams.delete('source');
        url.searchParams.delete('auto');
        url.searchParams.delete('wishlist');
        window.history.replaceState({}, '', url.toString());
    };

    const formatBytes = (bytes: number | null): string => {
        if (!bytes) {
            return '0 MB';
        }

        if (bytes >= 1024 * 1024 * 1024) {
            return `${(bytes / 1024 / 1024 / 1024).toFixed(2)} GB`;
        }

        return `${(bytes / 1024 / 1024).toFixed(2)} MB`;
    };

    const formatRate = (bytesPerSecond: number | null): string => {
        if (!bytesPerSecond) {
            return '0 B/s';
        }

        if (bytesPerSecond >= 1024 * 1024 * 1024) {
            return `${(bytesPerSecond / 1024 / 1024 / 1024).toFixed(2)} GB/s`;
        }

        if (bytesPerSecond >= 1024 * 1024) {
            return `${(bytesPerSecond / 1024 / 1024).toFixed(2)} MB/s`;
        }

        if (bytesPerSecond >= 1024) {
            return `${(bytesPerSecond / 1024).toFixed(1)} KB/s`;
        }

        return `${Math.round(bytesPerSecond)} B/s`;
    };

    const quotaPercent = $derived(
        quota.quota_bytes > 0
            ? Math.min(
                  100,
                  Math.round((quota.used_bytes / quota.quota_bytes) * 100),
              )
            : 0,
    );
    const mediaImportNeedsPolling = $derived(
        activeMediaImport !== null &&
            ['inspecting', 'queued', 'downloading', 'importing'].includes(
                activeMediaImport.status,
            ),
    );
    const activeDownload = $derived(
        activeTorrent !== null || activeMediaImport !== null,
    );
    const activeDownloadSpeed = $derived(
        activeTorrent?.download_speed_bytes_per_second ??
            activeMediaImport?.download_speed_bytes_per_second ??
            0,
    );

    const { start: startPolling, stop: stopPolling } = usePoll(
        2000,
        {
            only: [
                'quota',
                'activeTorrent',
                'activeMediaImport',
                'systemMetrics',
                'recentTorrents',
                'recentFileFolders',
            ],
        },
        {
            autoStart: false,
        },
    );

    $effect(() => {
        if (activeTorrent || mediaImportNeedsPolling) {
            startPolling();
        } else {
            stopPolling();
        }
    });

    $effect(() => {
        if (
            !prefillAutoSubmit ||
            prefillSubmitted ||
            !prefillUrl ||
            activeTorrent ||
            activeMediaImport
        ) {
            return;
        }

        prefillSubmitted = true;

        router.post(
            store.url(),
            { url: prefillUrl },
            {
                preserveScroll: true,
                onFinish: clearPrefillQuery,
            },
        );
    });

    $effect(() => {
        if (
            !prefillWishlistSave ||
            wishlistPrefillSaved ||
            !prefillUrl ||
            prefillAutoSubmit
        ) {
            return;
        }

        wishlistPrefillSaved = true;

        router.post(
            storeWishlist.url(),
            { url: prefillUrl },
            {
                preserveScroll: true,
                onFinish: clearPrefillQuery,
            },
        );
    });
</script>

<AppHead title="Dashboard" />

<div class="space-y-8">
    {#if billing.status}
        <div
            class="downloora-card bg-[var(--downloora-lime)] px-5 py-4 text-sm font-black text-[var(--downloora-ink)]"
        >
            {billing.status}
        </div>
    {/if}

    <section class="grid gap-6 lg:grid-cols-[22rem_minmax(0,1fr)]">
        <div
            class="downloora-card flex min-w-0 items-center gap-4 bg-[var(--downloora-paper)] p-5"
        >
            <div
                class="flex size-20 shrink-0 items-center justify-center rounded-full border-2 border-foreground bg-[var(--downloora-lime)] text-xl font-black text-[var(--downloora-ink)] shadow-[4px_4px_0_0_var(--foreground)]"
            >
                {user.name.slice(0, 2).toUpperCase()}
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex items-center justify-between gap-3">
                    <p class="truncate text-lg font-black uppercase">
                        {billing.current_plan_name}
                    </p>
                    <button
                        type="button"
                        onclick={() => (planDialogOpen = true)}
                        class="text-sm font-black uppercase text-[var(--downloora-orange)] underline decoration-2 underline-offset-4 hover:text-[var(--downloora-green)]"
                    >
                        Get more
                    </button>
                </div>
                <div class="downloora-progress mt-3 bg-background">
                    <div
                        class="downloora-progress-fill"
                        style={`width: ${quotaPercent}%`}
                    ></div>
                </div>
                <p class="mt-2 text-right text-sm font-semibold">
                    <span class="text-[var(--downloora-green)]"
                        >{formatBytes(quota.used_bytes)}</span
                    >
                    <span class="text-muted-foreground">
                        / {formatBytes(quota.quota_bytes)}</span
                    >
                </p>
            </div>
        </div>

        <div
            class="downloora-card flex min-w-0 items-center bg-[var(--downloora-paper)] p-4"
        >
            <TorrentSubmitForm
                {activeDownload}
                initialUrl={prefillUrl}
                {wishlistItems}
            />
        </div>
    </section>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="downloora-card bg-[var(--downloora-paper)] p-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p
                        class="text-xs font-black uppercase text-muted-foreground"
                    >
                        Download
                    </p>
                    <p class="mt-1 text-xl font-black tabular-nums">
                        {formatRate(activeDownloadSpeed)}
                    </p>
                </div>
                <span
                    class="flex size-10 items-center justify-center rounded-full border-2 border-foreground bg-[var(--downloora-lime)] text-[var(--downloora-ink)]"
                >
                    <Gauge class="size-5" />
                </span>
            </div>
        </div>

        <div class="downloora-card bg-[var(--downloora-paper)] p-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p
                        class="text-xs font-black uppercase text-muted-foreground"
                    >
                        CPU
                    </p>
                    <p class="mt-1 text-xl font-black tabular-nums">
                        {systemMetrics.cpu.usage_percent ?? 0}%
                    </p>
                </div>
                <span
                    class="flex size-10 items-center justify-center rounded-full border-2 border-foreground bg-[var(--downloora-purple)] text-[var(--downloora-paper)]"
                >
                    <Cpu class="size-5" />
                </span>
            </div>
            <p class="mt-2 text-xs font-semibold text-muted-foreground">
                {systemMetrics.cpu.cores} cores available
            </p>
        </div>

        <div class="downloora-card bg-[var(--downloora-paper)] p-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p
                        class="text-xs font-black uppercase text-muted-foreground"
                    >
                        RAM
                    </p>
                    <p class="mt-1 text-xl font-black tabular-nums">
                        {systemMetrics.memory.usage_percent ?? 0}%
                    </p>
                </div>
                <span
                    class="flex size-10 items-center justify-center rounded-full border-2 border-foreground bg-[var(--downloora-orange)] text-[var(--downloora-ink)]"
                >
                    <MemoryStick class="size-5" />
                </span>
            </div>
            <p class="mt-2 text-xs font-semibold text-muted-foreground">
                {formatBytes(systemMetrics.memory.used_bytes)} / {formatBytes(
                    systemMetrics.memory.total_bytes,
                )}
            </p>
        </div>

        <div class="downloora-card bg-[var(--downloora-paper)] p-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p
                        class="text-xs font-black uppercase text-muted-foreground"
                    >
                        Internet
                    </p>
                    <p class="mt-1 text-xl font-black tabular-nums">
                        {formatRate(
                            systemMetrics.network.total_bytes_per_second,
                        )}
                    </p>
                </div>
                <span
                    class="flex size-10 items-center justify-center rounded-full border-2 border-foreground bg-[var(--downloora-green)] text-[var(--downloora-paper)]"
                >
                    <Wifi class="size-5" />
                </span>
            </div>
            <p class="mt-2 text-xs font-semibold text-muted-foreground">
                {formatRate(systemMetrics.network.received_bytes_per_second)}
                down · {formatRate(
                    systemMetrics.network.transmitted_bytes_per_second,
                )}
                up
            </p>
        </div>
    </section>

    <section class="space-y-4 overflow-hidden">
        <div
            class="downloora-table-head grid min-h-14 grid-cols-[3.5rem_minmax(0,1fr)_7rem] items-center gap-4 px-4 text-sm font-black uppercase sm:grid-cols-[3.5rem_minmax(0,1fr)_8rem_9rem_12rem]"
        >
            <div class="flex items-center justify-center">
                <span
                    class="size-5 rounded border-2 border-[var(--downloora-ink)] bg-[var(--downloora-paper)]"
                ></span>
            </div>
            <div class="flex items-center gap-6">
                <span>Name</span>
                <label
                    class="hidden h-10 w-full max-w-72 items-center gap-2 rounded-full border-2 border-[var(--downloora-ink)] bg-[var(--downloora-paper)] px-3 text-[var(--downloora-ink)] sm:flex"
                >
                    <input
                        placeholder="Search your files"
                        class="min-w-0 flex-1 bg-transparent text-sm normal-case outline-none placeholder:text-[var(--downloora-ink)]/50"
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

        {#if activeMediaImport}
            <MediaImportProgress mediaImport={activeMediaImport} />
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

<PlanPickerDialog
    bind:open={planDialogOpen}
    currentPlanId={billing.current_plan_id}
/>
