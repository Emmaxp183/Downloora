<script lang="ts">
    let {
        title,
        description = '',
        variant = 'default',
    }: {
        title: string;
        description?: string;
        variant?: 'default' | 'small';
    } = $props();
</script>

<header class={variant === 'small' ? '' : 'mb-8 space-y-0.5'}>
    <h2
        class={variant === 'small'
            ? 'mb-0.5 text-lg font-black tracking-tight'
            : 'text-3xl font-black tracking-tight'}
    >
        {title}
    </h2>
    {#if description}
        <p class="text-sm font-medium text-muted-foreground">{description}</p>
    {/if}
</header>
