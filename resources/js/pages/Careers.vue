<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Check, Pencil, Play, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { destroy, index, rename, store, switchMethod } from '@/routes/careers';

interface CareerRow {
    id: number;
    name: string;
    type: string;
    active: boolean;
    lastPlayedAt: string | null;
    squadName: string | null;
    division: number | null;
    seasons: number;
    currentSeason: number | null;
}

const props = defineProps<{
    careers: CareerRow[];
    activeId: number;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Careers', href: index() }] },
});

const page = usePage();
const error = computed(
    () => (page.props.errors as Record<string, string>)?.career ?? null,
);

const newName = ref('');
const renamingId = ref<number | null>(null);
const renameTo = ref('');

// The last save cannot go, so the button is not offered when there is only one.
const canDelete = computed(() => props.careers.length > 1);

function create(): void {
    const name = newName.value.trim();

    if (!name) {
        return;
    }

    router.post(store().url, { name }, { preserveScroll: true });
    newName.value = '';
}

function play(career: CareerRow): void {
    router.post(switchMethod(career.id).url);
}

function startRename(career: CareerRow): void {
    renamingId.value = career.id;
    renameTo.value = career.name;
}

function commitRename(career: CareerRow): void {
    const name = renameTo.value.trim();
    renamingId.value = null;

    if (!name || name === career.name) {
        return;
    }

    router.patch(rename(career.id).url, { name }, { preserveScroll: true });
}

// Deleting a save takes its squad, seasons, players and matches with it, so it
// asks first and names what is going.
function remove(career: CareerRow): void {
    if (
        !window.confirm(
            `Delete "${career.name}"? Its squad, seasons and every match played in it go too. This cannot be undone.`,
        )
    ) {
        return;
    }

    router.delete(destroy(career.id).url, { preserveScroll: true });
}
</script>

<template>
    <Head title="Careers" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div
            class="flex flex-wrap items-end justify-between gap-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <div>
                <h1 class="text-lg font-medium">Your saves</h1>
                <p class="text-sm text-muted-foreground">
                    Each career is its own club, season and squad. Only one is
                    played at a time.
                </p>
            </div>
            <div class="flex items-end gap-2">
                <label class="flex flex-col gap-1">
                    <span class="text-xs text-muted-foreground"
                        >New career</span
                    >
                    <input
                        v-model="newName"
                        type="text"
                        maxlength="60"
                        placeholder="Name this save"
                        class="w-48 rounded-md border border-border bg-transparent px-2 py-1.5 text-sm"
                        @keyup.enter="create"
                    />
                </label>
                <Button :disabled="!newName.trim()" @click="create">
                    Start
                </Button>
            </div>
        </div>

        <p
            v-if="error"
            class="rounded-md border border-destructive/40 px-3 py-2 text-sm text-destructive"
        >
            {{ error }}
        </p>

        <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="career in props.careers"
                :key="career.id"
                class="flex flex-col gap-3 rounded-xl border p-4"
                :class="
                    career.active
                        ? 'border-primary bg-primary/5'
                        : 'border-sidebar-border/70 dark:border-sidebar-border'
                "
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <input
                            v-if="renamingId === career.id"
                            v-model="renameTo"
                            type="text"
                            maxlength="60"
                            class="w-full rounded-md border border-border bg-transparent px-2 py-1 text-sm font-medium"
                            @keyup.enter="commitRename(career)"
                            @blur="commitRename(career)"
                        />
                        <h2 v-else class="truncate font-medium">
                            {{ career.name }}
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            {{ career.squadName ?? 'Not started' }}
                            <template v-if="career.division">
                                &middot; Division {{ career.division }}
                            </template>
                        </p>
                    </div>
                    <span
                        v-if="career.active"
                        class="flex shrink-0 items-center gap-1 rounded-md bg-primary/15 px-2 py-0.5 text-xs font-medium text-primary"
                    >
                        <Check class="size-3" /> Playing
                    </span>
                </div>

                <dl class="grid grid-cols-2 gap-1 text-xs">
                    <dt class="text-muted-foreground">Season</dt>
                    <dd class="text-right font-mono tabular-nums">
                        {{ career.currentSeason ?? '—' }}
                    </dd>
                    <dt class="text-muted-foreground">Seasons played</dt>
                    <dd class="text-right font-mono tabular-nums">
                        {{ career.seasons }}
                    </dd>
                    <dt class="text-muted-foreground">Last played</dt>
                    <dd class="text-right font-mono tabular-nums">
                        {{ career.lastPlayedAt ?? '—' }}
                    </dd>
                </dl>

                <div class="mt-auto flex flex-wrap gap-2">
                    <Button
                        v-if="!career.active"
                        size="sm"
                        class="flex-1"
                        @click="play(career)"
                    >
                        <Play class="size-3.5" /> Play
                    </Button>
                    <Button
                        size="sm"
                        variant="outline"
                        @click="startRename(career)"
                    >
                        <Pencil class="size-3.5" /> Rename
                    </Button>
                    <Button
                        v-if="canDelete"
                        size="sm"
                        variant="outline"
                        class="text-destructive"
                        @click="remove(career)"
                    >
                        <Trash2 class="size-3.5" /> Delete
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
