<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index, promote } from '@/routes/youth';
import type { Prospect } from '@/types/youth';

const props = defineProps<{
    prospects: Prospect[];
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

        <div
            class="flex-1 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
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
                    class="flex items-center justify-between gap-3 rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border"
                >
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="truncate text-sm font-medium">{{
                                prospect.name
                            }}</span>
                            <Badge variant="secondary">{{
                                prospect.position
                            }}</Badge>
                            <span class="text-xs text-muted-foreground"
                                >{{ prospect.age }}y</span
                            >
                        </div>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            Ability
                            <span class="text-foreground tabular-nums">{{
                                prospect.overall
                            }}</span>
                            &middot; potential
                            <span class="text-foreground tabular-nums">{{
                                prospect.potential
                            }}</span>
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
            </div>
        </div>
    </div>
</template>
