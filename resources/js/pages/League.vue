<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Check, Copy, Crown, UserMinus } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/careers';
import {
    cadence,
    claim,
    invite,
    order,
    release,
    remove,
} from '@/routes/league';

interface Member {
    id: number;
    name: string;
    club: string | null;
    owner: boolean;
    you: boolean;
}

interface Club {
    id: number;
    name: string;
    division: number;
}

interface Invitation {
    id: number;
    url: string;
    status: string;
    expiresAt: string | null;
}

interface RoundFixture {
    id: number;
    home: string | null;
    away: string | null;
    homeGoals: number | null;
    awayGoals: number | null;
    played: boolean;
    duel: boolean;
    yours: boolean;
}

interface Round {
    matchday: number;
    deadlineAt: string | null;
    waitingOn: string[];
    yourOrder: {
        formation: string;
        mentality: string;
        ready: boolean;
    } | null;
    fixtures: RoundFixture[];
}

interface Choice {
    id: string;
    name: string;
}

const props = defineProps<{
    career: { id: number; name: string; roundHours: number };
    isOwner: boolean;
    yourTeamId: number | null;
    members: Member[];
    availableClubs: Club[];
    invitations: Invitation[];
    round: Round | null;
    formations: Choice[];
    mentalities: Choice[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Careers', href: index() }] },
});

const page = usePage();
const error = computed(() => {
    const errors = (page.props.errors ?? {}) as Record<string, string>;

    return (
        errors.club ?? errors.member ?? errors.order ?? errors.formation ?? null
    );
});

const copied = ref<number | null>(null);

const formation = ref(props.round?.yourOrder?.formation ?? '442');
const mentality = ref(props.round?.yourOrder?.mentality ?? 'balanced');
const hours = ref(props.career.roundHours);

const deadline = computed(() => {
    const at = props.round?.deadlineAt;

    return at === null || at === undefined
        ? null
        : new Date(at).toLocaleString();
});

function submit(ready: boolean): void {
    router.post(
        order(props.career.id).url,
        { formation: formation.value, mentality: mentality.value, ready },
        { preserveScroll: true },
    );
}

function setCadence(): void {
    router.post(
        cadence(props.career.id).url,
        { hours: hours.value },
        { preserveScroll: true },
    );
}

function score(fixture: RoundFixture): string {
    return fixture.played ? `${fixture.homeGoals} - ${fixture.awayGoals}` : 'v';
}

function invited(): void {
    router.post(invite(props.career.id).url, {}, { preserveScroll: true });
}

async function copy(invitation: Invitation): Promise<void> {
    await navigator.clipboard.writeText(invitation.url);
    copied.value = invitation.id;
    window.setTimeout(() => (copied.value = null), 1500);
}

function take(club: Club): void {
    router.post(
        claim({ career: props.career.id, team: club.id }).url,
        {},
        { preserveScroll: true },
    );
}

function giveBack(): void {
    router.delete(release(props.career.id).url, { preserveScroll: true });
}

