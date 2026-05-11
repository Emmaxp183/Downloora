<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import Download from 'lucide-svelte/icons/download';
    import FileAudio from 'lucide-svelte/icons/file-audio';
    import Film from 'lucide-svelte/icons/film';
    import Globe2 from 'lucide-svelte/icons/globe-2';
    import Image from 'lucide-svelte/icons/image';
    import X from 'lucide-svelte/icons/x';
    import {
        destroy,
        storeFormat,
    } from '@/actions/App/Http/Controllers/MediaImportController';
    import { Button } from '@/components/ui/button';
    import { cn } from '@/lib/utils';

    type MediaFormat = {
        id: string;
        selector: string;
        type: 'video' | 'audio' | 'file';
        extension: string | null;
        quality: string;
        duration_seconds: number | null;
        size_bytes: number | null;
        source: string | null;
    };

    type MediaImport = {
        id: number;
        title: string | null;
        source_url: string;
        source_domain: string | null;
        thumbnail_url: string | null;
        status: string;
        progress: number;
        duration_seconds: number | null;
        estimated_size_bytes: number | null;
        downloaded_bytes: number;
        download_speed_bytes_per_second: number;
        formats: MediaFormat[];
        selected_format: MediaFormat | null;
        error_message: string | null;
    };

    let { mediaImport }: { mediaImport: MediaImport } = $props();

    const title = $derived(mediaImport.title ?? 'Inspecting media');
    const progress = $derived(Math.min(100, Math.max(0, mediaImport.progress)));
    const status = $derived(mediaImport.status.replaceAll('_', ' '));
    const ready = $derived(mediaImport.status === 'ready');
    const failed = $derived(
        [
            'inspection_failed',
            'quota_exceeded',
            'download_failed',
            'import_failed',
        ].includes(mediaImport.status),
    );

    const formatBytes = (bytes: number | null): string => {
        if (!bytes) {
            return 'Unknown';
        }

        if (bytes >= 1024 * 1024 * 1024) {
            return `${(bytes / 1024 / 1024 / 1024).toFixed(2)} GB`;
        }

        return `${(bytes / 1024 / 1024).toFixed(2)} MB`;
    };

    const formatRate = (bytesPerSecond: number): string => {
        if (bytesPerSecond >= 1024 * 1024 * 1024) {
            return `${(bytesPerSecond / 1024 / 1024 / 1024).toFixed(2)} GB/s`;
        }

        if (bytesPerSecond >= 1024 * 1024) {
            return `${(bytesPerSecond / 1024 / 1024).toFixed(2)} MB/s`;
        }

        if (bytesPerSecond >= 1024) {
            return `${(bytesPerSecond / 1024).toFixed(1)} KB/s`;
        }

        return `${Math.round(bytesPerSecond)} B/s`;
    };

    const formatDuration = (seconds: number | null): string => {
        if (!seconds) {
            return 'Unknown';
        }

        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = seconds % 60;

        return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
    };

    const iconFor = (format: MediaFormat) => {
        if (format.type === 'audio') {
            return FileAudio;
        }

        if (format.type === 'video') {
            return Film;
        }

        return Image;
    };

    const usefulVideoQualities = ['360p', '720p', '1080p'];

    const formatScore = (format: MediaFormat): number => {
        const extension = format.extension?.toLowerCase();

        return (
            (extension === 'mp4' ? 1000 : 0) +
            (extension === 'webm' ? 500 : 0) +
            (format.size_bytes ?? 0) / 1024 / 1024 / 1024
        );
    };

    const bestFormat = (formats: MediaFormat[]): MediaFormat | null => {
        return (
            formats
                .toSorted(
                    (first, second) => formatScore(second) - formatScore(first),
                )
                .at(0) ?? null
        );
    };

    const displayFormats = (formats: MediaFormat[]): MediaFormat[] => {
        const curated = usefulVideoQualities
            .map((quality) =>
                bestFormat(
                    formats.filter(
                        (format) =>
                            format.type === 'video' &&
                            format.quality === quality &&
                            format.extension?.toLowerCase() !== 'mhtml',
                    ),
                ),
            )
            .filter((format): format is MediaFormat => format !== null);

        const audio = bestFormat(
            formats.filter(
                (format) =>
                    format.type === 'audio' &&
                    format.extension?.toLowerCase() !== 'mhtml',
            ),
        );

        if (audio) {
            curated.push(audio);
        }

        if (curated.length > 0) {
            return curated;
        }

        return formats
            .filter((format) => format.extension?.toLowerCase() !== 'mhtml')
            .slice(0, 6);
    };
