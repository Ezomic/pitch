<script setup lang="ts">
import { Pause, Play, RotateCcw } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import type {
    LineupPlayer,
    PlayerPositions,
    TimelineFrame,
} from '@/types/match';

const props = withDefaults(
    defineProps<{
        timeline: TimelineFrame[];
        homeName: string;
        awayName: string;
        lineups?: LineupPlayer[];
        positions?: PlayerPositions[];
    }>(),
    { lineups: () => [], positions: () => [] },
);

const PAD_X = 5; // keep markers inside the touchlines / behind the goals
const PAD_Y = 10;
const BASE_MS = 900; // per-event dwell at 1×; the ball travels the pass in this time

const reduceMotion =
    typeof window !== 'undefined' &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const index = ref(0);
const playing = ref(false);
const speed = ref(2);
let timer: number | null = null;
let raf: number | null = null;

const durMs = computed(() => BASE_MS / speed.value);
const last = computed(() => Math.max(0, props.timeline.length - 1));
const cur = computed<TimelineFrame | null>(
    () => props.timeline[index.value] ?? null,
);

// Normalised (0..1) pitch coords → percentage inside the padded playing area.
const px = (n: number) => PAD_X + n * (100 - 2 * PAD_X);
const py = (n: number) => PAD_Y + n * (100 - 2 * PAD_Y);

const from = computed(() =>
    cur.value ? { x: px(cur.value.x1), y: py(cur.value.y1) } : { x: 50, y: 50 },
);
const to = computed(() =>
    cur.value ? { x: px(cur.value.x2), y: py(cur.value.y2) } : { x: 50, y: 50 },
);

// The ball is animated frame by frame along a curved path so a pass arcs, a
// cross loops, a dribble carries with the player and a shot fizzes into the
// goal. `scale` lends it a little height and weight in flight.
const ball = ref({ x: 50, y: 50, scale: 1 });

function easing(type: string): (t: number) => number {
    if (type === 'shot' || type === 'header') {
        return (t) => t * t; // accelerate into the strike
    }

    if (type === 'dribble') {
        return (t) => t; // a steady carry
    }

    return (t) => 1 - (1 - t) * (1 - t); // a pass eases to its target
}

function animateBall(frame: TimelineFrame | null) {
    if (raf !== null) {
        cancelAnimationFrame(raf);
        raf = null;
    }

    if (!frame) {
        return;
    }

    const p0 = { x: from.value.x, y: from.value.y };
    const p1 = { x: to.value.x, y: to.value.y };
    const dx = p1.x - p0.x;
    const dy = p1.y - p0.y;
    const dist = Math.hypot(dx, dy);

    if (reduceMotion || dist < 0.5) {
        ball.value = { x: p1.x, y: p1.y, scale: 1 };

        return;
    }

    const isShot = frame.t === 'shot' || frame.t === 'header';
    const isCross = frame.t === 'cross';
    // A control point offset perpendicular to the pass bows its path; crosses
    // bow hardest, shots fly straight. The side alternates so play isn't lopsided.
    const bow = isShot ? 0 : dist * (isCross ? 0.28 : 0.12);
    const side = index.value % 2 === 0 ? 1 : -1;
    const cx = (p0.x + p1.x) / 2 + (-dy / dist) * bow * side;
    const cy = (p0.y + p1.y) / 2 + (dx / dist) * bow * side;

    // Longer balls travel a touch quicker; everything still lands within the frame.
    const duration = Math.min(durMs.value, 220 + dist * 9);
    const ease = easing(frame.t);
    const loft = isShot ? 0 : isCross ? 0.6 : 0.25;
    const start = performance.now();

    const step = (now: number) => {
        const t = Math.min(1, (now - start) / duration);
        const e = ease(t);
        const m = 1 - e;
        ball.value = {
            x: m * m * p0.x + 2 * m * e * cx + e * e * p1.x,
            y: m * m * p0.y + 2 * m * e * cy + e * e * p1.y,
            scale: 1 + (isShot ? 0.4 * e : loft * Math.sin(Math.PI * e)),
        };
        raf = t < 1 ? requestAnimationFrame(step) : null;
    };

    raf = requestAnimationFrame(step);
}

