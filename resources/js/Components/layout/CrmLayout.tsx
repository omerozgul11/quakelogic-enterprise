import { useEffect, useState } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import { SharedProps } from '@/Types';
import {
    LayoutDashboard, Building2, Users, Target, FolderKanban, ReceiptText, ContactRound,
    Bell, LogOut, Menu, X, Sun, Moon, ChevronDown, Landmark, FileMinus,
    Boxes, Package, Warehouse, ArrowLeftRight, ShoppingCart, Factory, ClipboardList,
    ListTree, Wrench, Cpu, HardDrive, BadgeCheck, FileCheck, LifeBuoy, Ticket,
    Settings, ShieldCheck, Clock, CalendarCheck, BarChart3, CopyCheck, Zap, PhoneCall,
    Wallet, Receipt, Tags, RefreshCw, Plug,
} from 'lucide-react';
import { cn, getInitials, avatarGradient } from '@/Lib/utils';
import { AppSwitcher } from '@/Components/layout/AppSwitcher';
import { HeaderClock } from '@/Components/layout/HeaderClock';
import { MenuSearch, menuMatches } from '@/Components/layout/MenuSearch';

interface NavItem {
    label: string;
    href: string;
    icon: React.ComponentType<{ className?: string }>;
    /** Only show this item when the user holds this permission. */
    permission?: string;
}

interface NavGroup {
    /** Optional section header rendered above the group. */
    label?: string;
    /** Only show this group when the user holds this permission. */
    permission?: string;
    items: NavItem[];
}

const navGroups: NavGroup[] = [
    {
        items: [
            { label: 'Dashboard', href: '/crm', icon: LayoutDashboard },
            { label: 'Clients', href: '/crm/clients', icon: Building2 },
            { label: 'Contacts', href: '/crm/contacts', icon: Users },
            { label: 'Quick Contacts', href: '/crm/quick-contacts', icon: PhoneCall },
            { label: 'Leads', href: '/crm/leads', icon: Target },
            { label: 'Follow-ups', href: '/crm/follow-ups', icon: CalendarCheck },
            { label: 'Projects', href: '/projects', icon: FolderKanban },
            { label: 'Invoices', href: '/crm/invoices', icon: ReceiptText },
            { label: 'Time Cards', href: '/crm/time-cards', icon: Clock },
        ],
    },
    {
        label: 'Sales tools',
        items: [
            { label: 'Reports', href: '/crm/reports', icon: BarChart3 },
            { label: 'Duplicates', href: '/crm/duplicates', icon: CopyCheck },
            { label: 'Automations', href: '/crm/automations', icon: Zap },
        ],
    },
    {
        label: 'Expenses',
        permission: 'access expenses',
        items: [
            { label: 'Overview', href: '/expenses', icon: Wallet },
            { label: 'Expenses', href: '/expenses/list', icon: Receipt },
            { label: 'Categories', href: '/expenses/categories', icon: Tags },
            { label: 'Recurring costs', href: '/expenses/recurring', icon: RefreshCw },
            { label: 'Reports', href: '/expenses/reports', icon: BarChart3 },
            { label: 'QuickBooks', href: '/expenses/quickbooks', icon: Plug },
        ],
    },
    {
        // The Enterprise Hub sections (Finance/AR + the ERP/WMS/EAM modules) all
        // render inside the CRM shell so the sidebar stays put. Each group is
        // gated by its own `access <section>` permission (every role has them).
        label: 'Finance',
        permission: 'access finance',
        items: [
            { label: 'Overview', href: '/finance', icon: Landmark },
            { label: 'Credit Notes', href: '/finance/credit-notes', icon: FileMinus },
        ],
    },
    {
        label: 'Inventory',
        permission: 'access inventory',
        items: [
            { label: 'Overview', href: '/inventory', icon: Boxes },
            { label: 'Products', href: '/inventory/products', icon: Package },
            { label: 'Warehouses', href: '/inventory/warehouses', icon: Warehouse },
            { label: 'Movements', href: '/inventory/movements', icon: ArrowLeftRight },
        ],
    },
    {
        label: 'Procurement',
        permission: 'access procurement',
        items: [
            { label: 'Overview', href: '/procurement', icon: ShoppingCart },
            { label: 'Suppliers', href: '/procurement/suppliers', icon: Factory },
            { label: 'Purchase Orders', href: '/procurement/purchase-orders', icon: ClipboardList },
            { label: 'Bills', href: '/procurement/bills', icon: Receipt },
            { label: 'Approval Flows', href: '/procurement/approval-flows', icon: ShieldCheck, permission: 'manage approval flows' },
        ],
    },
    {
        label: 'Manufacturing',
        permission: 'access manufacturing',
        items: [
            { label: 'Overview', href: '/manufacturing', icon: Factory },
            { label: 'BOMs', href: '/manufacturing/boms', icon: ListTree },
            { label: 'Work Orders', href: '/manufacturing/work-orders', icon: Wrench },
        ],
    },
    {
        label: 'Assets',
        permission: 'access assets',
        items: [
            { label: 'Overview', href: '/assets', icon: Cpu },
            { label: 'Registry', href: '/assets/registry', icon: HardDrive },
        ],
    },
    {
        label: 'Calibration',
        permission: 'access calibration',
        items: [
            { label: 'Overview', href: '/calibration', icon: BadgeCheck },
            { label: 'Certificates', href: '/calibration/certificates', icon: FileCheck },
        ],
    },
    {
        label: 'Service Desk',
        permission: 'access tickets',
        items: [
            { label: 'Overview', href: '/tickets', icon: LifeBuoy },
            { label: 'Tickets', href: '/tickets/queue', icon: Ticket },
        ],
    },
];

