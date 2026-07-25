<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { focus, index } from '@/routes/training';

interface TrainingPlayer {
    id: number;
    name: string;
    position: string;
    age: number;
    overall: number;
    potential: number;
    fitness: number;
    trainingFocus: string | null;
    atCeiling: boolean;
    attributes: Record<string, number>;
}

const props = defineProps<{
    players: TrainingPlayer[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Training', href: index() }],
    },
});

const attributes: { key: string; label: string }[] = [
    { key: 'vision', label: 'VIS' },
    { key: 'passing', label: 'PAS' },
    { key: 'dribbling', label: 'DRB' },
    { key: 'finishing', label: 'FIN' },
    { key: 'tackling', label: 'TAC' },
    { key: 'pace', label: 'PAC' },
];

function setFocus(player: TrainingPlayer, key: string): void {
    router.patch(
        focus(player.id).url,
        { focus: player.trainingFocus === key ? null : key },
        { preserveScroll: true, preserveState: true },
    );
}
</script>

<template>
    <Head title="Training" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <p class="text-sm text-muted-foreground">Senior training</p>
            <p class="text-lg font-medium">
                Drill an attribute each week. Gains cost fitness, so a trained
                player is less fresh come matchday.
            </p>
        </div>

        <p
            v-if="props.players.length === 0"
            class="rounded-xl border border-sidebar-border/70 p-6 text-center text-sm text-muted-foreground dark:border-sidebar-border"
        >
            No senior players to train yet.
        </p>

        <div v-else class="grid gap-3 md:grid-cols-2">
            <div
                v-for="player in props.players"
                :key="player.id"
                class="rounded-xl border border-sidebar-border/70 p-3 dark:border-sidebar-border"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="truncate text-sm font-medium">{{
                                player.name
                            }}</span>
                            <Badge variant="secondary">{{
                                player.position
                            }}</Badge>
                            <span class="text-xs text-muted-foreground"
                                >{{ player.age }}y</span
                            >
                        </div>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            Ability
                            <span class="text-foreground tabular-nums">{{
                                player.overall
                            }}</span>
                            &middot; potential
                            <span class="text-foreground tabular-nums">{{
                                player.potential
                            }}</span>
                            &middot; fitness
                            <span class="text-foreground tabular-nums"
                                >{{ player.fitness }}%</span
                            >
                        </p>
                    </div>
                    <Badge v-if="player.atCeiling" variant="outline"
                        >At ceiling</Badge
                    >
                </div>

                <p class="mt-2 mb-1 text-[11px] text-muted-foreground">
                    Tap an attribute to train it. Tap again to rest.
                </p>
                <div class="grid grid-cols-2 gap-x-3 gap-y-1">
                    <button
                        v-for="attr in attributes"
                        :key="attr.key"
                        type="button"
                        class="flex items-center gap-1.5 rounded px-1 py-0.5 text-left transition"
                        :class="
                            player.trainingFocus === attr.key
                                ? 'bg-accent'
                                : 'hover:bg-accent/40'
                        "
                        @click="setFocus(player, attr.key)"
                    >
                        <span
                            class="w-7 text-[10px] font-medium tracking-wide"
                            :class="
                                player.trainingFocus === attr.key
                                    ? 'text-accent-foreground'
                                    : 'text-muted-foreground'
                            "
                            >{{ attr.label }}</span
                        >
                        <span class="text-sm tabular-nums">{{
                            player.attributes[attr.key]
                        }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
