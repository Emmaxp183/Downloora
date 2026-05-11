<script lang="ts">
    import BookOpenText from 'lucide-svelte/icons/book-open-text';
    import Download from 'lucide-svelte/icons/download';
    import Eye from 'lucide-svelte/icons/eye';
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
    import { destroy } from '@/actions/App/Http/Controllers/StoredFileAccessController';
    import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.svelte';
    import FilePreviewDialog from '@/components/files/FilePreviewDialog.svelte';

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

    type PreviewKind = 'audio' | 'image' | 'pdf' | 'text' | 'video';

    let { file }: { file: StoredFile } = $props();

    let deleteDialogOpen = $state(false);
    let previewDialogOpen = $state(false);

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

    const getPreviewKind = (
        mimeType: string | null,
        extension: string,
    ): PreviewKind | null => {
        if (mimeType?.startsWith('video/')) {
            return 'video';
        }

        if (mimeType?.startsWith('audio/')) {
            return 'audio';
        }

        if (mimeType?.startsWith('image/')) {
            return 'image';
        }

        if (mimeType === 'application/pdf' || extension === 'pdf') {
            return 'pdf';
        }

        if (
            mimeType?.startsWith('text/') ||
            [
                'css',
                'csv',
                'html',
                'js',
                'json',
                'log',
                'md',
                'nfo',
                'php',
                'srt',
                'sql',
                'svg',
                'ts',
                'txt',
                'vtt',
                'xml',
                'yaml',
                'yml',
            ].includes(extension)
        ) {
            return 'text';
        }

        return null;
    };

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
    const previewKind = $derived(
        getPreviewKind(file.mime_type, getExtension(file.name)),
    );
    const canPreview = $derived(previewKind !== null);
    const iconColor = $derived(
        {
            archive: 'bg-[var(--downloora-orange)] text-[var(--downloora-ink)]',
            audio: 'bg-[var(--downloora-purple)] text-[var(--downloora-paper)]',
            code: 'bg-[var(--downloora-green)] text-[var(--downloora-paper)]',
            document:
                'bg-[var(--downloora-orange)] text-[var(--downloora-ink)]',
            ebook: 'bg-[var(--downloora-lime)] text-[var(--downloora-ink)]',
            image: 'bg-[var(--downloora-green)] text-[var(--downloora-paper)]',
            json: 'bg-[var(--downloora-lime)] text-[var(--downloora-ink)]',
            spreadsheet:
                'bg-[var(--downloora-green)] text-[var(--downloora-paper)]',
            text: 'bg-[var(--downloora-purple)] text-[var(--downloora-paper)]',
            unknown: 'bg-muted text-foreground',
            video: 'bg-[var(--downloora-orange)] text-[var(--downloora-ink)]',
        }[iconKind],
    );

    const deleteForm = $derived(destroy.form(file.id));
</script>

<div
    class="downloora-row grid min-h-20 grid-cols-[3.5rem_minmax(0,1fr)_7rem] items-center gap-4 px-4 sm:grid-cols-[3.5rem_minmax(0,1fr)_8rem_9rem_12rem]"
>
    <span
        class={`flex size-11 shrink-0 items-center justify-center rounded-full border-2 border-foreground shadow-[2px_2px_0_0_var(--foreground)] ${iconColor}`}
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

    {#if canPreview}
        <button
            type="button"
            onclick={() => (previewDialogOpen = true)}
            class="min-w-0 text-left"
            title="Preview"
        >
            <span class="block truncate text-base font-medium">
                {file.name}
            </span>
            <span
                class="block truncate text-xs font-medium text-muted-foreground"
            >
                {file.original_path}
            </span>
        </button>
    {:else}
        <div class="min-w-0">
            <p class="truncate text-base font-medium">{file.name}</p>
            <p class="truncate text-xs font-medium text-muted-foreground">
                {file.original_path}
            </p>
        </div>
    {/if}

    <div class="flex items-center justify-end gap-2 sm:justify-center">
        {#if canPreview}
            <button
                type="button"
                onclick={() => (previewDialogOpen = true)}
                class="downloora-icon-button"
                title="Preview"
            >
                {#if previewKind === 'video' || previewKind === 'audio'}
                    <Play class="size-4" />
                {:else}
                    <Eye class="size-4" />
                {/if}
            </button>
        {/if}

        <a
            href={file.download_url}
            class="downloora-icon-button"
            title="Download"
        >
            <Download class="size-4" />
        </a>

        <button
            type="button"
            onclick={() => (deleteDialogOpen = true)}
            class="downloora-icon-button downloora-danger"
            title="Delete"
        >
            <X class="size-4" />
        </button>
    </div>

    <div class="hidden text-base tabular-nums sm:block">{size}</div>

    <div class="hidden text-base text-muted-foreground sm:block">
        {changed}
    </div>
</div>

{#if previewKind}
    <FilePreviewDialog bind:open={previewDialogOpen} {file} {previewKind} />
{/if}

<ConfirmDeleteDialog
    bind:open={deleteDialogOpen}
    title="Delete this file?"
    description={`This will permanently delete ${file.name} from your library.`}
    confirmLabel="Delete file"
    form={deleteForm}
/>
