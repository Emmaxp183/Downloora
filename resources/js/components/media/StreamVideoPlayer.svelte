<script lang="ts">
    import Cast from 'lucide-svelte/icons/cast';
    import FastForward from 'lucide-svelte/icons/fast-forward';
    import LoaderCircle from 'lucide-svelte/icons/loader-circle';
    import Maximize from 'lucide-svelte/icons/maximize';
    import Minimize from 'lucide-svelte/icons/minimize';
    import Pause from 'lucide-svelte/icons/pause';
    import PictureInPicture from 'lucide-svelte/icons/picture-in-picture';
    import Play from 'lucide-svelte/icons/play';
    import Rewind from 'lucide-svelte/icons/rewind';
    import Settings2 from 'lucide-svelte/icons/settings-2';
    import Volume2 from 'lucide-svelte/icons/volume-2';
    import VolumeX from 'lucide-svelte/icons/volume-x';
    import type shaka from 'shaka-player';
    import { onDestroy, onMount, tick } from 'svelte';
    import { toast } from 'svelte-sonner';

    type RemoteCastState = 'connected' | 'connecting' | 'disconnected';

    type RemotePlaybackController = {
        state: RemoteCastState;
        prompt: () => Promise<void>;
        watchAvailability?: (
            callback: (available: boolean) => void,
        ) => Promise<number>;
        cancelWatchAvailability?: (id: number) => Promise<void>;
        addEventListener?: (
            type: string,
            listener: EventListenerOrEventListenerObject,
        ) => void;
        removeEventListener?: (
            type: string,
            listener: EventListenerOrEventListenerObject,
        ) => void;
    };

    type CastableVideoElement = HTMLVideoElement & {
        remote?: RemotePlaybackController;
        webkitCurrentPlaybackTargetIsWireless?: boolean;
        webkitShowPlaybackTargetPicker?: () => void;
    };

    type VariantTrack = shaka.extern.Track;
    type QualitySelection = 'auto' | number;

    let {
        src,
        adaptiveSrc = null,
        title,
    }: {
        src: string;
        adaptiveSrc?: string | null;
        title: string;
    } = $props();

    let playerElement = $state<HTMLDivElement | null>(null);
    let videoElement = $state<HTMLVideoElement | null>(null);
    let currentTime = $state(0);
    let duration = $state(0);
    let bufferedTime = $state(0);
    let volume = $state(1);
    let muted = $state(false);
    let paused = $state(true);
    let waiting = $state(false);
    let fullscreen = $state(false);
    let pictureInPictureAvailable = $state(false);
    let controlsVisible = $state(true);
    let touchControls = $state(false);
    let nativeMobilePlayer = $state(false);
    let playbackRate = $state(1);
    let shakaPlayer = $state<shaka.Player | null>(null);
    let shakaReady = $state(false);
    let nativeSourceUsingAdaptive = $state(false);
    let variantTracks = $state<VariantTrack[]>([]);
    let selectedTrackId = $state<QualitySelection>('auto');
    let castPickerAvailable = $state(false);
    let castSupportChecked = $state(false);
    let castStatus = $state<RemoteCastState>('disconnected');

    let hideControlsTimer: ReturnType<typeof setTimeout> | null = null;
    let watcherId: number | null = null;
    let remote: RemotePlaybackController | undefined;
    let castableVideo: CastableVideoElement | null = null;

    const formatBitrate = (bitsPerSecond: number): string => {
        if (bitsPerSecond >= 1_000_000) {
            return `${(bitsPerSecond / 1_000_000).toFixed(1)} Mbps`;
        }

        return `${Math.round(bitsPerSecond / 1_000)} Kbps`;
    };

    const formatQuality = (track: VariantTrack): string => {
        const resolution = track.height
            ? `${track.height}p`
            : formatBitrate(track.bandwidth);
        const frameRate =
            track.frameRate && track.frameRate > 30
                ? ` ${Math.round(track.frameRate)}fps`
                : '';

        return `${resolution}${frameRate}`;
    };

    const sortVariantTracks = (tracks: VariantTrack[]): VariantTrack[] =>
        [...tracks]
            .filter((track) => track.type === 'variant')
            .sort(
                (first, second) =>
                    (second.height ?? 0) - (first.height ?? 0) ||
                    second.bandwidth - first.bandwidth,
            );

    const progress = $derived(
        duration > 0 ? Math.min(100, (currentTime / duration) * 100) : 0,
    );
    const bufferedProgress = $derived(
        duration > 0 ? Math.min(100, (bufferedTime / duration) * 100) : 0,
    );
    const progressStyle = $derived(
        `linear-gradient(to right, var(--downloora-lime) 0%, var(--downloora-lime) ${progress}%, rgb(255 255 255 / 0.35) ${progress}%, rgb(255 255 255 / 0.35) ${bufferedProgress}%, rgb(255 255 255 / 0.16) ${bufferedProgress}%, rgb(255 255 255 / 0.16) 100%)`,
    );
    const castTitle = $derived(
        castStatus === 'connected'
            ? 'Casting to TV'
            : castStatus === 'connecting'
              ? 'Connecting to TV'
              : !castSupportChecked
                ? 'Checking casting support'
                : castPickerAvailable
                  ? 'Cast to TV'
                  : 'Casting is not available in this browser',
    );
    const streamTitle = $derived(
        adaptiveSrc && shakaReady
            ? 'Adaptive HLS stream'
            : 'Direct video stream',
    );
    const qualityOptions = $derived(sortVariantTracks(variantTracks));
    const activeVariantTrack = $derived(
        variantTracks.find((track) => track.active) ?? null,
    );
    const qualityLabel = $derived(
        shakaReady
            ? selectedTrackId === 'auto'
                ? `Auto${activeVariantTrack ? ` · ${formatQuality(activeVariantTrack)}` : ''}`
                : activeVariantTrack
                  ? formatQuality(activeVariantTrack)
                  : 'Manual'
            : 'Direct',
    );
    const controlsShown = $derived(controlsVisible || paused);

    const formatTime = (seconds: number): string => {
        if (!Number.isFinite(seconds) || seconds <= 0) {
            return '0:00';
        }

        const rounded = Math.floor(seconds);
        const hours = Math.floor(rounded / 3600);
        const minutes = Math.floor((rounded % 3600) / 60);
        const remainingSeconds = rounded % 60;

        if (hours > 0) {
            return `${hours}:${minutes.toString().padStart(2, '0')}:${remainingSeconds.toString().padStart(2, '0')}`;
        }

        return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
    };

    const showControls = (): void => {
        controlsVisible = true;

        if (hideControlsTimer) {
            clearTimeout(hideControlsTimer);
        }

        if (!paused && !touchControls) {
            hideControlsTimer = setTimeout(() => {
                controlsVisible = false;
            }, 2500);
        }
    };

    const updateMediaState = (): void => {
        if (!videoElement) {
            return;
        }

        currentTime = videoElement.currentTime;
        duration = videoElement.duration || 0;
        volume = videoElement.volume;
        muted = videoElement.muted;
        paused = videoElement.paused;

        if (videoElement.buffered.length > 0) {
            bufferedTime = videoElement.buffered.end(
                videoElement.buffered.length - 1,
            );
        }
    };

    const togglePlay = async (): Promise<void> => {
        if (!videoElement) {
            return;
        }

        try {
            if (videoElement.paused) {
                await videoElement.play();
            } else {
                videoElement.pause();
            }
        } catch {
            toast.error(
                'Playback could not start. Tap play again after the video loads.',
            );
        }

        updateMediaState();
        showControls();
    };

    const handleVideoClick = async (): Promise<void> => {
        if (nativeMobilePlayer) {
            return;
        }

        if (touchControls && !controlsVisible && !paused) {
            showControls();

            return;
        }

        await togglePlay();
    };

    const keepControlTap = (event: Event): void => {
        event.stopPropagation();
        showControls();
    };

    const activateControl = (
        event: Event,
        action: () => void | Promise<void>,
    ): void => {
        event.preventDefault();
        event.stopPropagation();
        showControls();
        void action();
    };

    const seekBy = (seconds: number): void => {
        if (!videoElement) {
            return;
        }

        videoElement.currentTime = Math.max(
            0,
            Math.min(duration, videoElement.currentTime + seconds),
        );
        updateMediaState();
        showControls();
    };

    const seekTo = (event: Event): void => {
        if (!videoElement) {
            return;
        }

        const input = event.currentTarget as HTMLInputElement;
        videoElement.currentTime = Number(input.value);
        updateMediaState();
    };

    const changeVolume = (event: Event): void => {
        if (!videoElement) {
            return;
        }

        const input = event.currentTarget as HTMLInputElement;
        videoElement.volume = Number(input.value);
        videoElement.muted = videoElement.volume === 0;
        updateMediaState();
    };

    const toggleMute = (): void => {
        if (!videoElement) {
            return;
        }

        videoElement.muted = !videoElement.muted;
        updateMediaState();
    };

    const toggleFullscreen = async (): Promise<void> => {
        if (!playerElement) {
            return;
        }

        if (document.fullscreenElement) {
            await document.exitFullscreen();
        } else {
            await playerElement.requestFullscreen();
        }
    };

    const togglePictureInPicture = async (): Promise<void> => {
        if (!videoElement || !pictureInPictureAvailable) {
            return;
        }

        if (document.pictureInPictureElement) {
            await document.exitPictureInPicture();
        } else {
            await videoElement.requestPictureInPicture();
        }
    };

    const cyclePlaybackRate = (): void => {
        if (!videoElement) {
            return;
        }

        const rates = [1, 1.25, 1.5, 2, 0.75];
        const nextRate =
            rates[(rates.indexOf(playbackRate) + 1) % rates.length];
        playbackRate = nextRate;
        videoElement.playbackRate = nextRate;
    };

    const updateVariantTracks = (
        player: shaka.Player | null = shakaPlayer,
    ): void => {
        if (!player) {
            variantTracks = [];

            return;
        }

        variantTracks = [...player.getVariantTracks()];
    };

    const changeQuality = (event: Event): void => {
        if (!shakaPlayer) {
            return;
        }

        const select = event.currentTarget as HTMLSelectElement;

        if (select.value === 'auto') {
            selectedTrackId = 'auto';
            shakaPlayer.configure({ abr: { enabled: true } });
            updateVariantTracks();

            return;
        }

        const trackId = Number(select.value);
        const track = variantTracks.find(
            (variantTrack) => variantTrack.id === trackId,
        );

        if (!track) {
            return;
        }

        selectedTrackId = track.id;
        shakaPlayer.configure({ abr: { enabled: false } });
        shakaPlayer.selectVariantTrack(track, true);
        updateVariantTracks();
    };

    const loadPlaybackSource = async (): Promise<void> => {
        if (!videoElement) {
            return;
        }

        if (nativeMobilePlayer) {
            await shakaPlayer?.destroy();
            shakaPlayer = null;
            shakaReady = false;
            selectedTrackId = 'auto';
            variantTracks = [];
            nativeSourceUsingAdaptive = Boolean(adaptiveSrc);
            videoElement.src = adaptiveSrc ?? src;
            videoElement.load();
            waiting = false;

            return;
        }

        if (adaptiveSrc) {
            waiting = true;
            let player: shaka.Player | null = null;

            try {
                const shakaModule = (await import('shaka-player')).default;

                shakaModule.polyfill.installAll();

                if (!shakaModule.Player.isBrowserSupported()) {
                    throw new Error(
                        'Shaka Player is not supported in this browser.',
                    );
                }

                player = new shakaModule.Player();
                await player.attach(videoElement);
                player.configure({
                    streaming: {
                        bufferingGoal: 45,
                        rebufferingGoal: 3,
                        bufferBehind: 30,
                    },
                    abr: {
                        enabled: true,
                    },
                });
                player.addEventListener('error', (event) => {
                    const detail = (event as CustomEvent).detail as
                        | { code?: number }
                        | undefined;
                    toast.error(
                        `Adaptive playback error${detail?.code ? ` (${detail.code})` : ''}. Falling back to direct video.`,
                    );
                    videoElement?.setAttribute('src', src);
                    videoElement?.load();
                    shakaReady = false;
                    nativeSourceUsingAdaptive = false;
                    selectedTrackId = 'auto';
                    variantTracks = [];
                });
                player.addEventListener('adaptation', () =>
                    updateVariantTracks(player),
                );
                player.addEventListener('variantchanged', () =>
                    updateVariantTracks(player),
                );

                await player.load(adaptiveSrc);
                shakaPlayer = player;
                shakaReady = true;
                selectedTrackId = 'auto';
                updateVariantTracks(player);
                waiting = false;

                return;
            } catch {
                await player?.destroy();
                shakaPlayer = null;
                shakaReady = false;
                nativeSourceUsingAdaptive = false;
                selectedTrackId = 'auto';
                variantTracks = [];
                toast.error(
                    'Adaptive playback is unavailable. Falling back to direct video.',
                );
            }
        }

        videoElement.src = src;
        videoElement.load();
        nativeSourceUsingAdaptive = false;
        selectedTrackId = 'auto';
        variantTracks = [];
        waiting = false;
    };

    const handleNativePlaybackError = (): void => {
        if (
            !videoElement ||
            !nativeMobilePlayer ||
            !nativeSourceUsingAdaptive
        ) {
            return;
        }

        nativeSourceUsingAdaptive = false;
        videoElement.src = src;
        videoElement.load();
        waiting = false;
        toast.error(
            'Adaptive playback is unavailable. Falling back to direct video.',
        );
    };

    const castToTv = async (): Promise<void> => {
        const video = videoElement as CastableVideoElement | null;

        if (!video) {
            return;
        }

        if (!castPickerAvailable) {
            toast.error(
                'Casting is not available in this browser. Try Chrome, Edge, or Safari with a supported TV.',
            );

            return;
        }

        if (typeof video.remote?.prompt === 'function') {
            castStatus = 'connecting';

            try {
                await video.remote.prompt();
                castStatus = video.remote.state;
            } catch {
                castStatus = 'disconnected';
            }

            return;
        }

        if (typeof video.webkitShowPlaybackTargetPicker === 'function') {
            castStatus = 'connecting';
            video.webkitShowPlaybackTargetPicker();
        }
    };

    const handleKeydown = (event: KeyboardEvent): void => {
        if (
            event.target instanceof HTMLInputElement ||
            event.target instanceof HTMLButtonElement ||
            event.target instanceof HTMLSelectElement
        ) {
            return;
        }

        if (event.key === ' ') {
            event.preventDefault();
            void togglePlay();
        } else if (event.key === 'ArrowLeft') {
            seekBy(-10);
        } else if (event.key === 'ArrowRight') {
            seekBy(10);
        } else if (event.key.toLowerCase() === 'f') {
            void toggleFullscreen();
        } else if (event.key.toLowerCase() === 'm') {
            toggleMute();
        }
    };

    onMount(() => {
        const updateFullscreen = (): void => {
            fullscreen = document.fullscreenElement === playerElement;
        };

        const setConnected = (): void => {
            castStatus =
                castableVideo?.webkitCurrentPlaybackTargetIsWireless === true
                    ? 'connected'
                    : (remote?.state ?? 'disconnected');
        };

        const startWatching = async (): Promise<void> => {
            await tick();

            touchControls =
                window.matchMedia('(pointer: coarse)').matches ||
                'ontouchstart' in window;
            nativeMobilePlayer =
                touchControls ||
                window.matchMedia('(max-width: 767px)').matches;
            controlsVisible = true;
            castableVideo = videoElement as CastableVideoElement | null;
            remote = castableVideo?.remote;
            castableVideo?.setAttribute('x-webkit-airplay', 'allow');
            castPickerAvailable =
                typeof remote?.prompt === 'function' ||
                typeof castableVideo?.webkitShowPlaybackTargetPicker ===
                    'function';
            castSupportChecked = true;
            pictureInPictureAvailable =
                document.pictureInPictureEnabled &&
                typeof videoElement?.requestPictureInPicture === 'function';
            await loadPlaybackSource();

            remote?.addEventListener?.('connecting', setConnected);
            remote?.addEventListener?.('connect', setConnected);
            remote?.addEventListener?.('disconnect', setConnected);
            castableVideo?.addEventListener(
                'webkitcurrentplaybacktargetiswirelesschanged',
                setConnected,
            );

            if (remote?.watchAvailability) {
                try {
                    watcherId = await remote.watchAvailability((available) => {
                        castPickerAvailable =
                            available ||
                            typeof castableVideo?.webkitShowPlaybackTargetPicker ===
                                'function';
                    });
                } catch {
                    castPickerAvailable = true;
                }
            }

            setConnected();
            updateMediaState();
        };

        document.addEventListener('fullscreenchange', updateFullscreen);
        void startWatching();

        return () => {
            document.removeEventListener('fullscreenchange', updateFullscreen);

            if (watcherId !== null) {
                void remote?.cancelWatchAvailability?.(watcherId);
            }

            remote?.removeEventListener?.('connecting', setConnected);
            remote?.removeEventListener?.('connect', setConnected);
            remote?.removeEventListener?.('disconnect', setConnected);
            castableVideo?.removeEventListener(
                'webkitcurrentplaybacktargetiswirelesschanged',
                setConnected,
            );
        };
    });

    onDestroy(() => {
        if (hideControlsTimer) {
            clearTimeout(hideControlsTimer);
        }

        void shakaPlayer?.destroy();
    });
