<script lang="ts">
    import type { Snippet } from 'svelte';
    import { getContext } from 'svelte';
    import { cn } from '@/lib/utils';
    import { DROPDOWN_MENU_CONTEXT, type DropdownMenuContext } from './context';

    type AsChildProps = {
        class?: string;
        onClick?: () => void;
        [key: string]: any;
    };

    let {
        asChild = false,
        class: className = '',
        children,
    }: {
        asChild?: boolean;
        class?: string;
        children?: Snippet<[AsChildProps]>;
    } = $props();

    const { setOpen } = getContext<DropdownMenuContext>(DROPDOWN_MENU_CONTEXT);

    const handleClick = () => setOpen(false);

    const classes = () =>
        cn(
            'flex w-full cursor-pointer select-none items-center rounded-xl px-3 py-2 text-sm font-medium outline-none hover:bg-[var(--seedr-lime)] hover:text-[var(--seedr-ink)]',
            className,
        );
</script>

{#if asChild}
    {@render children?.({ class: classes(), onClick: handleClick })}
{:else}
    <button type="button" class={classes()} onclick={handleClick}>
        {@render children?.({})}
    </button>
{/if}
