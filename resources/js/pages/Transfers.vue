<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index, renew, sell, sign } from '@/routes/transfers';

interface TransferPlayer {
    id: number;
    name: string;
    position: string;
    age: number;
    overall: number;
    value: number;
    wage: number;
    contractYears: number;
    affordable: boolean;
}

interface Finances {
    income: number;
    wageBill: number;
    net: number;
}

const props = defineProps<{
    bank: number;
    finances: Finances;
    market: TransferPlayer[];
    owned: TransferPlayer[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Transfers', href: index() }],
    },
});

function signPlayer(id: number): void {
    router.post(sign(id).url, {}, { preserveScroll: true });
}

function sellPlayer(id: number): void {
    router.post(sell(id).url, {}, { preserveScroll: true });
}

function renewPlayer(id: number): void {
    router.post(renew(id).url, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Transfers" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div
                class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <p class="text-sm text-muted-foreground">Transfer bank</p>
                <p
                    class="text-2xl font-medium tabular-nums"
                    :class="props.bank < 0 ? 'text-destructive' : ''"
                >
                    £{{ props.bank }}m
                </p>
            </div>
            <div
                class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <p class="text-sm text-muted-foreground">Weekly income</p>
                <p class="text-2xl font-medium tabular-nums">
                    £{{ props.finances.income }}m
                </p>
            </div>
            <div
                class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <p class="text-sm text-muted-foreground">Weekly wages</p>
                <p class="text-2xl font-medium tabular-nums">
                    £{{ props.finances.wageBill }}m
                </p>
            </div>
            <div
                class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <p class="text-sm text-muted-foreground">Weekly net</p>
                <p
                    class="text-2xl font-medium tabular-nums"
                    :class="props.finances.net < 0 ? 'text-destructive' : ''"
                >
                    {{ props.finances.net < 0 ? '-' : '+' }}£{{
                        Math.abs(props.finances.net)
                    }}m
                </p>
            </div>
        </div>

        <div class="grid flex-1 gap-4 lg:grid-cols-2">
            <div
                class="flex flex-col gap-2 rounded-xl border border-sidebar-border/70 p-3 dark:border-sidebar-border"
            >
                <h2 class="px-1 text-sm font-medium text-muted-foreground">
                    Free agents
                </h2>
                <p
                    v-if="props.market.length === 0"
                    class="px-1 text-sm text-muted-foreground"
                >
                    No players on the market.
                </p>
                <div
                    v-for="player in props.market"
                    :key="player.id"
                    class="flex items-center justify-between gap-2 rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border"
                >
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="truncate text-sm font-medium">{{
                                player.name
                            }}</span>
                            <Badge variant="secondary">{{
                                player.position
                            }}</Badge>
                        </div>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{ player.age }}y &middot; ability
                            <span class="text-foreground tabular-nums">{{
                                player.overall
                            }}</span>
                            &middot;
                            <span class="tabular-nums"
                                >£{{ player.wage }}m/wk</span
                            >
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <span class="text-sm font-medium tabular-nums"
                            >£{{ player.value }}m</span
                        >
                        <Button
                            size="sm"
                            :disabled="!player.affordable"
                            @click="signPlayer(player.id)"
                        >
                            Sign
                        </Button>
                    </div>
                </div>
            </div>

            <div
                class="flex flex-col gap-2 rounded-xl border border-sidebar-border/70 p-3 dark:border-sidebar-border"
            >
                <h2 class="px-1 text-sm font-medium text-muted-foreground">
                    Your players
                </h2>
                <p
                    v-if="props.owned.length === 0"
                    class="px-1 text-sm text-muted-foreground"
                >
                    You have not signed any players yet.
                </p>
                <div
                    v-for="player in props.owned"
                    :key="player.id"
                    class="flex items-center justify-between gap-2 rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border"
                >
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="truncate text-sm font-medium">{{
                                player.name
                            }}</span>
                            <Badge variant="secondary">{{
                                player.position
                            }}</Badge>
                        </div>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{ player.age }}y &middot; ability
                            <span class="text-foreground tabular-nums">{{
                                player.overall
                            }}</span>
                            &middot;
                            <span class="tabular-nums"
                                >£{{ player.wage }}m/wk</span
                            >
                        </p>
                        <p class="mt-0.5 text-xs">
                            <Badge
                                :variant="
                                    player.contractYears <= 1
                                        ? 'destructive'
                                        : 'outline'
                                "
                            >
                                {{ player.contractYears }}y contract
                            </Badge>
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <span class="text-sm font-medium tabular-nums"
                            >£{{ player.value }}m</span
                        >
                        <Button
                            size="sm"
                            variant="secondary"
                            @click="renewPlayer(player.id)"
                        >
                            Renew
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            @click="sellPlayer(player.id)"
                        >
                            Sell
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
