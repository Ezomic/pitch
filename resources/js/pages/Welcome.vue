<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { dashboard, login } from '@/routes';

// One legible lever: a better keeper turns conceded chances into saves, not
// goals — chances conceded holds while goals conceded falls.
const keeping = ref(64);

const fill = computed(() => ((keeping.value - 40) / 50) * 100);
const goalsTarget = computed(() => 1.72 - ((keeping.value - 40) / 50) * 0.86);
const goalsBar = computed(() => Math.max(6, (goalsTarget.value / 2.0) * 100));

const trackStyle = computed(() => ({
    background: `linear-gradient(to right, var(--primary) 0%, var(--primary) ${fill.value}%, var(--secondary) ${fill.value}%, var(--secondary) 100%)`,
}));

// Ease the displayed number toward its target so the metric visibly "moves".
const displayed = ref(goalsTarget.value);
const reduceMotion =
    typeof window !== 'undefined' &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;
let raf = 0;

watch(goalsTarget, (target) => {
    if (reduceMotion) {
        displayed.value = target;
        return;
    }
    cancelAnimationFrame(raf);
    const from = displayed.value;
    const start = performance.now();
    const dur = 260;
    const step = (now: number) => {
        const t = Math.min(1, (now - start) / dur);
        const eased = 1 - Math.pow(1 - t, 3);
        displayed.value = from + (target - from) * eased;
        if (t < 1) raf = requestAnimationFrame(step);
    };
    raf = requestAnimationFrame(step);
});

onBeforeUnmount(() => cancelAnimationFrame(raf));

const levers = [
    {
        title: 'Formation',
        desc: 'Presets from 4-3-3 to 5-3-2, or drag players to your own custom shape on the pitch.',
        path: 'M3 4h18v16H3z M8 4v16',
    },
    {
        title: 'Mentality',
        desc: 'Attacking, balanced, defensive — trades chances created against chances conceded.',
        path: 'M12 3v18M5 8l7-5 7 5M5 16l7 5 7-5',
    },
    {
        title: 'Goalkeeper',
        desc: 'A real shot-stopping lever: better keeping turns conceded chances into saves.',
        path: 'M6 10a6 6 0 0 1 12 0v9H6z M9 19v-4h6v4',
    },
    {
        title: 'Set pieces',
        desc: 'Nominate a taker; delivery and finishing turn corners and free-kicks into goals.',
        path: 'M12 3v4M12 17v4M3 12h4M17 12h4',
    },
];

const features = [
    {
        tag: 'SEASON',
        title: 'Divisions, promotion, relegation',
        desc: 'Win your tier and go up; finish bottom and drop. Standings, fixtures and a board objective.',
    },
    {
        tag: 'CUP',
        title: 'Knockout cup',
        desc: 'A parallel single-elimination bracket drawn alongside the league, one round a week.',
    },
    {
        tag: 'MARKET',
        title: 'Transfers, wages & contracts',
        desc: 'A running bank, a weekly wage bill against income, expiring deals, and incoming bids.',
    },
    {
        tag: 'ACADEMY',
        title: 'Youth & loans',
        desc: 'Develop prospects, loan them out for guaranteed minutes, and bring them back stronger.',
    },
    {
        tag: 'TRAINING',
        title: 'Senior training focus',
        desc: 'Drill an attribute at a fitness cost — sharper in training, or fresher for Saturday.',
    },
    {
        tag: 'INBOX',
        title: 'News feed',
        desc: 'Results, board messages and transfer offers in a single feed you act on in a click.',
    },
];
</script>

