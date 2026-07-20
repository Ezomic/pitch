<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BookOpen,
    Clapperboard,
    FolderGit2,
    LayoutGrid,
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
import { show as matchShow } from '@/routes/match';
import { index as scoutsIndex } from '@/routes/scouts';
import { show as seasonShow } from '@/routes/season';
import { edit as squadEdit } from '@/routes/squad';
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
        title: 'Match',
        href: matchShow(),
        icon: Clapperboard,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
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
