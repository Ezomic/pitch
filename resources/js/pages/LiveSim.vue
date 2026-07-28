<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Pause, Play, RotateCcw } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

interface Frame {
    m: number;
    b: [number, number];
    c: number;
    s: 0 | 1;
    p: [number, number][];
    j?: boolean; // ball placed (kickoff/set piece): snap, don't glide
    goal?: number; // side that scored on this frame (ball is in the net), else -1
}
interface PlayerMeta {
    s: 0 | 1;
    slot: number;
    name: string | null;
    gk: boolean;
}
interface MomentWhy {
    decision: {
        optionsVisible: number;
        optionsTotal: number;
        chosenThreat: number;
        bestThreat: number;
        gap: number;
    } | null;
    roll: {
        threshold: number;
        draw: number;
        succeeded: boolean;
        attribute: number;
        pressure: number;
    } | null;
}
interface Moment {
    minute: number;
    side: 0 | 1;
    kind: string;
    text: string;
    why?: MomentWhy | null;
}
interface BenchPlayer {
    id: number;
    name: string;
    position: string;
}

const props = defineProps<{
    matchId: number;
    players: PlayerMeta[];
    homeName: string;
    awayName: string;
    totalTicks: number;
    subsRemaining: number;
    onPitch: { slot: number; name: string }[];
    bench: BenchPlayer[];
}>();

defineOptions({ layout: { breadcrumbs: [{ title: 'Play', href: '/play' }] } });

const PAD_X = 5;
const PAD_Y = 6; // near-symmetric with PAD_X so full-width shapes are not pinched
const SEG_MS = 210;
const CHUNK = 300; // ticks per fetch (~75 keyframes)
const PREFETCH = 24; // keyframes of headroom before fetching more

const meta = ref<PlayerMeta[]>(props.players.map((p) => ({ ...p })));
const frames = ref<Frame[]>([]);
const feed = ref<Moment[]>([]);
const score = ref({ h: 0, a: 0 });
// Frame indices whose goal has already been counted/celebrated, so replaying or
// reviewing a goal never double-counts the score.
const celebratedGoals = new Set<number>();
const subsLeft = ref(props.subsRemaining);
const mentality = ref<'attacking' | 'balanced' | 'defensive'>('balanced');

const playhead = ref(0);
const playing = ref(false);
const speed = ref(1);
const finished = ref(false);
const serverTick = ref(0);
const celebrating = ref(false);
const celebrationText = ref('');
const reviewingMinute = ref<number | null>(null);
const openWhy = ref<Moment | null>(null);

function toggleWhy(m: Moment): void {
    if (!m.why) {
        return;
    }

    openWhy.value = openWhy.value === m ? null : m;
}
let fetching = false;
let raf: number | null = null;
let lastTs: number | null = null;
let celebrationTimer: number | null = null;

const outSlot = ref<number | null>(null);
const inPlayer = ref<number | null>(null);

const count = computed(() => frames.value.length);
const seg = computed(() =>
    Math.min(Math.floor(playhead.value), Math.max(0, count.value - 1)),
);
const frac = computed(() =>
    Math.min(1, Math.max(0, playhead.value - seg.value)),
);
const cur = computed(() => frames.value[seg.value] ?? null);

const px = (n: number) => PAD_X + n * (100 - 2 * PAD_X);
const py = (n: number) => PAD_Y + n * (100 - 2 * PAD_Y);
const lerp = (a: number, b: number, t: number) => a + (b - a) * t;

