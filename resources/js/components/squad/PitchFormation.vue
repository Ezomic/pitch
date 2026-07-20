<script setup lang="ts">
import type { SquadSlot } from '@/types/squad';

defineProps<{
    slots: SquadSlot[];
    selectedSlot: number | null;
}>();

const emit = defineEmits<{
    select: [slot: number];
}>();

function position(slot: SquadSlot): Record<string, string> {
    return {
        left: `${(slot.zone.x / 5) * 100}%`,
        top: `${12 + (slot.zone.y / 2) * 76}%`,
    };
}

function surname(name: string): string {
    const parts = name.trim().split(' ');

    return parts[parts.length - 1];
}
</script>

<template>
    <div
        class="relative aspect-[4/3] w-full overflow-hidden rounded-xl border border-emerald-900/30 bg-emerald-700 dark:bg-emerald-800"
    >
        <div
            class="pointer-events-none absolute inset-y-0 left-1/2 w-px -translate-x-1/2 bg-white/25"
        />
        <div
            class="pointer-events-none absolute top-1/2 left-1/2 aspect-square h-1/3 -translate-x-1/2 -translate-y-1/2 rounded-full border border-white/25"
        />
        <div
            class="pointer-events-none absolute inset-y-1/4 left-0 w-[8%] border-y border-r border-white/25"
        />
        <div
            class="pointer-events-none absolute inset-y-1/4 right-0 w-[8%] border-y border-l border-white/25"
        />

        <button
            v-for="slot in slots"
            :key="slot.slot"
            type="button"
            class="absolute flex -translate-x-1/2 -translate-y-1/2 flex-col items-center gap-1 focus:outline-none"
            :style="position(slot)"
            @click="emit('select', slot.slot)"
        >
            <span
                class="flex h-12 w-12 items-center justify-center rounded-full border text-sm font-medium shadow-sm transition"
                :class="
                    selectedSlot === slot.slot
                        ? 'border-white bg-white text-emerald-900 ring-2 ring-white'
                        : 'border-white/60 bg-emerald-900/70 text-white hover:bg-emerald-900'
                "
            >
                {{ slot.player?.vision ?? '+' }}
            </span>
            <span
                class="max-w-20 truncate rounded bg-emerald-950/70 px-1.5 py-0.5 text-xs text-white"
            >
                {{ slot.player ? surname(slot.player.name) : 'Empty' }}
            </span>
        </button>
    </div>
</template>
