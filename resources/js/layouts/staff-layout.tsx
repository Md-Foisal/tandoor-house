import { Link, router, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    Activity,
    BarChart3,
    BookOpen,
    CalendarDays,
    ChefHat,
    ExternalLink,
    LayoutDashboard,
    LayoutGrid,
    LogOut,
    MessageSquareQuote,
    Package,
    ReceiptText,
    Settings,
    Users,
    Bike,
    ChevronsUpDown,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { LogoMark } from '@/components/brand/logo';
import { OfflineBanner } from '@/components/brand/states';
import { ThemeToggle } from '@/components/brand/theme-toggle';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarInset,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarProvider,
    SidebarRail,
    SidebarTrigger,
} from '@/components/ui/sidebar';
import { useT } from '@/lib/i18n';
import type { StaffArea } from '@/types';

type AreaLink = { area: StaffArea; href: string; icon: LucideIcon };

export const staffLinks: { service: AreaLink[]; backOffice: AreaLink[] } = {
    service: [
        { area: 'today', href: '/staff', icon: LayoutDashboard },
        { area: 'orders', href: '/staff/orders', icon: ReceiptText },
        { area: 'kitchen', href: '/staff/kitchen', icon: ChefHat },
        { area: 'floor', href: '/staff/floor', icon: LayoutGrid },
        {
            area: 'reservations',
            href: '/staff/reservations',
            icon: CalendarDays,
        },
        { area: 'deliveries', href: '/staff/deliveries', icon: Bike },
    ],
    backOffice: [
        { area: 'menu', href: '/staff/menu', icon: BookOpen },
        { area: 'inventory', href: '/staff/inventory', icon: Package },
        { area: 'team', href: '/staff/team', icon: Users },
        { area: 'reports', href: '/staff/reports', icon: BarChart3 },
        { area: 'reviews', href: '/staff/reviews', icon: MessageSquareQuote },
        { area: 'activity', href: '/staff/activity', icon: Activity },
        { area: 'settings', href: '/staff/settings', icon: Settings },
    ],
};

function isActive(url: string, href: string): boolean {
    const path = url.split('?')[0] ?? url;

    return href === '/staff'
        ? path === '/staff'
        : path === href || path.startsWith(`${href}/`);
}

function NavGroup({ label, links }: { label: string; links: AreaLink[] }) {
    const t = useT();
    const { url } = usePage();
    const { staffAreas } = usePage().props;
    const allowed = links.filter((link) => staffAreas.includes(link.area));

    if (allowed.length === 0) {
        return null;
    }

    return (
        <SidebarGroup>
            <SidebarGroupLabel className="text-label text-ink-muted">
                {label}
            </SidebarGroupLabel>
            <SidebarGroupContent>
                <SidebarMenu>
                    {allowed.map(({ area, href, icon: Icon }) => (
                        <SidebarMenuItem key={area}>
                            <SidebarMenuButton
                                asChild
                                isActive={isActive(url, href)}
                                tooltip={t(`staff.areas.${area}`)}
                                className="h-11 font-semibold data-[active=true]:bg-ember-soft data-[active=true]:text-ember"
                            >
                                <Link href={href} prefetch>
                                    <Icon aria-hidden="true" />
                                    <span>{t(`staff.areas.${area}`)}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    ))}
                </SidebarMenu>
            </SidebarGroupContent>
        </SidebarGroup>
    );
}

/**
 * Staff panel shell: a sidebar that collapses to icons on desktop and
 * tablet and becomes a sheet on phones. Only sections the role may use
 * are listed; the server enforces the same rule with gates.
 */
export default function StaffLayout({ children }: { children: ReactNode }) {
    const t = useT();
    const { auth, name, sidebarOpen } = usePage().props;

    return (
        <SidebarProvider defaultOpen={sidebarOpen}>
            <Sidebar collapsible="icon" variant="sidebar">
                <SidebarHeader>
                    <Link
                        href="/staff"
                        className="flex h-12 items-center gap-2.5 rounded-md px-1.5 outline-none focus-visible:outline-2 focus-visible:outline-ember"
                    >
                        <LogoMark className="size-7" />
                        <span className="truncate font-display text-lg group-data-[collapsible=icon]:hidden">
                            {name}
                        </span>
                    </Link>
                </SidebarHeader>
                <SidebarContent>
                    <NavGroup
                        label={t('staff.groups.service')}
                        links={staffLinks.service}
                    />
                    <NavGroup
                        label={t('staff.groups.back_office')}
                        links={staffLinks.backOffice}
                    />
                </SidebarContent>
                <SidebarFooter>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <SidebarMenuButton
                                        size="lg"
                                        tooltip={auth.user?.name}
                                        className="data-[state=open]:bg-clay"
                                    >
                                        <span className="grid size-8 shrink-0 place-items-center rounded-full bg-ember-soft text-sm font-bold text-ember">
                                            {auth.user?.name.charAt(0)}
                                        </span>
                                        <span className="flex min-w-0 flex-col text-left leading-tight">
                                            <span className="truncate font-semibold">
                                                {auth.user?.name}
                                            </span>
                                            <span className="truncate text-xs text-ink-muted">
                                                {auth.user
                                                    ? t(
                                                          `roles.${auth.user.role}`,
                                                      )
                                                    : null}
                                            </span>
                                        </span>
                                        <ChevronsUpDown
                                            className="ml-auto size-4"
                                            aria-hidden="true"
                                        />
                                    </SidebarMenuButton>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    side="top"
                                    align="start"
                                    className="w-64"
                                >
                                    <DropdownMenuLabel className="text-label text-ink-muted">
                                        {t('ui.theme.label')}
                                    </DropdownMenuLabel>
                                    <div className="px-2 pb-2">
                                        <ThemeToggle />
                                    </div>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        asChild
                                        className="min-h-11"
                                    >
                                        <Link href="/">
                                            <ExternalLink aria-hidden="true" />
                                            {t('staff.view_site')}
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        className="min-h-11"
                                        onSelect={() => router.post('/logout')}
                                    >
                                        <LogOut aria-hidden="true" />
                                        {t('staff.sign_out')}
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarFooter>
                <SidebarRail />
            </Sidebar>
            <SidebarInset className="min-w-0 bg-paper">
                <OfflineBanner />
                <header className="sticky top-0 z-20 flex h-14 items-center gap-2 border-b border-line bg-paper/95 px-3 backdrop-blur-sm md:hidden">
                    <SidebarTrigger
                        className="size-11"
                        aria-label={t('staff.open_menu')}
                    />
                    <span className="font-display text-lg">{name}</span>
                </header>
                <main id="main" className="flex min-w-0 flex-1 flex-col">
                    {children}
                </main>
            </SidebarInset>
        </SidebarProvider>
    );
}
