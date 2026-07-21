<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowRightLeft, Pause, Play } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { show as seasonShow } from '@/routes/season';
import type { LiveMoment } from '@/types/match';

interface LineupSlot {
    slot: number;
    name: string;
    position: string;
}
interface PoolOption {
    id: number;
    name: string;
    position: string;
}

const props = defineProps<{
    opponentName: string;
    moments: LiveMoment[];
    lineup: LineupSlot[];
    squadOptions: PoolOption[];
    bench: number[];
    subsRemaining: number;
    benchUrl: string;
    subUrl: string;
    finishUrl: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Season', href: seasonShow() }],
    },
});

const HALF_TIME = 45;
const FULL_TIME = 90;
const MS_PER_MINUTE = 260;
const MAX_BENCH = 7;

const clock = ref(0);
const running = ref(false);
const started = ref(false);
const selectedBench = ref<number[]>([...props.bench]);
const subbingIn = ref<number | null>(null);
let timer: ReturnType<typeof setInterval> | null = null;

const revealed = computed(() =>
    props.moments.filter((m) => m.minute <= clock.value),
);
const homeGoals = computed(
    () =>
        revealed.value.filter((m) => m.side === 'home' && m.kind === 'goal')
            .length,
);
const awayGoals = computed(
    () =>
        revealed.value.filter((m) => m.side === 'away' && m.kind === 'goal')
            .length,
);
const feed = computed(() => [...revealed.value].reverse());

const halfTime = computed(
    () => clock.value === HALF_TIME && clock.value < FULL_TIME,
);
const fullTime = computed(() => clock.value >= FULL_TIME);
const benchPlayers = computed(() =>
    props.squadOptions.filter((o) => props.bench.includes(o.id)),
);
const canSub = computed(
    () =>
        started.value &&
        !running.value &&
        !fullTime.value &&
        props.subsRemaining > 0,
);

function stop(): void {
    if (timer !== null) {
        clearInterval(timer);
        timer = null;
    }

    running.value = false;
}

function tick(): void {
    if (clock.value >= FULL_TIME) {
        stop();

        return;
    }

    clock.value += 1;

    if (clock.value === HALF_TIME || clock.value >= FULL_TIME) {
        stop();
    }
}

function start(): void {
    if (fullTime.value || running.value) {
        return;
    }

    running.value = true;
    timer = setInterval(tick, MS_PER_MINUTE);
}

function toggleBench(id: number): void {
    const at = selectedBench.value.indexOf(id);

    if (at >= 0) {
        selectedBench.value.splice(at, 1);
    } else if (selectedBench.value.length < MAX_BENCH) {
        selectedBench.value.push(id);
    }
}

function kickOff(): void {
    router.post(
        props.benchUrl,
        { players: selectedBench.value },
        {
            preserveState: true,
            preserveScroll: true,
            only: ['bench', 'squadOptions', 'subsRemaining'],
            onSuccess: () => {
                started.value = true;
                start();
            },
        },
    );
}

function makeSub(slot: number): void {
    if (subbingIn.value === null) {
        return;
    }

    router.post(
        props.subUrl,
        { minute: clock.value, slot, in: subbingIn.value },
        {
            preserveState: true,
            preserveScroll: true,
            only: [
                'moments',
                'bench',
                'subsRemaining',
                'lineup',
                'squadOptions',
            ],
            onSuccess: () => {
                subbingIn.value = null;
            },
        },
    );
}

function confirmResult(): void {
    router.post(props.finishUrl);
}

onBeforeUnmount(stop);
</script>

