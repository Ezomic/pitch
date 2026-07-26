<script setup lang="ts">
import { Pause, Play, RotateCcw, Zap } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
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
const SEG_MS = 620; // base time per event at 1×; the clock runs continuously

const reduceMotion =
    typeof window !== 'undefined' &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// A single continuous playhead (in frame units) drives the whole replay, so the
// ball and players glide from one event into the next instead of stopping on
// each. `index` is just the frame the playhead currently sits in.
const playhead = ref(0);
const playing = ref(false);
const speed = ref(1);
const highlights = ref(false);
let raf: number | null = null;
let lastTs: number | null = null;

// Normalised (0..1) pitch coords → percentage inside the padded playing area.
const px = (n: number) => PAD_X + n * (100 - 2 * PAD_X);
const py = (n: number) => PAD_Y + n * (100 - 2 * PAD_Y);

const count = computed(() => props.timeline.length);
const index = computed(() =>
    Math.min(Math.floor(playhead.value), Math.max(0, count.value - 1)),
);
const cur = computed<TimelineFrame | null>(
    () => props.timeline[index.value] ?? null,
);

// A small, stable per-point offset so the ball doesn't snap between a handful of
// zone centres. Deterministic, so the replay stays reproducible.
const jitter = (i: number, axis: number) => {
    const h = Math.sin(i * 12.9898 + axis * 78.233) * 43758.5453;

    return (h - Math.floor(h) - 0.5) * 5; // ±2.5% of the pitch
};
const clampPct = (v: number, pad: number) =>
    Math.max(pad, Math.min(100 - pad, v));

// The ball's path as a continuous chain of points: the very first origin, then
// each event's destination, each nudged off its zone centre. The ball flows
// through them without ever stopping.
const keypoints = computed<{ x: number; y: number }[]>(() => {
    const tl = props.timeline;

    if (tl.length === 0) {
        return [];
    }

    const raw: [number, number][] = [[tl[0].x1, tl[0].y1]];

    for (const f of tl) {
        raw.push([f.x2, f.y2]);
    }

    return raw.map(([nx, ny], i) => ({
        x: clampPct(px(nx) + jitter(i, 1), 2),
        y: clampPct(py(ny) + jitter(i, 2), 3),
    }));
});

// A Catmull-Rom pass so the ball weaves smoothly through the keypoints rather
// than kinking at each one.
const catmull = (p0: number, p1: number, p2: number, p3: number, t: number) => {
    const t2 = t * t;
    const t3 = t2 * t;

    return (
        0.5 *
        (2 * p1 +
            (-p0 + p2) * t +
            (2 * p0 - 5 * p1 + 4 * p2 - p3) * t2 +
            (-p0 + 3 * p1 - 3 * p2 + p3) * t3)
    );
};

// The ball position at any point on the timeline: a spline in open play, a
// straight fizz for a shot. Shared by the live ball and its trailing ghosts.
function ballPointAt(ph: number): {
    x: number;
    y: number;
    seg: number;
    t: number;
} {
    const kp = keypoints.value;

    if (kp.length < 2) {
        return { x: 50, y: 50, seg: 0, t: 0 };
    }

    const seg = Math.min(Math.floor(ph), kp.length - 2);
    const t = Math.min(1, Math.max(0, ph - seg));
    const a = kp[seg];
    const b = kp[seg + 1];
    const type = props.timeline[seg]?.t ?? 'pass';
    const isShot = type === 'shot' || type === 'header';

    if (Math.hypot(b.x - a.x, b.y - a.y) < 0.5) {
        return { x: b.x, y: b.y, seg, t };
    }

    const e = isShot ? t * t : type === 'dribble' ? t : 1 - (1 - t) * (1 - t);

    if (isShot) {
        return { x: a.x + (b.x - a.x) * e, y: a.y + (b.y - a.y) * e, seg, t };
    }

    const p0 = kp[seg - 1] ?? a;
    const p3 = kp[seg + 2] ?? b;

    return {
        x: catmull(p0.x, a.x, b.x, p3.x, e),
        y: catmull(p0.y, a.y, b.y, p3.y, e),
        seg,
        t,
    };
}

