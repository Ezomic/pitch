<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { advance, rollover, show } from '@/routes/season';
import type {
    Matchday,
    NextFixture,
    SeasonHistory,
    SeasonObjective,
    StandingRow,
} from '@/types/season';

const props = defineProps<{
    seasonNumber: number;
    history: SeasonHistory[];
    objective: SeasonObjective;
    standings: StandingRow[];
    matchdays: Matchday[];
    currentMatchday: number | null;
    currentDate: string;
    nextFixtureDate: string | null;
    nextFixture: NextFixture | null;
    liveFixture: {
        opponentName: string;
        home: boolean;
        url: string;
        scoutUrl: string;
    } | null;
    complete: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Season', href: show() }],
    },
});

function advanceWeek(): void {
    router.post(advance().url, {}, { preserveScroll: true });
}

function playLive(): void {
    if (props.liveFixture) {
        router.get(props.liveFixture.url);
    }
}

function scoutOpponent(): void {
    if (props.liveFixture) {
        router.get(props.liveFixture.scoutUrl);
    }
}

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString(undefined, {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function newSeason(): void {
    router.post(rollover().url, {}, { preserveScroll: true });
}

function ordinal(n: number): string {
    const s = ['th', 'st', 'nd', 'rd'];
    const v = n % 100;

    return n + (s[(v - 20) % 10] ?? s[v] ?? s[0]);
}

const userPosition = () =>
    ordinal(props.standings.findIndex((r) => r.isUser) + 1);

const columns: { key: keyof StandingRow; label: string }[] = [
    { key: 'played', label: 'P' },
    { key: 'won', label: 'W' },
    { key: 'drawn', label: 'D' },
    { key: 'lost', label: 'L' },
    { key: 'goalsFor', label: 'GF' },
    { key: 'goalsAgainst', label: 'GA' },
    { key: 'goalDifference', label: 'GD' },
    { key: 'points', label: 'Pts' },
];
</script>

<template>
    <Head title="Season" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div v-if="props.liveFixture">
                    <p class="text-sm text-muted-foreground">
                        {{ formatDate(props.currentDate) }} &middot; Kick-off
                    </p>
                    <p class="text-lg font-medium">
                        Your squad
                        {{ props.liveFixture.home ? 'vs' : 'away to' }}
                        {{ props.liveFixture.opponentName }}
                    </p>
                </div>
                <div v-else-if="!props.complete && props.nextFixture">
                    <p class="text-sm text-muted-foreground">
                        {{ formatDate(props.currentDate) }} &middot; Matchday
                        {{ props.currentMatchday
                        }}<template v-if="props.nextFixtureDate">
                            plays
                            {{ formatDate(props.nextFixtureDate) }}</template
                        >
                    </p>
                    <p class="text-lg font-medium">
                        Your squad
                        {{ props.nextFixture.home ? 'vs' : 'away to' }}
                        {{ props.nextFixture.opponentName }}
                    </p>
                </div>
                <div v-else>
                    <p class="text-sm text-muted-foreground">
                        Season {{ props.seasonNumber }} complete
                    </p>
                    <p class="text-lg font-medium">
                        You finished {{ userPosition() }} of
                        {{ props.standings.length }}
                    </p>
                </div>

                <template v-if="props.liveFixture">
                    <Button variant="outline" @click="scoutOpponent">
                        Scout opponent
                    </Button>
                    <Button @click="playLive"> Play your match </Button>
                </template>
                <Button v-else-if="!props.complete" @click="advanceWeek">
                    Advance week
                </Button>
                <Button v-else variant="outline" @click="newSeason">
                    Start season {{ props.seasonNumber + 1 }}
                </Button>
            </div>
        </div>

        <div
            class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <p class="text-sm">
                <span class="text-muted-foreground">Board objective:</span>
                finish top {{ props.objective.target }} of
                {{ props.objective.teams }}
            </p>
            <span
                v-if="props.objective.met === null"
                class="text-sm text-muted-foreground"
            >
                Currently {{ props.objective.position }}
            </span>
            <span
                v-else-if="props.objective.met"
                class="rounded-md bg-emerald-500/15 px-2 py-0.5 text-sm font-medium text-emerald-600 dark:text-emerald-400"
            >
                Met &middot; finished {{ props.objective.position }}
            </span>
            <span
                v-else
                class="rounded-md bg-red-500/15 px-2 py-0.5 text-sm font-medium text-red-600 dark:text-red-400"
            >
                Missed &middot; finished {{ props.objective.position }}
            </span>
        </div>

        <div class="grid flex-1 gap-4 lg:grid-cols-[1.3fr_1fr]">
            <div
                class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="text-sm font-medium text-muted-foreground">
                        Standings
                    </h2>
                    <span class="text-xs text-muted-foreground"
                        >Season {{ props.seasonNumber }}</span
                    >
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-muted-foreground">
                            <th class="py-1 pr-2 text-left font-medium">#</th>
                            <th class="py-1 text-left font-medium">Team</th>
                            <th
                                v-for="col in columns"
                                :key="col.key"
                                class="w-8 py-1 text-right font-medium tabular-nums"
                            >
                                {{ col.label }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(row, index) in props.standings"
                            :key="row.name"
                            class="border-t border-sidebar-border/50"
                            :class="
                                row.isUser
                                    ? 'font-medium text-accent-foreground'
                                    : ''
                            "
                        >
                            <td class="py-1.5 pr-2 tabular-nums">
                                {{ index + 1 }}
                            </td>
                            <td class="py-1.5">{{ row.name }}</td>
                            <td
                                v-for="col in columns"
                                :key="col.key"
                                class="py-1.5 text-right tabular-nums"
                            >
                                {{ row[col.key] }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                class="flex max-h-[70vh] flex-col gap-3 overflow-y-auto rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <h2 class="text-sm font-medium text-muted-foreground">
                    Fixtures
                </h2>
                <div
                    v-for="day in props.matchdays"
                    :key="day.matchday"
                    class="space-y-1"
                >
                    <p class="text-xs text-muted-foreground">
                        Matchday {{ day.matchday }}
                    </p>
                    <div
                        v-for="fixture in day.fixtures"
                        :key="fixture.id"
                        class="flex items-center justify-between gap-2 rounded-md px-2 py-1 text-sm"
                        :class="
                            fixture.isUser ? 'bg-accent/40 font-medium' : ''
                        "
                    >
                        <span class="truncate">
                            {{ fixture.homeName }}
                            <span class="text-muted-foreground">v</span>
                            {{ fixture.awayName }}
                        </span>
                        <span class="flex shrink-0 items-center gap-2">
                            <span v-if="fixture.played" class="tabular-nums">
                                {{ fixture.homeGoals }}-{{ fixture.awayGoals }}
                            </span>
                            <span v-else class="text-muted-foreground">—</span>
                            <Link
                                v-if="fixture.reportUrl"
                                :href="fixture.reportUrl"
                                class="text-xs text-muted-foreground underline underline-offset-2 hover:text-foreground"
                            >
                                report
                            </Link>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="props.history.length > 0"
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <h2 class="mb-3 text-sm font-medium text-muted-foreground">
                Past seasons
            </h2>
            <div class="flex flex-col gap-1">
                <div
                    v-for="entry in props.history"
                    :key="entry.number"
                    class="flex items-center justify-between gap-2 rounded-md px-2 py-1.5 text-sm"
                >
                    <span>Season {{ entry.number }}</span>
                    <span class="text-muted-foreground">
                        finished {{ entry.position }} of {{ entry.teams }} on
                        {{ entry.points }} pts
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
