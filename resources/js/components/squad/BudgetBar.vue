<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    budget: number;
    spent: number;
    remaining: number;
}>();

const pct = computed(() =>
    props.budget > 0 ? Math.min(100, (props.spent / props.budget) * 100) : 0,
);

function money(value: number): string {
    return `£${value}m`;
}
</script>

<template>
    <div
        class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
    >
        <div class="flex items-baseline justify-between gap-2">
            <h2 class="text-sm font-medium text-muted-foreground">
                Transfer budget
            </h2>
            <span class="text-sm tabular-nums">
                <span class="font-medium">{{ money(props.spent) }}</span>
                <span class="text-muted-foreground">
                    / {{ money(props.budget) }}</span
                >
            </span>
        </div>

        <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-muted">
            <div
                class="h-full rounded-full transition-all"
                :class="
                    props.remaining <= 0
                        ? 'bg-red-500'
                        : props.remaining < props.budget * 0.1
                          ? 'bg-amber-500'
                          : 'bg-foreground/70'
                "
                :style="{ width: `${pct}%` }"
            />
        </div>

        <p class="mt-1.5 text-xs text-muted-foreground">
            {{ money(props.remaining) }} left to spend.
        </p>
    </div>
</template>
