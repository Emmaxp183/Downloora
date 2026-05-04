<script module lang="ts">
    import { index as library } from '@/routes/library';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Library',
                href: library(),
            },
        ],
    };
</script>

<script lang="ts">
    import Clock3 from 'lucide-svelte/icons/clock-3';
    import Search from 'lucide-svelte/icons/search';
    import AppHead from '@/components/AppHead.svelte';
    import FileFolderRow from '@/components/files/FileFolderRow.svelte';

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
        media_import_id: number | null;
        name: string;
        download_url: string | null;
        size_bytes: number;
        updated_at?: string | null;
        files: StoredFile[];
    };

    let {
        quota,
        fileFolders,
    }: {
        quota: {
            used_bytes: number;
            quota_bytes: number;
            remaining_bytes: number;
        };
        fileFolders: FileFolder[];
    } = $props();

    const formatBytes = (bytes: number): string => {
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
</script>

<AppHead title="Library" />

<div class="space-y-8">
    <section
        class="seedr-card flex flex-col gap-4 bg-[var(--seedr-paper)] p-5 sm:flex-row sm:items-end sm:justify-between"
    >
        <div>
            <h1 class="text-3xl font-black tracking-tight">Library</h1>
            <p class="mt-1 text-sm font-medium text-muted-foreground">
                Completed files stored in your private cloud library.
            </p>
        </div>

        <div class="w-full max-w-sm">
            <div
                class="flex items-center justify-between text-sm font-semibold"
            >
                <span class="text-[var(--seedr-green)]"
                    >{formatBytes(quota.used_bytes)}</span
                >
                <span class="text-muted-foreground"
                    >{formatBytes(quota.quota_bytes)}</span
                >
            </div>
            <div class="seedr-progress mt-2">
                <div
                    class="seedr-progress-fill"
                    style={`width: ${quotaPercent}%`}
                ></div>
            </div>
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

        {#each fileFolders as folder (folder.id)}
            <FileFolderRow {folder} />
        {:else}
            <div class="px-4 py-16 text-center text-sm text-zinc-500">
                No completed files yet.
            </div>
        {/each}
    </section>
</div>