watch(index, () => animateBall(cur.value), { immediate: true });

const isMoving = computed(
    () =>
        !!cur.value &&
        (cur.value.x1 !== cur.value.x2 || cur.value.y1 !== cur.value.y2),
);

const minute = computed(() => cur.value?.m ?? 0);

const score = computed(() => {
    let h = 0;
    let a = 0;

    for (let i = 0; i <= index.value && i < props.timeline.length; i++) {
        const f = props.timeline[i];

        if (f.goal) {
            if (f.s === 0) {
                h++;
            } else {
                a++;
            }
        }
    }

    return { h, a };
});

// The caption is authored server-side (MatchCommentary), so the feed and the
// replay share one voice.
const caption = computed(() => cur.value?.label ?? '');

const sideName = computed(() =>
    cur.value?.s === 1 ? props.awayName : props.homeName,
);

// The 22 living players for the current frame: each lineup's identity paired
// with its position this frame (falling back to the resting formation spot when
// no motion track is available), and a flag for whoever is on the ball.
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

const players = computed<LivePlayer[]>(() => {
    const frame = props.positions[index.value];

    return props.lineups.map((line, i) => {
        const spot = frame?.p[i];

        return {
            key: i,
            x: px(spot ? spot[0] : line.x),
            y: py(spot ? spot[1] : line.y),
            s: line.s,
            slot: line.slot,
            gk: line.gk,
            name: line.name,
            ball: frame ? frame.b === i : false,
        };
    });
});

const playerTransition = computed(() =>
    reduceMotion
        ? 'none'
        : `left ${durMs.value}ms ease, top ${durMs.value}ms ease`,
);

// A gentle broadcast-camera drift toward the ball: the pitch is scaled up a
// touch and panned a few percent so the action stays roughly centred.
const clampDrift = (v: number) => Math.max(-2.6, Math.min(2.6, v));
const camera = computed(() =>
    reduceMotion
        ? 'none'
        : `translate(${clampDrift((50 - ball.value.x) * 0.06)}%, ${clampDrift(
              (50 - ball.value.y) * 0.06,
          )}%) scale(1.06)`,
);

// The goal the scoring side is attacking, so the net ripples on the right spot.
const goalSide = computed(() => (cur.value?.s === 1 ? 'left' : 'right'));

function clear() {
    if (timer !== null) {
        window.clearTimeout(timer);
        timer = null;
    }
}

function schedule() {
    clear();
    timer = window.setTimeout(() => {
        if (!playing.value) {
            return;
        }

        if (index.value >= last.value) {
            playing.value = false;

            return;
        }

        index.value++;
        schedule();
    }, durMs.value);
}

function play() {
    if (props.timeline.length === 0) {
        return;
    }

    if (index.value >= last.value) {
        index.value = 0;
    }

    playing.value = true;
    schedule();
}

function pause() {
    playing.value = false;
    clear();
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
    index.value = 0;
}

function cycleSpeed() {
    speed.value =
        speed.value === 1
            ? 2
            : speed.value === 2
              ? 4
              : speed.value === 4
                ? 8
                : 1;

    if (playing.value) {
        schedule();
    }
}

function onScrub(event: Event) {
    pause();
    index.value = Number((event.target as HTMLInputElement).value);
}

