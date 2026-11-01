<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { RoundStatus } from '@/types/league';

const props = defineProps<{ status: RoundStatus }>();

const labels: Record<RoundStatus, string> = {
    open: 'Open',
    playing: 'Playing',
    resolved: 'Resolved',
    failed: 'Failed',
};

const variants = {
    open: 'outline',
    playing: 'secondary',
    resolved: 'default',
    failed: 'destructive',
} as const satisfies Record<RoundStatus, string>;
</script>

<template>
    <Badge :variant="variants[props.status]" :data-status="props.status">
        <span
            v-if="props.status === 'playing'"
            class="size-1.5 animate-pulse rounded-full bg-current"
        />
        {{ labels[props.status] }}
    </Badge>
</template>
