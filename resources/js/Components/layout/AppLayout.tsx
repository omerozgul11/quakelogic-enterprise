import { useEffect, useRef, useState } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import { SharedProps } from '@/Types';
import {
    LayoutDashboard, Target, FileText, Building2,
    Users, Bell, LogOut, Settings, FileSearch, MessageSquare,
    BarChart3, Puzzle, Sparkles, ShieldCheck, KanbanSquare,
    Menu, X, Sun, Moon, ChevronDown, TrendingUp, Activity, BookOpen,
    CalendarDays, Inbox, FileSignature, LibraryBig, ScrollText, PenLine, Gauge,
} from 'lucide-react';
import { cn, getInitials, avatarGradient } from '@/Lib/utils';
import { clearChat } from '@/Lib/chatStore';
import { AppSwitcher } from '@/Components/layout/AppSwitcher';
import { GlobalSearch } from '@/Components/layout/GlobalSearch';
import { PwaControls } from '@/Components/layout/PwaControls';
import { QuakeAiChat } from '@/Components/layout/QuakeAiChat';
import { HeaderClock } from '@/Components/layout/HeaderClock';

interface NavItem {
    label: string;
    href: string;
    icon: React.ComponentType<{ className?: string }>;
    permission?: string;
}

interface NavSection {
    title?: string;
    items: NavItem[];
    /** Render as a collapsible dropdown (closed by default) to save space. */
    collapsible?: boolean;
}

const sections: NavSection[] = [
    { items: [
        { label: 'Dashboard', href: '/', icon: LayoutDashboard },
        { label: 'Calendar', href: '/calendar', icon: CalendarDays },
        { label: 'QuakeAI', href: '/ai', icon: Sparkles, permission: 'use ai assistant' },
        { label: 'Proposal Writer', href: '/ai/writer', icon: PenLine, permission: 'use ai assistant' },
        { label: 'Datasheet Writer', href: '/ai/datasheets', icon: ScrollText, permission: 'use ai assistant' },
    ] },
    {
        title: 'Pipeline',
        items: [
            { label: 'Opportunities', href: '/opportunities', icon: Target, permission: 'view opportunities' },
            { label: 'Command Center', href: '/dashboard/opportunities', icon: Gauge, permission: 'view executive dashboard' },
            { label: 'Applications', href: '/proposals/board', icon: KanbanSquare, permission: 'view proposals' },
            { label: 'Proposals', href: '/proposals', icon: FileText, permission: 'view proposals' },
            { label: 'Documents', href: '/documents', icon: FileSearch, permission: 'view proposals' },
            { label: 'Contracts', href: '/contracts', icon: FileSignature, permission: 'view contracts' },
        ],
    },
    {
        title: 'Library',
        items: [
            { label: 'Compliance', href: '/compliance', icon: ShieldCheck, permission: 'view compliance' },
            { label: 'Template Library', href: '/templates', icon: LibraryBig, permission: 'view templates' },
        ],
    },
    {
        title: 'Relationships',
        items: [
            { label: 'Companies/Agencies', href: '/companies', icon: Building2, permission: 'view crm' },
            { label: 'Contacts', href: '/contacts', icon: Users, permission: 'view crm' },
            { label: 'Inbox', href: '/follow-ups', icon: Inbox, permission: 'view follow ups' },
        ],
    },
    {
        title: 'Insights',
        items: [
            { label: 'Team Performance', href: '/reports/users', icon: Users, permission: 'view dashboards' },
            { label: 'Market Pricing', href: '/market-pricing', icon: TrendingUp, permission: 'view opportunities' },
            { label: 'Integrations', href: '/integrations', icon: Puzzle, permission: 'manage integrations' },
        ],
    },
];

function matchesHref(path: string, href: string): boolean {
    if (href === '/') return path === '/';
    return path === href || path.startsWith(href + '/');
}

