<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import ConditionIndicator from '@/components/ConditionIndicator.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index, promote } from '@/routes/youth';
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

function promoteProspect(id: number): void {
    router.post(promote(id).url, {}, { preserveScroll: true });
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

                <div v-else class="flex flex-col gap-2">
                    <div
                        v-for="prospect in props.prospects"
                        :key="prospect.id"
                        class="rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border"
                    >
                        <div class="flex items-center justify-between gap-3">
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
                        <div
                            class="mt-2 border-t border-sidebar-border/40 pt-2"
                        >
                            <ConditionIndicator
                                :fitness="prospect.fitness"
                                :form="prospect.form"
                            />
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
