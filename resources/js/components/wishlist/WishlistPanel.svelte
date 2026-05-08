<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Bookmark from 'lucide-svelte/icons/bookmark';
    import BookmarkPlus from 'lucide-svelte/icons/bookmark-plus';
    import Link2 from 'lucide-svelte/icons/link-2';
    import Play from 'lucide-svelte/icons/play';
    import X from 'lucide-svelte/icons/x';
    import { destroy } from '@/actions/App/Http/Controllers/WishlistItemController';
    import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.svelte';
    import {
        DropdownMenu,
        DropdownMenuContent,
        DropdownMenuTrigger,
    } from '@/components/ui/dropdown-menu';
    import { store as storeTorrent } from '@/routes/torrents';
    import { store as storeWishlist } from '@/routes/wishlist';

    type WishlistItem = {
        id: number;
        url: string;
        source_type: 'magnet' | 'media' | string;
        source_domain: string | null;
        title: string | null;
        created_at?: string | null;
    };

    let {
        items,
        disabled = false,
        currentUrl = '',
        processing = false,
    }: {
        items: WishlistItem[];
        disabled?: boolean;
        currentUrl?: string;
        processing?: boolean;
    } = $props();

    let open = $state(false);
    let deletingItem = $state<WishlistItem | null>(null);
    let deleteDialogOpen = $state(false);
    let startingItemId = $state<number | null>(null);
    let savingCurrentUrl = $state(false);

    const deleteForm = $derived(
        deletingItem ? destroy.form(deletingItem.id) : null,
    );
    const trimmedCurrentUrl = $derived(currentUrl.trim());
    const canSaveCurrentUrl = $derived(
        trimmedCurrentUrl.length > 0 && !processing && !savingCurrentUrl,
    );

    const itemTitle = (item: WishlistItem): string => {
        if (item.title) {
            return item.title;
        }

        if (item.source_domain) {
            return item.source_domain;
        }

        return item.source_type === 'magnet' ? 'Magnet link' : 'Saved media';
    };

    const sourceLabel = (item: WishlistItem): string => {
        if (item.source_type === 'magnet') {
            return 'Magnet';
        }

        return item.source_domain ?? 'Media URL';
    };

    const savedAt = (item: WishlistItem): string => {
        if (!item.created_at) {
            return 'Saved';
        }

        return new Intl.DateTimeFormat(undefined, {
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        }).format(new Date(item.created_at));
    };

    const startItem = (item: WishlistItem): void => {
        startingItemId = item.id;

        router.post(
            storeTorrent.url(),
            { url: item.url },
            {
                preserveScroll: true,
                onFinish: () => (startingItemId = null),
            },
        );
    };

    const saveCurrentUrl = (): void => {
        if (!canSaveCurrentUrl) {
            return;
        }

        savingCurrentUrl = true;

        router.post(
            storeWishlist.url(),
            { url: trimmedCurrentUrl },
            {
                preserveScroll: true,
                onFinish: () => (savingCurrentUrl = false),
            },
        );
    };

    const requestDelete = (item: WishlistItem): void => {
        deletingItem = item;
        deleteDialogOpen = true;
    };

    $effect(() => {
        if (!deleteDialogOpen) {
            deletingItem = null;
        }
    });
</script>

