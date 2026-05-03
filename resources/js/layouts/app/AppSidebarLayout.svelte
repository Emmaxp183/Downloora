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

<div
    class="min-h-screen bg-white text-zinc-900 dark:bg-zinc-950 dark:text-zinc-50"
>
    <header
        class="sticky top-0 z-30 border-b border-dashed border-zinc-200/90 bg-white/95 backdrop-blur dark:border-zinc-800 dark:bg-zinc-950/92"
    >
        <div
            class="relative flex min-h-20 w-full items-center gap-4 px-4 py-3 sm:px-6"
        >
            <Link
                href={toUrl(dashboard())}
                class="flex min-w-0 items-center gap-3"
            >
                <span
                    class="flex size-12 shrink-0 items-center justify-center rounded-full bg-white text-zinc-950 shadow-[0_10px_34px_rgba(24,24,27,0.16)] ring-1 ring-zinc-100 dark:bg-zinc-900 dark:text-white dark:ring-zinc-800"
                >
                    <AppLogoIcon class="size-6" />
                </span>
                <span class="hidden min-w-0 sm:block">
                    <span
                        class="block truncate text-sm font-semibold uppercase tracking-[0.16em]"
                        >Seedr Drive</span
                    >
                    <span class="block truncate text-xs text-zinc-500"
                        >Torrent cloud storage</span
                    >
                </span>
            </Link>

            <div class="ml-auto">
                <nav
                    class="mr-3 flex items-center justify-center gap-1 sm:hidden"
                >
                    {#each navItems as item (toUrl(item.href))}
                        <Link
                            href={toUrl(item.href)}
                            title={item.title}
                            class={cn(
                                'group relative flex size-10 items-center justify-center text-zinc-500 transition hover:text-zinc-950 dark:text-zinc-400 dark:hover:text-white',
                                url.isCurrentUrl(item.href, url.currentUrl) &&
                                    'text-zinc-950 dark:text-white',
                            )}
                        >
                            {#if item.icon}
                                <item.icon class="size-5" />
                            {/if}
                            <span
                                class="absolute inset-x-2 -bottom-3 h-1 rounded-full bg-indigo-400 opacity-0 transition group-hover:opacity-40"
                                class:opacity-100={url.isCurrentUrl(
                                    item.href,
                                    url.currentUrl,
                                )}
                            ></span>
                        </Link>
                    {/each}
                </nav>
            </div>

            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    {#snippet children(props)}
                        <button
                            type="button"
                            class="flex size-10 items-center justify-center rounded-full bg-zinc-100 text-sm font-semibold text-zinc-700 ring-1 ring-zinc-200 transition hover:bg-zinc-200 dark:bg-zinc-900 dark:text-zinc-100 dark:ring-zinc-800"
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

    <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6">
        {@render children?.()}
    </main>

    <Toaster />
</div>