<template>
    <Head title="Live match" />

    <div class="mx-auto flex h-full w-full max-w-2xl flex-1 flex-col gap-4 p-4">
        <div
            class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div class="mb-4 text-center">
                <span
                    class="inline-block rounded-full bg-muted px-3 py-1 text-sm font-medium tabular-nums"
                >
                    {{
                        !started
                            ? 'Team talk'
                            : fullTime
                              ? 'FT'
                              : halfTime
                                ? 'HT'
                                : `${clock}'`
                    }}
                </span>
            </div>

            <div class="flex items-center justify-between gap-4">
                <div class="flex-1 text-center">
                    <p class="text-sm text-muted-foreground">Your squad</p>
                    <p class="text-4xl font-medium tabular-nums">
                        {{ homeGoals }}
                    </p>
                </div>
                <span class="text-2xl text-muted-foreground">-</span>
                <div class="flex-1 text-center">
                    <p class="text-sm text-muted-foreground">
                        {{ props.opponentName }}
                    </p>
                    <p class="text-4xl font-medium tabular-nums">
                        {{ awayGoals }}
                    </p>
                </div>
            </div>

            <div class="mt-6 flex justify-center gap-2">
                <Button v-if="!started" @click="kickOff"> Kick off </Button>
                <Button v-else-if="fullTime" @click="confirmResult">
                    Confirm result
                </Button>
                <Button v-else-if="running" variant="outline" @click="stop">
                    <Pause class="h-4 w-4" />
                    Pause
                </Button>
                <Button v-else variant="outline" @click="start">
                    <Play class="h-4 w-4" />
                    {{ halfTime ? 'Kick off second half' : 'Resume' }}
                </Button>
            </div>
        </div>

        <!-- Pre-kickoff: name the bench -->
        <div
            v-if="!started"
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <h2 class="mb-1 text-sm font-medium text-muted-foreground">
                Name your bench
            </h2>
            <p class="mb-3 text-xs text-muted-foreground">
                Pick up to {{ MAX_BENCH }} substitutes ({{
                    selectedBench.length
                }}
                selected).
            </p>
            <div class="grid grid-cols-2 gap-2">
                <button
                    v-for="option in props.squadOptions"
                    :key="option.id"
                    type="button"
                    class="flex items-center justify-between gap-2 rounded-lg border p-2 text-left text-sm transition"
                    :class="
                        selectedBench.includes(option.id)
                            ? 'border-foreground bg-accent/50'
                            : 'border-sidebar-border/70 hover:bg-accent/30 dark:border-sidebar-border'
                    "
                    @click="toggleBench(option.id)"
                >
                    <span class="truncate">{{ option.name }}</span>
                    <Badge variant="secondary">{{ option.position }}</Badge>
                </button>
            </div>
        </div>

        <!-- In-match: substitutions (while paused) -->
        <div
            v-else-if="canSub"
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-medium text-muted-foreground">
                    Substitutions
                </h2>
                <Badge variant="outline">{{ props.subsRemaining }} left</Badge>
            </div>

            <p
                v-if="benchPlayers.length === 0"
                class="text-sm text-muted-foreground"
            >
                No players on the bench.
            </p>

            <template v-else>
                <p class="mb-2 text-xs text-muted-foreground">
                    {{
                        subbingIn === null
                            ? 'Choose a substitute to bring on.'
                            : 'Now tap the player to replace.'
                    }}
                </p>
                <div class="mb-3 flex flex-wrap gap-2">
                    <Button
                        v-for="player in benchPlayers"
                        :key="player.id"
                        size="sm"
                        :variant="
                            subbingIn === player.id ? 'default' : 'outline'
                        "
                        @click="
                            subbingIn =
                                subbingIn === player.id ? null : player.id
                        "
                    >
                        <ArrowRightLeft class="h-3.5 w-3.5" />
                        {{ player.name }}
                    </Button>
                </div>
                <div v-if="subbingIn !== null" class="grid grid-cols-2 gap-2">
                    <Button
                        v-for="spot in props.lineup"
                        :key="spot.slot"
                        size="sm"
                        variant="secondary"
                        class="justify-between"
                        @click="makeSub(spot.slot)"
                    >
                        <span class="truncate">{{ spot.name }}</span>
                        <span class="text-xs text-muted-foreground">{{
                            spot.position
                        }}</span>
                    </Button>
                </div>
            </template>
        </div>

        <div
            class="flex-1 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <h2 class="mb-3 text-sm font-medium text-muted-foreground">
                Commentary
            </h2>

            <p v-if="feed.length === 0" class="text-sm text-muted-foreground">
                Kick-off. The match is under way.
            </p>

            <ul v-else class="flex flex-col gap-2">
                <li
                    v-for="(moment, index) in feed"
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
                                ? moment.side === 'home'
                                    ? 'font-medium text-emerald-600 dark:text-emerald-400'
                                    : 'font-medium text-red-600 dark:text-red-400'
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
