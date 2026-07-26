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

// The ball is animated from `from` to `to` on every frame so a pass is visibly
// travelling from the player on the ball to the receiver.
const ball = ref({ x: 50, y: 50 });
const ballAnim = ref(false);

function place(frame: TimelineFrame | null) {
    if (raf !== null) {
        cancelAnimationFrame(raf);
        raf = null;
    }

    if (!frame) {
        return;
    }

    if (reduceMotion) {
        ball.value = { x: to.value.x, y: to.value.y };
        ballAnim.value = false;

        return;
    }

    ballAnim.value = false;
    ball.value = { x: from.value.x, y: from.value.y };
    raf = requestAnimationFrame(() => {
        raf = requestAnimationFrame(() => {
            ballAnim.value = true;
            ball.value = { x: to.value.x, y: to.value.y };
        });
    });
}

watch(index, () => place(cur.value), { immediate: true });

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
            <div class="pointer-events-none absolute inset-0">
                <!-- halfway line + centre circle -->
                <div
                    class="absolute inset-y-0 left-1/2 w-px -translate-x-1/2 bg-white/25"
                ></div>
                <div
                    class="absolute top-1/2 left-1/2 aspect-square h-1/4 -translate-x-1/2 -translate-y-1/2 rounded-full border border-white/25"
                ></div>
                <!-- penalty boxes -->
                <div
                    class="absolute inset-y-[26%] left-0 w-[12%] border-y border-r border-white/25"
                ></div>
                <div
                    class="absolute inset-y-[26%] right-0 w-[12%] border-y border-l border-white/25"
                ></div>
                <!-- goals -->
                <div
                    class="absolute inset-y-[42%] left-0 w-[2%] border-y border-r border-white/40 bg-white/10"
                ></div>
                <div
                    class="absolute inset-y-[42%] right-0 w-[2%] border-y border-l border-white/40 bg-white/10"
                ></div>
            </div>

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
                        cur.s === 1 ? 'stroke-amber-300/50' : 'stroke-white/50'
                    "
                    stroke-width="0.4"
                    stroke-dasharray="1.5 1.5"
                    stroke-linecap="round"
                />
            </svg>

            <!-- The 22 living players, each easing to its position each frame -->
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
                    class="block rounded-full"
                    :class="[
                        p.gk
                            ? 'size-2 ring-2 ring-black/40'
                            : p.ball
                              ? 'size-3 ring-2 ring-black/40'
                              : 'size-2.5 ring-1 ring-black/25',
                        p.s === 0
                            ? p.ball
                                ? 'bg-white'
                                : 'bg-white/70'
                            : p.ball
                              ? 'bg-amber-400'
                              : 'bg-amber-400/70',
                    ]"
                ></span>
                <span
                    v-if="p.ball && p.name"
                    class="absolute top-3.5 left-1/2 -translate-x-1/2 rounded bg-black/55 px-1 py-px text-[10px] leading-tight font-medium whitespace-nowrap text-white"
                    >{{ p.name }}</span
                >
            </div>

            <!-- Ball -->
            <div
                v-if="cur"
                class="absolute z-20 -translate-x-1/2 -translate-y-1/2"
                :style="{
                    left: `${ball.x}%`,
                    top: `${ball.y}%`,
                    transition: ballAnim
                        ? `left ${durMs}ms linear, top ${durMs}ms linear`
                        : 'none',
                }"
            >
                <span
                    class="block rounded-full bg-white shadow ring-1 ring-black/30"
                    :class="
                        cur.goal
                            ? 'size-3.5 ring-4 ring-white/50'
                            : cur.t === 'shot' || cur.t === 'header'
                              ? 'size-3'
                              : 'size-2'
                    "
                ></span>
            </div>

            <!-- Goal flash -->
            <div
                v-if="cur?.goal"
                class="pointer-events-none absolute inset-0 flex items-center justify-center"
            >
                <span
                    class="rounded-md bg-black/55 px-4 py-1.5 font-mono text-xl font-bold tracking-wide text-white"
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
