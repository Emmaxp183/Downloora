<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import type { Snippet } from 'svelte';

    let {
        title = '',
        description = '',
        canonical = '',
        image = '',
        type = 'website',
        robots = '',
        children,
    }: {
        title?: string;
        description?: string;
        canonical?: string;
        image?: string;
        type?: string;
        robots?: string;
        children?: Snippet;
    } = $props();

    const appName = $derived(
        page.props.seo.appName || import.meta.env.VITE_APP_NAME || 'Downloora',
    );
    const baseUrl = $derived(page.props.seo.baseUrl.replace(/\/+$/, ''));
    const metaDescription = $derived(
        description || page.props.seo.defaultDescription,
    );
    const metaRobots = $derived(robots || page.props.seo.robots);
    const fullTitle = $derived(title ? `${title} - ${appName}` : appName);
    const canonicalUrl = $derived(toAbsoluteUrl(canonical || '/'));
    const imageUrl = $derived(
        toAbsoluteUrl(image || page.props.seo.defaultImage),
    );

    function toAbsoluteUrl(value: string): string {
        if (!value) {
            return baseUrl;
        }

        try {
            return new URL(value).toString();
        } catch {
            return `${baseUrl}/${value.replace(/^\/+/, '')}`;
        }
    }
</script>

<svelte:head>
    <title>{fullTitle}</title>
    <meta name="description" content={metaDescription} />
    <meta name="robots" content={metaRobots} />
    <link rel="canonical" href={canonicalUrl} />
    <meta property="og:site_name" content={appName} />
    <meta property="og:type" content={type} />
    <meta property="og:title" content={fullTitle} />
    <meta property="og:description" content={metaDescription} />
    <meta property="og:url" content={canonicalUrl} />
    <meta property="og:image" content={imageUrl} />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content={fullTitle} />
    <meta name="twitter:description" content={metaDescription} />
    <meta name="twitter:image" content={imageUrl} />
    {@render children?.()}
</svelte:head>
