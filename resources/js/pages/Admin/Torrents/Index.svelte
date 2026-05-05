<script module lang="ts">
    import { index as adminTorrents } from '@/routes/admin/torrents';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Admin torrents',
                href: adminTorrents(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { destroy } from '@/actions/App/Http/Controllers/Admin/TorrentController';

    type Torrent = {
        id: number;
        name: string | null;
        status: string;
        progress: number;
        total_size_bytes: number | null;
        error_message: string | null;
        user: {
            id: number;
            name: string;
            email: string;
        };
    };

    let { torrents }: { torrents: Torrent[] } = $props();
</script>

<AppHead title="Admin torrents" />

<div class="flex h-full flex-1 flex-col gap-5 overflow-x-auto">
    <div class="downloora-card bg-[var(--downloora-paper)] p-5">
        <h1 class="text-3xl font-black tracking-tight">Torrents</h1>
        <p class="text-sm font-medium text-muted-foreground">
            Review active, failed, and completed torrent jobs.
        </p>
    </div>

    <div class="space-y-4">
        {#each torrents as torrent (torrent.id)}
            <div
                class="downloora-row grid gap-4 p-4 lg:grid-cols-[minmax(0,1fr)_10rem]"
            >
                <div class="min-w-0">
                    <p class="truncate text-base font-bold">
                        {torrent.name ?? 'Inspecting torrent'}
                    </p>
                    <p class="text-xs font-medium text-muted-foreground">
                        {torrent.user.name} · {torrent.status.replaceAll(
                            '_',
                            ' ',
                        )} · {torrent.progress}%
                    </p>
                    {#if torrent.error_message}
                        <p class="mt-1 text-xs font-semibold text-destructive">
                            {torrent.error_message}
                        </p>
                    {/if}
                </div>

                <Form {...destroy.form(torrent.id)}>
                    {#snippet children({ processing })}
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                    {/snippet}
                </Form>
            </div>
        {:else}
            <div
                class="downloora-card px-4 py-10 text-center text-sm text-muted-foreground"
            >
                No torrents found.
            </div>
        {/each}
    </div>
</div>
