<script lang="ts">
    import BookOpenText from 'lucide-svelte/icons/book-open-text';
    import Download from 'lucide-svelte/icons/download';
    import FileArchive from 'lucide-svelte/icons/file-archive';
    import FileAudio from 'lucide-svelte/icons/file-audio';
    import FileCode2 from 'lucide-svelte/icons/file-code-2';
    import FileImage from 'lucide-svelte/icons/file-image';
    import FileJson from 'lucide-svelte/icons/file-json';
    import FileQuestion from 'lucide-svelte/icons/file-question';
    import FileSpreadsheet from 'lucide-svelte/icons/file-spreadsheet';
    import FileText from 'lucide-svelte/icons/file-text';
    import FileType from 'lucide-svelte/icons/file-type';
    import FileVideo from 'lucide-svelte/icons/file-video';
    import Play from 'lucide-svelte/icons/play';
    import X from 'lucide-svelte/icons/x';
    import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.svelte';
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

    type FileIconKind =
        | 'archive'
        | 'audio'
        | 'code'
        | 'document'
        | 'ebook'
        | 'image'
        | 'json'
        | 'spreadsheet'
        | 'text'
        | 'unknown'
        | 'video';

    let { file }: { file: StoredFile } = $props();

    let deleteDialogOpen = $state(false);

    const getExtension = (name: string): string => {
        const match = /\.([a-z0-9]+)$/i.exec(name);

        return match?.[1]?.toLowerCase() ?? '';
    };

    const getFileIconKind = (
        mimeType: string | null,
        extension: string,
    ): FileIconKind => {
        if (mimeType?.startsWith('image/')) {
            return 'image';
        }

        if (mimeType?.startsWith('video/')) {
            return 'video';
        }

        if (mimeType?.startsWith('audio/')) {
            return 'audio';
        }

        if (
            ['epub', 'mobi', 'azw', 'azw3', 'fb2'].includes(extension) ||
            mimeType === 'application/epub+zip'
        ) {
            return 'ebook';
        }

        if (
            [
                'zip',
                'rar',
                '7z',
                'tar',
                'gz',
                'bz2',
                'xz',
                'iso',
                'dmg',
            ].includes(extension) ||
            mimeType?.includes('zip') ||
            mimeType?.includes('archive') ||
            mimeType === 'application/x-rar-compressed'
        ) {
            return 'archive';
        }

        if (['json', 'geojson'].includes(extension)) {
            return 'json';
        }

        if (
            [
                'js',
                'ts',
                'svelte',
                'php',
                'py',
                'go',
                'rs',
                'java',
                'c',
                'cpp',
                'cs',
                'sh',
                'sql',
                'html',
                'css',
                'xml',
                'yaml',
                'yml',
            ].includes(extension)
        ) {
            return 'code';
        }

        if (
            ['csv', 'ods', 'xls', 'xlsx'].includes(extension) ||
            mimeType?.includes('spreadsheet') ||
            mimeType?.includes('excel')
        ) {
            return 'spreadsheet';
        }

        if (
            ['pdf', 'doc', 'docx', 'odt', 'rtf'].includes(extension) ||
            mimeType === 'application/pdf' ||
            mimeType?.includes('wordprocessingml')
        ) {
            return 'document';
        }

        if (
            ['log', 'md', 'nfo', 'srt', 'txt'].includes(extension) ||
            mimeType?.startsWith('text/')
        ) {
            return 'text';
        }

        return 'unknown';
    };

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

    const iconKind = $derived(
        getFileIconKind(file.mime_type, getExtension(file.name)),
    );
    const iconColor = $derived(
        {
            archive: 'text-orange-400',
            audio: 'text-violet-400',
            code: 'text-sky-400',
            document: 'text-red-400',
            ebook: 'text-amber-400',
            image: 'text-emerald-400',
            json: 'text-lime-400',
            spreadsheet: 'text-green-400',
            text: 'text-blue-400',
            unknown: 'text-zinc-400',
            video: 'text-rose-400',
        }[iconKind],
    );

    const deleteForm = $derived(destroy.form(file.id));
</script>

<div
    class="grid min-h-20 grid-cols-[3.5rem_minmax(0,1fr)_7rem] items-center gap-4 border-b border-zinc-100 bg-white px-4 text-zinc-900 transition hover:bg-zinc-50/80 sm:grid-cols-[3.5rem_minmax(0,1fr)_8rem_9rem_12rem] dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-50 dark:hover:bg-zinc-900/70"
>
    <span
        class={`flex size-10 shrink-0 items-center justify-center ${iconColor}`}
    >
        {#if iconKind === 'archive'}
            <FileArchive class="size-6 stroke-[1.7]" />
        {:else if iconKind === 'audio'}
            <FileAudio class="size-6 stroke-[1.7]" />
        {:else if iconKind === 'code'}
            <FileCode2 class="size-6 stroke-[1.7]" />
        {:else if iconKind === 'document'}
            <FileText class="size-6 stroke-[1.7]" />
        {:else if iconKind === 'ebook'}
            <BookOpenText class="size-6 stroke-[1.7]" />
        {:else if iconKind === 'image'}
            <FileImage class="size-6 stroke-[1.7]" />
        {:else if iconKind === 'json'}
            <FileJson class="size-6 stroke-[1.7]" />
        {:else if iconKind === 'spreadsheet'}
            <FileSpreadsheet class="size-6 stroke-[1.7]" />
        {:else if iconKind === 'text'}
            <FileType class="size-6 stroke-[1.7]" />
        {:else if iconKind === 'video'}
            <FileVideo class="size-6 stroke-[1.7]" />
        {:else}
            <FileQuestion class="size-6 stroke-[1.7]" />
        {/if}
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

        <button
            type="button"
            onclick={() => (deleteDialogOpen = true)}
            class="flex size-9 items-center justify-center rounded-full bg-zinc-100 text-zinc-500 transition hover:bg-rose-50 hover:text-rose-500 dark:bg-zinc-900 dark:hover:bg-rose-950"
            title="Delete"
        >
            <X class="size-4" />
        </button>
    </div>

    <div class="hidden text-base tabular-nums sm:block">{size}</div>

    <div class="hidden text-base text-zinc-700 sm:block dark:text-zinc-300">
        {changed}
    </div>
</div>

<ConfirmDeleteDialog
    bind:open={deleteDialogOpen}
    title="Delete this file?"
    description={`This will permanently delete ${file.name} from your library.`}
    confirmLabel="Delete file"
    form={deleteForm}
/>
