<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Pause, Play } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { show as seasonShow } from '@/routes/season';
import type { LiveMoment } from '@/types/match';

const props = defineProps<{
    opponentName: string;
    moments: LiveMoment[];
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

const clock = ref(0);
const running = ref(false);
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

function confirmResult(): void {
    router.post(props.finishUrl);
}

onMounted(start);
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
                    {{ fullTime ? 'FT' : halfTime ? 'HT' : `${clock}'` }}
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
                <Button v-if="fullTime" @click="confirmResult">
                    Confirm result
                </Button>
                <template v-else-if="halfTime">
                    <Button @click="start">
                        <Play class="h-4 w-4" />
                        Kick off second half
                    </Button>
                </template>
                <Button v-else-if="running" variant="outline" @click="stop">
                    <Pause class="h-4 w-4" />
                    Pause
                </Button>
                <Button v-else variant="outline" @click="start">
                    <Play class="h-4 w-4" />
                    Resume
                </Button>
            </div>
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
