<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { index } from '@/routes/careers';
import { show } from '@/routes/league';
import type { MatchMoment } from '@/types/match';

interface ReplayStat {
    label: string;
    home: number;
    away: number;
}

const props = defineProps<{
    career: { id: number; name: string };
    replay: {
        home: string | null;
        away: string | null;
        matchday: number;
        version: string | null;
        homeGoals: number;
        awayGoals: number;
        stats: ReplayStat[];
        moments: MatchMoment[];
    };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Careers', href: index() }] },
});
</script>

<template>
    <Head :title="`${props.replay.home} v ${props.replay.away}`" />

    <div class="mx-auto flex h-full w-full max-w-2xl flex-1 flex-col gap-4 p-4">
        <div
            class="rounded-xl border border-sidebar-border/70 p-4 sm:p-6 dark:border-sidebar-border"
        >
            <p class="mb-4 text-center text-xs text-muted-foreground">
                {{ props.career.name }}, matchday {{ props.replay.matchday }}
            </p>
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0 flex-1 text-center">
                    <p class="truncate text-sm text-muted-foreground">
                        {{ props.replay.home }}
                    </p>
                    <p
                        class="text-4xl font-medium tabular-nums"
                        data-test="home-goals"
                    >
                        {{ props.replay.homeGoals }}
                    </p>
                </div>
                <span class="text-2xl text-muted-foreground">-</span>
                <div class="min-w-0 flex-1 text-center">
                    <p class="truncate text-sm text-muted-foreground">
                        {{ props.replay.away }}
                    </p>
                    <p
                        class="text-4xl font-medium tabular-nums"
                        data-test="away-goals"
                    >
                        {{ props.replay.awayGoals }}
                    </p>
                </div>
            </div>

            <div
                v-if="props.replay.stats.length"
                class="mt-6 grid grid-cols-2 gap-3"
            >
                <div
                    v-for="stat in props.replay.stats"
                    :key="stat.label"
                    class="rounded-lg border border-sidebar-border/60 p-3 text-center dark:border-sidebar-border"
                >
                    <p class="text-xl font-medium tabular-nums">
                        {{ stat.home }} - {{ stat.away }}
                    </p>
                    <p class="text-[11px] text-muted-foreground">
                        {{ stat.label }}
                    </p>
                </div>
            </div>
        </div>

        <div
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <h2 class="mb-3 text-sm font-medium text-muted-foreground">
                Key moments
            </h2>

            <p
                v-if="props.replay.moments.length === 0"
                class="text-sm text-muted-foreground"
            >
                No goals in this one.
            </p>

            <ul v-else class="flex flex-col gap-2">
                <li
                    v-for="(moment, index) in props.replay.moments"
                    :key="index"
                    class="flex items-baseline gap-3 text-sm"
                >
                    <span
                        class="w-9 shrink-0 text-right text-muted-foreground tabular-nums"
                        >{{ moment.minute }}'</span
                    >
                    <span
                        class="font-medium text-emerald-600 dark:text-emerald-400"
                    >
                        {{ moment.text }}
                    </span>
                </li>
            </ul>
        </div>

        <div class="flex items-center justify-between text-xs">
            <Link
                :href="show(props.career.id)"
                class="text-primary underline-offset-4 hover:underline"
            >
                Back to the league
            </Link>
            <span v-if="props.replay.version" class="text-muted-foreground">
                {{ props.replay.version }}
            </span>
        </div>
    </div>
</template>