// The live ball, lofted and scaled by the kind of ball it is.
const ball = computed(() => {
    const p = ballPointAt(playhead.value);
    const type = props.timeline[p.seg]?.t ?? 'pass';
    const isShot = type === 'shot' || type === 'header';

    if (reduceMotion) {
        return { x: p.x, y: p.y, scale: 1 };
    }

    const isCross = type === 'cross';
    const loft = isShot ? 0 : isCross ? 0.6 : 0.25;
    const e = isShot
        ? p.t * p.t
        : type === 'dribble'
          ? p.t
          : 1 - (1 - p.t) * (1 - p.t);

    return {
        x: p.x,
        y: p.y,
        scale: 1 + (isShot ? 0.4 * e : loft * Math.sin(Math.PI * e)),
    };
});

// A short fading trail behind the ball to sell its speed and direction.
const trail = computed(() =>
    reduceMotion
        ? []
        : [0.05, 0.11, 0.18].map((d) =>
              ballPointAt(Math.max(0, playhead.value - d)),
          ),
);

const from = computed(() =>
    cur.value ? { x: px(cur.value.x1), y: py(cur.value.y1) } : { x: 50, y: 50 },
);
const to = computed(() =>
    cur.value ? { x: px(cur.value.x2), y: py(cur.value.y2) } : { x: 50, y: 50 },
);
const isMoving = computed(
    () =>
        !!cur.value &&
        (cur.value.x1 !== cur.value.x2 || cur.value.y1 !== cur.value.y2),
);

const minute = computed(() => cur.value?.m ?? 0);

