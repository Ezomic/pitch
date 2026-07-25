<script setup lang="ts">
withDefaults(
    defineProps<{
        label: string;
        value: string;
        tone?: 'default' | 'positive' | 'negative';
    }>(),
    { tone: 'default' },
);

const toneClass: Record<'default' | 'positive' | 'negative', string> = {
    default: 'text-foreground',
    positive: 'text-primary',
    negative: 'text-destructive',
};
</script>

<template>
    <div
        class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
    >
        <p class="text-sm text-muted-foreground">{{ label }}</p>
        <p
            class="mt-0.5 text-2xl font-semibold tracking-tight tabular-nums"
            :class="toneClass[tone]"
        >
            {{ value }}
        </p>
    </div>
</template>
