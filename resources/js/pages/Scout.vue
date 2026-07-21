<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { show as seasonShow } from '@/routes/season';
import type { SquadProfile } from '@/types/squad';

const props = defineProps<{
    opponentName: string;
    style: string;
    home: boolean;
    opponent: SquadProfile;
    matchup: SquadProfile;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Season', href: seasonShow() }],
    },
});

const opponentMetrics: { label: string; value: string; hint: string }[] = [
    {
        label: 'Chances / 90',
        value: props.opponent.chancesPer90.toFixed(2),
        hint: 'How much they create',
    },
    {
        label: 'Goals / 90',
        value: props.opponent.goalsPer90.toFixed(2),
        hint: 'How much they score',
    },
    {
        label: 'Chances conceded / 90',
        value: props.opponent.chancesConcededPer90.toFixed(2),
        hint: 'How open they are',
    },
    {
        label: 'Goals conceded / 90',
        value: props.opponent.goalsConcededPer90.toFixed(2),
        hint: 'How leaky they are',
    },
];

const matchupMetrics: { label: string; value: string; hint: string }[] = [
    {
        label: 'You score / 90',
        value: props.matchup.goalsPer90.toFixed(2),
        hint: 'Your goals against them',
    },
    {
        label: 'You concede / 90',
        value: props.matchup.goalsConcededPer90.toFixed(2),
        hint: 'Their goals against you',
    },
];
</script>

<template>
    <Head title="Scouting" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm text-muted-foreground">
                        Scouting report &middot;
                        {{ props.home ? 'home' : 'away' }}
                    </p>
                    <p class="text-lg font-medium">
                        {{ props.opponentName }}
                        <span class="text-sm text-muted-foreground"
                            >&middot; {{ props.style }}</span
                        >
                    </p>
                </div>
                <Link
                    :href="seasonShow().url"
                    class="flex shrink-0 items-center gap-1.5 rounded-md border border-sidebar-border/70 px-2.5 py-1.5 text-sm text-muted-foreground transition hover:bg-accent/40 dark:border-sidebar-border"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Season
                </Link>
            </div>
        </div>

        <div
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <h2 class="mb-3 text-sm font-medium text-muted-foreground">
                How they play
            </h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="metric in opponentMetrics"
                    :key="metric.label"
                    class="rounded-lg bg-muted/40 p-3"
                >
                    <p class="text-xs text-muted-foreground">
                        {{ metric.label }}
                    </p>
                    <p class="text-2xl font-medium tabular-nums">
                        {{ metric.value }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ metric.hint }}
                    </p>
                </div>
            </div>
        </div>

        <div
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <h2 class="mb-1 text-sm font-medium text-muted-foreground">
                Your projection against them
            </h2>
            <p class="mb-3 text-xs text-muted-foreground">
                How your current squad, formation and mentality are expected to
                fare in this specific matchup. Adjust your tactics on the squad
                screen and check back.
            </p>
            <div class="grid gap-3 sm:grid-cols-2">
                <div
                    v-for="metric in matchupMetrics"
                    :key="metric.label"
                    class="rounded-lg bg-muted/40 p-3"
                >
                    <p class="text-xs text-muted-foreground">
                        {{ metric.label }}
                    </p>
                    <p class="text-2xl font-medium tabular-nums">
                        {{ metric.value }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ metric.hint }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