// Section roots (/crm, /finance) match exactly; everything else matches the
// href or any path beneath it.
const SECTION_ROOTS = ['/crm', '/finance', '/inventory', '/procurement', '/manufacturing', '/assets', '/calibration', '/tickets', '/expenses'];
function isActive(path: string, href: string): boolean {
    if (SECTION_ROOTS.includes(href)) return path === href;
    return path === href || path.startsWith(href + '/');
}

function useDarkMode(): [boolean, () => void] {
    const [dark, setDark] = useState(false);
    useEffect(() => {
        setDark(document.documentElement.classList.contains('dark'));
    }, []);
    const toggle = () => {
        const el = document.documentElement;
        const next = !el.classList.contains('dark');
        el.classList.toggle('dark', next);
        try { localStorage.setItem('theme', next ? 'dark' : 'light'); } catch { /* ignore */ }
        setDark(next);
    };
    return [dark, toggle];
}

function timeAgo(iso: string | null): string {
    if (!iso) return '';
    const diff = Date.now() - new Date(iso).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h ago`;
    const days = Math.floor(hrs / 24);
    return days < 7 ? `${days}d ago` : new Date(iso).toLocaleDateString();
}

export function CrmLayout({ children }: { children: React.ReactNode }) {
    const page = usePage<SharedProps>();
    const { auth, flash, notifications_count, notifications } = page.props;
    const path = page.url.split('?')[0];
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [menuOpen, setMenuOpen] = useState(false);
    const [notifOpen, setNotifOpen] = useState(false);
    // Dismiss the notifications / account dropdowns on any outside click or Escape
    // (a document listener also catches header clicks the overlay sits beneath).
    useEffect(() => {
        if (!notifOpen && !menuOpen) return;
        const onDown = (e: MouseEvent) => {
            const el = e.target as Element | null;
            if (notifOpen && !el?.closest('[data-dd="notif"]')) setNotifOpen(false);
            if (menuOpen && !el?.closest('[data-dd="account"]')) setMenuOpen(false);
        };
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') { setNotifOpen(false); setMenuOpen(false); }
        };
        document.addEventListener('mousedown', onDown);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('mousedown', onDown);
            document.removeEventListener('keydown', onKey);
        };
    }, [notifOpen, menuOpen]);

    const [dark, toggleDark] = useDarkMode();
    const user = auth.user;
    const recentNotifications = notifications ?? [];

    const openNotification = (n: SharedProps['notifications'][number]) => {
        setNotifOpen(false);
        if (n.url) router.post(`/notifications/${n.id}/read`, { follow: true }, { preserveScroll: true });
    };
    const handleLogout = () => router.post('/logout');

    // Collapsible sidebar sections. A section auto-expands while you're on one of
    // its pages; manual expand/collapse is remembered across navigations.
    const [openGroups, setOpenGroups] = useState<Record<string, boolean>>(() => {
        try { return JSON.parse(localStorage.getItem('crmNavGroups') ?? '{}'); } catch { return {}; }
    });
    const groupHasActive = (group: NavGroup) => group.items.some(item => isActive(path, item.href));
    const isGroupOpen = (group: NavGroup) => groupHasActive(group) || openGroups[group.label ?? ''] === true;

    // Sidebar menu search. While a query is present every group is force-opened
    // and only matching items (or all items of a group whose label matches) show.
    const [menuQuery, setMenuQuery] = useState('');
    const toggleGroup = (label: string) => {
        setOpenGroups(prev => {
            const next = { ...prev, [label]: !(prev[label] ?? false) };
            try { localStorage.setItem('crmNavGroups', JSON.stringify(next)); } catch { /* ignore */ }
            return next;
        });
    };

    const SidebarBody = ({ mobile = false }: { mobile?: boolean }) => (
        <div className="flex h-full flex-col">
            <div className="flex h-16 items-center justify-between px-3">
                <AppSwitcher activeKey="crm" onNavigate={() => mobile && setSidebarOpen(false)} />
                {mobile && (
                    <button onClick={() => setSidebarOpen(false)} className="text-muted-foreground hover:text-foreground">
                        <X className="h-5 w-5" />
                    </button>
                )}
            </div>

            <div className="px-4 pb-1 pt-2">
                <span className="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider text-primary">
                    <ContactRound className="h-3.5 w-3.5" /> CRM
                </span>
            </div>

            <MenuSearch value={menuQuery} onChange={setMenuQuery} />

            <nav className="sidebar-scroll flex-1 space-y-1 overflow-y-auto px-3 py-3">
                {(() => {
                    const searching = menuQuery.trim() !== '';
                    const renderItem = (item: NavItem) => {
                        const Icon = item.icon;
                        const active = isActive(path, item.href);
                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                onClick={() => mobile && setSidebarOpen(false)}
                                className={cn('nav-chip group', active ? 'nav-chip-active' : 'nav-chip-idle')}
                            >
                                <Icon className="h-[18px] w-[18px] shrink-0" />
                                <span className="min-w-0 truncate">{item.label}</span>
                            </Link>
                        );
                    };

                    const visible = navGroups
                        .filter(group => !group.permission || (user?.permissions ?? []).includes(group.permission))
                        .map(group => {
                            const permitted = group.items.filter(item => !item.permission || (user?.permissions ?? []).includes(item.permission));
                            const labelMatch = !!group.label && menuMatches(group.label, menuQuery);
                            const items = searching && !labelMatch
                                ? permitted.filter(item => menuMatches(item.label, menuQuery))
                                : permitted;
                            return { group, items };
                        })
                        .filter(({ items }) => items.length > 0);

                    if (searching && visible.length === 0) {
                        return (
                            <p className="px-3 py-6 text-center text-sm text-muted-foreground">
                                No menu items match “{menuQuery.trim()}”.
                            </p>
                        );
                    }

                    return visible.map(({ group, items }) => {
                        // The core CRM group has no label — always shown, never collapses.
                        if (!group.label) {
                            return <div key="core" className="space-y-0.5">{items.map(renderItem)}</div>;
                        }

                        const open = searching || isGroupOpen(group);
                        return (
                            <div key={group.label}>
                                <button
                                    onClick={() => toggleGroup(group.label!)}
                                    className="flex w-full items-center justify-between rounded-lg px-3 py-1.5 text-xs font-bold uppercase tracking-wider text-muted-foreground/70 transition-colors hover:bg-secondary hover:text-foreground"
                                >
                                    <span className="truncate">{group.label}</span>
                                    <ChevronDown className={cn('h-4 w-4 shrink-0 transition-transform', open ? '' : '-rotate-90')} />
                                </button>
                                {open && <div className="mt-0.5 space-y-0.5">{items.map(renderItem)}</div>}
                            </div>
                        );
                    });
                })()}
            </nav>

            <div className="mt-auto p-3">
                <div className="flex items-center gap-3 rounded-lg bg-secondary/40 px-2 py-2">
                    <span className={cn('flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br text-xs font-bold text-white', avatarGradient(user?.name))}>
                        {getInitials(user?.name)}
                    </span>
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-semibold text-foreground">{user?.name}</p>
                        <p className="truncate text-xs text-muted-foreground">{user?.roles?.[0] ?? 'User'}</p>
                    </div>
                </div>
            </div>
        </div>
    );

    return (
        <div className="ql-liquid flex min-h-screen">
            {sidebarOpen && (
                <div className="fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-sm lg:hidden" onClick={() => setSidebarOpen(false)} />
            )}

            <aside className={cn(
                'glass-panel fixed inset-y-0 left-0 z-50 w-64 transform shadow-2xl transition-transform duration-300 ease-in-out lg:hidden',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full'
            )}>
                {SidebarBody({ mobile: true })}
            </aside>

            <aside className="glass-panel hidden w-64 shrink-0 lg:block">
                <div className="sticky top-0 h-screen">{SidebarBody({})}</div>
            </aside>

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="glass-panel sticky top-0 z-30 flex h-16 items-center gap-2 px-4 sm:px-6">
                    <button className="lg:hidden" onClick={() => setSidebarOpen(true)}>
                        <Menu className="h-6 w-6 text-muted-foreground" />
                    </button>

                    <div className="ml-auto flex items-center gap-1.5 sm:gap-2">
                        <HeaderClock />

                        <button
                            onClick={toggleDark}
                            title={dark ? 'Switch to light mode' : 'Switch to dark mode'}
                            className="flex h-9 w-9 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                        >
                            {dark ? <Sun className="h-[18px] w-[18px]" /> : <Moon className="h-[18px] w-[18px]" />}
                        </button>

                        <div data-dd="notif" className="relative">
                            <button onClick={() => setNotifOpen(v => !v)} title="Notifications"
                                className="relative flex h-9 w-9 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground">
                                <Bell className="h-[18px] w-[18px]" />
                                {notifications_count > 0 && (
                                    <span className="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-primary ring-2 ring-card" />
                                )}
                            </button>
                            {notifOpen && (
                                <>
                                    <div className="fixed inset-0 z-10" onClick={() => setNotifOpen(false)} />
                                    <div className="animate-dropdown origin-top-right absolute right-0 top-11 z-20 w-80 max-w-[calc(100vw-1.5rem)] overflow-hidden rounded-xl border border-border bg-card shadow-xl ring-1 ring-black/5 dark:ring-white/10">
                                        <div className="border-b border-border px-4 py-3"><p className="text-sm font-semibold text-foreground">Notifications</p></div>
                                        <div className="max-h-80 overflow-y-auto">
                                            {recentNotifications.length === 0 ? (
                                                <div className="px-4 py-8 text-center">
                                                    <Bell className="mx-auto mb-2 h-6 w-6 text-muted-foreground/50" />
                                                    <p className="text-sm text-muted-foreground">You're all caught up</p>
                                                </div>
                                            ) : recentNotifications.map(n => (
                                                <button key={n.id} onClick={() => openNotification(n)}
                                                    className={cn('flex w-full items-start gap-3 border-b border-border px-4 py-3 text-left transition-colors hover:bg-secondary last:border-0', !n.read && 'bg-primary/[0.04]')}>
                                                    <span className={cn('mt-1.5 h-2 w-2 shrink-0 rounded-full', n.read ? 'bg-transparent' : 'bg-primary')} />
                                                    <span className="min-w-0 flex-1">
                                                        <span className="block truncate text-sm font-medium text-foreground">{n.title}</span>
                                                        {n.message && <span className="block truncate text-xs text-muted-foreground">{n.message}</span>}
                                                        <span className="mt-0.5 block text-[11px] text-muted-foreground/70">{timeAgo(n.created_at)}</span>
                                                    </span>
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                </>
                            )}
                        </div>

                        <div data-dd="account" className="relative">
                            <button onClick={() => setMenuOpen(v => !v)} className="flex items-center gap-2 rounded-full py-1 pl-1 pr-2 transition-colors hover:bg-secondary">
                                <span className={cn('flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br text-[11px] font-bold text-white', avatarGradient(user?.name))}>
                                    {getInitials(user?.name)}
                                </span>
                                <span className="hidden text-sm font-medium text-foreground sm:inline">{user?.name?.split(' ')[0]}</span>
                                <ChevronDown className={cn('h-4 w-4 text-muted-foreground transition-transform', menuOpen && 'rotate-180')} />
                            </button>
                            {menuOpen && (
                                <>
                                    <div className="fixed inset-0 z-10" onClick={() => setMenuOpen(false)} />
                                    <div className="animate-dropdown origin-top-right absolute right-0 top-11 z-20 w-60 max-w-[calc(100vw-1.5rem)] overflow-hidden rounded-xl border border-border bg-card py-1 shadow-xl ring-1 ring-black/5 dark:ring-white/10">
                                        <div className="border-b border-border px-4 py-3">
                                            <p className="text-sm font-semibold text-foreground">{user?.name}</p>
                                            <p className="truncate text-xs text-muted-foreground">{user?.email}</p>
                                        </div>
                                        <Link href="/" onClick={() => setMenuOpen(false)} className="flex items-center gap-2.5 px-4 py-2.5 text-sm text-foreground transition-colors hover:bg-secondary">
                                            <LayoutDashboard className="h-4 w-4 text-muted-foreground" /> Proposals home
                                        </Link>
                                        <Link href="/settings" onClick={() => setMenuOpen(false)} className="flex items-center gap-2.5 px-4 py-2.5 text-sm text-foreground transition-colors hover:bg-secondary">
                                            <Settings className="h-4 w-4 text-muted-foreground" /> Settings
                                        </Link>
                                        {user?.roles?.includes('Super Admin') && (
                                            <Link href="/admin" onClick={() => setMenuOpen(false)} className="flex items-center gap-2.5 px-4 py-2.5 text-sm text-foreground transition-colors hover:bg-secondary">
                                                <ShieldCheck className="h-4 w-4 text-muted-foreground" /> Admin Panel
                                            </Link>
                                        )}
                                        <div className="mt-1 border-t border-border pt-1">
                                            <button onClick={handleLogout} className="flex w-full items-center gap-2.5 px-4 py-2.5 text-sm text-destructive transition-colors hover:bg-destructive/10">
                                                <LogOut className="h-4 w-4" /> Sign out
                                            </button>
                                        </div>
                                    </div>
                                </>
                            )}
                        </div>
                    </div>
                </header>

                {(flash?.success || flash?.error || flash?.warning) && (
                    <div className="space-y-2 px-4 pt-4 sm:px-6">
                        {flash.success && <div className="animate-rise rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-300">{flash.success}</div>}
                        {flash.error && <div className="animate-rise rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">{flash.error}</div>}
                        {flash.warning && <div className="animate-rise rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300">{flash.warning}</div>}
                    </div>
                )}

                <main key={path} className="animate-page flex-1">{children}</main>

                <footer className="py-4 text-center text-xs text-muted-foreground">
                    QuakeLogic CRM — © {new Date().getFullYear()} QuakeLogic Inc.
                </footer>
            </div>
        </div>
    );
}