const ball = computed(() => {
    const a = frames.value[seg.value];
    const b = frames.value[seg.value + 1];

    if (!a) {
        return { x: 50, y: 50 };
    }

    if (!b) {
        return { x: px(a.b[0]), y: py(a.b[1]) };
    }

    // The next frame is a placed ball (kickoff / set piece): hold on the current
    // spot instead of gliding across the pitch, so a goal stays in the net until
    // the restart and a set-piece ball doesn't drift untouched.
    if (b.j) {
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

const livePlayers = computed<LivePlayer[]>(() => {
    const a = frames.value[seg.value];
    const b = frames.value[seg.value + 1];

    if (!a) {
        return [];
    }

    return meta.value.map((m, i) => {
        const pa = a.p[i];
        const pb = b?.p[i] ?? pa;

        return {
            key: i,
            x: px(lerp(pa[0], pb[0], frac.value)),
            y: py(lerp(pa[1], pb[1], frac.value)),
            s: m.s,
            slot: m.slot,
            gk: m.gk,
            name: m.name,
            ball: a.c === i,
        };
    });
});

const clock = computed(() => {
    const minute = cur.value?.m ?? 0;
    const secs = Math.floor(frac.value * 60);

    return `${minute}:${secs.toString().padStart(2, '0')}`;
});

// The caption shows the latest commentary line, so its team label must come from
// that moment's side, not from whoever happens to be in possession in the frame
// currently on screen. Otherwise an opposition line gets stamped "Your squad".
const captionMoment = computed(() => feed.value[0] ?? null);
const caption = computed(() => captionMoment.value?.text ?? 'Kick-off');
const captionSide = computed<0 | 1>(() => captionMoment.value?.side ?? 0);
const sideName = computed(() =>
    captionSide.value === 1 ? props.awayName : props.homeName,
);

// The moments worth reviewing: goals, clear chances, big saves and penalties.
const MAJOR_KINDS = new Set(['goal', 'chance', 'save']);
function isMajor(m: Moment): boolean {
    return MAJOR_KINDS.has(m.kind) || /penalt/i.test(m.text);
}

// feed is newest-first; keep that order so the latest highlight sits on top.
const majorEvents = computed(() => feed.value.filter(isMajor));

// How many keyframes of run-up to rewind so the chance is seen developing.
const REVIEW_LEAD = 12;

// Jump the replay back to a moment and play it from just before it happened.
function review(minute: number): void {
    const target = frames.value.findIndex((f) => f.m >= minute);

    if (target < 0) {
        return;
    }

    if (celebrationTimer !== null) {
        window.clearTimeout(celebrationTimer);
        celebrationTimer = null;
    }

    celebrating.value = false;
    reviewingMinute.value = minute;
    playhead.value = Math.max(0, target - REVIEW_LEAD);
    lastTs = null;
    void play();
}

function xsrf(): string {
    const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return m ? decodeURIComponent(m[1]) : '';
}

async function postJson(
    url: string,
    body: Record<string, unknown>,
): Promise<Record<string, unknown>> {
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrf(),
        },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });

    return res.json();
}

async function fetchNext(): Promise<void> {
    if (fetching || finished.value) {
        return;
    }

    fetching = true;

    try {
        const res = (await postJson(`/play/${props.matchId}/advance`, {
            ticks: CHUNK,
        })) as {
            frames: Frame[];
            moments: Moment[];
            goals: { minute: number; side: 0 | 1 }[];
            tick: number;
            finished: boolean;
        };
        frames.value.push(...res.frames);

        for (const m of res.moments) {
            feed.value.unshift(m);
        }

        serverTick.value = res.tick;

        if (res.finished) {
            finished.value = true;
        }
    } finally {
        fetching = false;
    }
}

// Fire the goal the moment the ball reaches the net (the frame the engine marks),
// freezing the playhead on it so the ball is seen in the goal before the
// announcement, then the celebration resumes into the restart.
function scanGoals(from: number, to: number): boolean {
    for (let i = Math.max(0, from); i <= to; i++) {
        const f = frames.value[i];
        const side = f?.goal ?? -1;

        if (side >= 0 && !celebratedGoals.has(i)) {
            celebratedGoals.add(i);

            if (side === 0) {
                score.value.h++;
            } else {
                score.value.a++;
            }

            playhead.value = i; // hold on the ball-in-net frame
            celebrate(side as 0 | 1);

            return true;
        }
    }

    return false;
}

function celebrate(side: 0 | 1): void {
    celebrating.value = true;
    celebrationText.value = side === 0 ? props.homeName : props.awayName;

    if (celebrationTimer !== null) {
        window.clearTimeout(celebrationTimer);
    }

    celebrationTimer = window.setTimeout(() => {
        celebrating.value = false;
        celebrationTimer = null;
        lastTs = null; // avoid a jump when play resumes
    }, 5000);
}

function loop(ts: number): void {
    if (!playing.value) {
        return;
    }

    if (lastTs === null) {
        lastTs = ts;
    }

    const dt = Math.min(ts - lastTs, 64);
    lastTs = ts;

    const max = count.value - 1;

    // Freeze the pitch during a goal celebration, then resume for the restart.
    if (!celebrating.value) {
        const prev = Math.floor(playhead.value);
        playhead.value = Math.min(
            max,
            playhead.value + (dt / SEG_MS) * speed.value,
        );

        // Any frame newly crossed this step may be the goal; scanGoals freezes on
        // it and celebrates, so nothing is skipped even at higher speeds.
        scanGoals(prev, Math.floor(playhead.value));
    }

    if (
        !finished.value &&
        !fetching &&
        count.value - playhead.value < PREFETCH
    ) {
        void fetchNext();
    }

    if (finished.value && playhead.value >= max) {
        playing.value = false;
        lastTs = null;

        return;
    }

    raf = requestAnimationFrame(loop);
}

