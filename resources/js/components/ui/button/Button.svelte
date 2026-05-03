<script lang="ts">
    import type { Snippet } from 'svelte';
    import { cn } from '@/lib/utils';

    type Variant =
        | 'default'
        | 'secondary'
        | 'ghost'
        | 'destructive'
        | 'outline'
        | 'link';
    type Size = 'default' | 'sm' | 'lg' | 'icon';
    type AsChildProps = {
        class?: string;
        onClick?: (event: MouseEvent) => void;
        [key: string]: any;
    };

    const base =
        'inline-flex items-center justify-center gap-2 rounded-full border-2 border-foreground text-sm font-bold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50';

    const variants: Record<Variant, string> = {
        default:
            'bg-[var(--seedr-orange)] text-[var(--seedr-ink)] shadow-[3px_3px_0_0_var(--foreground)] hover:translate-x-0.5 hover:translate-y-0.5 hover:shadow-[1px_1px_0_0_var(--foreground)]',
        secondary:
            'bg-[var(--seedr-lime)] text-[var(--seedr-ink)] shadow-[3px_3px_0_0_var(--foreground)] hover:translate-x-0.5 hover:translate-y-0.5 hover:shadow-[1px_1px_0_0_var(--foreground)]',
        ghost: 'border-transparent hover:border-foreground hover:bg-muted',
        destructive:
            'border-[var(--seedr-danger)] bg-[var(--seedr-danger)] text-white shadow-[3px_3px_0_0_var(--foreground)] hover:translate-x-0.5 hover:translate-y-0.5 hover:shadow-[1px_1px_0_0_var(--foreground)]',
        outline:
            'bg-card text-foreground shadow-[3px_3px_0_0_var(--foreground)] hover:translate-x-0.5 hover:translate-y-0.5 hover:bg-muted hover:shadow-[1px_1px_0_0_var(--foreground)]',
        link: 'border-transparent text-primary underline-offset-4 hover:underline',
    };

    const sizes: Record<Size, string> = {
        default: 'min-h-10 px-5 py-2',
        sm: 'min-h-9 px-4 py-1.5 text-xs',
        lg: 'min-h-12 px-8 py-3 text-base',
        icon: 'size-10 p-0',
    };

    let {
        children,
        asChild = false,
        variant = 'default',
        size = 'default',
        class: className = '',
        type = 'button',
        ...rest
    }: {
        children?: Snippet<[AsChildProps]>;
        asChild?: boolean;
        variant?: Variant;
        size?: Size;
        class?: string;
        type?: 'button' | 'submit' | 'reset';
        [key: string]: unknown;
    } = $props();

    const classes = () => cn(base, variants[variant], sizes[size], className);
</script>

{#if asChild}
    {@render children?.({ class: classes(), ...rest })}
{:else}
    <button class={classes()} type={type} {...rest}>
        {@render children?.({})}
    </button>
{/if}