</script>

<!-- svelte-ignore a11y_no_noninteractive_tabindex, a11y_no_noninteractive_element_interactions -->
<div
    bind:this={playerElement}
    class="group/player relative aspect-video max-h-[75vh] min-h-52 w-full overflow-hidden bg-black text-white focus:outline-none sm:min-h-0"
    role="application"
    tabindex="0"
    onkeydown={handleKeydown}
    onpointerdown={showControls}
    onpointermove={showControls}
    ontouchstart={showControls}
    onfocus={showControls}
>
    <div
        class={`pointer-events-none absolute inset-x-0 top-0 z-10 flex items-start justify-between gap-3 bg-gradient-to-b from-black/85 via-black/35 to-transparent p-2 pb-10 transition-opacity duration-200 sm:gap-4 sm:p-4 sm:pb-12 ${controlsShown ? 'opacity-100' : 'opacity-0'}`}
    >
        <div class="min-w-0">
            <p
                class="max-w-[calc(100vw-4rem)] truncate text-xs font-bold text-white sm:max-w-[70vw] sm:text-base"
            >
                {title}
            </p>
            <p class="mt-1 hidden text-xs font-semibold text-white/65 sm:block">
                {streamTitle}
            </p>
        </div>

        <div
            class="hidden shrink-0 items-center gap-2 rounded-full bg-white/12 px-3 py-1.5 text-xs font-bold text-white/85 backdrop-blur sm:flex"
            title={qualityLabel}
        >
            <span>{qualityLabel}</span>
        </div>
    </div>

    <video
        bind:this={videoElement}
        preload="metadata"
        controls={nativeMobilePlayer}
        playsinline
        class="size-full bg-black object-contain"
        onclick={handleVideoClick}
        onloadedmetadata={updateMediaState}
        ontimeupdate={updateMediaState}
        onprogress={updateMediaState}
        onvolumechange={updateMediaState}
        onerror={handleNativePlaybackError}
        onplay={() => {
            paused = false;
            waiting = false;
            showControls();
        }}
        onpause={() => {
            paused = true;
            controlsVisible = true;
            updateMediaState();
        }}
        onwaiting={() => (waiting = true)}
        onplaying={() => {
            waiting = false;
            showControls();
        }}
        {title}
    ></video>

    {#if waiting && !nativeMobilePlayer}
        <div
            class="absolute inset-0 flex items-center justify-center bg-black/15"
        >
            <div
                class="flex items-center gap-3 rounded-full bg-black/70 px-5 py-3 text-sm font-bold text-white shadow-xl backdrop-blur"
            >
                <LoaderCircle
                    class="size-5 animate-spin text-[var(--downloora-lime)]"
                />
                <span>Buffering</span>
            </div>
        </div>
    {/if}

    {#if !nativeMobilePlayer}
        <div
            class={`absolute left-1/2 top-1/2 z-10 flex -translate-x-1/2 -translate-y-1/2 items-center gap-3 rounded-full bg-black/35 px-3 py-3 shadow-2xl backdrop-blur-md transition duration-200 sm:gap-7 sm:px-7 sm:py-4 ${controlsShown ? 'scale-100 opacity-100' : 'pointer-events-none scale-95 opacity-0'}`}
            role="group"
            aria-label="Playback controls"
            onpointerdown={keepControlTap}
            ontouchstart={keepControlTap}
        >
            <button
                type="button"
                onclick={(event) => activateControl(event, () => seekBy(-10))}
                class="flex size-10 items-center justify-center rounded-full bg-white/10 text-white shadow-lg transition hover:scale-105 hover:bg-white/20 sm:size-14"
                title="Back 10 seconds"
                aria-label="Back 10 seconds"
            >
                <Rewind class="size-5 sm:size-7" />
            </button>

            <button
                type="button"
                onclick={(event) => activateControl(event, togglePlay)}
                class="flex size-14 items-center justify-center rounded-full bg-white text-black shadow-xl transition hover:scale-105 hover:bg-[var(--downloora-lime)] sm:size-20"
                title={paused ? 'Play' : 'Pause'}
                aria-label={paused ? 'Play' : 'Pause'}
            >
                {#if paused}
                    <Play class="ml-1 size-7 fill-current sm:size-10" />
                {:else}
                    <Pause class="size-7 fill-current sm:size-10" />
                {/if}
            </button>

            <button
                type="button"
                onclick={(event) => activateControl(event, () => seekBy(10))}
                class="flex size-10 items-center justify-center rounded-full bg-white/10 text-white shadow-lg transition hover:scale-105 hover:bg-white/20 sm:size-14"
                title="Forward 10 seconds"
                aria-label="Forward 10 seconds"
            >
                <FastForward class="size-5 sm:size-7" />
            </button>
        </div>

        <div
            class={`absolute inset-x-0 bottom-0 z-10 flex flex-col gap-2 bg-gradient-to-t from-black/95 via-black/70 to-transparent px-2 pb-2 pt-16 transition-opacity duration-200 sm:gap-3 sm:px-5 sm:pb-5 sm:pt-24 ${controlsShown ? 'opacity-100' : 'pointer-events-none opacity-0'}`}
            role="group"
            aria-label="Media settings"
            onpointerdown={keepControlTap}
            ontouchstart={keepControlTap}
        >
            <div
                class="grid grid-cols-[2.5rem_1fr_2.5rem] items-center gap-2 sm:grid-cols-[auto_1fr_auto] sm:gap-3"
            >
                <span class="text-xs font-bold tabular-nums text-white/85">
                    {formatTime(currentTime)}
                </span>
                <input
                    type="range"
                    min="0"
                    max={duration || 0}
                    step="0.1"
                    value={currentTime}
                    oninput={seekTo}
                    onpointerdown={keepControlTap}
                    ontouchstart={keepControlTap}
                    class="h-2 w-full min-w-0 cursor-pointer appearance-none rounded-full accent-[var(--downloora-lime)]"
                    style={`background: ${progressStyle}`}
                    title="Seek"
                    aria-label="Seek"
                />
                <span
                    class="text-right text-xs font-bold tabular-nums text-white/65"
                >
                    {formatTime(duration)}
                </span>
            </div>

            <div
                class="grid grid-cols-[auto_1fr] items-center gap-2 rounded-2xl border border-white/10 bg-black/38 p-1.5 shadow-xl backdrop-blur-md sm:flex sm:flex-nowrap sm:justify-between sm:gap-x-4 sm:gap-y-3 sm:rounded-full sm:p-2 sm:px-3"
            >
                <div class="flex min-w-0 items-center gap-1 sm:gap-2">
                    <button
                        type="button"
                        onclick={(event) => activateControl(event, toggleMute)}
                        class="flex size-10 items-center justify-center rounded-full text-white transition hover:bg-white/14"
                        title={muted ? 'Unmute' : 'Mute'}
                        aria-label={muted ? 'Unmute' : 'Mute'}
                    >
                        {#if muted || volume === 0}
                            <VolumeX class="size-5" />
                        {:else}
                            <Volume2 class="size-5" />
                        {/if}
                    </button>

                    <input
                        type="range"
                        min="0"
                        max="1"
                        step="0.01"
                        value={muted ? 0 : volume}
                        oninput={changeVolume}
                        onpointerdown={keepControlTap}
                        ontouchstart={keepControlTap}
                        class="hidden h-1.5 w-24 cursor-pointer accent-[var(--downloora-lime)] md:block"
                        title="Volume"
                        aria-label="Volume"
                    />

                    <div class="hidden min-w-0 pl-1 md:block">
                        <p
                            class="max-w-64 truncate text-xs font-bold text-white"
                        >
                            {title}
                        </p>
                        <p class="text-[11px] font-semibold text-white/55">
                            {qualityLabel}
                        </p>
                    </div>
                </div>

                <div
                    class="flex min-w-0 flex-wrap items-center justify-end gap-1.5 sm:shrink-0 sm:flex-nowrap sm:gap-2"
                >
                    {#if shakaReady && qualityOptions.length > 0}
                        <label class="relative">
                            <span class="sr-only">Playback quality</span>
                            <select
                                value={selectedTrackId}
                                onchange={changeQuality}
                                onclick={keepControlTap}
                                onpointerdown={keepControlTap}
                                ontouchstart={keepControlTap}
                                class="h-10 max-w-24 appearance-none rounded-full bg-white/10 py-0 pl-3 pr-8 text-xs font-bold text-white outline-none transition hover:bg-white/18 focus-visible:ring-2 focus-visible:ring-[var(--downloora-lime)] sm:max-w-32 sm:pr-9"
                                title="Playback quality"
                                aria-label="Playback quality"
                            >
                                <option
                                    class="bg-neutral-950 text-white"
                                    value="auto"
                                >
                                    Auto
                                </option>
                                {#each qualityOptions as track (track.id)}
                                    <option
                                        class="bg-neutral-950 text-white"
                                        value={track.id}
                                    >
                                        {formatQuality(track)}
                                    </option>
                                {/each}
                            </select>
                            <Settings2
                                class="pointer-events-none absolute right-2.5 top-1/2 size-4 -translate-y-1/2 text-white/70 sm:right-3"
                            />
                        </label>
                    {:else}
                        <span
                            class="hidden rounded-full bg-white/10 px-3 py-2 text-xs font-bold text-white/80 min-[380px]:inline"
                            title={streamTitle}
                        >
                            Direct
                        </span>
                    {/if}

                    <button
                        type="button"
                        onclick={(event) =>
                            activateControl(event, cyclePlaybackRate)}
                        class="flex h-10 min-w-10 items-center justify-center rounded-full bg-white/10 px-2 text-xs font-bold text-white tabular-nums transition hover:bg-white/18 sm:min-w-12 sm:px-3"
                        title="Playback speed"
                        aria-label="Playback speed"
                    >
                        {playbackRate}x
                    </button>

                    <button
                        type="button"
                        onclick={(event) => activateControl(event, castToTv)}
                        class={`flex size-10 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/18 ${castStatus === 'connected' ? 'bg-[var(--downloora-lime)] text-[var(--downloora-ink)]' : !castPickerAvailable && castSupportChecked ? 'opacity-70' : ''}`}
                        title={castTitle}
                        aria-label={castTitle}
                        disabled={castStatus === 'connecting'}
                    >
                        <Cast class="size-5" />
                    </button>

                    {#if pictureInPictureAvailable}
                        <button
                            type="button"
                            onclick={(event) =>
                                activateControl(event, togglePictureInPicture)}
                            class="hidden size-10 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/18 sm:flex"
                            title="Picture in picture"
                            aria-label="Picture in picture"
                        >
                            <PictureInPicture class="size-5" />
                        </button>
                    {/if}

                    <button
                        type="button"
                        onclick={(event) =>
                            activateControl(event, toggleFullscreen)}
                        class="flex size-10 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/18"
                        title={fullscreen ? 'Exit fullscreen' : 'Fullscreen'}
                        aria-label={fullscreen
                            ? 'Exit fullscreen'
                            : 'Fullscreen'}
                    >
                        {#if fullscreen}
                            <Minimize class="size-5" />
                        {:else}
                            <Maximize class="size-5" />
                        {/if}
                    </button>
                </div>
            </div>
        </div>
    {/if}
</div>