// All nav destinations, so we can highlight only the most specific match
// (e.g. /proposals/board highlights "Applications", not also "Proposals").
const ALL_HREFS = [
    ...sections.flatMap(s => s.items.map(i => i.href)),
    '/guide',
    '/admin/activity',
    '/admin',
];

function activeHref(currentUrl: string): string | null {
    const path = currentUrl.split('?')[0];
    let best: string | null = null;
    for (const href of ALL_HREFS) {
        if (matchesHref(path, href) && (!best || href.length > best.length)) {
            best = href;
        }
    }
    return best;
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

const SIDEBAR_MIN = 184;
const SIDEBAR_MAX = 400;
const SIDEBAR_DEFAULT = 256;

function useSidebarWidth(): [number, (e: React.MouseEvent) => void, boolean, () => void] {
    const [width, setWidth] = useState(SIDEBAR_DEFAULT);
    const [resizing, setResizing] = useState(false);
    const widthRef = useRef(width);

    useEffect(() => {
        const saved = Number(localStorage.getItem('sidebar-width'));
        if (saved >= SIDEBAR_MIN && saved <= SIDEBAR_MAX) {
            setWidth(saved);
            widthRef.current = saved;
        }
    }, []);

    const startResize = (e: React.MouseEvent) => {
        e.preventDefault();
        const startX = e.clientX;
        const startWidth = widthRef.current;
        setResizing(true);
        document.body.style.cursor = 'col-resize';
        document.body.style.userSelect = 'none';
        const onMove = (ev: MouseEvent) => {
            const w = Math.min(SIDEBAR_MAX, Math.max(SIDEBAR_MIN, startWidth + ev.clientX - startX));
            widthRef.current = w;
            setWidth(w);
        };
        const onUp = () => {
            setResizing(false);
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
            try { localStorage.setItem('sidebar-width', String(widthRef.current)); } catch { /* ignore */ }
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseup', onUp);
        };
        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp);
    };

    const reset = () => {
        widthRef.current = SIDEBAR_DEFAULT;
        setWidth(SIDEBAR_DEFAULT);
        try { localStorage.setItem('sidebar-width', String(SIDEBAR_DEFAULT)); } catch { /* ignore */ }
    };

    return [width, startResize, resizing, reset];
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

export function AppLayout({ children }: { children: React.ReactNode }) {
    const page = usePage<SharedProps>();
    const { auth, flash, notifications_count, notifications, inbox_unread_count } = page.props;
    const currentUrl = page.url;
    const matchedHref = activeHref(currentUrl);
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [menuOpen, setMenuOpen] = useState(false);
    const [notifOpen, setNotifOpen] = useState(false);
    const [dark, toggleDark] = useDarkMode();
    const [sidebarWidth, startResize, resizing, resetSidebarWidth] = useSidebarWidth();
    const user = auth.user;
    const recentNotifications = notifications ?? [];

    // Dismiss the notifications / account dropdowns on any outside click or Escape
    // — the same approach the app switcher uses (a document listener catches header
    // clicks that a z-indexed overlay would miss).
    const notifRef = useRef<HTMLDivElement>(null);
    const accountRef = useRef<HTMLDivElement>(null);
    useEffect(() => {
        if (!notifOpen && !menuOpen) return;
        const onDown = (e: MouseEvent) => {
            const t = e.target as Node;
            if (notifOpen && !notifRef.current?.contains(t)) setNotifOpen(false);
            if (menuOpen && !accountRef.current?.contains(t)) setMenuOpen(false);
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

    // Collapsible nav sections (System / Help). Closed by default to save space;
    // the user's open/closed choice is remembered across sessions.
    const [openSections, setOpenSections] = useState<Record<string, boolean>>({});
    useEffect(() => {
        try {
            const saved = localStorage.getItem('nav-open-sections');
            if (saved) setOpenSections(JSON.parse(saved));
        } catch { /* ignore */ }
    }, []);
    const toggleSection = (title: string) => {
        setOpenSections(prev => {
            const next = { ...prev, [title]: !(prev[title] ?? false) };
            try { localStorage.setItem('nav-open-sections', JSON.stringify(next)); } catch { /* ignore */ }
            return next;
        });
    };

    const openNotification = (n: SharedProps['notifications'][number]) => {
        setNotifOpen(false);
        if (n.url) {
            router.post(`/notifications/${n.id}/read`, { follow: true }, { preserveScroll: true });
        } else if (!n.read) {
            router.post(`/notifications/${n.id}/read`, {}, { preserveScroll: true });
        }
    };
    const markAllRead = () => router.post('/notifications/read-all', {}, { preserveScroll: true });

    const hasPermission = (permission?: string) =>
        !permission || (user?.permissions?.includes(permission) ?? false);
    const handleLogout = () => { clearChat(); router.post('/logout'); };
    const isSuperAdmin = user?.roles?.includes('Super Admin');

    const adminItems: NavItem[] = [
        { label: 'Activity Log', href: '/admin/activity', icon: Activity },
        { label: 'Audit Log', href: '/admin/audit-logs', icon: ScrollText },
        { label: 'Admin', href: '/admin', icon: Settings },
    ];

    const SidebarBody = ({ mobile = false }: { mobile?: boolean }) => {
        const renderItem = (item: NavItem) => {
            const Icon = item.icon;
            const active = item.href === matchedHref;
            return (
                <Link
                    key={item.href}
                    href={item.href}
                    onClick={() => mobile && setSidebarOpen(false)}
                    className={cn('nav-chip group', active ? 'nav-chip-active' : 'nav-chip-idle')}
                >
                    <Icon className="h-[18px] w-[18px] shrink-0" />
                    <span className="min-w-0 truncate" title={item.label}>{item.label}</span>
                    {item.href === '/follow-ups' && inbox_unread_count > 0 && (
                        <span className="ml-auto shrink-0 text-xs font-semibold tabular-nums text-orange-500">
                            ({inbox_unread_count > 99 ? '99+' : inbox_unread_count})
                        </span>
                    )}
                </Link>
            );
        };

        const renderSection = (key: number | string, title: string | undefined, items: NavItem[], collapsible: boolean) => {
            if (items.length === 0) return null;
            // Collapsible sections start closed to save space, but auto-open when
            // they contain the current page so its highlight is never hidden.
            const containsActive = items.some(i => i.href === matchedHref);
            const open = !collapsible || containsActive || (openSections[title ?? ''] ?? false);
            return (
                <div key={key}>
                    {title && (collapsible ? (
                        <button
                            type="button"
                            onClick={() => toggleSection(title)}
                            aria-expanded={open}
                            className="flex w-full items-center gap-1 rounded-lg px-3 pb-1.5 pt-0.5 text-[11.5px] font-bold uppercase tracking-[0.12em] text-muted-foreground/70 transition-colors hover:text-foreground"
                        >
                            <span className="truncate">{title}</span>
                            <ChevronDown className={cn('ml-auto h-3.5 w-3.5 shrink-0 transition-transform duration-200', open ? '' : '-rotate-90')} />
                        </button>
                    ) : (
                        <p className="truncate px-3 pb-1.5 text-[11.5px] font-bold uppercase tracking-[0.12em] text-muted-foreground/70">
                            {title}
                        </p>
                    ))}
                    {open && <div className="space-y-0.5">{items.map(renderItem)}</div>}
                </div>
            );
        };

        return (
        <div className="flex h-full flex-col">
            <div className="flex h-16 items-center justify-between px-3">
                <AppSwitcher onNavigate={() => mobile && setSidebarOpen(false)} />
                {mobile && (
                    <button onClick={() => setSidebarOpen(false)} className="text-muted-foreground hover:text-foreground">
                        <X className="h-5 w-5" />
                    </button>
                )}
            </div>

            <nav className="sidebar-scroll flex-1 space-y-5 overflow-y-auto px-3 py-3">
                {sections.map((section, si) =>
                    renderSection(si, section.title, section.items.filter(i => hasPermission(i.permission)), !!section.collapsible),
                )}

                {isSuperAdmin && renderSection('system', 'System', adminItems, true)}
            </nav>

            <div className="space-y-1 border-t border-border p-3">
                {renderItem({ label: 'User Guide', href: '/guide', icon: BookOpen })}
                <Link
                    href="/settings"
                    onClick={() => mobile && setSidebarOpen(false)}
                    className="flex items-center gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-secondary"
                >
                    <span className={cn('flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br text-xs font-bold text-white', avatarGradient(user?.name))}>
                        {getInitials(user?.name)}
                    </span>
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-semibold text-foreground">{user?.name}</p>
                        <p className="truncate text-xs text-muted-foreground">{user?.roles?.[0] ?? 'User'}</p>
                    </div>
                </Link>
            </div>
        </div>
        );
    };

    return (
        <div className="ql-liquid flex min-h-screen">
            {/* Mobile overlay */}
            {sidebarOpen && (
                <div className="fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-sm lg:hidden" onClick={() => setSidebarOpen(false)} />
            )}

            {/* Mobile sidebar */}
            <aside className={cn(
                'fixed inset-y-0 left-0 z-50 w-64 transform bg-card shadow-xl transition-transform duration-300 ease-in-out lg:hidden',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full'
            )}>
                <SidebarBody mobile />
            </aside>

            {/* Desktop sidebar — drag the right edge to resize */}
            <aside className="glass-panel hidden shrink-0 lg:block" style={{ width: sidebarWidth }}>
                <div className="sticky top-0 h-screen">
                    <SidebarBody />
                    <div
                        onMouseDown={startResize}
                        onDoubleClick={resetSidebarWidth}
                        title="Drag to resize · double-click to reset"
                        className={cn(
                            'absolute inset-y-0 -right-[3px] z-10 w-1.5 cursor-col-resize transition-colors',
                            resizing ? 'bg-primary/50' : 'hover:bg-primary/30',
                        )}
                    />
                </div>
            </aside>

            {/* Main column */}
            <div className="flex min-w-0 flex-1 flex-col">
                <header className="glass-panel sticky top-0 z-30 flex h-16 items-center gap-2 px-4 sm:px-6">
                    <button className="lg:hidden" onClick={() => setSidebarOpen(true)}>
                        <Menu className="h-6 w-6 text-muted-foreground" />
                    </button>

                    <GlobalSearch />

                    <div className="ml-auto flex items-center gap-1.5 sm:gap-2">
                        <HeaderClock />
                        <PwaControls />

                        {hasPermission('use ai assistant') && (
                            <QuakeAiChat active={matchedHref === '/ai'} />
                        )}

                        <button
                            onClick={toggleDark}
                            title={dark ? 'Switch to light mode' : 'Switch to dark mode'}
                            className="flex h-9 w-9 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                        >
                            {dark ? <Sun className="h-[18px] w-[18px]" /> : <Moon className="h-[18px] w-[18px]" />}
                        </button>

                        <div ref={notifRef} className="relative">
                            <button
                                onClick={() => setNotifOpen(v => !v)}
                                className="relative flex h-9 w-9 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                                title="Notifications"
                            >
                                <Bell className="h-[18px] w-[18px]" />
                                {notifications_count > 0 && (
                                    <span className="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-bold text-white ring-2 ring-card">
                                        {notifications_count > 9 ? '9+' : notifications_count}
                                    </span>
                                )}
                            </button>

                            {notifOpen && (
                                <>
                                    <div className="animate-dropdown origin-top-right absolute right-0 top-11 z-20 w-80 max-w-[calc(100vw-1.5rem)] overflow-hidden rounded-xl border border-border bg-card shadow-xl ring-1 ring-black/5 dark:ring-white/10">
                                        <div className="flex items-center justify-between border-b border-border px-4 py-3">
                                            <p className="text-sm font-semibold text-foreground">Notifications</p>
                                            {notifications_count > 0 && (
                                                <button onClick={markAllRead} className="text-xs font-medium text-primary hover:underline">Mark all read</button>
                                            )}
                                        </div>
                                        <div className="max-h-80 overflow-y-auto">
                                            {recentNotifications.length === 0 ? (
                                                <div className="px-4 py-8 text-center">
                                                    <Bell className="mx-auto mb-2 h-6 w-6 text-muted-foreground/50" />
                                                    <p className="text-sm text-muted-foreground">You're all caught up</p>
                                                </div>
                                            ) : (
                                                recentNotifications.map(n => (
                                                    <button
                                                        key={n.id}
                                                        onClick={() => openNotification(n)}
                                                        className={cn(
                                                            'flex w-full items-start gap-3 border-b border-border px-4 py-3 text-left transition-colors hover:bg-secondary last:border-0',
                                                            !n.read && 'bg-primary/[0.04]'
                                                        )}
                                                    >
                                                        <span className={cn('mt-1.5 h-2 w-2 shrink-0 rounded-full', n.read ? 'bg-transparent' : 'bg-primary')} />
                                                        <span className="min-w-0 flex-1">
                                                            <span className="block truncate text-sm font-medium text-foreground">{n.title}</span>
                                                            {n.message && <span className="block truncate text-xs text-muted-foreground">{n.message}</span>}
                                                            <span className="mt-0.5 block text-[11px] text-muted-foreground/70">{timeAgo(n.created_at)}</span>
                                                        </span>
                                                    </button>
                                                ))
                                            )}
                                        </div>
                                        <Link href="/notifications" onClick={() => setNotifOpen(false)} className="block border-t border-border px-4 py-2.5 text-center text-sm font-medium text-primary transition-colors hover:bg-secondary">
                                            View all
                                        </Link>
                                    </div>
                                </>
                            )}
                        </div>

                        <div ref={accountRef} className="relative">
                            <button
                                onClick={() => setMenuOpen(v => !v)}
                                className="flex items-center gap-2 rounded-full py-1 pl-1 pr-2 transition-colors hover:bg-secondary"
                            >
                                <span className={cn('flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br text-[11px] font-bold text-white', avatarGradient(user?.name))}>
                                    {getInitials(user?.name)}
                                </span>
                                <span className="hidden text-sm font-medium text-foreground sm:inline">{user?.name?.split(' ')[0]}</span>
                                <ChevronDown className={cn('h-4 w-4 text-muted-foreground transition-transform', menuOpen && 'rotate-180')} />
                            </button>

                            {menuOpen && (
                                <>
                                    <div className="animate-dropdown origin-top-right absolute right-0 top-11 z-20 w-60 max-w-[calc(100vw-1.5rem)] overflow-hidden rounded-xl border border-border bg-card py-1 shadow-xl ring-1 ring-black/5 dark:ring-white/10">
                                        <div className="border-b border-border px-4 py-3">
                                            <p className="text-sm font-semibold text-foreground">{user?.name}</p>
                                            <p className="truncate text-xs text-muted-foreground">{user?.email}</p>
                                            {user?.roles?.[0] && <p className="mt-0.5 text-xs font-medium text-primary">{user.roles[0]}</p>}
                                        </div>
                                        <Link href="/settings" onClick={() => setMenuOpen(false)} className="flex items-center gap-2.5 px-4 py-2.5 text-sm text-foreground transition-colors hover:bg-secondary">
                                            <Settings className="h-4 w-4 text-muted-foreground" /> Settings
                                        </Link>
                                        {isSuperAdmin && (
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

                <main key={currentUrl.split('?')[0]} className="animate-page flex-1">
                    {children}
                </main>

                <footer className="border-t border-border py-4 text-center text-xs text-muted-foreground">
                    <Link href="/legal" className="transition-colors hover:text-foreground hover:underline" title="Terms, copyright & legal notice">
                        QuakeLogic Proposals — © {new Date().getFullYear()} QuakeLogic Inc.
                    </Link>
                </footer>
            </div>
        </div>
    );
}
