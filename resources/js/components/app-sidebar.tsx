import { Link, usePage } from '@inertiajs/react';
import {
    BellRing,
    Eye,
    FileText,
    LayoutGrid,
    Newspaper,
    Rss,
    ScrollText,
    ShieldAlert,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
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
import { index as audit } from '@/routes/admin/audit';
import { index as users } from '@/routes/admin/users';
import { index as alerts } from '@/routes/alerts';
import { index as briefings } from '@/routes/briefings';
import { index as incidents } from '@/routes/incidents';
import { index as items } from '@/routes/items';
import { index as sources } from '@/routes/sources';
import { index as watchlist } from '@/routes/watchlist';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { auth, openAlerts } = usePage().props;

    const mainNavItems: NavItem[] = [
        { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
        { title: 'Alerts', href: alerts(), icon: BellRing, badge: openAlerts },
        { title: 'Feed', href: items(), icon: Newspaper },
        { title: 'Incidents', href: incidents(), icon: ShieldAlert },
        { title: 'Daily briefing', href: briefings(), icon: FileText },
    ];

    const analystNavItems: NavItem[] = [
        { title: 'Sources', href: sources(), icon: Rss },
        { title: 'Watchlist', href: watchlist(), icon: Eye },
    ];

    const adminNavItems: NavItem[] = [
        { title: 'Users', href: users(), icon: Users },
        { title: 'Audit log', href: audit(), icon: ScrollText },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} label="Monitoring" />
                {auth.can.analyze && (
                    <NavMain items={analystNavItems} label="Configure" />
                )}
                {auth.can.admin && (
                    <NavMain items={adminNavItems} label="Admin" />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
