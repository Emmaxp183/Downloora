<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import Download from 'lucide-svelte/icons/download';
    import FileText from 'lucide-svelte/icons/file-text';
    import Play from 'lucide-svelte/icons/play';
    import X from 'lucide-svelte/icons/x';
    import { destroy } from '@/actions/App/Http/Controllers/StoredFileAccessController';

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

    let { file }: { file: StoredFile } = $props();

    const canPlay = $derived(
        file.mime_type?.startsWith('video/') ||
            file.mime_type?.startsWith('audio/'),
    );

    const size = $derived(`${(file.size_bytes / 1024 / 1024).toFixed(2)} MB`);
    const changed = $derived(
        file.updated_at
            ? new Intl.DateTimeFormat(undefined, {
                  month: 'short',
                  day: 'numeric',
                  hour: 'numeric',
                  minute: '2-digit',
              }).format(new Date(file.updated_at))
            : 'Unknown',
    );

    const confirmDelete = (event: MouseEvent): void => {
        if (!confirm(`Delete ${file.name}?`)) {
            event.preventDefault();
        }
    };
</script>

<div
    class="grid min-h-20 grid-cols-[3.5rem_minmax(0,1fr)_7rem] items-center gap-4 border-b border-zinc-100 bg-white px-4 text-zinc-900 transition hover:bg-zinc-50/80 sm:grid-cols-[3.5rem_minmax(0,1fr)_8rem_9rem_12rem] dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-50 dark:hover:bg-zinc-900/70"
>
    <span
        class="flex size-10 shrink-0 items-center justify-center text-amber-400"
    >
        <FileText class="size-6 stroke-[1.7]" />
    </span>

    <div class="min-w-0">
        <p class="truncate text-base font-medium">{file.name}</p>
        <p class="truncate text-xs text-zinc-500">{file.original_path}</p>
    </div>

    <div class="flex items-center justify-end gap-2 sm:justify-center">
        <a
            href={file.download_url}
            class="flex size-9 items-center justify-center rounded-full bg-zinc-100 text-zinc-500 transition hover:bg-indigo-50 hover:text-indigo-500 dark:bg-zinc-900 dark:hover:bg-indigo-950"
            title="Download"
        >
            <Download class="size-4" />
        </a>

        {#if canPlay}
            <a
                href={file.stream_url}
                target="_blank"
                class="flex size-9 items-center justify-center rounded-full bg-zinc-100 text-zinc-500 transition hover:bg-indigo-50 hover:text-indigo-500 dark:bg-zinc-900 dark:hover:bg-indigo-950"
                title="Stream"
            >
                <Play class="size-4" />
            </a>
        {/if}

        <Link
            href={destroy(file.id)}
            as="button"
            type="button"
            preserveScroll
            onclick={confirmDelete}
            class="flex size-9 items-center justify-center rounded-full bg-zinc-100 text-zinc-500 transition hover:bg-rose-50 hover:text-rose-500 dark:bg-zinc-900 dark:hover:bg-rose-950"
            title="Delete"
        >
            <X class="size-4" />
        </Link>
    </div>

    <div class="hidden text-base tabular-nums sm:block">{size}</div>

    <div class="hidden text-base text-zinc-700 sm:block dark:text-zinc-300">
        {changed}
    </div>
</div>
