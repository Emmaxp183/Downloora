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
    };

    let { files }: { files: StoredFile[] } = $props();
</script>

<AppHead title="Library" />

<div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
    <div>
        <h1 class="text-2xl font-semibold tracking-normal">Library</h1>
        <p class="text-sm text-muted-foreground">
            Completed files stored in your private cloud library.
        </p>
    </div>

    <div class="overflow-hidden rounded-lg border">
        {#if files.length > 0}
            {#each files as file (file.id)}
                <FileRow {file} />
            {/each}
        {:else}
            <div class="px-4 py-10 text-center text-sm text-muted-foreground">
                No completed files yet.
            </div>
        {/if}
    </div>
</div>
