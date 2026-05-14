<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import ArrowRight from 'lucide-svelte/icons/arrow-right';
    import Check from 'lucide-svelte/icons/check';
    import Crown from 'lucide-svelte/icons/crown';
    import Download from 'lucide-svelte/icons/download';
    import Flame from 'lucide-svelte/icons/flame';
    import Folder from 'lucide-svelte/icons/folder';
    import Leaf from 'lucide-svelte/icons/leaf';
    import Link2 from 'lucide-svelte/icons/link-2';
    import Play from 'lucide-svelte/icons/play';
    import Rocket from 'lucide-svelte/icons/rocket';
    import Shield from 'lucide-svelte/icons/shield';
    import Sparkles from 'lucide-svelte/icons/sparkles';
    import Star from 'lucide-svelte/icons/star';
    import Upload from 'lucide-svelte/icons/upload';
    import Users from 'lucide-svelte/icons/users';
    import AppHead from '@/components/AppHead.svelte';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';
    import { toUrl } from '@/lib/utils';
    import { dashboard, login, register } from '@/routes';
    import {
        cloudTorrentStorage,
        downloadSocialMediaVideos,
        oneClickTorrentSeeding,
        privateTorrentCloud,
        seedrAlternative,
        torrentToCloud,
    } from '@/routes/seo';

    let {
        canRegister = true,
    }: {
        canRegister: boolean;
    } = $props();

    const auth = $derived(page.props.auth);
    const seoDescription =
        'Downloora saves magnet links, torrent files, and media downloads to private cloud storage so you can stream, download, and manage files from any browser.';
    const primaryRoute = $derived(auth.user ? dashboard() : register());
    const primaryLabel = $derived(auth.user ? 'Open Dashboard' : 'Get Started');

    const articles = [
        {
            title: 'Add links three ways',
            date: 'MAY 4, 2026',
            copy: 'Paste a magnet, upload a torrent file, or share a link from your phone. Downloora keeps the add-to-cloud flow fast and predictable.',
            icon: Link2,
            color: 'bg-[#C9E265]',
        },
        {
            title: 'Folders, zip downloads',
            date: 'MAY 3, 2026',
            copy: 'Completed torrents land as tidy folders. Download the whole folder as a zip or open it to grab the exact file you need.',
            icon: Folder,
            color: 'bg-[#FF6B4A]',
        },
        {
            title: 'Private quota storage',
            date: 'MAY 2, 2026',
            copy: 'Every account gets private RustFS-backed storage with quotas, so users can register themselves without sharing one messy library.',
            icon: Shield,
            color: 'bg-[#9D8BB5]',
        },
    ];

    const features = [
        {
            eyebrow: 'Cross-Platform',
            title: 'Watch on any device, anytime.',
            copy: 'Videos, music, documents — enjoy everything right in your browser. Works with every file format. Cast to your TV in one tap. Nothing to install.',
            icon: Play,
            image: '/image1.jpg',
            color: 'bg-[#3D6B4F] text-[#F2EFE9]',
        },
        {
            eyebrow: 'Mobile Ready',
            title: 'Use it from any screen with no software install.',
            copy: 'Register, paste a magnet, upload a .torrent, monitor progress, and download files from a clean browser interface.',
            icon: Upload,
            image: '/image2.jpg',
            color: 'bg-[#C9E265] text-[#1A261D]',
        },
        {
            eyebrow: 'Multi-user',
            title: 'Quotas and private libraries are built in.',
            copy: 'Users see their own files, active download, quota meter, and file actions. Admin controls can grow from the same foundation.',
            icon: Users,
            image: '/Image3.jpg',
            color: 'bg-[#9D8BB5] text-[#F2EFE9]',
        },
    ];

    const plans = [
        {
            name: 'Basic',
            storage: '50 GB',
            price: '€5.99',
            color: 'bg-white',
            items: [
                'Faster transfers',
                'Private cloud files',
                'Casual weekend use',
            ],
        },
        {
            name: 'Pro',
            storage: '100 GB',
            price: '€10',
            color: 'bg-[#C9E265]',
            items: [
                'Priority transfers',
                'Larger libraries',
                'Most popular choice',
            ],
        },
        {
            name: 'Master',
            storage: '1 TB',
            price: '€20',
            color: 'bg-white',
            items: [
                'Power user storage',
                'Large folder archives',
                'Advanced library workflows',
            ],
        },
    ];

    const seoLinks = [
        {
            title: 'Cloud Torrent Storage',
            href: cloudTorrentStorage(),
        },
        {
            title: 'Torrent to Cloud',
            href: torrentToCloud(),
        },
        {
            title: 'Seedr Alternative',
            href: seedrAlternative(),
        },
        {
            title: 'Private Torrent Cloud',
            href: privateTorrentCloud(),
        },
        {
            title: 'Download Social Media Videos',
            href: downloadSocialMediaVideos(),
        },
        {
            title: 'One-Click Torrent Seeding',
            href: oneClickTorrentSeeding(),
        },
    ];
