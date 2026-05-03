<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import Link2 from 'lucide-svelte/icons/link-2';
    import Plus from 'lucide-svelte/icons/plus';
    import Upload from 'lucide-svelte/icons/upload';
    import { store } from '@/actions/App/Http/Controllers/TorrentController';

    let {
        disabled = false,
    }: {
        disabled?: boolean;
    } = $props();

    let fileInput: HTMLInputElement;

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
</script>

<Form {...store.form()} resetOnSuccess class="w-full">
    {#snippet children({ errors, processing, progress, submit })}
        <div class="flex w-full items-stretch gap-3">
            <div
                class="group flex min-h-14 min-w-0 flex-1 overflow-hidden border border-zinc-100 bg-zinc-50 shadow-[0_16px_34px_rgba(24,24,27,0.04)] transition focus-within:border-indigo-200 focus-within:bg-white dark:border-zinc-800 dark:bg-zinc-900 dark:focus-within:border-indigo-500/40"
            >
                <div
                    class="flex w-14 shrink-0 items-center justify-center text-zinc-400"
                >
                    <Link2 class="size-5" />
                </div>
                <input
                    id="magnet_uri"
                    name="magnet_uri"
                    placeholder="Paste magnet link URL here"
                    disabled={disabled || processing}
                    class="min-w-0 flex-1 bg-transparent text-base outline-none placeholder:text-zinc-400 disabled:cursor-not-allowed disabled:opacity-60"
                    autocomplete="off"
                />
                <button
                    type="submit"
                    disabled={disabled || processing}
                    class="flex w-16 shrink-0 items-center justify-center bg-zinc-100 text-zinc-950 transition hover:bg-zinc-200 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-zinc-800 dark:text-white dark:hover:bg-zinc-700"
                    title="Add magnet"
                >
                    <Plus class="size-7 stroke-[3]" />
                </button>
            </div>

            <input
                bind:this={fileInput}
                id="torrent_file"
                name="torrent_file"
                type="file"
                accept=".torrent,application/x-bittorrent"
                disabled={disabled || processing}
                class="hidden"
                onchange={(event) => submitTorrentFile(event, submit)}
            />
            <button
                type="button"
                disabled={disabled || processing}
                onclick={chooseTorrentFile}
                class="relative flex min-h-14 w-14 shrink-0 items-center justify-center text-zinc-950 transition hover:text-indigo-500 disabled:cursor-not-allowed disabled:opacity-50 dark:text-white dark:hover:text-indigo-300"
                title="Upload torrent file"
            >
                <Upload class="size-7 stroke-[3]" />
                <span
                    class="absolute inset-x-2 bottom-0 h-1 rounded-full bg-indigo-400"
                    class:opacity-0={!processing}
                ></span>
            </button>
        </div>

        {#if errors.magnet_uri}
            <p class="mt-2 text-sm text-red-500">{errors.magnet_uri}</p>
        {:else if errors.torrent_file}
            <p class="mt-2 text-sm text-red-500">{errors.torrent_file}</p>
        {:else if progress}
            <p class="mt-2 text-sm text-zinc-500">
                Uploading torrent file {progress.percentage}%.
            </p>
        {:else if disabled}
            <p class="mt-2 text-sm text-zinc-500">
                Finish or cancel your active torrent before adding another.
            </p>
        {/if}
    {/snippet}
</Form>
