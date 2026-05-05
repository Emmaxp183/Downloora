<script lang="ts">
    import { cn } from '@/lib/utils';

    let { class: className = '', type = 'text', ...rest } = $props();
</script>

<input
    type={type}
    class={cn(
        'flex min-h-11 w-full rounded-full border-2 border-foreground bg-card px-4 py-2 text-sm shadow-none transition file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--downloora-lime)] focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:opacity-50',
        className,
    )}
    {...rest}
/>
