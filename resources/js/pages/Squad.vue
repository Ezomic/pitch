<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import PitchFormation from '@/components/squad/PitchFormation.vue';
import PlayerPoolItem from '@/components/squad/PlayerPoolItem.vue';
import TeamProfile from '@/components/squad/TeamProfile.vue';
import { assign, edit } from '@/routes/squad';
import type { PoolPlayer, Squad, SquadProfile } from '@/types/squad';

const props = defineProps<{
    squad: Squad;
    pool: PoolPlayer[];
    profile: SquadProfile;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Squad', href: edit() }],
    },
});

const selectedSlot = ref<number | null>(null);
const previousProfile = ref<SquadProfile | null>(null);

watch(
    () => props.profile,
    (_next, prev) => {
        previousProfile.value = prev ?? null;
    },
);

function pick(playerId: number): void {
    if (selectedSlot.value === null) {
        return;
    }

    router.patch(
        assign().url,
        { slot: selectedSlot.value, player_id: playerId },
        { preserveScroll: true, preserveState: true },
    );
}
</script>

<template>
    <Head title="Squad" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <TeamProfile :profile="props.profile" :previous="previousProfile" />

        <div class="grid flex-1 gap-4 lg:grid-cols-[1.4fr_1fr]">
            <div class="flex flex-col gap-3">
                <PitchFormation
                    :slots="props.squad.slots"
                    :selected-slot="selectedSlot"
                    @select="(slot) => (selectedSlot = slot)"
                />
                <p class="text-sm text-muted-foreground">
                    <template v-if="selectedSlot === null">
                        Select a position on the pitch, then choose a player to
                        fill it.
                    </template>
                    <template v-else>
                        Position {{ selectedSlot }} selected. Pick a player to
                        swap in, or choose another position.
                    </template>
                </p>
            </div>

            <div
                class="flex max-h-[70vh] flex-col gap-2 overflow-y-auto rounded-xl border border-sidebar-border/70 p-3 dark:border-sidebar-border"
            >
                <h2 class="px-1 text-sm font-medium text-muted-foreground">
                    Player pool
                </h2>
                <PlayerPoolItem
                    v-for="player in props.pool"
                    :key="player.id"
                    :player="player"
                    :can-pick="selectedSlot !== null"
                    @pick="pick"
                />
            </div>
        </div>
    </div>
</template>
