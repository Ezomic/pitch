<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed } from 'vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { compare, edit } from '@/routes/squad';
import type { SquadProfile, TacticOption } from '@/types/squad';

interface Setup {
    formationA: string;
    mentalityA: string;
    formationB: string;
    mentalityB: string;
}

const props = defineProps<{
    setup: Setup;
    profiles: { a: SquadProfile; b: SquadProfile };
    formations: TacticOption[];
    mentalities: TacticOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Squad', href: edit() }],
    },
});

function update(patch: Partial<Setup>): void {
    router.get(
        compare().url,
        { ...props.setup, ...patch },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

const metrics: {
    key: keyof SquadProfile;
    label: string;
    higherIsBetter: boolean;
}[] = [
    { key: 'goalsPer90', label: 'Goals / 90', higherIsBetter: true },
    { key: 'chancesPer90', label: 'Chances / 90', higherIsBetter: true },
    {
        key: 'goalsConcededPer90',
        label: 'Goals conceded / 90',
        higherIsBetter: false,
    },
    {
        key: 'chancesConcededPer90',
        label: 'Chances conceded / 90',
        higherIsBetter: false,
    },
    {
        key: 'progressivePassShare',
        label: 'Progressive pass share',
        higherIsBetter: true,
    },
    { key: 'meanDecisionGap', label: 'Decision gap', higherIsBetter: false },
];

const rows = computed(() =>
    metrics.map((metric) => {
        const a = props.profiles.a[metric.key];
        const b = props.profiles.b[metric.key];
        const diff = b - a;
        const better =
            Math.abs(diff) < 0.005
                ? 'flat'
                : diff > 0 === metric.higherIsBetter
                  ? 'up'
                  : 'down';

        return { label: metric.label, a, b, diff, better };
    }),
);

function fmt(value: number): string {
    return value.toFixed(2);
}

function diffClass(better: string): string {
    if (better === 'up') {
        return 'text-emerald-600 dark:text-emerald-400';
    }

    if (better === 'down') {
        return 'text-red-600 dark:text-red-400';
    }

    return 'text-muted-foreground';
}
</script>

<template>
    <Head title="Compare" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div
            class="flex items-center justify-between gap-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <div>
                <p class="text-sm text-muted-foreground">A/B compare</p>
                <p class="text-lg font-medium">
                    Same players, two setups side by side
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

        <div class="grid gap-3 sm:grid-cols-2">
            <div
                v-for="side in ['A', 'B']"
                :key="side"
                class="flex gap-3 rounded-xl border border-sidebar-border/70 p-3 dark:border-sidebar-border"
            >
                <div class="flex-1">
                    <label class="mb-1 block text-xs text-muted-foreground"
                        >Setup {{ side }} formation</label
                    >
                    <Select
                        :model-value="
                            side === 'A'
                                ? props.setup.formationA
                                : props.setup.formationB
                        "
                        @update:model-value="
                            (v) =>
                                update(
                                    side === 'A'
                                        ? { formationA: String(v) }
                                        : { formationB: String(v) },
                                )
                        "
                    >
                        <SelectTrigger class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="f in props.formations"
                                :key="f.id"
                                :value="f.id"
                                >{{ f.name }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
                <div class="flex-1">
                    <label class="mb-1 block text-xs text-muted-foreground"
                        >Mentality</label
                    >
                    <Select
                        :model-value="
                            side === 'A'
                                ? props.setup.mentalityA
                                : props.setup.mentalityB
                        "
                        @update:model-value="
                            (v) =>
                                update(
                                    side === 'A'
                                        ? { mentalityA: String(v) }
                                        : { mentalityB: String(v) },
                                )
                        "
                    >
                        <SelectTrigger class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="m in props.mentalities"
                                :key="m.id"
                                :value="m.id"
                                >{{ m.name }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
            </div>
        </div>

        <div
            class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <table class="w-full min-w-[420px] text-sm">
                <thead>
                    <tr class="text-muted-foreground">
                        <th class="px-3 py-2 text-left font-medium">Metric</th>
                        <th class="px-3 py-2 text-right font-medium">A</th>
                        <th class="px-3 py-2 text-right font-medium">B</th>
                        <th class="px-3 py-2 text-right font-medium">B - A</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in rows"
                        :key="row.label"
                        class="border-t border-sidebar-border/50"
                    >
                        <td class="px-3 py-2">{{ row.label }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">
                            {{ fmt(row.a) }}
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums">
                            {{ fmt(row.b) }}
                        </td>
                        <td
                            class="px-3 py-2 text-right tabular-nums"
                            :class="diffClass(row.better)"
                        >
                            {{ row.diff > 0 ? '+' : '' }}{{ fmt(row.diff) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="text-xs text-muted-foreground">
            Green means setup B is better than A on that metric.
        </p>
    </div>
</template>
