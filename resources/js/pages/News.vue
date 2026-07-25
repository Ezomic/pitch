<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { accept, decline, index } from '@/routes/news';

interface NewsItem {
    id: number;
    category: 'result' | 'board' | 'offer';
    title: string;
    body: string;
    read: boolean;
    date: string | null;
    offer: { fee: number } | null;
}

const props = defineProps<{
    items: NewsItem[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'News', href: index() }],
    },
});

const label: Record<NewsItem['category'], string> = {
    result: 'Result',
    board: 'Board',
    offer: 'Offer',
};

function acceptOffer(id: number): void {
    router.post(accept(id).url, {}, { preserveScroll: true });
}

function declineOffer(id: number): void {
    router.post(decline(id).url, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="News" />

    <div class="mx-auto flex h-full w-full max-w-2xl flex-1 flex-col gap-3 p-4">
        <h1 class="text-lg font-medium">Inbox</h1>

        <p
            v-if="props.items.length === 0"
            class="rounded-xl border border-sidebar-border/70 p-6 text-center text-sm text-muted-foreground dark:border-sidebar-border"
        >
            Nothing in the inbox yet. Play on and results, board notes and
            transfer offers will land here.
        </p>

        <div
            v-for="item in props.items"
            :key="item.id"
            class="rounded-xl border p-4 dark:border-sidebar-border"
            :class="
                item.read
                    ? 'border-sidebar-border/70'
                    : 'border-accent-foreground/30 bg-accent/30'
            "
        >
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span
                        v-if="!item.read"
                        class="h-2 w-2 rounded-full bg-accent-foreground"
                    />
                    <span class="text-sm font-medium">{{ item.title }}</span>
                </div>
                <Badge
                    :variant="
                        item.category === 'offer' ? 'default' : 'secondary'
                    "
                    >{{ label[item.category] }}</Badge
                >
            </div>
            <p class="mt-1 text-sm text-muted-foreground">{{ item.body }}</p>

            <div v-if="item.offer" class="mt-3 flex items-center gap-2">
                <Button size="sm" @click="acceptOffer(item.id)">
                    Accept £{{ item.offer.fee }}m
                </Button>
                <Button
                    size="sm"
                    variant="outline"
                    @click="declineOffer(item.id)"
                >
                    Decline
                </Button>
            </div>

            <p v-if="item.date" class="mt-2 text-xs text-muted-foreground/70">
                {{ item.date }}
            </p>
        </div>
    </div>
</template>