const score = computed(() => {
    let h = 0;
    let a = 0;

    for (let i = 0; i <= index.value && i < count.value; i++) {
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

// The 22 living players, each interpolated continuously between its position at
// the surrounding frames, so the shape drifts smoothly rather than snapping.
interface LivePlayer {
    key: number;
    x: number;
    y: number;
    s: 0 | 1;
    slot: number;
    gk: boolean;
    name: string | null;
    ball: boolean;
    target: boolean;
}

const smooth = (t: number) => t * t * (3 - 2 * t);

const players = computed<LivePlayer[]>(() => {
    const pos = props.positions;
    const lu = props.lineups;

    if (lu.length === 0) {
        return [];
    }

    const nMax = Math.max(0, count.value - 1);
    const seg = Math.min(Math.floor(playhead.value), nMax);
    const t = smooth(Math.min(1, Math.max(0, playhead.value - seg)));
    const a = pos[seg];
    const b = pos[Math.min(seg + 1, nMax)] ?? a;
    const carrier = a?.b ?? -1;

    const out = lu.map((line, i) => {
        const pa = a?.p[i];
        const pb = b?.p[i] ?? pa;

        return {
            key: i,
            x: pa && pb ? px(pa[0] + (pb[0] - pa[0]) * t) : px(line.x),
            y: pa && pb ? py(pa[1] + (pb[1] - pa[1]) * t) : py(line.y),
            s: line.s,
            slot: line.slot,
            gk: line.gk,
            name: line.name,
            ball: carrier === i,
            target: false,
        };
    });

    // Pin the two players involved in this event onto the ball's path, so the
    // ball leaves the real carrier and arrives at the real receiver.
    const kp = keypoints.value;
    const frame = props.timeline[seg];
    const isShot = frame && (frame.t === 'shot' || frame.t === 'header');

    if (out[carrier] && kp[seg]) {
        out[carrier].x = kp[seg].x;
        out[carrier].y = kp[seg].y;
    }

    const receiver = b?.b ?? -1;

    if (!isShot && out[receiver] && kp[seg + 1]) {
        out[receiver].x = kp[seg + 1].x;
        out[receiver].y = kp[seg + 1].y;
        out[receiver].target = true;
    }

    // The keeper dives across to meet a shot he saves.
    if (frame?.t === 'save' && kp[seg]) {
        const gk = out.find((p) => p.gk && p.s === frame.s);

        if (gk) {
            gk.x += (kp[seg].x - gk.x) * 0.6;
            gk.y += (kp[seg].y - gk.y) * 0.6;
        }
    }

    return out;
});

// A broadcast-camera drift toward the ball; it zooms in and tracks harder on a
// shot or a goal so the big moments land.
const camera = computed(() => {
    if (reduceMotion) {
        return 'none';
    }

    const f = cur.value;
    const punch = !!f && (f.t === 'shot' || f.t === 'header' || f.goal);
    const zoom = punch ? 1.18 : 1.06;
    const k = punch ? 0.09 : 0.06;
    const cap = punch ? 4 : 2.6;
    const clampDrift = (v: number) => Math.max(-cap, Math.min(cap, v));

    return `translate(${clampDrift((50 - ball.value.x) * k)}%, ${clampDrift(
        (50 - ball.value.y) * k,
    )}%) scale(${zoom})`;
});

// The goal the scoring side is attacking, so the net ripples on the right spot.
const goalSide = computed(() => (cur.value?.s === 1 ? 'left' : 'right'));

// The celebration fires only once the ball has actually hit the net, not the
// moment the strike leaves the boot.
const goalHit = computed(
    () =>
        !!cur.value?.goal && playhead.value - Math.floor(playhead.value) > 0.55,
);

// The chances and goals, plus a little run-up and follow-through, that
// highlights mode plays while it skims everything in between.
const highlightSegs = computed(() => {
    const set = new Set<number>();
    props.timeline.forEach((f, i) => {
        if (f.goal || f.t === 'shot' || f.t === 'header') {
            for (let d = -3; d <= 1; d++) {
                set.add(i + d);
            }
        }
    });

    return set;
});

function loop(ts: number) {
    if (!playing.value) {
        return;
    }

    if (lastTs === null) {
        lastTs = ts;
    }

    // Clamp the step so returning to a backgrounded tab resumes smoothly rather
    // than leaping forward by the whole time it was hidden.
    const dt = Math.min(ts - lastTs, 64);
    lastTs = ts;

    // A settled ball (a tackle, a clearance, a corner) is passed through quickly
    // so the replay lingers on movement, not on dead moments.
    const kp = keypoints.value;
    const seg = Math.min(
        Math.floor(playhead.value),
        Math.max(0, kp.length - 2),
    );
    const a = kp[seg];
    const b = kp[seg + 1];
    const frame = props.timeline[seg];
    const still = a && b && Math.hypot(b.x - a.x, b.y - a.y) < 0.5;
    const isShot = frame?.t === 'shot' || frame?.t === 'header';

    let pace: number;

    if (highlights.value && !highlightSegs.value.has(seg)) {
        pace = 9; // blitz through the routine stuff between chances
    } else if (frame?.goal) {
        pace = 0.5; // linger on the goal and the celebration
    } else if (isShot) {
        pace = 0.65; // savour the strike
    } else if (still) {
        pace = 1.6; // gently skim a settled ball
    } else {
        pace = 1;
    }

    playhead.value = Math.min(
        count.value,
        playhead.value + (dt / SEG_MS) * speed.value * pace,
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
    speed.value =
        speed.value === 1
            ? 2
            : speed.value === 2
              ? 4
              : speed.value === 4
                ? 8
                : 1;
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
            <!-- Camera: the pitch, players and ball drift gently toward the ball -->
            <div
                class="absolute inset-0 origin-center will-change-transform"
                :style="{
                    transform: camera,
                    transition: reduceMotion
                        ? 'none'
                        : 'transform 320ms ease-out',
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
                    :style="{ left: `${p.x}%`, top: `${p.y}%` }"
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
                        v-if="(p.ball || p.target) && p.name"
                        class="absolute top-5 left-1/2 -translate-x-1/2 rounded px-1 py-px text-[10px] leading-tight font-medium whitespace-nowrap"
                        :class="
                            p.ball
                                ? 'bg-black/55 text-white'
                                : 'bg-black/40 text-white/80'
                        "
                        >{{ p.name }}</span
                    >
                </div>

                <!-- Fading ball trail -->
                <div
                    v-for="(g, i) in trail"
                    :key="`tr-${i}`"
                    class="pointer-events-none absolute z-[15] size-1.5 -translate-x-1/2 -translate-y-1/2 rounded-full bg-white"
                    :style="{
                        left: `${g.x}%`,
                        top: `${g.y}%`,
                        opacity: 0.28 - i * 0.08,
                    }"
                ></div>

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
                    v-if="goalHit"
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
                v-if="goalHit"
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
                :max="count"
                step="any"
                :value="playhead"
                aria-label="Match timeline"
                class="h-1.5 flex-1 cursor-pointer appearance-none rounded-full bg-secondary accent-primary"
                @input="onScrub"
            />
            <button
                type="button"
                class="flex size-9 shrink-0 items-center justify-center rounded-full border transition-colors"
                :class="
                    highlights
                        ? 'border-primary bg-primary/15 text-primary'
                        : 'border-border text-muted-foreground hover:text-foreground'
                "
                :aria-label="
                    highlights ? 'Play the full match' : 'Play highlights only'
                "
                :aria-pressed="highlights"
                @click="highlights = !highlights"
            >
                <Zap class="size-4" />
            </button>
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
