<script setup lang="ts">
import { computed } from 'vue';

interface Shot {
    minute: number;
    side: 0 | 1;
    actor: number;
    x: number;
    y: number;
    outcome: string;
}

const props = defineProps<{
    shots: Shot[];
    homeName: string;
    awayName: string;
}>();

// The zone grid the events are recorded on: x runs 0..5 up the acting side's
// own attacking axis, y runs 0..4 across the pitch.
const ZONE_X = 5;
const ZONE_Y = 4;

const PAD_X = 4; // keep a marker on the goal line inside the frame
const PAD_Y = 6;

interface Marker {
    key: string;
    x: number;
    y: number;
    side: 0 | 1;
    outcome: string;
    minute: number;
}

// Each side's shots are recorded from its own point of view, so the away side's
// have to be mirrored to put both onto one pitch attacking opposite ends.
const markers = computed<Marker[]>(() =>
    props.shots.map((shot, i) => {
        const advance = shot.x / ZONE_X;
        const along = shot.side === 0 ? advance : 1 - advance;

        return {
            key: `${i}-${shot.minute}-${shot.actor}`,
            x: PAD_X + along * (100 - 2 * PAD_X),
            y: PAD_Y + (shot.y / ZONE_Y) * (100 - 2 * PAD_Y),
            side: shot.side,
            outcome: shot.outcome,
            minute: shot.minute,
        };
    }),
);

function tally(side: 0 | 1, outcome: string): number {
    return props.shots.filter((s) => s.side === side && s.outcome === outcome)
        .length;
}

const legend = [
    { outcome: 'goal', label: 'Goal' },
    { outcome: 'saved', label: 'Saved' },
    { outcome: 'blocked', label: 'Blocked' },
    { outcome: 'off', label: 'Off target' },
];

// A goal is the thing you look for, so it is the only filled, full-size marker.
function radius(outcome: string): number {
    return outcome === 'goal' ? 2.1 : outcome === 'saved' ? 1.6 : 1.3;
}

function opacity(outcome: string): number {
    return outcome === 'goal' ? 1 : outcome === 'saved' ? 0.85 : 0.5;
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <div
            class="relative w-full overflow-hidden rounded-lg"
            style="aspect-ratio: 3 / 2; background: rgba(16, 122, 74, 0.16)"
        >
            <svg
                class="absolute inset-0 h-full w-full"
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
            </svg>

            <span
                v-for="m in markers"
                :key="m.key"
                class="pointer-events-none absolute -translate-x-1/2 -translate-y-1/2 rounded-full"
                :class="
                    m.side === 0
                        ? m.outcome === 'goal'
                            ? 'bg-primary'
                            : 'border border-primary bg-primary/30'
                        : m.outcome === 'goal'
                          ? 'bg-foreground'
                          : 'border border-foreground bg-foreground/25'
                "
                :style="{
                    left: `${m.x}%`,
                    top: `${m.y}%`,
                    width: `${radius(m.outcome) * 2}%`,
                    aspectRatio: '1',
                    opacity: opacity(m.outcome),
                }"
                :title="`${m.minute}' ${m.outcome}`"
            />
        </div>

        <div
            class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 text-[11px] text-muted-foreground"
        >
            <span class="flex items-center gap-1">
                <span class="size-2 rounded-full bg-primary" />
                {{ homeName }}
            </span>
            <span
                v-for="l in legend"
                :key="l.outcome"
                class="font-mono tabular-nums"
            >
                {{ l.label }} {{ tally(0, l.outcome) }}–{{
                    tally(1, l.outcome)
                }}
            </span>
            <span class="flex items-center gap-1">
                <span class="size-2 rounded-full bg-foreground" />
                {{ awayName }}
            </span>
        </div>
    </div>
</template>
