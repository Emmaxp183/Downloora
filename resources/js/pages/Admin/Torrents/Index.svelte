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

<div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
    <div>
        <h1 class="text-2xl font-semibold tracking-normal">Torrents</h1>
        <p class="text-sm text-muted-foreground">Review active, failed, and completed torrent jobs.</p>
    </div>

    <div class="overflow-hidden rounded-lg border">
        {#each torrents as torrent (torrent.id)}
            <div class="grid gap-3 border-b p-4 lg:grid-cols-[minmax(0,1fr)_10rem]">
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium">
                        {torrent.name ?? 'Inspecting torrent'}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {torrent.user.name} · {torrent.status.replaceAll('_', ' ')} · {torrent.progress}%
                    </p>
                    {#if torrent.error_message}
                        <p class="mt-1 text-xs text-destructive">{torrent.error_message}</p>
                    {/if}
                </div>

                <Form {...destroy.form(torrent.id)}>
                    {#snippet children({ processing })}
                        <Button type="submit" variant="outline" disabled={processing}>
                            Cancel
                        </Button>
                    {/snippet}
                </Form>
            </div>
        {:else}
            <div class="px-4 py-10 text-center text-sm text-muted-foreground">
                No torrents found.
            </div>
        {/each}
    </div>
</div>
