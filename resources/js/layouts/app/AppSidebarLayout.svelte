<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import Files from 'lucide-svelte/icons/files';
    import Gauge from 'lucide-svelte/icons/gauge';
    import Settings from 'lucide-svelte/icons/settings';
    import Shield from 'lucide-svelte/icons/shield';
    import type { Snippet } from 'svelte';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';
    import {
        DropdownMenu,
        DropdownMenuContent,
        DropdownMenuTrigger,
    } from '@/components/ui/dropdown-menu';
    import { Toaster } from '@/components/ui/sonner';
    import UserMenuContent from '@/components/UserMenuContent.svelte';
    import { currentUrlState } from '@/lib/currentUrl.svelte';
    import { cn, toUrl } from '@/lib/utils';
    import { index as adminTorrents } from '@/routes/admin/torrents';
    import { index as adminUsers } from '@/routes/admin/users';
    import { dashboard } from '@/routes';
    import { index as library } from '@/routes/library';
    import { edit as editProfile } from '@/routes/profile';
    import type { BreadcrumbItem, NavItem } from '@/types';

    let {
        children,
    }: {
        breadcrumbs?: BreadcrumbItem[];
        children?: Snippet;
    } = $props();

    const user = $derived(page.props.auth.user);
    const url = currentUrlState();

    const navItems = $derived.by((): NavItem[] => [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: Gauge,
        },
        {
            title: 'Library',
            href: library(),
            icon: Files,
        },
        ...(user.is_admin
            ? [
                  {
                      title: 'Users',
                      href: adminUsers(),
                      icon: Shield,
                  },
                  {
                      title: 'Torrents',
                      href: adminTorrents(),
                      icon: Shield,
                  },
              ]
            : []),
        {
            title: 'Settings',
            href: editProfile(),
            icon: Settings,
        },
    ]);

    const initials = $derived(
        user.name
            .split(' ')
            .map((part) => part[0])
            .join('')
            .slice(0, 2)
            .toUpperCase(),
    );
</script>

<div class="downloora-page relative overflow-x-hidden">
    <div
        class="downloora-dots pointer-events-none fixed inset-0 opacity-[0.045]"
    ></div>

    <header class="sticky top-0 z-30 px-3 pt-3 sm:px-6">
        <div
            class="downloora-pill relative mx-auto flex min-h-16 w-full max-w-7xl items-center gap-4 px-3 py-2 sm:px-4"
        >
            <Link
                href={toUrl(dashboard())}
                class="flex min-w-0 items-center gap-3 rounded-full bg-foreground px-4 py-2 text-background transition hover:bg-[var(--downloora-orange)] hover:text-[var(--downloora-ink)]"
            >
                <span
                    class="flex size-9 shrink-0 items-center justify-center"
                >
                    <AppLogoIcon class="size-6" />
                </span>
                <span class="hidden min-w-0 sm:block">
                    <span
                        class="block truncate text-sm font-semibold uppercase tracking-[0.16em]"
                        >Downloora</span
                    >
                    <span class="block truncate text-xs opacity-70"
                        >Torrent cloud storage</span
                    >
                </span>
            </Link>

            <div class="ml-auto">
                <nav
                    class="mr-3 flex items-center justify-center gap-1 sm:flex"
                >
                    {#each navItems as item (toUrl(item.href))}
                        <Link
                            href={toUrl(item.href)}
                            title={item.title}
                            class={cn(
                                'group relative flex size-10 items-center justify-center rounded-full text-muted-foreground transition hover:bg-muted hover:text-foreground',
                                url.isCurrentUrl(item.href, url.currentUrl) &&
                                    'bg-[var(--downloora-lime)] text-[var(--downloora-ink)]',
                            )}
                        >
                            {#if item.icon}
                                <item.icon class="size-5" />
                            {/if}
                        </Link>
                    {/each}
                </nav>
            </div>

            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    {#snippet children(props)}
                        <button
                            type="button"
                            class="downloora-icon-button size-11 bg-[var(--downloora-lime)] text-sm font-bold text-[var(--downloora-ink)]"
                            onclick={props.onclick}
                            aria-expanded={props['aria-expanded']}
                            data-state={props['data-state']}
                        >
                            {initials}
                        </button>
                    {/snippet}
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-64">
                    <UserMenuContent {user} />
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </header>

    <main class="relative z-10 mx-auto w-full max-w-7xl px-4 py-8 sm:px-6">
        {@render children?.()}
    </main>

    <Toaster />
</div>
