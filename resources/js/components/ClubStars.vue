<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        stars: number | null;
        label?: string;
    }>(),
    { label: '' },
);

// Five slots, each full, half or empty, so a rating reads at a glance.
const slots = computed(() => {
    const value = props.stars ?? 0;

    return [1, 2, 3, 4, 5].map((slot) => {
        if (value >= slot) {
            return 'full';
        }

        return value >= slot - 0.5 ? 'half' : 'empty';
    });
});

const title = computed(() =>
    props.stars === null
        ? 'Not rated'
        : `${props.label ? `${props.label}: ` : ''}${props.stars} of 5 stars`,
);
</script>

<template>
    <span
        v-if="props.stars !== null"
        class="inline-flex items-center gap-px align-middle"
        :title="title"
        :aria-label="title"
    >
        <span
            v-for="(slot, i) in slots"
            :key="i"
            class="relative block size-3 leading-none"
            aria-hidden="true"
        >
            <!-- The empty slot, always drawn, so the rating keeps its shape -->
            <svg
                viewBox="0 0 20 20"
                class="absolute inset-0 size-3 text-muted-foreground/35"
                fill="currentColor"
            >
                <path
                    d="M10 1.6l2.6 5.3 5.8.8-4.2 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8L1.6 7.7l5.8-.8L10 1.6z"
                />
            </svg>
            <!-- The lit part: the whole star, or its left half. Anchored left and
                 sized explicitly, since pinning both edges would defeat the clip
                 that makes a half star a half. -->
            <span
                v-if="slot !== 'empty'"
                class="absolute top-0 left-0 block h-full overflow-hidden"
                :style="{ width: slot === 'half' ? '50%' : '100%' }"
            >
                <svg
                    viewBox="0 0 20 20"
                    class="size-3 text-amber-400"
                    fill="currentColor"
                >
                    <path
                        d="M10 1.6l2.6 5.3 5.8.8-4.2 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8L1.6 7.7l5.8-.8L10 1.6z"
                    />
                </svg>
            </span>
        </span>
    </span>
    <span v-else class="text-muted-foreground">–</span>
</template>
