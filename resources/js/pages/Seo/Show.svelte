<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import ArrowRight from 'lucide-svelte/icons/arrow-right';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import Search from 'lucide-svelte/icons/search';
    import Shield from 'lucide-svelte/icons/shield';
    import AppHead from '@/components/AppHead.svelte';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';
    import { toUrl } from '@/lib/utils';
    import { dashboard, home, register } from '@/routes';

    type Feature = {
        title: string;
        copy: string;
    };

    type Question = {
        question: string;
        answer: string;
    };

    type SeoPage = {
        path: string;
        title: string;
        meta_title: string;
        description: string;
        eyebrow: string;
        heading: string;
        intro: string;
        image: string;
        features: Feature[];
        questions: Question[];
    };

    type RelatedPage = {
        title: string;
        path: string;
    };

    let {
        seoPage,
        relatedPages,
    }: {
        seoPage: SeoPage;
        relatedPages: RelatedPage[];
    } = $props();

    const auth = $derived(page.props.auth);
    const primaryRoute = $derived(auth.user ? dashboard() : register());
    const primaryLabel = $derived(
        auth.user ? 'Open Dashboard' : 'Create Account',
    );
</script>

<AppHead
    title={seoPage.meta_title}
    description={seoPage.description}
    canonical={seoPage.path}
    image={seoPage.image}
/>

