<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Columns2, TrendingUp } from '@lucide/vue';
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
import { assign, compare, edit, role, tactics, whatIf } from '@/routes/squad';
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
    roles: TacticOption[];
}>();

function changeTactics(formation: string, mentality: string): void {
    router.patch(
        tactics().url,
        { formation, mentality },
        { preserveScroll: true, preserveState: true },
    );
}

function setRole(slot: number, value: string): void {
    router.patch(
        role().url,
        { slot, role: value === '' ? null : value },
        { preserveScroll: true, preserveState: true },
    );
}

const filledSlots = () => props.squad.slots.filter((s) => s.player !== null);

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Squad', href: edit() }],
    },
});

const selectedSlot = ref<number | null>(null);
const previousProfile = ref<SquadProfile | null>(null);

function toggleSlot(slot: number): void {
    selectedSlot.value = selectedSlot.value === slot ? null : slot;
}

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
    return (
        selectedSlot.value !== null &&
        player.injuredWeeks === 0 &&
        player.suspendedWeeks === 0 &&
        affordable(player)
    );
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
        <div class="flex items-center justify-end gap-2">
            <Link
                :href="compare().url"
                class="flex items-center gap-1.5 rounded-md border border-sidebar-border/70 px-2.5 py-1.5 text-sm text-muted-foreground transition hover:bg-accent/40 dark:border-sidebar-border"
            >
                <Columns2 class="h-4 w-4" />
                A/B compare
            </Link>
            <Link
                :href="whatIf().url"
                class="flex items-center gap-1.5 rounded-md border border-sidebar-border/70 px-2.5 py-1.5 text-sm text-muted-foreground transition hover:bg-accent/40 dark:border-sidebar-border"
            >
                <TrendingUp class="h-4 w-4" />
                What-if analysis
            </Link>
        </div>
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
                    @select="toggleSlot"
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

                <div
                    v-if="filledSlots().length > 0"
                    class="rounded-xl border border-sidebar-border/70 p-3 dark:border-sidebar-border"
                >
                    <h2 class="mb-2 text-sm font-medium text-muted-foreground">
                        Roles
                    </h2>
                    <div class="grid gap-x-4 gap-y-1.5 sm:grid-cols-2">
                        <label
                            v-for="s in filledSlots()"
                            :key="s.slot"
                            class="flex items-center justify-between gap-2 text-sm"
                        >
                            <span class="min-w-0 truncate">{{
                                s.player?.name
                            }}</span>
                            <select
                                class="shrink-0 rounded-md border border-sidebar-border/70 bg-transparent px-1.5 py-1 text-xs dark:border-sidebar-border"
                                :value="s.role ?? ''"
                                @change="
                                    setRole(
                                        s.slot,
                                        ($event.target as HTMLSelectElement)
                                            .value,
                                    )
                                "
                            >
                                <option value="">No role</option>
                                <option
                                    v-for="r in props.roles"
                                    :key="r.id"
                                    :value="r.id"
                                >
                                    {{ r.name }}
                                </option>
                            </select>
                        </label>
                    </div>
                </div>
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