</script>

<div class="downloora-row p-4">
    <div class="flex flex-col gap-4">
        <div class="flex items-start gap-4">
            {#if mediaImport.thumbnail_url}
                <img
                    src={mediaImport.thumbnail_url}
                    alt=""
                    class="h-20 w-28 shrink-0 rounded-2xl border-2 border-foreground object-cover shadow-[2px_2px_0_0_var(--foreground)]"
                />
            {:else}
                <span
                    class="flex size-14 shrink-0 items-center justify-center rounded-full border-2 border-foreground bg-[var(--downloora-purple)] text-[var(--downloora-paper)] shadow-[2px_2px_0_0_var(--foreground)]"
                >
                    <Globe2 class="size-6" />
                </span>
            {/if}

            <div class="min-w-0 flex-1">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="truncate text-base font-bold">{title}</p>
                        <p
                            class="mt-1 truncate text-xs font-medium text-muted-foreground"
                        >
                            {mediaImport.source_domain ??
                                mediaImport.source_url}
                            · {status} · {formatRate(
                                mediaImport.download_speed_bytes_per_second,
                            )}
                        </p>
                    </div>

                    <Form {...destroy.form(mediaImport.id)}>
                        {#snippet children({ processing })}
                            <button
                                type="submit"
                                disabled={processing}
                                class="downloora-icon-button downloora-danger"
                                title="Cancel media import"
                            >
                                <X class="size-4" />
                            </button>
                        {/snippet}
                    </Form>
                </div>

                {#if !ready}
                    <div class="downloora-progress mt-3">
                        <div
                            class={cn(
                                'downloora-progress-fill',
                                failed && 'bg-[var(--downloora-danger)]',
                            )}
                            style={`width: ${progress}%`}
                        ></div>
                    </div>
                    <div class="mt-2 flex items-center justify-between gap-3">
                        <p
                            class="text-xs font-medium capitalize text-muted-foreground"
                        >
                            {mediaImport.error_message ?? status}
                        </p>
                        <span class="text-xs font-bold tabular-nums">
                            {progress}%
                        </span>
                    </div>
                {/if}
            </div>
        </div>

        {#if ready}
            <div class="space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p
                        class="text-sm font-black uppercase tracking-[0.12em] text-[var(--downloora-green)]"
                    >
                        Choose a version
                    </p>
                    <p class="text-xs font-medium text-muted-foreground">
                        Save only media you own or have rights to download.
                    </p>
                </div>

                <div class="grid gap-3">
                    {#each displayFormats(mediaImport.formats) as format (format.id)}
                        {@const Icon = iconFor(format)}
                        <Form {...storeFormat.form(mediaImport.id)}>
                            {#snippet children({ processing, errors })}
                                <input
                                    type="hidden"
                                    name="format_id"
                                    value={format.id}
                                />
                                <div
                                    class="grid gap-3 rounded-2xl border-2 border-foreground bg-background p-3 sm:grid-cols-[minmax(0,1fr)_7rem]"
                                >
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        class="flex min-w-0 items-center gap-3 text-left disabled:opacity-50"
                                    >
                                        <span
                                            class="flex size-10 shrink-0 items-center justify-center rounded-full border-2 border-foreground bg-[var(--downloora-lime)] text-[var(--downloora-ink)]"
                                        >
                                            <Icon class="size-5" />
                                        </span>
                                        <span class="min-w-0">
                                            <span
                                                class="block truncate text-sm font-bold"
                                            >
                                                {format.quality}
                                                {#if format.extension}
                                                    · {format.extension.toUpperCase()}
                                                {/if}
                                            </span>
                                            <span
                                                class="block truncate text-xs font-medium text-muted-foreground"
                                            >
                                                {format.type}
                                                · {formatDuration(
                                                    format.duration_seconds ??
                                                        mediaImport.duration_seconds,
                                                )}
                                                · {formatBytes(
                                                    format.size_bytes,
                                                )}
                                                {#if format.source}
                                                    · {format.source}
                                                {/if}
                                            </span>
                                        </span>
                                    </button>

                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        class="w-full sm:w-auto"
                                    >
                                        <Download class="size-4" />
                                        Save
                                    </Button>

                                    {#if errors.format_id}
                                        <p
                                            class="sm:col-span-2 text-xs font-semibold text-destructive"
                                        >
                                            {errors.format_id}
                                        </p>
                                    {/if}
                                </div>
                            {/snippet}
                        </Form>
                    {/each}
                </div>
            </div>
        {/if}
    </div>
</div>
