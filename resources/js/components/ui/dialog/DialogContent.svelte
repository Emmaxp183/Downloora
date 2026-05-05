<script lang="ts">
    import type { Snippet } from 'svelte';
    import { getContext } from 'svelte';
    import { cn } from '@/lib/utils';
    import { DIALOG_CONTEXT, type DialogContext } from './context';

    let { class: className = '', children }: { class?: string; children?: Snippet } = $props();

    const { open, setOpen } = getContext<DialogContext>(DIALOG_CONTEXT);

    const close = () => setOpen(false);
</script>

{#if open()}
    <div class="fixed inset-0 z-50 flex items-center justify-center">
        <button
            type="button"
            class="fixed inset-0 bg-[var(--downloora-ink)]/55"
            aria-label="Close"
            onclick={close}
        ></button>
        <div
            class={cn(
                'relative z-10 w-[calc(100%-2rem)] max-w-lg rounded-[1.5rem] border-2 border-foreground bg-card p-6 text-card-foreground shadow-[8px_8px_0_0_var(--foreground)]',
                className,
            )}
            role="dialog"
            aria-modal="true"
        >
            {@render children?.()}
        </div>
    </div>
{/if}