</script>

<AppHead
    title="Private Torrent Cloud Storage"
    description={seoDescription}
    canonical="/"
    image="/image1.jpg"
/>

<svelte:head>
    <style>
        @keyframes welcome-float {
            0%,
            100% {
                transform: translateY(0) rotate(0deg);
            }
            50% {
                transform: translateY(-14px) rotate(1deg);
            }
        }

        @keyframes welcome-marquee {
            from {
                transform: translateX(0);
            }
            to {
                transform: translateX(-50%);
            }
        }
    </style>
</svelte:head>

<div
    class="welcome-page min-h-screen overflow-x-hidden bg-[#F2EFE9] text-[#1A261D]"
>
    <div class="pointer-events-none fixed inset-0 z-0 overflow-hidden">
        <Sparkles
            class="absolute top-[10%] left-[5%] size-20 animate-[welcome-float_7s_ease-in-out_infinite] text-[#FF6B4A]/70"
        />
        <Leaf
            class="absolute right-[8%] bottom-[18%] size-24 rotate-12 animate-[welcome-float_8s_ease-in-out_infinite] text-[#C9E265]/80"
        />
        <div
            class="absolute inset-0 opacity-[0.055] [background-image:radial-gradient(#1A261D_1px,transparent_1px)] [background-size:18px_18px]"
        ></div>
    </div>

    <nav class="fixed inset-x-0 top-5 z-40 flex justify-center px-4">
        <div
            class="flex w-full max-w-5xl items-center justify-between gap-3 rounded-full border-2 border-[#1A261D] bg-[#F2EFE9]/95 px-2 py-2 shadow-[6px_6px_0_0_#1A261D] backdrop-blur"
        >
            <Link
                href={toUrl(auth.user ? dashboard() : register())}
                class="flex items-center gap-3 rounded-full bg-[#1A261D] px-5 py-2 text-[#F2EFE9] transition hover:bg-[#FF6B4A] hover:text-[#1A261D]"
            >
                <AppLogoIcon class="size-5" />
                <span class="text-sm font-semibold uppercase tracking-[0.16em]">
                    Downloora
                </span>
            </Link>

            <div
                class="hidden items-center gap-6 text-sm font-semibold md:flex"
            >
                <a href="#how-it-works" class="hover:text-[#3D6B4F]"
                    >How it works</a
                >
                <a href="#features" class="hover:text-[#3D6B4F]">Features</a>
                <a href="#pricing" class="hover:text-[#3D6B4F]">Plans</a>
            </div>

            <div class="flex items-center gap-2">
                {#if auth.user}
                    <Link
                        href={toUrl(dashboard())}
                        class="rounded-full border-2 border-[#1A261D] bg-[#C9E265] px-5 py-2.5 text-sm font-semibold shadow-[2px_2px_0_0_#1A261D] transition hover:bg-[#b5d145]"
                    >
                        Dashboard
                    </Link>
                {:else}
                    <Link
                        href={toUrl(login())}
                        class="hidden rounded-full px-4 py-2.5 text-sm font-semibold transition hover:bg-[#1A261D]/5 sm:inline-flex"
                    >
                        Login
                    </Link>
                    {#if canRegister}
                        <Link
                            href={toUrl(register())}
                            class="rounded-full border-2 border-[#1A261D] bg-[#C9E265] px-5 py-2.5 text-sm font-semibold shadow-[2px_2px_0_0_#1A261D] transition hover:bg-[#b5d145]"
                        >
                            Register
                        </Link>
                    {/if}
                {/if}
            </div>
        </div>
    </nav>

    <header
        class="relative z-10 flex min-h-[92vh] items-center justify-center px-6 pt-32 pb-20"
    >
        <div
            class="mx-auto grid w-full max-w-7xl items-center gap-12 lg:grid-cols-[minmax(0,1fr)_27rem]"
        >
            <section class="text-center lg:text-left">
                <div
                    class="mb-8 inline-flex rotate-1 animate-[welcome-float_6s_ease-in-out_infinite] items-center gap-3 rounded-full border-2 border-[#1A261D] bg-[#1A261D] px-5 py-2 text-sm font-medium text-[#F2EFE9] shadow-[4px_4px_0_0_#9D8BB5]"
                >
                    <Users class="size-5 text-[#FF6B4A]" />
                    Multi-user torrent cloud storage
                </div>

                <h1
                    class="text-5xl font-bold leading-[0.95] tracking-tight sm:text-7xl lg:text-8xl"
                >
                    Ultra Cloud
                    <span class="block font-serif italic text-[#3D6B4F]">
                        Download
                    </span>
                </h1>

                <p
                    class="mx-auto mt-7 max-w-2xl text-lg leading-8 text-[#1A261D]/75 lg:mx-0"
                >
                    Fetch magnets and torrent files to your private cloud,
                    stream or download completed files, and keep every user
                    inside a clear storage quota.
                </p>

                <div
                    class="mt-10 flex flex-col items-center gap-5 sm:flex-row lg:justify-start"
                >
                    <Link
                        href={toUrl(primaryRoute)}
                        class="flex w-full items-center justify-center gap-3 rounded-full border-2 border-[#1A261D] bg-[#FF6B4A] px-8 py-4 text-base font-semibold shadow-[6px_6px_0_0_#1A261D] transition hover:translate-x-1 hover:translate-y-1 hover:shadow-[2px_2px_0_0_#1A261D] sm:w-auto"
                    >
                        {primaryLabel}
                        <span
                            class="rounded border border-[#1A261D] bg-[#F2EFE9] px-2 py-1 text-xs"
                        >
                            Free
                        </span>
                    </Link>
                    <a
                        href="#pricing"
                        class="flex w-full items-center justify-center gap-3 rounded-full border-2 border-[#1A261D] bg-[#F2EFE9] px-8 py-4 text-base font-semibold shadow-[6px_6px_0_0_#1A261D] transition hover:translate-x-1 hover:translate-y-1 hover:shadow-[2px_2px_0_0_#1A261D] sm:w-auto"
                    >
                        View Plans
                        <ArrowRight class="size-5" />
                    </a>
                </div>
            </section>

            <section
                class="relative rounded-[1.5rem] border-2 border-[#1A261D] bg-white p-4 shadow-[8px_8px_0_0_#1A261D] rotate-1"
            >
                <div
                    class="rounded-[1rem] border border-[#1A261D] bg-[#E8E4DB] p-5"
                >
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <span
                                class="flex size-12 items-center justify-center rounded-full border-2 border-[#1A261D] bg-[#C9E265]"
                            >
                                <AppLogoIcon class="size-6" />
                            </span>
                            <div>
                                <p
                                    class="font-semibold uppercase tracking-[0.12em]"
                                >
                                    Downloora
                                </p>
                                <p class="text-sm text-[#1A261D]/65">
                                    2 GB starter quota
                                </p>
                            </div>
                        </div>
                        <span
                            class="rounded-full border border-[#1A261D] bg-[#3D6B4F] px-3 py-1 text-xs font-semibold text-[#F2EFE9]"
                        >
                            Active
                        </span>
                    </div>

                    <div
                        class="mt-6 flex min-h-14 overflow-hidden rounded-full border-2 border-[#1A261D] bg-[#F2EFE9]"
                    >
                        <span class="flex w-14 items-center justify-center">
                            <Link2 class="size-5" />
                        </span>
                        <span
                            class="min-w-0 flex-1 truncate py-4 text-[#1A261D]/50"
                        >
                            Paste magnet link URL here
                        </span>
                        <span
                            class="flex w-16 items-center justify-center bg-[#FF6B4A]"
                        >
                            <Upload class="size-6" />
                        </span>
                    </div>

                    <div class="mt-6 space-y-3">
                        <div
                            class="rounded-2xl border-2 border-[#1A261D] bg-white p-4 shadow-[4px_4px_0_0_#1A261D]"
                        >
                            <div
                                class="flex items-center justify-between gap-4"
                            >
                                <div class="flex min-w-0 items-center gap-3">
                                    <Folder
                                        class="size-8 shrink-0 fill-[#C9E265] text-[#1A261D]"
                                    />
                                    <div class="min-w-0">
                                        <p class="truncate font-semibold">
                                            Open.Media.Bundle.1080p
                                        </p>
                                        <p class="text-sm text-[#1A261D]/60">
                                            4 files ready
                                        </p>
                                    </div>
                                </div>
                                <Download
                                    class="size-5 shrink-0 text-[#3D6B4F]"
                                />
                            </div>
                        </div>

                        <div
                            class="rounded-2xl border-2 border-[#1A261D] bg-[#C9E265] p-4 shadow-[4px_4px_0_0_#1A261D]"
                        >
                            <div
                                class="flex items-center justify-between text-sm font-semibold"
                            >
                                <span>Cloud transfer</span>
                                <span>78%</span>
                            </div>
                            <div
                                class="mt-3 h-2 rounded-full border border-[#1A261D] bg-[#F2EFE9]"
                            >
                                <div
                                    class="h-full w-[78%] rounded-full bg-[#3D6B4F]"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>

                <Rocket
                    class="absolute -right-8 -bottom-8 size-20 rotate-12 text-[#FF6B4A]"
                />
            </section>
        </div>
    </header>

    <section
        class="relative z-20 -rotate-1 scale-105 overflow-hidden border-y-2 border-[#1A261D] bg-[#3D6B4F] py-7 shadow-lg"
    >
        <div
            class="flex w-max animate-[welcome-marquee_30s_linear_infinite] whitespace-nowrap"
        >
            {#each Array(2) as _, index (index)}
                <span
                    class="flex items-center gap-6 px-6 text-2xl font-semibold uppercase tracking-tight text-[#F2EFE9] md:text-3xl"
                >
                    Unlock the full experience
                    <Star class="size-7 text-[#C9E265]" />
                    Premium storage, faster speeds
                    <Star class="size-7 text-[#FF6B4A]" />
                    Stream on any device
                    <Star class="size-7 text-[#9D8BB5]" />
                    Join Premium
                </span>
            {/each}
        </div>
    </section>

    <section class="relative z-10 mx-auto max-w-7xl px-6 py-24">
        <div class="mx-auto mb-16 max-w-3xl text-center">
            <h2 class="text-4xl font-bold tracking-tight md:text-5xl">
                Recent
                <span
                    class="block bg-linear-to-r from-[#FF6B4A] to-[#9D8BB5] bg-clip-text font-serif italic text-transparent"
                >
                    Articles
                </span>
            </h2>
            <p class="mt-5 text-lg text-[#1A261D]/70">
                Guides and product updates for getting more from your cloud
                torrent workflow.
            </p>
        </div>

        <div class="grid gap-8 md:grid-cols-3">
            {#each articles as article, index (article.title)}
                <article
                    class={`rounded-2xl border-2 border-[#1A261D] bg-[#F2EFE9] p-8 shadow-[6px_6px_0_0_#1A261D] transition hover:-translate-y-2 ${index === 1 ? 'md:translate-y-4' : ''}`}
                >
                    <div
                        class={`mb-6 flex size-12 items-center justify-center rounded-full border border-[#1A261D] ${article.color}`}
                    >
                        <article.icon class="size-6" />
                    </div>
                    <h3 class="mb-3 text-xl font-semibold tracking-tight">
                        {article.title}
                    </h3>
                    <p class="mb-4 text-sm italic text-[#1A261D]/65">
                        {article.date}
                    </p>
                    <p class="text-sm leading-6 text-[#1A261D]/80">
                        {article.copy}
                    </p>
                </article>
            {/each}
        </div>
    </section>

    <section
        id="how-it-works"
        class="border-y-2 border-[#F2EFE9] bg-[#1A261D] py-24 text-[#F2EFE9]"
    >
        <div class="mx-auto max-w-6xl px-6">
            <div class="mb-16 text-center">
                <div
                    class="mb-6 inline-flex rounded-full border border-[#F2EFE9]/30 bg-[#F2EFE9]/10 px-4 py-1.5 text-xs font-medium uppercase tracking-widest"
                >
                    Sign Up
                </div>
                <h2 class="text-4xl font-bold tracking-tight md:text-5xl">
                    Get Started Quickly
                </h2>
            </div>

            <div class="relative grid gap-12 md:grid-cols-3">
                <div
                    class="hidden md:block absolute top-12 left-[15%] right-[15%] h-px bg-linear-to-r from-transparent via-[#F2EFE9]/30 to-transparent"
                ></div>
                {#each ['Email & Password', 'Add a link', 'Download files'] as step, index (step)}
                    <div
                        class="relative flex flex-col items-center text-center"
                    >
                        <div
                            class={`z-10 mb-8 flex size-24 items-center justify-center rounded-2xl border-2 border-[#F2EFE9] text-[#1A261D] shadow-[4px_4px_0_0_#F2EFE9] ${index === 0 ? 'rotate-3 bg-[#C9E265]' : index === 1 ? '-rotate-3 bg-[#FF6B4A]' : 'rotate-6 bg-[#9D8BB5]'}`}
                        >
                            <span
                                class="font-serif text-4xl font-semibold italic"
                            >
                                {index + 1}
                            </span>
                        </div>
                        <h3
                            class={`mb-3 text-xl font-semibold tracking-tight ${index === 0 ? 'text-[#C9E265]' : index === 1 ? 'text-[#FF6B4A]' : 'text-[#9D8BB5]'}`}
                        >
                            {step}
                        </h3>
                        <p class="max-w-xs text-sm text-[#F2EFE9]/70">
                            {index === 0
                                ? 'Create your account securely.'
                                : index === 1
                                  ? 'Paste a magnet link or upload a .torrent file.'
                                  : 'Save completed files or folders from your library.'}
                        </p>
                    </div>
                {/each}
            </div>
        </div>
    </section>

    <section id="features" class="mx-auto max-w-6xl space-y-24 px-6 py-24">
        {#each features as feature, index (feature.title)}
            <div class="grid items-center gap-12 lg:grid-cols-2">
                <div class={index % 2 === 1 ? 'lg:order-2' : ''}>
                    <div
                        class={`mb-6 inline-flex rounded-full px-3 py-1 text-xs font-medium tracking-wide ${feature.color}`}
                    >
                        {feature.eyebrow}
                    </div>
                    <h2
                        class="mb-6 text-4xl font-bold tracking-tight md:text-5xl"
                    >
                        {feature.title}
                    </h2>
                    <p class="text-base leading-7 text-[#1A261D]/80">
                        {feature.copy}
                    </p>
                </div>

                <div class="relative">
                    <div
                        class={`mx-auto max-w-md rounded-xl border-2 border-[#1A261D] bg-white p-4 pb-8 shadow-[6px_6px_0_0_#1A261D] ${index % 2 === 0 ? '-rotate-2' : 'rotate-3'}`}
                    >
                        <div
                            class="aspect-square overflow-hidden rounded-lg border border-[#1A261D] bg-[#E8E4DB]"
                        >
                            <img
                                src={feature.image}
                                alt={feature.title}
                                class="h-full w-full object-cover"
                            />
                        </div>
                        <p
                            class="mt-4 text-center font-serif text-sm italic text-[#1A261D]/70"
                        >
                            Built for Downloora v1
                        </p>
                    </div>
                </div>
            </div>
        {/each}
    </section>

    <section
        class="overflow-hidden border-y-2 border-[#1A261D] bg-[#1A261D] py-20 text-center"
    >
        <div class="mx-auto mb-12 max-w-7xl px-6">
            <h2
                class="text-3xl font-bold tracking-tight text-[#F2EFE9] md:text-4xl"
            >
                What People Are Saying
            </h2>
            <p class="mt-2 font-serif text-lg italic text-[#C9E265]">
                Read Reviews
            </p>
        </div>

        <div
            class="mx-auto flex max-w-6xl flex-col items-center justify-center gap-6 px-6 md:flex-row"
        >
            {#each ['Pasted a link and the folder was ready in my cloud.', 'Everything is private to my account and easy to delete.', 'Folder zip download made the whole flow feel complete.'] as review, index (review)}
                <div
                    class={`w-full max-w-sm rounded-xl border-2 border-[#1A261D] p-6 text-left shadow-[6px_6px_0_0_#000] transition hover:rotate-0 ${index === 1 ? 'bg-[#C9E265] md:mt-8 -rotate-2' : 'bg-[#F2EFE9] rotate-2'}`}
                >
                    <div class="mb-4 flex text-[#FF6B4A]">
                        {#each Array(5) as _, starIndex (starIndex)}
                            <Star class="size-4 fill-current" />
                        {/each}
                    </div>
                    <p class="mb-6 text-sm leading-6 text-[#1A261D]">
                        "{review}"
                    </p>
                    <div
                        class="border-t border-[#1A261D]/20 pt-4 text-xs font-semibold"
                    >
                        Downloora user
                        <span class="block font-normal text-[#1A261D]/65">
                            Early access
                        </span>
                    </div>
                </div>
            {/each}
        </div>
    </section>

    <section
        id="pricing"
        class="relative overflow-hidden bg-[#F2EFE9] px-6 py-24"
    >
        <div class="relative z-10 mx-auto max-w-6xl text-center">
            <h2 class="mb-4 text-4xl font-bold tracking-tight md:text-5xl">
                Get More with Downloora Premium
            </h2>
            <p class="mb-16 text-base text-[#1A261D]/70">
                Upgrade anytime. Cancel anytime.
            </p>

            <div class="grid gap-6 text-left md:grid-cols-3">
                {#each plans as plan (plan.name)}
                    <div
                        class={`relative flex flex-col rounded-2xl border-2 border-[#1A261D] p-8 shadow-[6px_6px_0_0_#1A261D] transition hover:-translate-y-1 ${plan.color}`}
                    >
                        <div
                            class="absolute -top-4 -right-4 rotate-6 rounded-full border border-[#1A261D] bg-[#FF6B4A] px-3 py-1 text-xs font-semibold shadow-[2px_2px_0_0_#1A261D]"
                        >
                            {plan.price}
                        </div>
                        <h3 class="mb-2 text-2xl font-bold tracking-tight">
                            {plan.name}
                            <span class="text-base text-[#1A261D]/65">
                                {plan.storage}
                            </span>
                        </h3>
                        <div
                            class="mb-6 flex items-center gap-2 text-lg font-semibold text-[#3D6B4F]"
                        >
                            <Crown class="size-5" />
                            Monthly plan
                        </div>
                        <ul
                            class="mb-8 flex-1 space-y-4 text-sm text-[#1A261D]/80"
                        >
                            {#each plan.items as item (item)}
                                <li class="flex items-start gap-3">
                                    <Check
                                        class="mt-0.5 size-5 text-[#3D6B4F]"
                                    />
                                    {item}
                                </li>
                            {/each}
                        </ul>
                        <Link
                            href={toUrl(primaryRoute)}
                            class="w-full rounded-full border-2 border-[#1A261D] bg-[#1A261D] py-3 text-center font-semibold text-[#F2EFE9] transition hover:bg-[#3D6B4F]"
                        >
                            Choose Plan
                        </Link>
                    </div>
                {/each}
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-3xl px-6 py-20">
        <h2 class="mb-10 text-center text-3xl font-bold tracking-tight">
            Questions?
        </h2>
        <div class="space-y-4">
            <a
                href="#pricing"
                class="block rounded-xl border-2 border-[#1A261D] bg-white p-6 shadow-[4px_4px_0_0_#1A261D] transition hover:-translate-y-1"
            >
                <h4 class="flex items-center gap-2 font-semibold">
                    <Flame class="size-5 text-[#FF6B4A]" />
                    See Plans
                </h4>
            </a>
            <a
                href="#how-it-works"
                class="block rounded-xl border-2 border-[#1A261D] bg-white p-6 shadow-[4px_4px_0_0_#1A261D] transition hover:-translate-y-1"
            >
                <h4 class="flex items-center gap-2 font-semibold">
                    <Play class="size-5 text-[#3D6B4F]" />
                    How do downloads work?
                </h4>
            </a>
        </div>
    </section>

    <footer
        class="border-t-2 border-[#1A261D] bg-[#1A261D] px-6 py-10 text-[#F2EFE9]"
    >
        <div
            class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 text-sm md:flex-row"
        >
            <div class="flex items-center gap-3">
                <AppLogoIcon class="size-5" />
                <span class="font-semibold uppercase tracking-[0.16em]">
                    Downloora
                </span>
            </div>
            <span class="text-[#F2EFE9]/60">
                Torrent cloud storage powered by Laravel, Docker, and RustFS.
            </span>
            <nav
                class="flex flex-wrap justify-center gap-3 md:justify-end"
                aria-label="Popular pages"
            >
                {#each seoLinks as seoLink (seoLink.title)}
                    <Link
                        href={toUrl(seoLink.href)}
                        class="text-[#F2EFE9]/70 transition hover:text-[#C9E265]"
                    >
                        {seoLink.title}
                    </Link>
                {/each}
            </nav>
        </div>
    </footer>
</div>
