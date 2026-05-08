<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import Link2 from 'lucide-svelte/icons/link-2';
    import Plus from 'lucide-svelte/icons/plus';
    import Upload from 'lucide-svelte/icons/upload';
    import { tick } from 'svelte';
    import { store } from '@/actions/App/Http/Controllers/TorrentController';
    import WishlistPanel from '@/components/wishlist/WishlistPanel.svelte';

    type WishlistItem = {
        id: number;
        url: string;
        source_type: string;
        source_domain: string | null;
        title: string | null;
        created_at?: string | null;
    };

    let {
        activeDownload = false,
        initialUrl = '',
        wishlistItems = [],
    }: {
        activeDownload?: boolean;
        initialUrl?: string | null;
        wishlistItems?: WishlistItem[];
    } = $props();

    let fileInput: HTMLInputElement;
    let urlValue = $state('');
    let clipboardError = $state<string | null>(null);

    const chooseTorrentFile = (): void => {
        fileInput?.click();
    };

    const submitTorrentFile = (event: Event, submit: () => void): void => {
        const input = event.currentTarget as HTMLInputElement;

        if (!input.files?.length) {
            return;
        }

        submit();
    };

    const isDownloadUrl = (value: string): boolean => {
        if (value.startsWith('magnet:?')) {
            return true;
        }

        try {
            const url = new URL(value);

            return ['http:', 'https:'].includes(url.protocol);
        } catch {
            return false;
        }
    };

    const pasteClipboardAndSubmit = async (
        submit: () => void,
        processing: boolean,
    ): Promise<void> => {
        if (processing || !navigator.clipboard?.readText) {
            return;
        }

        try {
            const clipboardValue = (
                await navigator.clipboard.readText()
            ).trim();

            if (
                !clipboardValue ||
                !isDownloadUrl(clipboardValue) ||
                urlValue.trim() === clipboardValue
            ) {
                return;
            }

            clipboardError = null;
            urlValue = clipboardValue;

            await tick();
            submit();
        } catch {
            clipboardError = 'Allow clipboard access to auto-paste links.';
        }
    };

    $effect(() => {
        if (initialUrl && urlValue === '') {
            urlValue = initialUrl;
        }
    });
</script>

<Form {...store.form()} resetOnSuccess class="w-full">
    {#snippet children({ errors, processing, progress, submit })}
        <div class="flex w-full items-stretch gap-3">
            <div
                class="group flex min-h-14 min-w-0 flex-1 overflow-hidden rounded-full border-2 border-foreground bg-card shadow-[3px_3px_0_0_var(--foreground)] transition focus-within:bg-[var(--downloora-paper)]"
            >
                <div
                    class="flex w-14 shrink-0 items-center justify-center text-muted-foreground"
                >
                    <Link2 class="size-5" />
                </div>
                <input
                    id="url"
                    name="url"
                    bind:value={urlValue}
                    placeholder="Paste magnet link or media URL here"
                    disabled={processing}
                    class="min-w-0 flex-1 bg-transparent text-base font-medium outline-none placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-60"
                    autocomplete="off"
                    onclick={() => pasteClipboardAndSubmit(submit, processing)}
                />
                <button
                    type="submit"
                    disabled={processing}
                    class="flex w-16 shrink-0 items-center justify-center border-l-2 border-foreground bg-[var(--downloora-orange)] text-[var(--downloora-ink)] transition hover:bg-[var(--downloora-lime)] disabled:cursor-not-allowed disabled:opacity-50"
                    title={activeDownload
                        ? 'Save link to wishlist'
                        : 'Add link'}
                >
                    <Plus class="size-7 stroke-[3]" />
                </button>
            </div>

            <WishlistPanel
                items={wishlistItems}
                disabled={activeDownload}
                currentUrl={urlValue}
                {processing}
            />

            <input
                bind:this={fileInput}
                id="torrent_file"
                name="torrent_file"
                type="file"
                accept=".torrent,application/x-bittorrent"
                disabled={activeDownload || processing}
                class="hidden"
                onchange={(event) => submitTorrentFile(event, submit)}
            />
            <button
                type="button"
                disabled={activeDownload || processing}
                onclick={chooseTorrentFile}
                class="downloora-icon-button relative min-h-14 w-14 shrink-0 bg-[var(--downloora-lime)] text-[var(--downloora-ink)] disabled:cursor-not-allowed disabled:opacity-50"
                title="Upload torrent file"
            >
                <Upload class="size-7 stroke-[3]" />
                <span
                    class="absolute inset-x-2 bottom-1 h-1 rounded-full bg-[var(--downloora-green)]"
                    class:opacity-0={!processing}
                ></span>
            </button>
        </div>

        {#if errors.url}
            <p class="mt-2 text-sm font-semibold text-destructive">
                {errors.url}
            </p>
        {:else if errors.magnet_uri}
            <p class="mt-2 text-sm font-semibold text-destructive">
                {errors.magnet_uri}
            </p>
        {:else if errors.torrent_file}
            <p class="mt-2 text-sm font-semibold text-destructive">
                {errors.torrent_file}
            </p>
        {:else if clipboardError}
            <p class="mt-2 text-sm font-semibold text-destructive">
                {clipboardError}
            </p>
        {:else if progress}
            <p class="mt-2 text-sm font-medium text-muted-foreground">
                Uploading torrent file {progress.percentage}%.
            </p>
        {:else if activeDownload}
            <p class="mt-2 text-sm font-medium text-muted-foreground">
                Active download running. New links will be saved to your
                wishlist.
            </p>
        {/if}
    {/snippet}
</Form>
