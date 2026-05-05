<script lang="ts">
    let {
        class: className = '',
        alt = '',
        ...rest
    }: {
        class?: string;
        alt?: string;
        [key: string]: unknown;
    } = $props();
</script>

<img
    src="/logo_icon.svg"
    {alt}
    class={`block object-contain ${className}`}
    draggable="false"
    {...rest}
/>
