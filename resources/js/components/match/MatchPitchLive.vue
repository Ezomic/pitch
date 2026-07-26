<script setup lang="ts">
import { Pause, Play, RotateCcw } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import type { PitchStream } from '@/types/match';

const props = defineProps<{
    stream: PitchStream;
    homeName: string;
    awayName: string;
}>();

const PAD_X = 5;
const PAD_Y = 10;
const SEG_MS = 55; // real time per keyframe at 1×

const reduceMotion =
    typeof window !== 'undefined' &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const playhead = ref(0);
const playing = ref(false);
const speed = ref(1);
let raf: number | null = null;
let lastTs: number | null = null;

const frames = computed(() => props.stream.frames);
const count = computed(() => frames.value.length);

const px = (n: number) => PAD_X + n * (100 - 2 * PAD_X);
const py = (n: number) => PAD_Y + n * (100 - 2 * PAD_Y);
const lerp = (a: number, b: number, t: number) => a + (b - a) * t;

const seg = computed(() =>
    Math.min(Math.floor(playhead.value), Math.max(0, count.value - 1)),
);
const frac = computed(() =>
    Math.min(1, Math.max(0, playhead.value - seg.value)),
);
const nextSeg = computed(() => Math.min(seg.value + 1, count.value - 1));
const cur = computed(() => frames.value[seg.value] ?? null);

// The ball, interpolated between the two surrounding keyframes so it glides.
const ball = computed(() => {
    const a = frames.value[seg.value];
    const b = frames.value[nextSeg.value];

    if (!a) {
        return { x: 50, y: 50 };
    }

    if (!b) {
        return { x: px(a.b[0]), y: py(a.b[1]) };
    }

    return {
        x: px(lerp(a.b[0], b.b[0], frac.value)),
        y: py(lerp(a.b[1], b.b[1], frac.value)),
    };
});

interface LivePlayer {
    key: number;
    x: number;
    y: number;
    s: 0 | 1;
    slot: number;
    gk: boolean;
    name: string | null;
    ball: boolean;
}

// All 22 players, each interpolated between the surrounding keyframes.
const players = computed<LivePlayer[]>(() => {
    const a = frames.value[seg.value];
    const b = frames.value[nextSeg.value];

    if (!a) {
        return [];
    }

    return props.stream.players.map((meta, i) => {
        const pa = a.p[i];
        const pb = b?.p[i] ?? pa;

        return {
            key: i,
            x: px(lerp(pa[0], pb[0], frac.value)),
            y: py(lerp(pa[1], pb[1], frac.value)),
            s: meta.s,
            slot: meta.slot,
            gk: meta.gk,
            name: meta.name,
            ball: a.c === i,
        };
    });
});

const minute = computed(() => cur.value?.m ?? 0);

const clock = computed(() => {
    const total = count.value;
    const f = total > 0 ? Math.min(playhead.value, total) / total : 0;
    const secs = Math.floor(f * 90 * 60);

    return `${Math.floor(secs / 60)}:${(secs % 60).toString().padStart(2, '0')}`;
});

// The score ticks up as the clock passes each goal.
const score = computed(() => {
    let h = 0;
    let a = 0;

    for (const g of props.stream.goals) {
        if (g.m <= minute.value) {
            if (g.s === 0) {
                h++;
            } else {
                a++;
            }
        }
    }

    return { h, a };
});

const caption = computed(() => cur.value?.cap ?? '');
const sideName = computed(() =>
    cur.value?.s === 1 ? props.awayName : props.homeName,
);

// A gentle broadcast drift toward the ball.
const camera = computed(() => {
    if (reduceMotion) {
        return 'none';
    }

    const cap = 3;
    const clamp = (v: number) => Math.max(-cap, Math.min(cap, v));

    return `translate(${clamp((50 - ball.value.x) * 0.06)}%, ${clamp(
        (50 - ball.value.y) * 0.06,
    )}%) scale(1.06)`;
});

function loop(ts: number) {
    if (!playing.value) {
        return;
    }

    if (lastTs === null) {
        lastTs = ts;
    }

    const dt = Math.min(ts - lastTs, 64);
    lastTs = ts;

    playhead.value = Math.min(
        count.value,
        playhead.value + (dt / SEG_MS) * speed.value,
    );

    if (playhead.value >= count.value) {
        playing.value = false;
        lastTs = null;

        return;
    }

    raf = requestAnimationFrame(loop);
}

function play() {
    if (count.value === 0) {
        return;
    }

    if (playhead.value >= count.value) {
        playhead.value = 0;
    }

    playing.value = true;
    lastTs = null;
    raf = requestAnimationFrame(loop);
}

function pause() {
    playing.value = false;

    if (raf !== null) {
        cancelAnimationFrame(raf);
        raf = null;
    }

    lastTs = null;
}

function toggle() {
    if (playing.value) {
        pause();
    } else {
        play();
    }
}

function restart() {
    pause();
    playhead.value = 0;
}

function cycleSpeed() {
    speed.value = speed.value === 1 ? 2 : speed.value === 2 ? 4 : 1;
}

function onScrub(event: Event) {
    pause();
    playhead.value = Number((event.target as HTMLInputElement).value);
}

onBeforeUnmount(pause);
</script>

