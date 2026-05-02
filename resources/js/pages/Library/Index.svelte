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
    import FileRow from '@/components/files/FileRow.svelte';

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

    let {
        quota,
        files,
    }: {
        quota: {
            used_bytes: number;
            quota_bytes: number;
            remaining_bytes: number;
        };
        files: StoredFile[];
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
        class="flex flex-col gap-4 border-b border-dashed border-zinc-200 pb-7 sm:flex-row sm:items-end sm:justify-between dark:border-zinc-800"
    >
        <div>
            <h1 class="text-2xl font-semibold">Library</h1>
            <p class="mt-1 text-sm text-zinc-500">
                Completed files stored in your private cloud library.
            </p>
        </div>

        <div class="w-full max-w-sm">
            <div
                class="flex items-center justify-between text-sm font-semibold"
            >
                <span class="text-emerald-500"
                    >{formatBytes(quota.used_bytes)}</span
                >
                <span class="text-zinc-500"
                    >{formatBytes(quota.quota_bytes)}</span
                >
            </div>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-zinc-100">
                <div
                    class="h-full rounded-full bg-emerald-400"
                    style={`width: ${quotaPercent}%`}
                ></div>
            </div>
        </div>
    </section>

    <section class="overflow-hidden">
        <div
            class="grid min-h-14 grid-cols-[3.5rem_minmax(0,1fr)_7rem] items-center gap-4 border-b border-zinc-100 px-4 text-sm font-medium uppercase text-sky-700 sm:grid-cols-[3.5rem_minmax(0,1fr)_8rem_9rem_12rem] dark:border-zinc-800 dark:text-sky-300"
        >
            <div class="flex items-center justify-center">
                <span
                    class="size-5 rounded border-2 border-zinc-300 dark:border-zinc-700"
                ></span>
            </div>
            <div class="flex items-center gap-6">
                <span>Name</span>
                <label
                    class="hidden h-10 w-full max-w-72 items-center gap-2 border border-zinc-200 bg-white px-3 text-zinc-500 sm:flex dark:border-zinc-800 dark:bg-zinc-900"
                >
                    <input
                        placeholder="Search your files"
                        class="min-w-0 flex-1 bg-transparent text-sm normal-case outline-none placeholder:text-zinc-400"
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

        {#each files as file (file.id)}
            <FileRow {file} />
        {:else}
            <div class="px-4 py-16 text-center text-sm text-zinc-500">
                No completed files yet.
            </div>
        {/each}
    </section>
</div>