<template>
    <Head title="Pitch — deterministic football management" />

    <div class="min-h-screen bg-background text-foreground">
        <header
            class="sticky top-0 z-20 border-b border-border bg-background/80 backdrop-blur-md"
        >
            <div
                class="mx-auto flex h-16 max-w-6xl items-center justify-between px-6"
            >
                <a
                    href="#top"
                    class="flex items-center gap-2.5 text-lg font-extrabold tracking-tight"
                >
                    <svg
                        class="size-6 text-primary"
                        viewBox="0 0 24 24"
                        fill="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            fill-rule="evenodd"
                            clip-rule="evenodd"
                            d="M12 1.75A10.25 10.25 0 1 0 22.25 12 10.26 10.26 0 0 0 12 1.75Zm0 1.6 3.02 2.2-1.15 3.55h-3.74L8.98 5.54 12 3.35Zm-4.9 3.03.96 2.96-3.02 2.2-1.4-1.02a8.66 8.66 0 0 1 3.46-4.14Zm9.8 0a8.66 8.66 0 0 1 3.46 4.14l-1.4 1.02-3.02-2.2.96-2.96ZM4.24 12.9l1.4-1.02 3.02 2.2-1.15 3.55H4.9A8.62 8.62 0 0 1 3.4 12.9h.84Zm15.52 0h.84a8.62 8.62 0 0 1-1.5 4.73h-2.61l-1.15-3.55 3.02-2.2 1.4 1.02ZM9.4 19.9l1.15-3.55h2.9l1.15 3.55A8.6 8.6 0 0 1 12 20.65a8.6 8.6 0 0 1-2.6-.4v-.35Z"
                        />
                    </svg>
                    Pitch
                </a>
                <nav class="flex items-center gap-6">
                    <a
                        href="#engine"
                        class="hidden font-mono text-[13px] text-muted-foreground transition-colors hover:text-foreground sm:inline"
                        >Engine</a
                    >
                    <a
                        href="#levers"
                        class="hidden font-mono text-[13px] text-muted-foreground transition-colors hover:text-foreground sm:inline"
                        >Levers</a
                    >
                    <a
                        href="#club"
                        class="hidden font-mono text-[13px] text-muted-foreground transition-colors hover:text-foreground sm:inline"
                        >The club</a
                    >
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="rounded-lg bg-primary px-4 py-2 font-mono text-[13px] font-semibold text-primary-foreground transition-transform hover:-translate-y-px"
                    >
                        Dashboard
                    </Link>
                    <Link
                        v-else
                        :href="login()"
                        class="rounded-lg bg-primary px-4 py-2 font-mono text-[13px] font-semibold text-primary-foreground transition-transform hover:-translate-y-px"
                    >
                        Open the app
                    </Link>
                </nav>
            </div>
        </header>

        <main id="top">
            <!-- Hero -->
            <section class="relative overflow-hidden">
                <div
                    class="pointer-events-none absolute inset-0"
                    aria-hidden="true"
                >
                    <div
                        class="absolute inset-x-0 top-1/2 h-px bg-border/70"
                    ></div>
                    <div
                        class="absolute inset-y-0 left-1/2 w-px bg-border/70"
                    ></div>
                    <div
                        class="absolute -top-24 right-0 h-[520px] w-[520px] rounded-full opacity-60 blur-3xl"
                        style="
                            background: radial-gradient(
                                closest-side,
                                color-mix(
                                    in srgb,
                                    var(--primary) 16%,
                                    transparent
                                ),
                                transparent
                            );
                        "
                    ></div>
                </div>
                <div
                    class="relative mx-auto grid max-w-6xl items-center gap-12 px-6 py-16 md:grid-cols-[1.05fr_0.95fr] md:py-24"
                >
                    <div>
                        <p
                            class="font-mono text-xs tracking-[0.18em] text-primary uppercase"
                        >
                            Deterministic football management
                        </p>
                        <h1
                            class="mt-4 text-[clamp(2.5rem,7vw,4.5rem)] leading-[0.98] font-[850] tracking-tight text-balance"
                        >
                            Change one thing.<br /><span class="text-primary"
                                >See it move.</span
                            >
                        </h1>
                        <p
                            class="mt-5 max-w-[46ch] text-lg text-muted-foreground"
                        >
                            Pitch is a management sim built on a headless,
                            deterministic match engine. Adjust a single
                            attribute and watch its effect ripple across two
                            thousand simulated matches — measured, reproducible,
                            and honest about why.
                        </p>
                        <div class="mt-7 flex flex-wrap gap-3">
                            <Link
                                :href="login()"
                                class="rounded-lg bg-primary px-5 py-3 font-mono text-sm font-semibold text-primary-foreground transition-transform hover:-translate-y-px"
                            >
                                Start managing →
                            </Link>
                            <a
                                href="#engine"
                                class="rounded-lg border border-border px-5 py-3 font-mono text-sm text-foreground transition-colors hover:border-primary hover:text-primary"
                            >
                                How the engine thinks
                            </a>
                        </div>
                        <div class="mt-8 flex flex-wrap gap-8">
                            <div>
                                <div
                                    class="text-2xl font-extrabold tracking-tight tabular-nums"
                                >
                                    2,000
                                </div>
                                <div
                                    class="font-mono text-[11px] tracking-[0.12em] text-muted-foreground uppercase"
                                >
                                    sims / squad
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-2xl font-extrabold tracking-tight tabular-nums"
                                >
                                    100%
                                </div>
                                <div
                                    class="font-mono text-[11px] tracking-[0.12em] text-muted-foreground uppercase"
                                >
                                    reproducible
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-2xl font-extrabold tracking-tight tabular-nums"
                                >
                                    0
                                </div>
                                <div
                                    class="font-mono text-[11px] tracking-[0.12em] text-muted-foreground uppercase"
                                >
                                    hidden dice
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Interactive demo -->
                    <div
                        class="rounded-2xl border border-border bg-card p-6 shadow-xl shadow-black/5"
                    >
                        <div class="flex items-center justify-between">
                            <span
                                class="font-mono text-[11px] tracking-[0.14em] text-primary uppercase"
                                >One lever</span
                            >
                            <span
                                class="font-mono text-[11px] text-muted-foreground"
                                >seed 4471 · 2,000 sims</span
                            >
                        </div>

                        <div class="mt-5">
                            <div class="flex items-baseline justify-between">
                                <span
                                    class="font-mono text-[13px] text-muted-foreground"
                                    >Goalkeeper — shot-stopping</span
                                >
                                <span
                                    class="font-mono text-3xl font-bold tabular-nums"
                                    >{{ keeping }}</span
                                >
                            </div>
                            <input
                                v-model.number="keeping"
                                type="range"
                                min="40"
                                max="90"
                                step="1"
                                aria-label="Goalkeeper shot-stopping rating"
                                class="pitch-range mt-3 w-full"
                                :style="trackStyle"
                            />
                        </div>

                        <div class="mt-6 grid grid-cols-2 gap-3">
                            <div class="rounded-xl bg-secondary/60 p-4">
                                <div
                                    class="font-mono text-[10.5px] tracking-[0.1em] text-muted-foreground uppercase"
                                >
                                    Chances conceded / 90
                                </div>
                                <div
                                    class="mt-1 font-mono text-2xl font-bold text-muted-foreground tabular-nums"
                                >
                                    11.4
                                </div>
                                <div
                                    class="mt-1.5 font-mono text-[12px] text-muted-foreground"
                                >
                                    unchanged
                                </div>
                            </div>
                            <div class="rounded-xl bg-secondary/60 p-4">
                                <div
                                    class="font-mono text-[10.5px] tracking-[0.1em] text-muted-foreground uppercase"
                                >
                                    Goals conceded / 90
                                </div>
                                <div
                                    class="mt-1 font-mono text-2xl font-bold text-primary tabular-nums"
                                >
                                    {{ displayed.toFixed(2) }}
                                </div>
                                <div
                                    class="mt-2.5 h-1.5 overflow-hidden rounded-full bg-muted-foreground/25"
                                >
                                    <span
                                        class="block h-full rounded-full bg-primary transition-[width] duration-300 ease-out"
                                        :style="{ width: goalsBar + '%' }"
                                    ></span>
                                </div>
                            </div>
                        </div>
                        <p class="mt-4 text-[13.5px] text-muted-foreground">
                            <span class="font-semibold text-foreground"
                                >Same chances. Fewer goals.</span
                            >
                            The keeper does its one job, and the difference is a
                            number you can read — not a vibe.
                        </p>
                    </div>
                </div>
            </section>

            <!-- Engine -->
            <section id="engine" class="border-t border-border">
                <div class="mx-auto max-w-6xl px-6 py-16 md:py-24">
                    <p
                        class="font-mono text-xs tracking-[0.18em] text-primary uppercase"
                    >
                        The thesis
                    </p>
                    <h2
                        class="mt-3 max-w-[18ch] text-[clamp(1.6rem,4vw,2.5rem)] leading-[1.05] font-[820] tracking-tight text-balance"
                    >
                        A match engine you can regression-test like code.
                    </h2>
                    <p class="mt-3.5 max-w-[56ch] text-muted-foreground">
                        Same seed plus the same inputs produce the same match,
                        every time. So a season can run thousands of times, and
                        a squad change is a measurable delta — not a story you
                        tell yourself afterwards.
                    </p>

                    <div
                        class="mt-10 grid overflow-hidden rounded-2xl border border-border sm:grid-cols-3"
                    >
                        <div
                            class="border-b border-border p-6 sm:border-r sm:border-b-0"
                        >
                            <div
                                class="font-mono text-2xl font-bold tracking-tight"
                            >
                                seed → match
                            </div>
                            <div class="mt-2 text-sm text-muted-foreground">
                                Deterministic. Replay any fixture and get the
                                identical event log, down to the last throw-in.
                            </div>
                        </div>
                        <div
                            class="border-b border-border p-6 sm:border-r sm:border-b-0"
                        >
                            <div
                                class="font-mono text-2xl font-bold tracking-tight"
                            >
                                why, not what
                            </div>
                            <div class="mt-2 text-sm text-muted-foreground">
                                Every event records the options a player could
                                see versus the best available, and the raw
                                random draw.
                            </div>
                        </div>
                        <div class="p-6">
                            <div
                                class="font-mono text-2xl font-bold tracking-tight"
                            >
                                one → many
                            </div>
                            <div class="mt-2 text-sm text-muted-foreground">
                                Change a single attribute and the whole squad
                                profile shifts, averaged over a full sampled
                                season.
                            </div>
                        </div>
                    </div>

                    <div
                        class="mt-10 grid items-center gap-10 md:grid-cols-[1fr_0.9fr]"
                    >
                        <div
                            class="overflow-hidden rounded-2xl border border-border bg-secondary/40 font-mono text-[12.5px] shadow-lg shadow-black/5"
                        >
                            <div
                                class="flex items-center gap-1.5 border-b border-border px-3.5 py-3"
                            >
                                <span
                                    class="size-2.5 rounded-full bg-border"
                                ></span>
                                <span
                                    class="size-2.5 rounded-full bg-border"
                                ></span>
                                <span
                                    class="size-2.5 rounded-full bg-border"
                                ></span>
                                <span
                                    class="ml-2 text-[11px] tracking-[0.08em] text-muted-foreground uppercase"
                                    >match 4471 · event log</span
                                >
                            </div>
                            <div
                                class="space-y-1.5 overflow-x-auto px-4 py-4 leading-[1.85]"
                            >
                                <div class="whitespace-nowrap">
                                    <span class="text-muted-foreground"
                                        >67'</span
                                    >
                                    <span class="font-semibold">PASS</span>
                                    <span class="text-muted-foreground"
                                        >#8 → #10 zone 4,2 saw 5 / best 6</span
                                    >
                                </div>
                                <div class="whitespace-nowrap">
                                    <span class="text-muted-foreground"
                                        >67'</span
                                    >
                                    <span class="font-semibold">DRIBBLE</span>
                                    <span class="text-muted-foreground"
                                        >#10 zone 5,2
                                        <span class="text-destructive"
                                            >draw 0.41 ≤ 0.63</span
                                        ></span
                                    >
                                </div>
                                <div class="whitespace-nowrap">
                                    <span class="text-muted-foreground"
                                        >68'</span
                                    >
                                    <span class="font-semibold">SHOT</span>
                                    <span class="text-muted-foreground"
                                        >#10 finishing 78 · keeper
                                        <span
                                            class="text-amber-600 dark:text-amber-400"
                                            >64</span
                                        ></span
                                    >
                                </div>
                                <div class="whitespace-nowrap">
                                    <span class="text-muted-foreground"
                                        >68'</span
                                    >
                                    <span class="font-bold text-primary"
                                        >GOAL</span
                                    >
                                    <span class="text-muted-foreground"
                                        >threshold 0.29 ·
                                        <span class="text-destructive"
                                            >draw 0.22</span
                                        ></span
                                    >
                                </div>
                            </div>
                        </div>
                        <div>
                            <p
                                class="font-mono text-xs tracking-[0.18em] text-primary uppercase"
                            >
                                Auditable by design
                            </p>
                            <ul class="mt-4 space-y-4">
                                <li class="grid grid-cols-[1.5rem_1fr] gap-3">
                                    <span
                                        class="font-mono font-bold text-primary"
                                        >01</span
                                    >
                                    <div>
                                        <b class="font-semibold"
                                            >Every decision is inspectable.</b
                                        >
                                        <p
                                            class="mt-0.5 text-sm text-muted-foreground"
                                        >
                                            What the player saw, what they
                                            chose, and what the best option was
                                            — logged, not inferred.
                                        </p>
                                    </div>
                                </li>
                                <li class="grid grid-cols-[1.5rem_1fr] gap-3">
                                    <span
                                        class="font-mono font-bold text-primary"
                                        >02</span
                                    >
                                    <div>
                                        <b class="font-semibold"
                                            >Every outcome is reproducible.</b
                                        >
                                        <p
                                            class="mt-0.5 text-sm text-muted-foreground"
                                        >
                                            The exact random draw is recorded
                                            beside its threshold, so any moment
                                            can be replayed and explained.
                                        </p>
                                    </div>
                                </li>
                                <li class="grid grid-cols-[1.5rem_1fr] gap-3">
                                    <span
                                        class="font-mono font-bold text-primary"
                                        >03</span
                                    >
                                    <div>
                                        <b class="font-semibold"
                                            >Every lever is measurable.</b
                                        >
                                        <p
                                            class="mt-0.5 text-sm text-muted-foreground"
                                        >
                                            Turn the keeper from 64 to 88 and
                                            the goals-conceded line moves. That
                                            is the whole game.
                                        </p>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Levers -->
            <section id="levers" class="border-t border-border">
                <div class="mx-auto max-w-6xl px-6 py-16 md:py-24">
                    <p
                        class="font-mono text-xs tracking-[0.18em] text-primary uppercase"
                    >
                        Tactical levers
                    </p>
                    <h2
                        class="mt-3 text-[clamp(1.6rem,4vw,2.5rem)] leading-[1.05] font-[820] tracking-tight text-balance"
                    >
                        Shape, not just personnel.
                    </h2>
                    <p class="mt-3.5 max-w-[56ch] text-muted-foreground">
                        Pull a lever, read the effect in the squad profile
                        before a ball is kicked. Set up to steal a result off a
                        stronger rival, or open up against a weaker one.
                    </p>
                    <div
                        class="mt-10 grid gap-3.5 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <div
                            v-for="lever in levers"
                            :key="lever.title"
                            class="rounded-2xl border border-border bg-card p-5 shadow-sm"
                        >
                            <svg
                                class="size-7 text-primary"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path :d="lever.path" />
                            </svg>
                            <h3
                                class="mt-3.5 text-base font-semibold tracking-tight"
                            >
                                {{ lever.title }}
                            </h3>
                            <p
                                class="mt-1.5 text-[13.5px] text-muted-foreground"
                            >
                                {{ lever.desc }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Club -->
            <section id="club" class="border-t border-border">
                <div class="mx-auto max-w-6xl px-6 py-16 md:py-24">
                    <p
                        class="font-mono text-xs tracking-[0.18em] text-primary uppercase"
                    >
                        The whole club
                    </p>
                    <h2
                        class="mt-3 text-[clamp(1.6rem,4vw,2.5rem)] leading-[1.05] font-[820] tracking-tight text-balance"
                    >
                        A season with texture, on top of the engine.
                    </h2>
                    <p class="mt-3.5 max-w-[56ch] text-muted-foreground">
                        Everything that surrounds the match is here — and it all
                        consumes the same engine, so the management decisions
                        are as legible as the tactical ones.
                    </p>
                    <div
                        class="mt-10 grid gap-3.5 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <div
                            v-for="feat in features"
                            :key="feat.tag"
                            class="rounded-2xl border border-border bg-card p-5 shadow-sm"
                        >
                            <div
                                class="font-mono text-[11px] tracking-[0.1em] text-primary"
                            >
                                {{ feat.tag }}
                            </div>
                            <h3
                                class="mt-2 text-[16.5px] font-semibold tracking-tight"
                            >
                                {{ feat.title }}
                            </h3>
                            <p class="mt-2 text-[13.5px] text-muted-foreground">
                                {{ feat.desc }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- CTA -->
            <section class="border-t border-border">
                <div class="mx-auto max-w-6xl px-6 py-16 md:py-20">
                    <div
                        class="relative overflow-hidden rounded-3xl border border-border bg-card px-6 py-16 text-center"
                        style="
                            background-image: radial-gradient(
                                90% 120% at 50% -20%,
                                color-mix(
                                    in srgb,
                                    var(--primary) 16%,
                                    transparent
                                ),
                                transparent 60%
                            );
                        "
                    >
                        <p
                            class="font-mono text-xs tracking-[0.18em] text-primary uppercase"
                        >
                            Phase one is live
                        </p>
                        <h2
                            class="mx-auto mt-3 max-w-[20ch] text-[clamp(1.6rem,4vw,2.5rem)] leading-[1.05] font-[820] tracking-tight text-balance"
                        >
                            Prove that one attribute changes the match.
                        </h2>
                        <p
                            class="mx-auto mt-3.5 max-w-[48ch] text-muted-foreground"
                        >
                            No 3D yet — just the engine and a data-heavy
                            interface built to show, and measure, exactly what
                            your decisions do.
                        </p>
                        <div class="mt-7 flex flex-wrap justify-center gap-3">
                            <Link
                                :href="login()"
                                class="rounded-lg bg-primary px-5 py-3 font-mono text-sm font-semibold text-primary-foreground transition-transform hover:-translate-y-px"
                                >Open the app →</Link
                            >
                            <a
                                href="#top"
                                class="rounded-lg border border-border px-5 py-3 font-mono text-sm text-foreground transition-colors hover:border-primary hover:text-primary"
                                >Back to top</a
                            >
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-border">
            <div
                class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-6 py-8"
            >
                <a href="#top" class="flex items-center gap-2.5 font-bold">
                    <svg
                        class="size-5 text-primary"
                        viewBox="0 0 24 24"
                        fill="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            fill-rule="evenodd"
                            clip-rule="evenodd"
                            d="M12 1.75A10.25 10.25 0 1 0 22.25 12 10.26 10.26 0 0 0 12 1.75Zm0 1.6 3.02 2.2-1.15 3.55h-3.74L8.98 5.54 12 3.35Zm-4.9 3.03.96 2.96-3.02 2.2-1.4-1.02a8.66 8.66 0 0 1 3.46-4.14Zm9.8 0a8.66 8.66 0 0 1 3.46 4.14l-1.4 1.02-3.02-2.2.96-2.96ZM4.24 12.9l1.4-1.02 3.02 2.2-1.15 3.55H4.9A8.62 8.62 0 0 1 3.4 12.9h.84Zm15.52 0h.84a8.62 8.62 0 0 1-1.5 4.73h-2.61l-1.15-3.55 3.02-2.2 1.4 1.02ZM9.4 19.9l1.15-3.55h2.9l1.15 3.55A8.6 8.6 0 0 1 12 20.65a8.6 8.6 0 0 1-2.6-.4v-.35Z"
                        />
                    </svg>
                    Pitch
                </a>
                <span class="font-mono text-[12.5px] text-muted-foreground"
                    >Deterministic football management · a Thijssen Software
                    project</span
                >
            </div>
        </footer>
    </div>
</template>

<style scoped>
.pitch-range {
    -webkit-appearance: none;
    appearance: none;
    height: 6px;
    border-radius: 999px;
    cursor: pointer;
}
.pitch-range::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: var(--card);
    border: 3px solid var(--primary);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
    cursor: grab;
}
.pitch-range::-moz-range-thumb {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: var(--card);
    border: 3px solid var(--primary);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
    cursor: grab;
}
.pitch-range:focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 6px;
}
</style>
