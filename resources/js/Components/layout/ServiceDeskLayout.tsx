import { useEffect, useState } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import { SharedProps } from '@/Types';
import {
    LayoutDashboard, LifeBuoy,
    Bell, LogOut, Menu, X, Sun, Moon, ChevronDown,
} from 'lucide-react';
import { cn, getInitials, avatarGradient } from '@/Lib/utils';
import { AppSwitcher } from '@/Components/layout/AppSwitcher';
import { HeaderClock } from '@/Components/layout/HeaderClock';

interface NavItem {
    label: string;
    href: string;
    icon: React.ComponentType<{ className?: string }>;
}

const navItems: NavItem[] = [
    { label: 'Dashboard', href: '/tickets', icon: LayoutDashboard },
    { label: 'Tickets', href: '/tickets/queue', icon: LifeBuoy },
];

function isActive(path: string, href: string): boolean {
    if (href === '/tickets') return path === '/tickets';
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

export function ServiceDeskLayout({ children }: { children: React.ReactNode }) {
    const page = usePage<SharedProps>();
    const { auth, flash, notifications_count, notifications } = page.props;
    const path = page.url.split('?')[0];
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [menuOpen, setMenuOpen] = useState(false);
    const [notifOpen, setNotifOpen] = useState(false);
    const [dark, toggleDark] = useDarkMode();
    const user = auth.user;
    const recentNotifications = notifications ?? [];

    const openNotification = (n: SharedProps['notifications'][number]) => {
        setNotifOpen(false);
        if (n.url) router.post(`/notifications/${n.id}/read`, { follow: true }, { preserveScroll: true });
    };
    const handleLogout = () => router.post('/logout');

    const SidebarBody = ({ mobile = false }: { mobile?: boolean }) => (
        <div className="flex h-full flex-col">
            <div className="flex h-16 items-center justify-between px-3">
                <AppSwitcher onNavigate={() => mobile && setSidebarOpen(false)} />
                {mobile && (
                    <button onClick={() => setSidebarOpen(false)} className="text-muted-foreground hover:text-foreground">
                        <X className="h-5 w-5" />
                    </button>
                )}
            </div>

            <div className="px-4 pb-1 pt-2">
                <span className="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider text-primary">
                    <LifeBuoy className="h-3.5 w-3.5" /> Service Desk
                </span>
            </div>

            <nav className="sidebar-scroll flex-1 space-y-0.5 overflow-y-auto px-3 py-3">
                {navItems.map(item => {
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
                })}
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
                <SidebarBody mobile />
            </aside>

            <aside className="glass-panel hidden w-64 shrink-0 lg:block">
                <div className="sticky top-0 h-screen"><SidebarBody /></div>
            </aside>

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="glass sticky top-0 z-30 flex h-16 items-center gap-2 px-4 shadow-[0_6px_24px_-14px_rgba(15,23,42,0.5)] sm:px-6">
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

                        <div className="relative">
                            <button onClick={() => setNotifOpen(v => !v)} title="Notifications"
                                className="relative flex h-9 w-9 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground">
                                <Bell className="h-[18px] w-[18px]" />
                                {notifications_count > 0 && (
                                    <span className="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-bold text-white ring-2 ring-card">
                                        {notifications_count > 9 ? '9+' : notifications_count}
                                    </span>
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

                        <div className="relative">
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
                                        <button onClick={handleLogout} className="flex w-full items-center gap-2.5 px-4 py-2.5 text-sm text-destructive transition-colors hover:bg-destructive/10">
                                            <LogOut className="h-4 w-4" /> Sign out
                                        </button>
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
                    QuakeLogic Service Desk — © {new Date().getFullYear()} QuakeLogic Inc.
                </footer>
            </div>
        </div>
    );
}
