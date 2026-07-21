<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { edit, whatIf } from '@/routes/squad';
import type { Marginal } from '@/types/squad';

const props = defineProps<{
    marginal: Marginal;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Squad', href: edit() },
            { title: 'What-if', href: whatIf() },
        ],
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

function fmt(value: number): string {
    if (value === 0) {
        return '0';
    }

    return `${value > 0 ? '+' : ''}${value.toFixed(2)}`;
}

function goalsClass(value: number): string {
    if (value > 0.005) {
        return 'text-emerald-600 dark:text-emerald-400';
    }

    if (value < -0.005) {
        return 'text-red-600 dark:text-red-400';
    }

    return 'text-muted-foreground';
}

function concededClass(value: number): string {
    if (value < -0.005) {
        return 'text-emerald-600 dark:text-emerald-400';
    }

    if (value > 0.005) {
        return 'text-red-600 dark:text-red-400';
    }

    return 'text-muted-foreground';
}
</script>

<template>
    <Head title="What-if" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm text-muted-foreground">Marginal value</p>
                    <p class="text-lg font-medium">
                        What a +{{ props.marginal.delta }} to each attribute
                        would do
                    </p>
                </div>
                <Link
                    :href="edit().url"
                    class="flex shrink-0 items-center gap-1.5 rounded-md border border-sidebar-border/70 px-2.5 py-1.5 text-sm text-muted-foreground transition hover:bg-accent/40 dark:border-sidebar-border"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Squad
                </Link>
            </div>
            <p class="mt-2 text-xs text-muted-foreground">
                Each cell is the change to goals scored and conceded per match
                if that player gained +{{ props.marginal.delta }} in that
                attribute. Baseline:
                {{ props.marginal.baseline.goals.toFixed(2) }} scored,
                {{ props.marginal.baseline.conceded.toFixed(2) }} conceded per
                match. Green is better.
            </p>
        </div>

        <div
            class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <table class="w-full min-w-[640px] text-sm">
                <thead>
                    <tr class="text-muted-foreground">
                        <th class="px-3 py-2 text-left font-medium">Player</th>
                        <th
                            v-for="attr in attributes"
                            :key="attr.key"
                            class="px-3 py-2 text-center font-medium"
                        >
                            {{ attr.label }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in props.marginal.rows"
                        :key="row.slot"
                        class="border-t border-sidebar-border/50"
                    >
                        <td class="px-3 py-2 font-medium">{{ row.name }}</td>
                        <td
                            v-for="attr in attributes"
                            :key="attr.key"
                            class="px-3 py-2 text-center tabular-nums"
                        >
                            <div
                                :class="
                                    goalsClass(row.attributes[attr.key].goals)
                                "
                            >
                                {{ fmt(row.attributes[attr.key].goals) }}
                            </div>
                            <div
                                class="text-xs"
                                :class="
                                    concededClass(
                                        row.attributes[attr.key].conceded,
                                    )
                                "
                            >
                                {{ fmt(row.attributes[attr.key].conceded) }}
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="text-xs text-muted-foreground">
            Top number is goals scored per match, bottom is goals conceded.
        </p>
    </div>
</template>
