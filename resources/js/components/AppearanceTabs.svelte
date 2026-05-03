<script lang="ts">
    import Monitor from 'lucide-svelte/icons/monitor';
    import Moon from 'lucide-svelte/icons/moon';
    import Sun from 'lucide-svelte/icons/sun';
    import type { Component, SvelteComponent } from 'svelte';
    import { themeState } from '@/lib/theme.svelte';
    import type { Appearance } from '@/types';

    const { appearance, updateAppearance } = themeState();

    type IconComponent =
        | Component<{ class?: string }>
        | (new (...args: any[]) => SvelteComponent<{ class?: string }>);

    const tabs: { value: Appearance; Icon: IconComponent; label: string }[] = [
        { value: 'light', Icon: Sun, label: 'Light' },
        { value: 'dark', Icon: Moon, label: 'Dark' },
        { value: 'system', Icon: Monitor, label: 'System' },
    ];

    function handleAppearanceChange(value: Appearance) {
        updateAppearance(value);
    }
</script>

<div
    class="inline-flex gap-2 rounded-full border-2 border-foreground bg-background p-1 shadow-[3px_3px_0_0_var(--foreground)]"
>
    {#each tabs as { value, Icon, label } (value)}
        <button
            onclick={() => handleAppearanceChange(value)}
            class="flex items-center rounded-full border-2 px-3.5 py-1.5 text-sm font-bold transition {appearance.value ===
            value
                ? 'border-foreground bg-[var(--seedr-lime)] text-[var(--seedr-ink)]'
                : 'border-transparent text-muted-foreground hover:border-foreground hover:bg-muted hover:text-foreground'}"
        >
            <Icon class="-ml-1 h-4 w-4" />
            <span class="ml-1.5">{label}</span>
        </button>
    {/each}
</div>