onBeforeUnmount(() => {
    clear();

    if (raf !== null) {
        cancelAnimationFrame(raf);
    }
});
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
            <!-- Camera: the pitch, players and ball drift gently toward the ball -->
            <div
                class="absolute inset-0 origin-center will-change-transform"
                :style="{ transform: camera }"
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
                    <path d="M2 0 A2 2 0 0 1 0 2" />
                    <path d="M148 0 A2 2 0 0 0 150 2" />
                    <path d="M0 98 A2 2 0 0 1 2 100" />
                    <path d="M150 98 A2 2 0 0 0 148 100" />
                    <g fill="rgba(255,255,255,0.4)" stroke="none">
                        <circle cx="75" cy="50" r="0.8" />
                        <circle cx="12" cy="50" r="0.6" />
                        <circle cx="138" cy="50" r="0.6" />
                    </g>
                    <g stroke="rgba(255,255,255,0.5)" stroke-width="1.2">
                        <line x1="0" y1="42" x2="0" y2="58" />
                        <line x1="150" y1="42" x2="150" y2="58" />
                    </g>
                </svg>

                <!-- Pass line: from the ball carrier to the receiver / goal -->
                <svg
                    v-if="cur && isMoving"
                    class="pointer-events-none absolute inset-0 h-full w-full"
                    viewBox="0 0 100 100"
                    preserveAspectRatio="none"
                >
                    <line
                        :x1="from.x"
                        :y1="from.y"
                        :x2="to.x"
                        :y2="to.y"
                        :class="
                            cur.s === 1
                                ? 'stroke-amber-300/50'
                                : 'stroke-white/50'
                        "
                        stroke-width="0.4"
                        stroke-dasharray="1.5 1.5"
                        stroke-linecap="round"
                    />
                </svg>

                <!-- The 22 living players, in kit and numbered -->
                <div
                    v-for="p in players"
                    :key="`pl-${p.key}`"
                    class="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-1/2"
                    :style="{
                        left: `${p.x}%`,
                        top: `${p.y}%`,
                        transition: playerTransition,
                    }"
                >
                    <span
                        class="flex items-center justify-center rounded-full font-semibold tabular-nums shadow-sm"
                        :class="[
                            p.gk
                                ? 'size-3 text-[6px]'
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
                        >{{ p.gk ? '' : p.slot }}</span
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
                        class="block rounded-full bg-white ring-1 ring-black/30"
                        :class="
                            cur.goal
                                ? 'size-3.5 ring-4 ring-white/50'
                                : cur.t === 'shot' || cur.t === 'header'
                                  ? 'size-3'
                                  : 'size-2'
                        "
                        :style="{
                            transform: `scale(${ball.scale})`,
                            filter: `drop-shadow(0 ${ball.scale}px ${ball.scale}px rgba(0,0,0,0.45))`,
                        }"
                    ></span>
                </div>

                <!-- Net ripple at the scoring goal -->
                <div
                    v-if="cur?.goal"
                    class="pointer-events-none absolute top-1/2 -translate-y-1/2"
                    :class="goalSide === 'right' ? 'right-0' : 'left-0'"
                >
                    <span
                        class="goal-ripple block size-4 rounded-full bg-white/50"
                    ></span>
                </div>
            </div>

            <!-- Goal celebration (fixed to the frame, no camera drift) -->
            <div
                v-if="cur?.goal"
                class="pointer-events-none absolute inset-0 flex items-center justify-center overflow-hidden"
            >
                <span class="goal-flash absolute inset-0 bg-white/25"></span>
                <span
                    class="goal-pop rounded-md bg-black/60 px-4 py-1.5 font-mono text-xl font-bold tracking-wide text-white"
                    >GOAL</span
                >
            </div>

            <!-- Clock -->
            <div
                class="absolute top-2 left-2 rounded-md bg-black/45 px-2 py-0.5 font-mono text-xs text-white tabular-nums"
            >
                {{ minute }}'
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
                :max="last"
                :value="index"
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

<style scoped>
@keyframes goalFlash {
    from {
        opacity: 0.6;
    }
    to {
        opacity: 0;
    }
}
@keyframes goalPop {
    0% {
        transform: scale(0.6);
        opacity: 0;
    }
    40% {
        transform: scale(1.12);
        opacity: 1;
    }
    100% {
        transform: scale(1);
    }
}
@keyframes goalRipple {
    0% {
        transform: scale(0.4);
        opacity: 0.8;
    }
    100% {
        transform: scale(3.4);
        opacity: 0;
    }
}
.goal-flash {
    animation: goalFlash 700ms ease-out forwards;
}
.goal-pop {
    animation: goalPop 450ms ease-out;
}
.goal-ripple {
    animation: goalRipple 750ms ease-out forwards;
}
@media (prefers-reduced-motion: reduce) {
    .goal-flash,
    .goal-pop,
    .goal-ripple {
        animation: none;
    }
}
</style>
