<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowRightLeft,
    Clapperboard,
    Dumbbell,
    FlaskConical,
    Gamepad2,
    LayoutGrid,
    Medal,
    Newspaper,
    Scale,
    Shirt,
    Sprout,
    Telescope,
    Trophy,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { show as cupShow } from '@/routes/cup';
import { show as matchShow } from '@/routes/match';
import { index as newsIndex } from '@/routes/news';
import { show as playShow } from '@/routes/play';
import { index as scoutsIndex } from '@/routes/scouts';
import { show as seasonShow } from '@/routes/season';
import {
    compare as squadCompare,
    edit as squadEdit,
    whatIf as squadWhatIf,
} from '@/routes/squad';
import { index as trainingIndex } from '@/routes/training';
import { index as transfersIndex } from '@/routes/transfers';
import { index as youthIndex } from '@/routes/youth';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Squad',
        href: squadEdit(),
        icon: Shirt,
    },
    {
        title: 'Season',
        href: seasonShow(),
        icon: Trophy,
    },
    {
        title: 'Cup',
        href: cupShow(),
        icon: Medal,
    },
    {
        title: 'Transfers',
        href: transfersIndex(),
        icon: ArrowRightLeft,
    },
    {
        title: 'News',
        href: newsIndex(),
        icon: Newspaper,
    },
    {
        title: 'Scouting',
        href: scoutsIndex(),
        icon: Telescope,
    },
    {
        title: 'Academy',
        href: youthIndex(),
        icon: Sprout,
    },
    {
        title: 'Training',
        href: trainingIndex(),
        icon: Dumbbell,
    },
    {
        title: 'Match',
        href: matchShow(),
        icon: Clapperboard,
    },
    {
        title: 'Play',
        href: playShow(),
        icon: Gamepad2,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Squad comparison',
        href: squadCompare(),
        icon: Scale,
    },
    {
        title: 'What if',
        href: squadWhatIf(),
        icon: FlaskConical,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
