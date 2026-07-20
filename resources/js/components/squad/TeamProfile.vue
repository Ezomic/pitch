<script setup lang="ts">
import { ArrowDown, ArrowUp } from '@lucide/vue';
import { computed } from 'vue';
import type { SquadProfile } from '@/types/squad';

const props = defineProps<{
    profile: SquadProfile;
    previous: SquadProfile | null;
}>();

interface Metric {
    key: keyof SquadProfile;
    label: string;
    hint: string;
    higherIsBetter: boolean;
    format: (value: number) => string;
}

const metrics: Metric[] = [
    {
        key: 'meanDecisionGap',
        label: 'Decision gap',
        hint: 'How far off the best pass the team settles for. Lower is sharper.',
        higherIsBetter: false,
        format: (v) => v.toFixed(3),
    },
    {
        key: 'progressivePassShare',
        label: 'Progressive passing',
        hint: 'Share of completed passes that move play forward.',
        higherIsBetter: true,
        format: (v) => `${(v * 100).toFixed(1)}%`,
    },
    {
        key: 'chancesPer90',
        label: 'Chances / 90',
        hint: 'Shooting chances created per match.',
        higherIsBetter: true,
        format: (v) => v.toFixed(2),
    },
    {
        key: 'goalsPer90',
        label: 'Goals / 90',
        hint: 'Goals scored per match.',
        higherIsBetter: true,
        format: (v) => v.toFixed(2),
    },
    {
        key: 'chancesConcededPer90',
        label: 'Chances conceded / 90',
        hint: 'Chances the opponent creates. Lower means a tighter defence.',
        higherIsBetter: false,
        format: (v) => v.toFixed(2),
    },
    {
        key: 'goalsConcededPer90',
        label: 'Goals conceded / 90',
        hint: 'Goals the opponent scores per match.',
        higherIsBetter: false,
        format: (v) => v.toFixed(2),
    },
];

function delta(key: keyof SquadProfile): number | null {
    if (!props.previous) {
        return null;
    }

    const d = props.profile[key] - props.previous[key];

    return Math.abs(d) < 1e-9 ? 0 : d;
}

function improved(metric: Metric, d: number): boolean {
    return metric.higherIsBetter ? d > 0 : d < 0;
}

const read = computed(() => {
    const { chancesPer90, meanDecisionGap, chancesConcededPer90 } =
        props.profile;

    const creation =
        chancesPer90 >= 2.8
            ? 'creates chances freely'
            : chancesPer90 >= 2
              ? 'creates a healthy number of chances'
              : 'struggles to create chances';

    const vision =
        meanDecisionGap < 0.11
            ? 'reads the game well and finds the sharper pass'
            : meanDecisionGap < 0.17
              ? 'generally picks good options'
              : 'often misses the better pass';

    const defence =
        chancesConcededPer90 < 0.8
            ? 'hard to break down'
            : chancesConcededPer90 < 1.7
              ? 'reasonably solid'
              : 'leaky at the back';

    return `This squad ${creation} and ${vision}. At the back it is ${defence}.`;
});
</script>

<template>
    <div
        class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
    >
        <h2 class="text-sm font-medium text-muted-foreground">Team profile</h2>
        <p class="mt-1 mb-4 text-base">{{ read }}</p>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-3">
            <div
                v-for="metric in metrics"
                :key="metric.key"
                class="rounded-lg border border-sidebar-border/60 p-3 dark:border-sidebar-border"
            >
                <div class="flex items-baseline justify-between gap-1">
                    <span class="text-xs font-medium text-muted-foreground">{{
                        metric.label
                    }}</span>
                    <span
                        v-if="
                            delta(metric.key) !== null &&
                            delta(metric.key) !== 0
                        "
                        class="flex items-center gap-0.5 text-xs font-medium"
                        :class="
                            improved(metric, delta(metric.key)!)
                                ? 'text-emerald-600 dark:text-emerald-400'
                                : 'text-red-600 dark:text-red-400'
                        "
                    >
                        <ArrowUp
                            v-if="delta(metric.key)! > 0"
                            class="h-3 w-3"
                        />
                        <ArrowDown v-else class="h-3 w-3" />
                        {{ metric.format(Math.abs(delta(metric.key)!)) }}
                    </span>
                </div>
                <p class="mt-1 text-2xl font-medium tabular-nums">
                    {{ metric.format(props.profile[metric.key]) }}
                </p>
                <p class="mt-1 text-[11px] leading-tight text-muted-foreground">
                    {{ metric.hint }}
                </p>
            </div>
        </div>
    </div>
</template>