<template>
    <div class="flex flex-col gap-3">
        <!-- Scoreboard -->
        <div class="flex items-center justify-between font-mono text-sm">
            <span class="truncate">{{ props.homeName }}</span>
            <span class="flex items-center gap-2 tabular-nums">
                <span class="text-lg font-bold">{{ score.h }}</span>
                <span class="text-muted-foreground">–</span>
                <span class="text-lg font-bold">{{ score.a }}</span>
            </span>
            <span class="truncate text-right">{{ props.awayName }}</span>
        </div>

        <!-- Pitch -->
        <div
            class="relative aspect-[3/2] w-full overflow-hidden rounded-xl border border-emerald-900/40 bg-emerald-700 select-none dark:bg-emerald-800"
        >
            <div
                class="absolute inset-0 origin-center will-change-transform"
                :style="{
                    transform: camera,
                    transition: reduceMotion
                        ? 'none'
                        : 'transform 200ms ease-out',
                }"
            >
                <!-- Mowing stripes -->
                <div
                    class="pointer-events-none absolute inset-0"
                    style="
                        background: repeating-linear-gradient(
                            90deg,
                            rgba(255, 255, 255, 0.05) 0 8.3333%,
                            transparent 8.3333% 16.6666%
                        );
                    "
                ></div>

                <!-- Pitch markings -->
                <svg
                    class="pointer-events-none absolute inset-0 h-full w-full"
                    viewBox="0 0 150 100"
                    preserveAspectRatio="none"
                    fill="none"
                    stroke="rgba(255,255,255,0.25)"
                    stroke-width="0.4"
                >
                    <rect x="0.5" y="0.5" width="149" height="99" />
                    <line x1="75" y1="0" x2="75" y2="100" />
                    <circle cx="75" cy="50" r="13" />
                    <rect x="0" y="26" width="18" height="48" />
                    <rect x="132" y="26" width="18" height="48" />
                    <rect x="0" y="38" width="7" height="24" />
                    <rect x="143" y="38" width="7" height="24" />
                    <path d="M18 39 A13 13 0 0 1 18 61" />
                    <path d="M132 39 A13 13 0 0 0 132 61" />
                    <g stroke="rgba(255,255,255,0.5)" stroke-width="1.2">
                        <line x1="0" y1="42" x2="0" y2="58" />
                        <line x1="150" y1="42" x2="150" y2="58" />
                    </g>
                </svg>

                <!-- The 22 players -->
                <div
                    v-for="p in players"
                    :key="`pl-${p.key}`"
                    class="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-1/2"
                    :style="{ left: `${p.x}%`, top: `${p.y}%` }"
                >
                    <span
                        class="flex items-center justify-center rounded-full font-semibold tabular-nums shadow-sm"
                        :class="[
                            p.gk
                                ? 'size-3.5 text-[7px] ring-1 ring-black/20'
                                : p.ball
                                  ? 'size-5 text-[9px] ring-2 ring-black/50'
                                  : 'size-4 text-[7px] ring-1 ring-black/30',
                            p.s === 0
                                ? p.gk
                                    ? 'bg-lime-300 text-lime-950'
                                    : 'bg-white text-slate-900'
                                : p.gk
                                  ? 'bg-rose-300 text-rose-950'
                                  : 'bg-amber-400 text-amber-950',
                        ]"
                        >{{ p.gk ? 1 : p.slot }}</span
                    >
                    <span
                        v-if="p.ball && p.name"
                        class="absolute top-5 left-1/2 -translate-x-1/2 rounded bg-black/55 px-1 py-px text-[10px] leading-tight font-medium whitespace-nowrap text-white"
                        >{{ p.name }}</span
                    >
                </div>

                <!-- Ball -->
                <div
                    v-if="cur"
                    class="absolute z-20 -translate-x-1/2 -translate-y-1/2"
                    :style="{ left: `${ball.x}%`, top: `${ball.y}%` }"
                >
                    <span
                        class="block size-2 rounded-full bg-white ring-1 ring-black/30"
                        style="
                            filter: drop-shadow(0 1px 1px rgba(0, 0, 0, 0.45));
                        "
                    ></span>
                </div>
            </div>

            <!-- Clock -->
            <div
                class="absolute top-2 left-2 rounded-md bg-black/45 px-2 py-0.5 font-mono text-xs text-white tabular-nums"
            >
                {{ clock }}
            </div>
            <!-- Caption -->
            <div
                class="absolute right-2 bottom-2 max-w-[75%] truncate rounded-md bg-black/45 px-2 py-0.5 text-right font-mono text-xs text-white"
            >
                <span
                    :class="
                        cur?.s === 1 ? 'text-amber-300' : 'text-emerald-200'
                    "
                    >{{ sideName }}</span
                >
                &middot; {{ caption }}
            </div>
        </div>

        <!-- Controls -->
        <div class="flex items-center gap-3">
            <button
                type="button"
                class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground transition-transform hover:-translate-y-px"
                :aria-label="playing ? 'Pause' : 'Play'"
                @click="toggle"
            >
                <Pause v-if="playing" class="size-4" />
                <Play v-else class="size-4" />
            </button>
            <button
                type="button"
                class="flex size-9 shrink-0 items-center justify-center rounded-full border border-border text-muted-foreground transition-colors hover:text-foreground"
                aria-label="Restart"
                @click="restart"
            >
                <RotateCcw class="size-4" />
            </button>
            <input
                type="range"
                min="0"
                :max="count"
                step="any"
                :value="playhead"
                aria-label="Match timeline"
                class="h-1.5 flex-1 cursor-pointer appearance-none rounded-full bg-secondary accent-primary"
                @input="onScrub"
            />
            <button
                type="button"
                class="w-12 shrink-0 rounded-md border border-border py-1.5 font-mono text-xs tabular-nums transition-colors hover:border-primary hover:text-primary"
                aria-label="Playback speed"
                @click="cycleSpeed"
            >
                {{ speed }}×
            </button>
        </div>
    </div>
</template>
