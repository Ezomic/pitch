<script setup lang="ts">
import { Pause, Play, RotateCcw } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import type { TimelineFrame } from '@/types/match';

const props = defineProps<{
    timeline: TimelineFrame[];
    homeName: string;
    awayName: string;
}>();

const PAD_X = 4; // keep the ball inside the touchlines / behind the goals
const PAD_Y = 8;
const BASE_MS = 150;

const reduceMotion =
    typeof window !== 'undefined' &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const index = ref(0);
const playing = ref(false);
const speed = ref(1);
let timer: number | null = null;

const durMs = computed(() => BASE_MS / speed.value);
const last = computed(() => Math.max(0, props.timeline.length - 1));
const cur = computed<TimelineFrame | null>(
    () => props.timeline[index.value] ?? null,
);

const left = computed(() =>
    cur.value ? PAD_X + cur.value.x * (100 - 2 * PAD_X) : 50,
);
const top = computed(() =>
    cur.value ? PAD_Y + cur.value.y * (100 - 2 * PAD_Y) : 50,
);
const ballTransition = computed(() =>
    reduceMotion || !cur.value || cur.value.start ? '0ms' : `${durMs.value}ms`,
);

const minute = computed(() => cur.value?.m ?? 0);

const score = computed(() => {
    let h = 0;
    let a = 0;
    for (let i = 0; i <= index.value && i < props.timeline.length; i++) {
        const f = props.timeline[i];
        if (f.goal) f.s === 0 ? h++ : a++;
    }
    return { h, a };
});

const caption = computed(() => {
    const f = cur.value;
    if (!f) return '';
    if (f.goal) return `GOAL${f.who ? ` — ${f.who}` : ''}`;
    if (f.t === 'shot') return `Shot${f.who ? ` — ${f.who}` : ''}`;
    return f.t.charAt(0).toUpperCase() + f.t.slice(1);
});

const sideName = computed(() =>
    cur.value?.s === 1 ? props.awayName : props.homeName,
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
        if (!playing.value) return;
        if (index.value >= last.value) {
            playing.value = false;
            return;
        }
        index.value++;
        schedule();
    }, durMs.value);
}

function play() {
    if (props.timeline.length === 0) return;
    if (index.value >= last.value) index.value = 0;
    playing.value = true;
    schedule();
}

function pause() {
    playing.value = false;
    clear();
}

function toggle() {
    playing.value ? pause() : play();
}

function restart() {
    pause();
    index.value = 0;
}

function cycleSpeed() {
    speed.value = speed.value === 1 ? 2 : speed.value === 2 ? 4 : 1;
}

function onScrub(event: Event) {
    pause();
    index.value = Number((event.target as HTMLInputElement).value);
}

onBeforeUnmount(clear);
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

            <!-- Ball -->
            <div
                v-if="cur"
                class="absolute z-10 -translate-x-1/2 -translate-y-1/2"
                :style="{
                    left: `${left}%`,
                    top: `${top}%`,
                    transition: `left ${ballTransition} linear, top ${ballTransition} linear`,
                }"
            >
                <span
                    class="block rounded-full shadow"
                    :class="[
                        cur.s === 0 ? 'bg-white' : 'bg-amber-400',
                        cur.goal
                            ? 'h-4 w-4 ring-4 ring-white/50'
                            : cur.t === 'shot'
                              ? 'h-3.5 w-3.5 ring-2 ring-white/40'
                              : 'h-2.5 w-2.5',
                    ]"
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

            <!-- Clock + caption -->
            <div
                class="absolute top-2 left-2 rounded-md bg-black/45 px-2 py-0.5 font-mono text-xs text-white tabular-nums"
            >
                {{ minute }}'
            </div>
            <div
                class="absolute right-2 bottom-2 max-w-[70%] truncate rounded-md bg-black/45 px-2 py-0.5 text-right font-mono text-xs text-white"
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
