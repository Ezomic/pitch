<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { assign, hire, index, recall } from '@/routes/scouts';
import type { Scout } from '@/types/scout';

const props = defineProps<{
    budget: number;
    currentDate: string;
    academyCount: number;
    staff: Scout[];
    market: Scout[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Scouting', href: index() }],
    },
});

function hireScout(id: number): void {
    router.post(hire(id).url, {}, { preserveScroll: true });
}

function assignScout(id: number): void {
    router.post(assign(id).url, {}, { preserveScroll: true });
}

function recallScout(id: number): void {
    router.post(recall(id).url, {}, { preserveScroll: true });
}

function stars(rating: number): string {
    return '★'.repeat(rating) + '☆'.repeat(5 - rating);
}

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}
</script>

<template>
    <Head title="Scouting" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div
            class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ formatDate(props.currentDate) }}
                </p>
                <p class="text-lg font-medium">
                    Scouting network &middot; {{ props.academyCount }} in the
                    academy
                </p>
            </div>
            <div class="text-right">
                <p class="text-sm text-muted-foreground">Transfer budget</p>
                <p class="text-lg font-medium tabular-nums">
                    £{{ props.budget }}m
                </p>
            </div>
        </div>

        <div class="grid flex-1 gap-4 lg:grid-cols-2">
            <div
                class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <h2 class="text-sm font-medium text-muted-foreground">
                    Your scouts
                </h2>
                <p
                    v-if="props.staff.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No scouts on the books yet. Hire one from the market to
                    start finding youth.
                </p>
                <div
                    v-for="scout in props.staff"
                    :key="scout.id"
                    class="flex items-center justify-between gap-3 rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium">
                            {{ scout.name }}
                        </p>
                        <p class="text-xs text-amber-500">
                            {{ stars(scout.rating) }}
                        </p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{ scout.statusLabel
                            }}<template v-if="scout.nextDelivery">
                                &middot; next intake
                                {{ formatDate(scout.nextDelivery) }}</template
                            >
                        </p>
                    </div>
                    <Button
                        v-if="scout.status === 'idle'"
                        size="sm"
                        @click="assignScout(scout.id)"
                    >
                        Send scouting
                    </Button>
                    <Button
                        v-else
                        size="sm"
                        variant="outline"
                        @click="recallScout(scout.id)"
                    >
                        Recall
                    </Button>
                </div>
            </div>

            <div
                class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <h2 class="text-sm font-medium text-muted-foreground">
                    Hire a scout
                </h2>
                <div
                    v-for="scout in props.market"
                    :key="scout.id"
                    class="flex items-center justify-between gap-3 rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium">
                            {{ scout.name }}
                        </p>
                        <p class="text-xs text-amber-500">
                            {{ stars(scout.rating) }}
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <Badge variant="secondary" class="tabular-nums">
                            £{{ scout.cost }}m
                        </Badge>
                        <Button
                            size="sm"
                            :disabled="scout.cost > props.budget"
                            @click="hireScout(scout.id)"
                        >
                            Hire
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