<DropdownMenu bind:open>
    <DropdownMenuTrigger asChild>
        {#snippet children(trigger)}
            <button
                type="button"
                {...trigger}
                class="downloora-icon-button relative min-h-14 w-14 shrink-0 bg-[var(--downloora-paper)] text-[var(--downloora-ink)] data-[state=open]:bg-[var(--downloora-lime)]"
                title="Open wishlist"
                aria-label="Open wishlist"
            >
                <BookmarkPlus class="size-6 stroke-[2.4]" />
                {#if items.length}
                    <span
                        class="absolute -right-1 -top-1 flex size-5 items-center justify-center rounded-full border-2 border-foreground bg-[var(--downloora-orange)] text-[0.65rem] font-black leading-none text-[var(--downloora-ink)]"
                    >
                        {items.length}
                    </span>
                {/if}
            </button>
        {/snippet}
    </DropdownMenuTrigger>

    <DropdownMenuContent
        align="end"
        sideOffset={12}
        class="w-[min(24rem,calc(100vw-2rem))] rounded-[1.75rem] bg-[var(--downloora-paper)] p-4"
    >
        <div class="mb-4 flex items-start justify-between gap-3">
            <div class="flex min-w-0 items-center gap-3">
                <span
                    class="flex size-10 shrink-0 items-center justify-center rounded-full border-2 border-foreground bg-[var(--downloora-lime)] text-[var(--downloora-ink)] shadow-[2px_2px_0_0_var(--foreground)]"
                >
                    <Bookmark class="size-5 fill-current stroke-[2.2]" />
                </span>
                <div class="min-w-0">
                    <h2 class="truncate text-base font-black">Wishlist</h2>
                    <p class="text-xs font-semibold text-muted-foreground">
                        Save links now and start them later.
                    </p>
                </div>
            </div>
            <span class="text-sm font-black tabular-nums">
                {items.length}
            </span>
        </div>

        <button
            type="button"
            disabled={!canSaveCurrentUrl}
            onclick={saveCurrentUrl}
            class="mb-3 flex w-full items-center justify-center gap-2 rounded-full border-2 border-foreground bg-[var(--downloora-lime)] px-4 py-2 text-sm font-black text-[var(--downloora-ink)] shadow-[3px_3px_0_0_var(--foreground)] transition hover:bg-[var(--downloora-orange)] disabled:cursor-not-allowed disabled:opacity-50"
        >
            <BookmarkPlus class="size-4 stroke-[2.4]" />
            {savingCurrentUrl ? 'Saving...' : 'Save current link'}
        </button>

        {#if items.length}
            <div class="max-h-80 space-y-3 overflow-y-auto pr-1">
                {#each items as item (item.id)}
                    <article
                        class="rounded-[1.25rem] border-2 border-foreground bg-card p-3 shadow-[3px_3px_0_0_var(--foreground)]"
                    >
                        <div class="flex min-w-0 gap-3">
                            <span
                                class="flex size-10 shrink-0 items-center justify-center rounded-full border-2 border-foreground bg-[var(--downloora-paper)] text-[var(--downloora-green)]"
                            >
                                <Link2 class="size-5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <h3 class="truncate text-sm font-black">
                                    {itemTitle(item)}
                                </h3>
                                <p
                                    class="truncate text-xs font-semibold text-muted-foreground"
                                >
                                    {sourceLabel(item)} · {savedAt(item)}
                                </p>
                                <p
                                    class="mt-1 truncate text-xs font-medium text-muted-foreground"
                                >
                                    {item.url}
                                </p>
                            </div>
                        </div>

                        <div class="mt-3 flex items-center justify-end gap-2">
                            <button
                                type="button"
                                disabled={disabled ||
                                    startingItemId === item.id}
                                onclick={() => startItem(item)}
                                class="downloora-icon-button size-10 bg-[var(--downloora-lime)] text-[var(--downloora-ink)] disabled:cursor-not-allowed disabled:opacity-50"
                                title="Start download"
                            >
                                <Play class="size-4 fill-current" />
                            </button>
                            <button
                                type="button"
                                onclick={() => requestDelete(item)}
                                class="downloora-icon-button downloora-danger size-10"
                                title="Remove from wishlist"
                            >
                                <X class="size-4" />
                            </button>
                        </div>
                    </article>
                {/each}
            </div>
        {:else}
            <div
                class="rounded-[1.25rem] border-2 border-dashed border-foreground/30 px-4 py-8 text-center text-sm font-semibold text-muted-foreground"
            >
                No saved links yet.
            </div>
        {/if}
    </DropdownMenuContent>
</DropdownMenu>

{#if deleteForm && deletingItem}
    <ConfirmDeleteDialog
        bind:open={deleteDialogOpen}
        title="Remove this wishlist item?"
        description={`This removes ${itemTitle(deletingItem)} from your wishlist. It will not delete any downloaded files.`}
        confirmLabel="Remove"
        form={deleteForm}
    />
{/if}
