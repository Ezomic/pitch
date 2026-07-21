<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        fitness: number;
        form: number;
        showForm?: boolean;
    }>(),
    { showForm: true },
);

const barClass = computed(() => {
    if (props.fitness >= 66) {
        return 'bg-emerald-500';
    }

    if (props.fitness >= 33) {
        return 'bg-amber-500';
    }

    return 'bg-red-500';
});

const formClass = computed(() => {
    if (props.form > 0) {
        return 'text-emerald-600 dark:text-emerald-400';
    }

    if (props.form < 0) {
        return 'text-red-600 dark:text-red-400';
    }

    return 'text-muted-foreground';
});

const formLabel = computed(() =>
    props.form > 0 ? `+${props.form}` : String(props.form),
);
</script>

<template>
    <div class="flex items-center gap-1.5">
        <span
            class="w-6 text-[10px] font-medium tracking-wide text-muted-foreground"
            >FIT</span
        >
        <span class="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
            <span
                class="block h-full rounded-full"
                :class="barClass"
                :style="{ width: `${Math.max(0, Math.min(100, fitness))}%` }"
            />
        </span>
        <span
            class="w-7 text-right text-[10px] text-muted-foreground tabular-nums"
            >{{ fitness }}%</span
        >
        <span
            v-if="showForm"
            class="w-6 text-right text-[10px] font-medium tabular-nums"
            :class="formClass"
            :title="`Form ${formLabel}`"
            >{{ formLabel }}</span
        >
    </div>
</template>
