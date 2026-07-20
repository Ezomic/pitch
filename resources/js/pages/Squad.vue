<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import BudgetBar from '@/components/squad/BudgetBar.vue';
import PitchFormation from '@/components/squad/PitchFormation.vue';
import PlayerPoolItem from '@/components/squad/PlayerPoolItem.vue';
import TeamProfile from '@/components/squad/TeamProfile.vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { assign, edit, tactics } from '@/routes/squad';
import type {
    PoolPlayer,
    Squad,
    SquadProfile,
    TacticOption,
} from '@/types/squad';

const props = defineProps<{
    squad: Squad;
    pool: PoolPlayer[];
    profile: SquadProfile;
    formations: TacticOption[];
    mentalities: TacticOption[];
}>();

function changeTactics(formation: string, mentality: string): void {
    router.patch(
        tactics().url,
        { formation, mentality },
        { preserveScroll: true, preserveState: true },
    );
}

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

const selectedSlotValue = computed(() => {
    if (selectedSlot.value === null) {
        return 0;
    }

    const slot = props.squad.slots.find((s) => s.slot === selectedSlot.value);

    return slot?.player?.value ?? 0;
});

function affordable(player: PoolPlayer): boolean {
    if (player.slot !== null) {
        return true;
    }

    return player.value <= props.squad.remaining + selectedSlotValue.value;
}

function canPick(player: PoolPlayer): boolean {
    return selectedSlot.value !== null && affordable(player);
}

function unaffordable(player: PoolPlayer): boolean {
    return (
        selectedSlot.value !== null &&
        player.slot === null &&
        !affordable(player)
    );
}

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
                <div
                    class="flex gap-3 rounded-xl border border-sidebar-border/70 p-3 dark:border-sidebar-border"
                >
                    <div class="flex-1">
                        <label class="mb-1 block text-xs text-muted-foreground"
                            >Formation</label
                        >
                        <Select
                            :model-value="props.squad.formation"
                            @update:model-value="
                                (v) =>
                                    changeTactics(
                                        String(v),
                                        props.squad.mentality,
                                    )
                            "
                        >
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="f in props.formations"
                                    :key="f.id"
                                    :value="f.id"
                                >
                                    {{ f.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex-1">
                        <label class="mb-1 block text-xs text-muted-foreground"
                            >Mentality</label
                        >
                        <Select
                            :model-value="props.squad.mentality"
                            @update:model-value="
                                (v) =>
                                    changeTactics(
                                        props.squad.formation,
                                        String(v),
                                    )
                            "
                        >
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="m in props.mentalities"
                                    :key="m.id"
                                    :value="m.id"
                                >
                                    {{ m.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
                <BudgetBar
                    :budget="props.squad.budget"
                    :spent="props.squad.spent"
                    :remaining="props.squad.remaining"
                />
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
                class="flex max-h-[80vh] flex-col gap-2 overflow-y-auto rounded-xl border border-sidebar-border/70 p-3 dark:border-sidebar-border"
            >
                <h2 class="px-1 text-sm font-medium text-muted-foreground">
                    Player pool
                </h2>
                <PlayerPoolItem
                    v-for="player in props.pool"
                    :key="player.id"
                    :player="player"
                    :can-pick="canPick(player)"
                    :unaffordable="unaffordable(player)"
                    @pick="pick"
                />
            </div>
        </div>
    </div>
</template>
