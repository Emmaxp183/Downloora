<script lang="ts">
    import { Download, Play } from 'lucide-svelte';
    import { cn } from '@/lib/utils';

    type StoredFile = {
        id: number;
        name: string;
        original_path: string;
        mime_type: string | null;
        size_bytes: number;
        download_url: string;
        stream_url: string;
    };

    let { file }: { file: StoredFile } = $props();

    const canPlay = $derived(
        file.mime_type?.startsWith('video/') ||
            file.mime_type?.startsWith('audio/'),
    );

    const size = $derived(`${(file.size_bytes / 1024 / 1024).toFixed(2)} MB`);
</script>

<div class="flex items-center justify-between gap-4 border-b px-4 py-3">
    <div class="min-w-0">
        <p class="truncate text-sm font-medium">{file.name}</p>
        <p class="truncate text-xs text-muted-foreground">
            {file.original_path} · {size}
        </p>
    </div>

    <div class="flex shrink-0 items-center gap-2">
        {#if canPlay}
            <a
                href={file.stream_url}
                target="_blank"
                class={cn(
                    'inline-flex h-9 w-9 items-center justify-center rounded-md text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground',
                )}
            >
                <Play class="size-4" />
            </a>
        {/if}
        <a
            href={file.download_url}
            class={cn(
                'inline-flex h-9 w-9 items-center justify-center rounded-md border border-input bg-background text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground',
            )}
        >
            <Download class="size-4" />
        </a>
    </div>
</div>