<div class="min-h-screen bg-[#F2EFE9] text-[#1A261D]">
    <header class="border-b-2 border-[#1A261D] bg-[#F2EFE9]">
        <nav
            class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-4 sm:px-6"
            aria-label="Primary"
        >
            <Link
                href={toUrl(home())}
                class="flex min-w-0 items-center gap-3 rounded-full bg-[#1A261D] px-4 py-2 text-[#F2EFE9] transition hover:bg-[#3D6B4F]"
            >
                <AppLogoIcon class="size-5 shrink-0" />
                <span
                    class="truncate text-sm font-semibold uppercase tracking-[0.16em]"
                >
                    Downloora
                </span>
            </Link>

            <div
                class="hidden items-center gap-5 text-sm font-semibold md:flex"
            >
                {#each relatedPages as relatedPage (relatedPage.path)}
                    <a href={relatedPage.path} class="hover:text-[#3D6B4F]">
                        {relatedPage.title}
                    </a>
                {/each}
            </div>

            <Link
                href={toUrl(primaryRoute)}
                class="inline-flex items-center gap-2 rounded-full border-2 border-[#1A261D] bg-[#C9E265] px-4 py-2 text-sm font-semibold shadow-[2px_2px_0_0_#1A261D] transition hover:translate-x-0.5 hover:translate-y-0.5 hover:shadow-none"
            >
                {primaryLabel}
                <ArrowRight class="size-4" />
            </Link>
        </nav>
    </header>

    <main>
        <section
            class="mx-auto grid max-w-7xl items-center gap-10 px-5 py-16 sm:px-6 lg:grid-cols-[minmax(0,1fr)_26rem] lg:py-20"
        >
            <div>
                <div
                    class="mb-6 inline-flex items-center gap-2 rounded-full border-2 border-[#1A261D] bg-white px-4 py-2 text-xs font-bold uppercase tracking-[0.14em] shadow-[3px_3px_0_0_#1A261D]"
                >
                    <Search class="size-4 text-[#FF6B4A]" />
                    {seoPage.eyebrow}
                </div>

                <h1
                    class="max-w-4xl text-4xl font-black leading-tight tracking-tight sm:text-6xl"
                >
                    {seoPage.heading}
                </h1>

                <p class="mt-6 max-w-3xl text-lg leading-8 text-[#1A261D]/75">
                    {seoPage.intro}
                </p>

                <div class="mt-9 flex flex-col gap-4 sm:flex-row">
                    <Link
                        href={toUrl(primaryRoute)}
                        class="inline-flex items-center justify-center gap-3 rounded-full border-2 border-[#1A261D] bg-[#FF6B4A] px-7 py-3.5 font-semibold shadow-[5px_5px_0_0_#1A261D] transition hover:translate-x-1 hover:translate-y-1 hover:shadow-none"
                    >
                        {primaryLabel}
                        <ArrowRight class="size-5" />
                    </Link>
                    <a
                        href="#questions"
                        class="inline-flex items-center justify-center rounded-full border-2 border-[#1A261D] bg-white px-7 py-3.5 font-semibold shadow-[5px_5px_0_0_#1A261D] transition hover:translate-x-1 hover:translate-y-1 hover:shadow-none"
                    >
                        Read Questions
                    </a>
                </div>
            </div>

            <figure
                class="overflow-hidden rounded-xl border-2 border-[#1A261D] bg-white p-3 shadow-[8px_8px_0_0_#1A261D]"
            >
                <img
                    src={seoPage.image}
                    alt={seoPage.title}
                    class="aspect-[4/3] w-full rounded-lg object-cover"
                />
            </figure>
        </section>

        <section
            class="border-y-2 border-[#1A261D] bg-[#1A261D] py-16 text-[#F2EFE9]"
        >
            <div class="mx-auto max-w-7xl px-5 sm:px-6">
                <div class="grid gap-5 md:grid-cols-3">
                    {#each seoPage.features as feature (feature.title)}
                        <article
                            class="rounded-lg border-2 border-[#F2EFE9] bg-[#F2EFE9] p-6 text-[#1A261D] shadow-[5px_5px_0_0_#C9E265]"
                        >
                            <CheckCircle2 class="mb-5 size-8 text-[#3D6B4F]" />
                            <h2 class="text-xl font-black tracking-tight">
                                {feature.title}
                            </h2>
                            <p class="mt-3 text-sm leading-6 text-[#1A261D]/75">
                                {feature.copy}
                            </p>
                        </article>
                    {/each}
                </div>
            </div>
        </section>

        <section
            id="questions"
            class="mx-auto grid max-w-7xl gap-10 px-5 py-16 sm:px-6 lg:grid-cols-[20rem_minmax(0,1fr)]"
        >
            <div>
                <div
                    class="inline-flex rounded-full bg-[#C9E265] px-4 py-2 text-xs font-bold uppercase tracking-[0.14em]"
                >
                    Search-friendly answers
                </div>
                <h2 class="mt-5 text-3xl font-black tracking-tight">
                    Questions people search before choosing Downloora
                </h2>
            </div>

            <div class="space-y-4">
                {#each seoPage.questions as item (item.question)}
                    <article
                        class="rounded-lg border-2 border-[#1A261D] bg-white p-6 shadow-[4px_4px_0_0_#1A261D]"
                    >
                        <h3 class="text-lg font-black tracking-tight">
                            {item.question}
                        </h3>
                        <p class="mt-3 leading-7 text-[#1A261D]/75">
                            {item.answer}
                        </p>
                    </article>
                {/each}
            </div>
        </section>

        <section class="bg-white px-5 py-14 sm:px-6">
            <div
                class="mx-auto flex max-w-7xl flex-col gap-8 lg:flex-row lg:items-center lg:justify-between"
            >
                <div>
                    <div
                        class="flex items-center gap-3 text-sm font-bold uppercase tracking-[0.16em] text-[#3D6B4F]"
                    >
                        <Shield class="size-5" />
                        Private cloud torrent workflow
                    </div>
                    <h2
                        class="mt-3 max-w-3xl text-3xl font-black tracking-tight"
                    >
                        Build more search depth with related Downloora pages.
                    </h2>
                </div>

                <div class="flex flex-wrap gap-3">
                    {#each relatedPages as relatedPage (relatedPage.path)}
                        <a
                            href={relatedPage.path}
                            class="rounded-full border-2 border-[#1A261D] bg-[#F2EFE9] px-4 py-2 text-sm font-semibold transition hover:bg-[#C9E265]"
                        >
                            {relatedPage.title}
                        </a>
                    {/each}
                </div>
            </div>
        </section>
    </main>

    <footer
        class="border-t-2 border-[#1A261D] bg-[#1A261D] px-5 py-8 text-[#F2EFE9] sm:px-6"
    >
        <div
            class="mx-auto flex max-w-7xl flex-col gap-4 text-sm md:flex-row md:items-center md:justify-between"
        >
            <Link
                href={toUrl(home())}
                class="flex items-center gap-3 font-semibold uppercase tracking-[0.16em]"
            >
                <AppLogoIcon class="size-5" />
                Downloora
            </Link>
            <span class="text-[#F2EFE9]/70">
                Cloud torrent storage, private libraries, and browser downloads.
            </span>
        </div>
    </footer>
</div>
