<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { PoolPlayer } from '@/types/squad';

const props = defineProps<{
    player: PoolPlayer;
    canPick: boolean;
    unaffordable: boolean;
}>();

const emit = defineEmits<{
    pick: [playerId: number];
}>();

const stats: { key: keyof PoolPlayer; label: string; key_metric: boolean }[] = [
    { key: 'vision', label: 'VIS', key_metric: true },
    { key: 'passing', label: 'PAS', key_metric: true },
    { key: 'dribbling', label: 'DRB', key_metric: true },
    { key: 'finishing', label: 'FIN', key_metric: true },
    { key: 'tackling', label: 'TAC', key_metric: false },
    { key: 'pace', label: 'PAC', key_metric: false },
];
</script>

<template>
    <button
        type="button"
        class="w-full rounded-lg border border-sidebar-border/70 p-3 text-left transition hover:border-sidebar-border hover:bg-accent/40 disabled:cursor-not-allowed disabled:opacity-50 dark:border-sidebar-border"
        :disabled="!props.canPick"
        @click="emit('pick', props.player.id)"
    >
        <div class="mb-2 flex items-center justify-between gap-2">
            <span class="truncate text-sm font-medium">{{
                props.player.name
            }}</span>
            <div class="flex shrink-0 items-center gap-1">
                <span class="text-xs text-muted-foreground tabular-nums"
                    >{{ props.player.age }}y</span
                >
                <span class="text-xs font-medium tabular-nums"
                    >£{{ props.player.value }}m</span
                >
                <Badge variant="secondary">{{ props.player.position }}</Badge>
                <Badge v-if="props.player.slot !== null" variant="outline">
                    Slot {{ props.player.slot }}
                </Badge>
                <Badge
                    v-else-if="props.unaffordable"
                    variant="outline"
                    class="border-red-500/40 text-red-600 dark:text-red-400"
                >
                    Over budget
                </Badge>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-x-3 gap-y-1">
            <div
                v-for="stat in stats"
                :key="stat.key"
                class="flex items-center gap-1.5"
                :class="stat.key_metric ? '' : 'opacity-50'"
            >
                <span
                    class="w-7 text-[10px] font-medium tracking-wide text-muted-foreground"
                    >{{ stat.label }}</span
                >
                <span
                    class="h-1.5 flex-1 overflow-hidden rounded-full bg-muted"
                >
                    <span
                        class="block h-full rounded-full bg-foreground/70"
                        :style="{
                            width: `${(Number(props.player[stat.key]) / 20) * 100}%`,
                        }"
                    />
                </span>
                <span
                    class="w-4 text-right text-[10px] text-muted-foreground tabular-nums"
                    >{{ props.player[stat.key] }}</span
                >
            </div>
        </div>
    </button>
</template>
