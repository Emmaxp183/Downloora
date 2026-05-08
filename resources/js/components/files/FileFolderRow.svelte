<script lang="ts">
    import ChevronRight from 'lucide-svelte/icons/chevron-right';
    import Download from 'lucide-svelte/icons/download';
    import Folder from 'lucide-svelte/icons/folder';
    import X from 'lucide-svelte/icons/x';
    import { destroy as destroyMediaFolder } from '@/actions/App/Http/Controllers/MediaFolderAccessController';
    import { destroy as destroyTorrentFolder } from '@/actions/App/Http/Controllers/TorrentFolderAccessController';
    import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.svelte';
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

    let { folder }: { folder: FileFolder } = $props();

    let expanded = $state(false);
    let deleteDialogOpen = $state(false);

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

    const deleteForm = $derived(
        folder.torrent_id
            ? destroyTorrentFolder.form(folder.torrent_id)
            : folder.media_import_id
              ? destroyMediaFolder.form(folder.media_import_id)
              : null,
    );
</script>

<div>
    <div
        class="downloora-row grid min-h-20 w-full grid-cols-[3.5rem_minmax(0,1fr)_7rem] items-center gap-4 px-4 sm:grid-cols-[3.5rem_minmax(0,1fr)_8rem_9rem_12rem]"
    >
        <button
            type="button"
            onclick={() => (expanded = !expanded)}
            class="flex size-11 shrink-0 items-center justify-center rounded-full border-2 border-foreground bg-[var(--downloora-lime)] text-[var(--downloora-ink)] shadow-[2px_2px_0_0_var(--foreground)]"
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
            <span
                class="block truncate text-xs font-medium text-muted-foreground"
                >{count}</span
            >
        </button>

        <span class="flex items-center justify-end gap-2 sm:justify-center">
            {#if folder.download_url}
                <a
                    href={folder.download_url}
                    class="downloora-icon-button"
                    title="Download folder as zip"
                >
                    <Download class="size-4" />
                </a>
            {/if}

            {#if deleteForm}
                <button
                    type="button"
                    onclick={() => (deleteDialogOpen = true)}
                    class="downloora-icon-button downloora-danger"
                    title="Delete folder"
                >
                    <X class="size-4" />
                </button>
            {/if}

            <button
                type="button"
                onclick={() => (expanded = !expanded)}
                class="downloora-icon-button"
                aria-expanded={expanded}
                title={expanded ? 'Close folder' : 'Open folder'}
            >
                <ChevronRight
                    class={`size-5 transition-transform ${expanded ? 'rotate-90' : ''}`}
                />
            </button>
        </span>

        <span class="hidden text-base tabular-nums sm:block">{size}</span>

        <span class="hidden text-base text-muted-foreground sm:block">
            {changed}
        </span>
    </div>

    {#if expanded}
        <div class="mt-3 space-y-3 pl-6">
            {#each folder.files as file (file.id)}
                <FileRow {file} />
            {/each}
        </div>
    {/if}
</div>

{#if deleteForm}
    <ConfirmDeleteDialog
        bind:open={deleteDialogOpen}
        title="Delete this folder and all files inside?"
        description={`This will permanently delete ${folder.name} and every file inside it.`}
        confirmLabel="Delete folder"
        form={deleteForm}
    />
{/if}
