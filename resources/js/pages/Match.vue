<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { RefreshCw } from '@lucide/vue';
import MatchPitch2D from '@/components/match/MatchPitch2D.vue';
import { Button } from '@/components/ui/button';
import { show } from '@/routes/match';
import type { MatchReport } from '@/types/match';

const props = withDefaults(
    defineProps<{
        seed: number;
        report: MatchReport;
        opponentName?: string;
        hideReseed?: boolean;
    }>(),
    {
        opponentName: 'Opposition',
        hideReseed: false,
    },
);

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Match', href: show() }],
    },
});

function watchAnother(): void {
    router.get(
        show().url,
        { seed: Math.floor(Math.random() * 1_000_000) + 1 },
        { preserveScroll: true },
    );
}

const stats = [
    { label: 'Shots', key: 'shots' as const },
    { label: 'Passes completed', key: 'passesCompleted' as const },
    { label: 'Progressive passes', key: 'progressivePasses' as const },
];
</script>

<template>
    <Head title="Match" />

    <div class="mx-auto flex h-full w-full max-w-2xl flex-1 flex-col gap-4 p-4">
        <div
            class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div class="flex items-center justify-between gap-4">
                <div class="text-center">
                    <p class="text-sm text-muted-foreground">Your squad</p>
                    <p class="text-4xl font-medium tabular-nums">
                        {{ props.report.homeGoals }}
                    </p>
                </div>
                <span class="text-2xl text-muted-foreground">-</span>
                <div class="text-center">
                    <p class="text-sm text-muted-foreground">
                        {{ props.opponentName }}
                    </p>
                    <p class="text-4xl font-medium tabular-nums">
                        {{ props.report.awayGoals }}
                    </p>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-3 gap-3">
                <div
                    v-for="stat in stats"
                    :key="stat.key"
                    class="rounded-lg border border-sidebar-border/60 p-3 text-center dark:border-sidebar-border"
                >
                    <p class="text-xl font-medium tabular-nums">
                        {{ props.report[stat.key] }}
                    </p>
                    <p class="text-[11px] text-muted-foreground">
                        {{ stat.label }}
                    </p>
                </div>
            </div>

            <Button
                v-if="!props.hideReseed"
                class="mt-6 w-full"
                variant="outline"
                @click="watchAnother"
            >
                <RefreshCw class="h-4 w-4" />
                Watch another match
            </Button>
        </div>

        <div
            v-if="props.report.timeline.length > 0"
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <h2 class="mb-3 text-sm font-medium text-muted-foreground">
                Match replay
            </h2>
            <MatchPitch2D
                :timeline="props.report.timeline"
                :lineups="props.report.lineups"
                :positions="props.report.positions"
                home-name="Your squad"
                :away-name="props.opponentName"
            />
        </div>

        <div
            class="flex-1 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <h2 class="mb-3 text-sm font-medium text-muted-foreground">
                Key moments
            </h2>

            <p
                v-if="props.report.moments.length === 0"
                class="text-sm text-muted-foreground"
            >
                A quiet match, your squad struggled to work a clear opening.
            </p>

            <ul v-else class="flex flex-col gap-2">
                <li
                    v-for="(moment, index) in props.report.moments"
                    :key="index"
                    class="flex items-baseline gap-3 text-sm"
                >
                    <span
                        class="w-9 shrink-0 text-right text-muted-foreground tabular-nums"
                        >{{ moment.minute }}'</span
                    >
                    <span
                        :class="
                            moment.kind === 'goal'
                                ? 'font-medium text-emerald-600 dark:text-emerald-400'
                                : moment.kind === 'turnover'
                                  ? 'text-muted-foreground'
                                  : ''
                        "
                    >
                        {{ moment.text }}
                    </span>
                </li>
            </ul>
        </div>
    </div>
</template>
