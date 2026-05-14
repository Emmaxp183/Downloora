<script lang="ts">
    import Download from 'lucide-svelte/icons/download';
    import ExternalLink from 'lucide-svelte/icons/external-link';
    import X from 'lucide-svelte/icons/x';
    import StreamVideoPlayer from '@/components/media/StreamVideoPlayer.svelte';
    import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';

    type StoredFile = {
        id: number;
        name: string;
        original_path: string;
        mime_type: string | null;
        size_bytes: number;
        download_url: string;
        stream_url: string;
        cast_url: string | null;
        adaptive_stream_url: string | null;
        adaptive_stream_status: string | null;
        updated_at?: string | null;
    };

    type PreviewKind = 'audio' | 'image' | 'pdf' | 'text' | 'video';

    let {
        open = $bindable(false),
        file,
        previewKind,
    }: {
        open?: boolean;
        file: StoredFile;
        previewKind: PreviewKind;
    } = $props();

    const videoUrl = $derived(file.cast_url ?? file.stream_url);
</script>

<Dialog bind:open>
    <DialogContent
        class="flex max-h-[calc(100svh-1rem)] w-[calc(100%-0.75rem)] max-w-6xl flex-col gap-3 overflow-hidden rounded-xl p-2 sm:max-h-[calc(100vh-2rem)] sm:w-[calc(100%-2rem)] sm:gap-4 sm:rounded-[1.5rem] sm:p-5"
    >
        <div class="flex items-center justify-between gap-2 sm:gap-3">
            <div class="min-w-0">
                <DialogTitle>
                    <span class="block truncate text-sm sm:text-base">
                        {file.name}
                    </span>
                </DialogTitle>
                <p
                    class="mt-1 hidden truncate text-xs font-medium text-muted-foreground sm:block"
                >
                    {file.original_path}
                </p>
            </div>

            <div class="flex shrink-0 items-center gap-1 sm:gap-2">
                <a
                    href={file.stream_url}
                    target="_blank"
                    rel="noreferrer"
                    class="downloora-icon-button"
                    title="Open in new tab"
                >
                    <ExternalLink class="size-4" />
                </a>

                <a
                    href={file.download_url}
                    class="downloora-icon-button"
                    title="Download"
                >
                    <Download class="size-4" />
                </a>

                <button
                    type="button"
                    onclick={() => (open = false)}
                    class="downloora-icon-button downloora-danger"
                    title="Close"
                >
                    <X class="size-4" />
                </button>
            </div>
        </div>

        <div
            class="min-h-0 overflow-hidden rounded-lg border-2 border-foreground bg-[var(--downloora-ink)]"
        >
            {#if previewKind === 'video'}
                <StreamVideoPlayer
                    src={videoUrl}
                    adaptiveSrc={file.adaptive_stream_url}
                    title={file.name}
                />
            {:else if previewKind === 'audio'}
                <div class="flex min-h-56 items-center p-6">
                    <audio
                        src={file.stream_url}
                        controls
                        preload="metadata"
                        class="w-full"
                    ></audio>
                </div>
            {:else if previewKind === 'image'}
                <img
                    src={file.stream_url}
                    alt={file.name}
                    class="max-h-[75vh] w-full object-contain"
                />
            {:else if previewKind === 'pdf'}
                <iframe
                    src={file.stream_url}
                    title={file.name}
                    class="h-[75vh] w-full bg-white"
                ></iframe>
            {:else}
                <iframe
                    src={file.stream_url}
                    title={file.name}
                    class="h-[75vh] w-full bg-white"
                ></iframe>
            {/if}
        </div>
    </DialogContent>
</Dialog>