function kick(member: Member): void {
    if (
        !window.confirm(
            `Remove ${member.name} from the league? Their club goes back to the AI.`,
        )
    ) {
        return;
    }

    router.delete(remove({ career: props.career.id, member: member.id }).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="props.career.name" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div
            class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <div>
                <h1 class="text-lg font-medium">{{ props.career.name }}</h1>
                <p class="text-sm text-muted-foreground">
                    {{ props.members.length }}
                    {{ props.members.length === 1 ? 'manager' : 'managers' }}.
                    Every club nobody has taken is run by the AI.
                </p>
            </div>
            <Button v-if="props.isOwner" @click="invited">
                Create invite link
            </Button>
        </div>

        <p
            v-if="error"
            class="rounded-md border border-destructive/40 px-3 py-2 text-sm text-destructive"
        >
            {{ error }}
        </p>

        <div
            v-if="props.round"
            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <div
                class="mb-3 flex flex-wrap items-baseline justify-between gap-2"
            >
                <h2 class="text-sm font-medium">
                    Matchday {{ props.round.matchday }}
                </h2>
                <p class="text-xs text-muted-foreground">
                    <template v-if="props.round.waitingOn.length">
                        Waiting on
                        {{ props.round.waitingOn.join(', ') }}
                    </template>
                    <template v-else>Everyone is ready.</template>
                    <template v-if="deadline">
                        Plays without them after {{ deadline }}.
                    </template>
                </p>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <div v-if="props.yourTeamId" class="flex flex-col gap-2">
                    <div class="flex flex-wrap items-end gap-2">
                        <label class="flex flex-col gap-1">
                            <span class="text-xs text-muted-foreground"
                                >Shape</span
                            >
                            <select
                                v-model="formation"
                                class="rounded-md border border-border bg-transparent px-2 py-1.5 text-sm"
                            >
                                <option
                                    v-for="f in props.formations"
                                    :key="f.id"
                                    :value="f.id"
                                >
                                    {{ f.name }}
                                </option>
                            </select>
                        </label>
                        <label class="flex flex-col gap-1">
                            <span class="text-xs text-muted-foreground"
                                >Mentality</span
                            >
                            <select
                                v-model="mentality"
                                class="rounded-md border border-border bg-transparent px-2 py-1.5 text-sm"
                            >
                                <option
                                    v-for="m in props.mentalities"
                                    :key="m.id"
                                    :value="m.id"
                                >
                                    {{ m.name }}
                                </option>
                            </select>
                        </label>
                        <Button @click="submit(true)">
                            <Check class="size-3.5" /> Ready
                        </Button>
                        <Button variant="outline" @click="submit(false)">
                            Save only
                        </Button>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        <template v-if="props.round.yourOrder?.ready">
                            Your team sheet is in. The matchday plays as soon as
                            the last manager is ready.
                        </template>
                        <template v-else>
                            Nothing handed in yet. Miss the deadline and your
                            club plays the way it always plays.
                        </template>
                    </p>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    Take a club below and you can name a side for this matchday.
                </p>

                <ul class="flex flex-col gap-1">
                    <li
                        v-for="fixture in props.round.fixtures"
                        :key="fixture.id"
                        class="flex items-center justify-between gap-2 rounded-md px-2 py-1 text-sm"
                        :class="fixture.yours ? 'bg-primary/10' : ''"
                    >
                        <span class="truncate">{{ fixture.home }}</span>
                        <span
                            class="shrink-0 font-mono tabular-nums"
                            :class="
                                fixture.duel
                                    ? 'text-primary'
                                    : 'text-muted-foreground'
                            "
                        >
                            {{ score(fixture) }}
                        </span>
                        <span class="truncate text-right">{{
                            fixture.away
                        }}</span>
                    </li>
                </ul>
            </div>

            <div v-if="props.isOwner" class="mt-4 flex items-end gap-2">
                <label class="flex flex-col gap-1">
                    <span class="text-xs text-muted-foreground"
                        >Hours per round</span
                    >
                    <input
                        v-model.number="hours"
                        type="number"
                        min="1"
                        max="336"
                        class="w-24 rounded-md border border-border bg-transparent px-2 py-1.5 text-sm"
                    />
                </label>
                <Button size="sm" variant="outline" @click="setCadence">
                    Set cadence
                </Button>
            </div>
        </div>

        <div class="grid flex-1 gap-4 lg:grid-cols-2">
            <div
                class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <h2 class="mb-3 text-sm font-medium text-muted-foreground">
                    Managers
                </h2>
                <ul class="flex flex-col gap-2">
                    <li
                        v-for="member in props.members"
                        :key="member.id"
                        class="flex items-center justify-between gap-2 rounded-md border border-border px-3 py-2 text-sm"
                    >
                        <span class="flex min-w-0 items-center gap-2">
                            <Crown
                                v-if="member.owner"
                                class="size-3.5 shrink-0 text-primary"
                            />
                            <span class="truncate">
                                {{ member.name }}
                                <span
                                    v-if="member.you"
                                    class="text-muted-foreground"
                                    >(you)</span
                                >
                            </span>
                        </span>
                        <span class="flex shrink-0 items-center gap-2">
                            <span
                                class="text-xs"
                                :class="
                                    member.club
                                        ? 'text-foreground'
                                        : 'text-muted-foreground'
                                "
                            >
                                {{ member.club ?? 'No club yet' }}
                            </span>
                            <Button
                                v-if="props.isOwner && !member.owner"
                                size="sm"
                                variant="outline"
                                @click="kick(member)"
                            >
                                <UserMinus class="size-3.5" />
                            </Button>
                        </span>
                    </li>
                </ul>

                <div v-if="props.isOwner" class="mt-4">
                    <h3 class="mb-2 text-xs font-semibold">Invitations</h3>
                    <p
                        v-if="!props.invitations.length"
                        class="text-xs text-muted-foreground"
                    >
                        No invitations yet.
                    </p>
                    <ul v-else class="flex flex-col gap-1">
                        <li
                            v-for="i in props.invitations"
                            :key="i.id"
                            class="flex items-center justify-between gap-2 text-xs"
                        >
                            <span class="truncate font-mono">{{ i.url }}</span>
                            <span class="flex shrink-0 items-center gap-2">
                                <span
                                    class="rounded px-1.5 py-0.5"
                                    :class="
                                        i.status === 'open'
                                            ? 'bg-primary/15 text-primary'
                                            : 'text-muted-foreground'
                                    "
                                >
                                    {{ i.status }}
                                </span>
                                <Button
                                    v-if="i.status === 'open'"
                                    size="sm"
                                    variant="outline"
                                    @click="copy(i)"
                                >
                                    <Copy class="size-3" />
                                    {{ copied === i.id ? 'Copied' : 'Copy' }}
                                </Button>
                            </span>
                        </li>
                    </ul>
                </div>
            </div>

            <div
                class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="text-sm font-medium text-muted-foreground">
                        {{ props.yourTeamId ? 'Your club' : 'Pick a club' }}
                    </h2>
                    <Button
                        v-if="props.yourTeamId"
                        size="sm"
                        variant="outline"
                        @click="giveBack"
                    >
                        Give it up
                    </Button>
                </div>

                <p
                    v-if="props.yourTeamId"
                    class="text-sm text-muted-foreground"
                >
                    You are already running a club in this league.
                </p>
                <ul v-else class="flex max-h-96 flex-col gap-1 overflow-y-auto">
                    <li
                        v-for="club in props.availableClubs"
                        :key="club.id"
                        class="flex items-center justify-between gap-2 rounded-md border border-border px-3 py-2 text-sm"
                    >
                        <span class="truncate">
                            {{ club.name }}
                            <span class="text-xs text-muted-foreground">
                                Division {{ club.division }}
                            </span>
                        </span>
                        <Button size="sm" @click="take(club)">Take over</Button>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