async function play(): Promise<void> {
    if (finished.value && playhead.value >= count.value - 1) {
        return;
    }

    if (count.value === 0) {
        await fetchNext();
    }

    playing.value = true;
    lastTs = null;
    raf = requestAnimationFrame(loop);
}

function pause(): void {
    playing.value = false;

    if (raf !== null) {
        cancelAnimationFrame(raf);
        raf = null;
    }

    lastTs = null;
}

function toggle(): void {
    reviewingMinute.value = null;

    if (playing.value) {
        pause();
    } else {
        void play();
    }
}

function cycleSpeed(): void {
    speed.value = speed.value === 1 ? 2 : speed.value === 2 ? 4 : 1;
}

async function setMentality(
    m: 'attacking' | 'balanced' | 'defensive',
): Promise<void> {
    mentality.value = m;
    await postJson(`/play/${props.matchId}/mentality`, { mentality: m });
}

const benchList = ref<BenchPlayer[]>(props.bench.map((b) => ({ ...b })));
const onPitchList = ref(props.onPitch.map((p) => ({ ...p })));

async function makeSub(): Promise<void> {
    if (
        outSlot.value === null ||
        inPlayer.value === null ||
        subsLeft.value <= 0
    ) {
        return;
    }

    const incoming = benchList.value.find((b) => b.id === inPlayer.value);
    const res = (await postJson(`/play/${props.matchId}/sub`, {
        out_slot: outSlot.value,
        player_id: inPlayer.value,
    })) as { subsRemaining: number };
    subsLeft.value = res.subsRemaining;

    if (incoming) {
        const slot = outSlot.value;
        const m = meta.value.find((x) => x.s === 0 && x.slot === slot);

        if (m) {
            m.name = incoming.name;
        }

        const op = onPitchList.value.find((x) => x.slot === slot);

        if (op) {
            op.name = incoming.name;
        }

        benchList.value = benchList.value.filter(
            (b) => b.id !== inPlayer.value,
        );
    }

    outSlot.value = null;
    inPlayer.value = null;
}

onMounted(() => {
    void fetchNext();
});
onBeforeUnmount(() => {
    pause();

    if (celebrationTimer !== null) {
        window.clearTimeout(celebrationTimer);
    }
});
</script>

