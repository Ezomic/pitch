<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { show } from '@/routes/cup';

interface CupTie {
    homeName: string;
    awayName: string;
    homeGoals: number | null;
    awayGoals: number | null;
    played: boolean;
    bye: boolean;
    involvesUser: boolean;
    winnerName: string | null;
    userWon: boolean;
}

interface CupRound {
    round: number;
    name: string;
    ties: CupTie[];
}

const props = defineProps<{
    seasonNumber: number;
    rounds: CupRound[];
    champion: string | null;
    userOut: number | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Cup', href: show() }],
    },
});

function sideClass(tie: CupTie, side: 'home' | 'away'): string {
    if (!tie.played || tie.winnerName === null) {
        return '';
    }

    const name = side === 'home' ? tie.homeName : tie.awayName;

    return name === tie.winnerName
        ? 'font-medium text-foreground'
        : 'text-muted-foreground line-through decoration-muted-foreground/40';
}
</script>

<template>
    <Head title="Cup" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div
            class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <div>
                <p class="text-sm text-muted-foreground">
                    Season {{ props.seasonNumber }} cup
                </p>
                <p class="text-lg font-medium">Knockout</p>
            </div>
            <span
                v-if="props.champion"
                class="rounded-md bg-amber-500/15 px-2.5 py-1 text-sm font-medium text-amber-600 dark:text-amber-400"
            >
                🏆 {{ props.champion }}
            </span>
            <span
                v-else-if="props.userOut"
                class="rounded-md bg-red-500/15 px-2.5 py-1 text-sm font-medium text-red-600 dark:text-red-400"
            >
                Knocked out
            </span>
            <span
                v-else
                class="rounded-md bg-emerald-500/15 px-2.5 py-1 text-sm font-medium text-emerald-600 dark:text-emerald-400"
            >
                Still in
            </span>
        </div>

        <div class="grid flex-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div
                v-for="round in props.rounds"
                :key="round.round"
                class="flex flex-col gap-2 rounded-xl border border-sidebar-border/70 p-3 dark:border-sidebar-border"
            >
                <h2 class="px-1 text-sm font-medium text-muted-foreground">
                    {{ round.name }}
                </h2>
                <div
                    v-for="(tie, index) in round.ties"
                    :key="index"
                    class="rounded-lg border border-sidebar-border/70 p-2.5 text-sm dark:border-sidebar-border"
                    :class="
                        tie.involvesUser
                            ? 'ring-1 ring-accent-foreground/30'
                            : ''
                    "
                >
                    <div class="flex items-center justify-between gap-2">
                        <span :class="sideClass(tie, 'home')">{{
                            tie.homeName
                        }}</span>
                        <span class="text-muted-foreground tabular-nums">{{
                            tie.played && !tie.bye ? tie.homeGoals : ''
                        }}</span>
                    </div>
                    <div
                        v-if="!tie.bye"
                        class="mt-1 flex items-center justify-between gap-2"
                    >
                        <span :class="sideClass(tie, 'away')">{{
                            tie.awayName
                        }}</span>
                        <span class="text-muted-foreground tabular-nums">{{
                            tie.played ? tie.awayGoals : ''
                        }}</span>
                    </div>
                    <p v-else class="mt-1 text-xs text-muted-foreground italic">
                        Bye
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
