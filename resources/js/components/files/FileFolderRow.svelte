<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import ChevronRight from 'lucide-svelte/icons/chevron-right';
    import Download from 'lucide-svelte/icons/download';
    import Folder from 'lucide-svelte/icons/folder';
    import X from 'lucide-svelte/icons/x';
    import FileRow from '@/components/files/FileRow.svelte';
    import { destroy } from '@/actions/App/Http/Controllers/TorrentFolderAccessController';

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

    let { folder }: { folder: FileFolder } = $props();

    let expanded = $state(false);

    const size = $derived(`${(folder.size_bytes / 1024 / 1024).toFixed(2)} MB`);
    const changed = $derived(
        folder.updated_at
            ? new Intl.DateTimeFormat(undefined, {
                  month: 'short',
                  day: 'numeric',
                  hour: 'numeric',
                  minute: '2-digit',
              }).format(new Date(folder.updated_at))
            : 'Unknown',
    );

    const count = $derived(
        folder.files.length === 1
            ? '1 file'
            : `${folder.files.length.toLocaleString()} files`,
    );

    const confirmDelete = (event: MouseEvent): void => {
        if (!confirm(`Delete ${folder.name} and all files inside?`)) {
            event.preventDefault();
        }
    };
</script>

<div>
    <div
        class="grid min-h-20 w-full grid-cols-[3.5rem_minmax(0,1fr)_7rem] items-center gap-4 border-b border-zinc-100 bg-white px-4 text-zinc-900 transition hover:bg-zinc-50/80 sm:grid-cols-[3.5rem_minmax(0,1fr)_8rem_9rem_12rem] dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-50 dark:hover:bg-zinc-900/70"
    >
        <button
            type="button"
            onclick={() => (expanded = !expanded)}
            class="flex size-10 shrink-0 items-center justify-center text-amber-400"
            aria-expanded={expanded}
            title={expanded ? 'Close folder' : 'Open folder'}
        >
            <Folder class="size-8 fill-current stroke-[1.5]" />
        </button>

        <button
            type="button"
            onclick={() => (expanded = !expanded)}
            class="min-w-0 text-left"
            aria-expanded={expanded}
        >
            <span class="block truncate text-base font-medium">
                {folder.name}
            </span>
            <span class="block truncate text-xs text-zinc-500">{count}</span>
        </button>

        <span class="flex items-center justify-end gap-2 sm:justify-center">
            {#if folder.download_url}
                <a
                    href={folder.download_url}
                    class="flex size-9 items-center justify-center rounded-full bg-zinc-100 text-zinc-500 transition hover:bg-indigo-50 hover:text-indigo-500 dark:bg-zinc-900 dark:hover:bg-indigo-950"
                    title="Download folder as zip"
                >
                    <Download class="size-4" />
                </a>
            {/if}

            {#if folder.torrent_id}
                <Link
                    href={destroy(folder.torrent_id)}
                    as="button"
                    type="button"
                    preserveScroll
                    onclick={confirmDelete}
                    class="flex size-9 items-center justify-center rounded-full bg-zinc-100 text-zinc-500 transition hover:bg-rose-50 hover:text-rose-500 dark:bg-zinc-900 dark:hover:bg-rose-950"
                    title="Delete folder"
                >
                    <X class="size-4" />
                </Link>
            {/if}

            <button
                type="button"
                onclick={() => (expanded = !expanded)}
                class="flex size-9 items-center justify-center rounded-full bg-zinc-100 text-zinc-500 transition hover:bg-zinc-200 dark:bg-zinc-900 dark:hover:bg-zinc-800"
                aria-expanded={expanded}
                title={expanded ? 'Close folder' : 'Open folder'}
            >
                <ChevronRight
                    class={`size-5 text-zinc-400 transition-transform ${expanded ? 'rotate-90' : ''}`}
                />
            </button>
        </span>

        <span class="hidden text-base tabular-nums sm:block">{size}</span>

        <span class="hidden text-base text-zinc-700 sm:block dark:text-zinc-300">
            {changed}
        </span>
    </div>

    {#if expanded}
        <div class="border-b border-zinc-100 pl-6 dark:border-zinc-800">
            {#each folder.files as file (file.id)}
                <FileRow {file} />
            {/each}
        </div>
    {/if}
</div>
