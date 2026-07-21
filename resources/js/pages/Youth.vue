<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { focus, index, promote } from '@/routes/youth';
import type { StandingRow } from '@/types/season';
import type { Prospect, YouthFixture } from '@/types/youth';

const props = defineProps<{
    prospects: Prospect[];
    leagueTable: StandingRow[];
    fixtures: YouthFixture[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Academy', href: index() }],
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

function promoteProspect(id: number): void {
    router.post(promote(id).url, {}, { preserveScroll: true });
}

function setFocus(prospect: Prospect, key: string): void {
    router.patch(
        focus(prospect.id).url,
        { focus: prospect.trainingFocus === key ? null : key },
        { preserveScroll: true, preserveState: true },
    );
}
</script>

<template>
    <Head title="Academy" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <p class="text-sm text-muted-foreground">Youth academy</p>
            <p class="text-lg font-medium">
                {{ props.prospects.length }} prospect{{
                    props.prospects.length === 1 ? '' : 's'
                }}
                developing
            </p>
        </div>

        <div class="grid flex-1 gap-4 lg:grid-cols-[1.2fr_1fr]">
            <div
                class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <h2 class="mb-3 text-sm font-medium text-muted-foreground">
                    Prospects
                </h2>
                <p
                    v-if="props.prospects.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No prospects yet. Hire a scout and send them out to start
                    finding youth.
                </p>

                <div v-else class="flex flex-col gap-3">
                    <div
                        v-for="prospect in props.prospects"
                        :key="prospect.id"
                        class="rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="truncate text-sm font-medium"
                                        >{{ prospect.name }}</span
                                    >
                                    <Badge variant="secondary">{{
                                        prospect.position
                                    }}</Badge>
                                    <span class="text-xs text-muted-foreground"
                                        >{{ prospect.age }}y</span
                                    >
                                </div>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    Ability
                                    <span
                                        class="text-foreground tabular-nums"
                                        >{{ prospect.overall }}</span
                                    >
                                    &middot; potential
                                    <span
                                        class="text-foreground tabular-nums"
                                        >{{ prospect.potential }}</span
                                    >
                                </p>
                            </div>
                            <Button
                                v-if="prospect.promotable"
                                size="sm"
                                @click="promoteProspect(prospect.id)"
                            >
                                Promote
                            </Button>
                            <Badge v-else variant="outline" class="shrink-0">
                                Developing
                            </Badge>
                        </div>

                        <p class="mt-2 mb-1 text-[11px] text-muted-foreground">
                            Tap an attribute to make it this prospect's training
                            focus.
                        </p>
                        <div class="grid grid-cols-2 gap-x-3 gap-y-1">
                            <button
                                v-for="attr in attributes"
                                :key="attr.key"
                                type="button"
                                class="flex items-center gap-1.5 rounded px-1 py-0.5 text-left transition"
                                :class="
                                    prospect.trainingFocus === attr.key
                                        ? 'bg-accent'
                                        : 'hover:bg-accent/40'
                                "
                                @click="setFocus(prospect, attr.key)"
                            >
                                <span
                                    class="w-7 text-[10px] font-medium tracking-wide"
                                    :class="
                                        prospect.trainingFocus === attr.key
                                            ? 'text-accent-foreground'
                                            : 'text-muted-foreground'
                                    "
                                    >{{ attr.label }}</span
                                >
                                <span
                                    class="h-1.5 flex-1 overflow-hidden rounded-full bg-muted"
                                >
                                    <span
                                        class="block h-full rounded-full"
                                        :class="
                                            prospect.trainingFocus === attr.key
                                                ? 'bg-emerald-500'
                                                : 'bg-foreground/70'
                                        "
                                        :style="{
                                            width: `${(prospect.attributes[attr.key] / 20) * 100}%`,
                                        }"
                                    />
                                </span>
                                <span
                                    class="w-4 text-right text-[10px] text-muted-foreground tabular-nums"
                                    >{{ prospect.attributes[attr.key] }}</span
                                >
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="flex flex-col gap-4 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <div>
                    <h2 class="mb-3 text-sm font-medium text-muted-foreground">
                        Youth league
                    </h2>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-muted-foreground">
                                <th class="py-1 text-left font-medium">Team</th>
                                <th class="w-8 py-1 text-right font-medium">
                                    P
                                </th>
                                <th class="w-8 py-1 text-right font-medium">
                                    GD
                                </th>
                                <th class="w-8 py-1 text-right font-medium">
                                    Pts
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in props.leagueTable"
                                :key="row.name"
                                class="border-t border-sidebar-border/50"
                                :class="
                                    row.isUser
                                        ? 'font-medium text-accent-foreground'
                                        : ''
                                "
                            >
                                <td class="truncate py-1.5">{{ row.name }}</td>
                                <td class="py-1.5 text-right tabular-nums">
                                    {{ row.played }}
                                </td>
                                <td class="py-1.5 text-right tabular-nums">
                                    {{ row.goalDifference }}
                                </td>
                                <td class="py-1.5 text-right tabular-nums">
                                    {{ row.points }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div>
                    <h3 class="mb-2 text-xs font-medium text-muted-foreground">
                        Academy fixtures
                    </h3>
                    <div class="flex flex-col gap-1">
                        <div
                            v-for="fixture in props.fixtures"
                            :key="fixture.id"
                            class="flex items-center justify-between gap-2 rounded-md px-2 py-1 text-sm"
                        >
                            <span class="truncate">
                                <span class="text-muted-foreground">v</span>
                                {{ fixture.opponent }}
                            </span>
                            <span
                                v-if="fixture.played"
                                class="shrink-0 tabular-nums"
                            >
                                {{ fixture.goalsFor }}-{{
                                    fixture.goalsAgainst
                                }}
                            </span>
                            <span v-else class="shrink-0 text-muted-foreground"
                                >&mdash;</span
                            >
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
