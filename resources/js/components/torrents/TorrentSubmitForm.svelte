<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import { Link } from 'lucide-svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { store } from '@/actions/App/Http/Controllers/TorrentController';

    let {
        disabled = false,
    }: {
        disabled?: boolean;
    } = $props();
</script>

<Form {...store.form()} resetOnSuccess class="rounded-lg border p-4">
    {#snippet children({ errors, processing })}
        <label for="magnet_uri" class="text-sm font-medium">Magnet link</label>
        <div class="mt-2 flex gap-2">
            <Input
                id="magnet_uri"
                name="magnet_uri"
                placeholder="magnet:?xt=urn:btih:..."
                disabled={disabled || processing}
            />
            <Button type="submit" disabled={disabled || processing}>
                <Link class="size-4" />
                Add
            </Button>
        </div>
        {#if errors.magnet_uri}
            <p class="mt-2 text-sm text-destructive">{errors.magnet_uri}</p>
        {/if}
        {#if disabled}
            <p class="mt-2 text-xs text-muted-foreground">
                Finish or cancel your active torrent before adding another.
            </p>
        {/if}
    {/snippet}
</Form>