<template>
    <Head title="Play a match" />

    <div class="flex flex-col gap-4 p-4 md:flex-row">
        <div class="flex flex-1 flex-col gap-3">
            <div class="flex items-center justify-between font-mono text-sm">
                <span class="truncate">{{ homeName }}</span>
                <span class="flex items-center gap-2 tabular-nums">
                    <span class="text-2xl font-bold">{{ score.h }}</span>
                    <span class="text-muted-foreground">–</span>
                    <span class="text-2xl font-bold">{{ score.a }}</span>
                </span>
                <span class="truncate text-right">{{ awayName }}</span>
            </div>

            <div
                class="relative aspect-[3/2] w-full overflow-hidden rounded-xl border border-emerald-900/40 bg-emerald-700 select-none dark:bg-emerald-800"
            >
                <div class="absolute inset-0">
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
                    </svg>

                    <div
                        v-for="p in livePlayers"
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

                    <div
                        v-if="cur"
                        class="absolute z-20 -translate-x-1/2 -translate-y-1/2"
                        :style="{ left: `${ball.x}%`, top: `${ball.y}%` }"
                    >
                        <span
                            class="block size-2 rounded-full bg-white ring-1 ring-black/30"
                            style="
                                filter: drop-shadow(
                                    0 1px 1px rgba(0, 0, 0, 0.45)
                                );
                            "
                        ></span>
                    </div>
                </div>

                <div
                    class="absolute top-2 left-2 rounded-md bg-black/45 px-2 py-0.5 font-mono text-xs text-white tabular-nums"
                >
                    {{ clock }}
                </div>
                <div
                    class="absolute right-2 bottom-2 max-w-[75%] truncate rounded-md bg-black/45 px-2 py-0.5 text-right font-mono text-xs text-white"
                >
                    <span
                        :class="
                            captionSide === 1
                                ? 'text-amber-300'
                                : 'text-emerald-200'
                        "
                        >{{ sideName }}</span
                    >
                    &middot; {{ caption }}
                </div>
                <div
                    v-if="celebrating"
                    class="absolute inset-0 flex flex-col items-center justify-center gap-1 bg-black/60 text-white"
                >
                    <span class="text-4xl font-black tracking-widest"
                        >GOAL!</span
                    >
                    <span class="font-mono text-sm">{{ celebrationText }}</span>
                    <span
                        class="mt-1 font-mono text-2xl font-bold tabular-nums"
                    >
                        {{ score.h }} – {{ score.a }}
                    </span>
                </div>
                <div
                    v-if="finished"
                    class="absolute inset-0 flex items-center justify-center bg-black/50 font-mono text-lg text-white"
                >
                    Full time · {{ score.h }} – {{ score.a }}
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button
                    type="button"
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground transition-transform hover:-translate-y-px"
                    :aria-label="playing ? 'Pause' : 'Play'"
                    @click="toggle"
                >
                    <Pause v-if="playing" class="size-5" />
                    <Play v-else class="size-5" />
                </button>
                <button
                    type="button"
                    class="w-12 shrink-0 rounded-md border border-border py-1.5 font-mono text-xs tabular-nums transition-colors hover:border-primary hover:text-primary"
                    @click="cycleSpeed"
                >
                    {{ speed }}×
                </button>
                <a
                    href="/play"
                    class="flex items-center gap-1 rounded-md border border-border px-3 py-1.5 text-xs text-muted-foreground transition-colors hover:text-foreground"
                >
                    <RotateCcw class="size-3.5" /> New match
                </a>
                <span class="ml-auto font-mono text-xs text-muted-foreground">
                    {{ finished ? 'Finished' : playing ? 'Playing' : 'Paused' }}
                </span>
            </div>
        </div>

        <!-- Management -->
        <div class="flex w-full flex-col gap-4 md:w-80">
            <div class="rounded-xl border border-border p-3">
                <h3 class="mb-2 text-sm font-semibold">Key moments</h3>
                <ul class="flex max-h-56 flex-col gap-1 overflow-y-auto">
                    <li v-for="(m, i) in majorEvents" :key="`ke-${i}`">
                        <button
                            type="button"
                            class="flex w-full items-start gap-2 rounded-md border px-2 py-1.5 text-left text-xs transition-colors"
                            :class="
                                reviewingMinute === m.minute
                                    ? 'border-primary bg-primary/10'
                                    : 'border-transparent hover:border-border hover:bg-muted/50'
                            "
                            @click="review(m.minute)"
                        >
                            <span
                                class="mt-0.5 size-2 shrink-0 rounded-full"
                                :class="{
                                    'bg-emerald-500': m.kind === 'goal',
                                    'bg-sky-500': m.kind === 'chance',
                                    'bg-violet-500': m.kind === 'save',
                                    'bg-rose-500':
                                        m.kind !== 'goal' &&
                                        m.kind !== 'chance' &&
                                        m.kind !== 'save',
                                }"
                            ></span>
                            <span
                                class="w-6 shrink-0 font-mono text-muted-foreground"
                                >{{ m.minute }}'</span
                            >
                            <span
                                class="flex-1"
                                :class="{
                                    'font-semibold': m.kind === 'goal',
                                }"
                                >{{ m.text }}</span
                            >
                            <Play
                                class="mt-0.5 size-3 shrink-0 text-muted-foreground"
                            />
                        </button>
                    </li>
                    <li
                        v-if="majorEvents.length === 0"
                        class="px-2 py-1.5 text-xs text-muted-foreground"
                    >
                        Goals and big chances will appear here.
                    </li>
                </ul>
            </div>

            <div class="rounded-xl border border-border p-3">
                <h3 class="mb-2 text-sm font-semibold">Mentality</h3>
                <div class="grid grid-cols-3 gap-1">
                    <button
                        v-for="m in [
                            'defensive',
                            'balanced',
                            'attacking',
                        ] as const"
                        :key="m"
                        type="button"
                        class="rounded-md border px-2 py-1.5 text-xs capitalize transition-colors"
                        :class="
                            mentality === m
                                ? 'border-primary bg-primary/10 text-primary'
                                : 'border-border text-muted-foreground hover:text-foreground'
                        "
                        @click="setMentality(m)"
                    >
                        {{ m }}
                    </button>
                </div>
            </div>

            <div class="rounded-xl border border-border p-3">
                <div class="mb-2 flex items-center justify-between">
                    <h3 class="text-sm font-semibold">Substitution</h3>
                    <span class="font-mono text-xs text-muted-foreground"
                        >{{ subsLeft }} left</span
                    >
                </div>
                <label class="mb-1 block text-xs text-muted-foreground"
                    >Off</label
                >
                <select
                    v-model="outSlot"
                    class="mb-2 w-full rounded-md border border-border bg-transparent px-2 py-1.5 text-xs"
                >
                    <option :value="null">Select player</option>
                    <option
                        v-for="p in onPitchList"
                        :key="p.slot"
                        :value="p.slot"
                    >
                        {{ p.slot }} · {{ p.name }}
                    </option>
                </select>
                <label class="mb-1 block text-xs text-muted-foreground"
                    >On</label
                >
                <select
                    v-model="inPlayer"
                    class="mb-2 w-full rounded-md border border-border bg-transparent px-2 py-1.5 text-xs"
                >
                    <option :value="null">Select player</option>
                    <option v-for="b in benchList" :key="b.id" :value="b.id">
                        {{ b.position }} · {{ b.name }}
                    </option>
                </select>
                <button
                    type="button"
                    class="w-full rounded-md bg-primary py-1.5 text-xs font-medium text-primary-foreground disabled:opacity-40"
                    :disabled="
                        outSlot === null || inPlayer === null || subsLeft <= 0
                    "
                    @click="makeSub"
                >
                    Make substitution
                </button>
            </div>

            <div class="rounded-xl border border-border p-3">
                <h3 class="mb-2 text-sm font-semibold">Commentary</h3>
                <ul
                    class="flex max-h-72 flex-col gap-1 overflow-y-auto text-xs"
                >
                    <li v-for="(m, i) in feed.slice(0, 40)" :key="i">
                        <button
                            type="button"
                            class="flex w-full gap-2 rounded text-left"
                            :class="
                                m.why
                                    ? 'cursor-pointer hover:text-foreground'
                                    : 'cursor-default'
                            "
                            @click="toggleWhy(m)"
                        >
                            <span
                                class="w-6 shrink-0 font-mono text-muted-foreground"
                                >{{ m.minute }}'</span
                            >
                            <span class="flex-1">{{ m.text }}</span>
                            <span
                                v-if="m.why"
                                class="shrink-0 font-mono text-[10px] text-muted-foreground"
                                >{{ openWhy === m ? '▾' : 'why' }}</span
                            >
                        </button>

                        <!-- Decision inspector: what the player saw and the draw -->
                        <div
                            v-if="openWhy === m && m.why"
                            class="mt-1 ml-8 flex flex-col gap-1 rounded-md bg-muted/50 px-2 py-1.5 font-mono text-[10px] text-muted-foreground"
                        >
                            <div v-if="m.why.decision">
                                Saw
                                <span class="text-foreground">{{
                                    m.why.decision.optionsVisible
                                }}</span>
                                of {{ m.why.decision.optionsTotal }} options ·
                                chose threat
                                <span class="text-foreground">{{
                                    m.why.decision.chosenThreat.toFixed(2)
                                }}</span>
                                · best
                                <span class="text-foreground">{{
                                    m.why.decision.bestThreat.toFixed(2)
                                }}</span>
                                <span
                                    v-if="m.why.decision.gap > 0.05"
                                    class="text-amber-500"
                                >
                                    (left {{ m.why.decision.gap.toFixed(2) }} on
                                    the table)</span
                                >
                            </div>
                            <div v-if="m.why.roll">
                                Roll
                                <span class="text-foreground">{{
                                    m.why.roll.draw.toFixed(2)
                                }}</span>
                                vs threshold
                                {{ m.why.roll.threshold.toFixed(2) }} →
                                <span
                                    :class="
                                        m.why.roll.succeeded
                                            ? 'text-emerald-500'
                                            : 'text-rose-500'
                                    "
                                    >{{
                                        m.why.roll.succeeded
                                            ? 'made it'
                                            : 'lost'
                                    }}</span
                                >
                                <span class="opacity-70">
                                    (skill +{{
                                        m.why.roll.attribute.toFixed(2)
                                    }}, pressure −{{
                                        m.why.roll.pressure.toFixed(2)
                                    }})</span
                                >
                            </div>
                        </div>
                    </li>
                    <li v-if="feed.length === 0" class="text-muted-foreground">
                        Press play to kick off.
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
