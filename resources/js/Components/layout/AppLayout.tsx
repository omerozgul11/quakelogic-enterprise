import { useEffect, useState } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import { SharedProps } from '@/Types';
import {
    LayoutDashboard, Target, FileText, Shield, Building2,
    Users, Bell, LogOut, Settings, FileSearch, MessageSquare,
    DollarSign, BarChart3, Puzzle, Sparkles, ShieldCheck,
    Menu, X, Sun, Moon, ChevronDown,
} from 'lucide-react';
import { cn, getInitials, avatarGradient } from '@/Lib/utils';
import { Logo } from '@/Components/ui/Logo';

interface NavItem {
    label: string;
    href: string;
    icon: React.ComponentType<{ className?: string }>;
    permission?: string;
}

interface NavSection {
    title?: string;
    items: NavItem[];
}

const sections: NavSection[] = [
    { items: [{ label: 'Dashboard', href: '/', icon: LayoutDashboard }] },
    {
        title: 'Pipeline',
        items: [
            { label: 'Opportunities', href: '/opportunities', icon: Target, permission: 'view opportunities' },
            { label: 'Capture', href: '/capture', icon: Shield, permission: 'view capture plans' },
            { label: 'Proposals', href: '/proposals', icon: FileText, permission: 'view proposals' },
            { label: 'Documents', href: '/documents', icon: FileSearch, permission: 'view proposals' },
        ],
    },
    {
        title: 'Relationships',
        items: [
            { label: 'Agencies', href: '/agencies', icon: Building2, permission: 'view crm' },
            { label: 'Companies', href: '/companies', icon: Building2, permission: 'view crm' },
            { label: 'Contacts', href: '/contacts', icon: Users, permission: 'view crm' },
            { label: 'Follow-Ups', href: '/follow-ups', icon: MessageSquare, permission: 'view follow ups' },
        ],
    },
    {
        title: 'Insights',
        items: [
            { label: 'Commissions', href: '/commissions', icon: DollarSign, permission: 'view own commissions' },
            { label: 'Reports', href: '/reports', icon: BarChart3, permission: 'view dashboards' },
            { label: 'Ask QuakeAI', href: '/ai', icon: Sparkles, permission: 'use ai assistant' },
            { label: 'Integrations', href: '/integrations', icon: Puzzle, permission: 'manage integrations' },
        ],
    },
];

function isActive(currentUrl: string, href: string): boolean {
    const path = currentUrl.split('?')[0];
    if (href === '/') return path === '/';
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

export function AppLayout({ children }: { children: React.ReactNode }) {
    const page = usePage<SharedProps>();
    const { auth, flash, notifications_count } = page.props;
    const currentUrl = page.url;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [menuOpen, setMenuOpen] = useState(false);
    const [dark, toggleDark] = useDarkMode();
    const user = auth.user;

    const hasPermission = (permission?: string) =>
        !permission || (user?.permissions?.includes(permission) ?? false);
    const handleLogout = () => router.post('/logout');
    const isSuperAdmin = user?.roles?.includes('Super Admin');

    const SidebarBody = ({ mobile = false }: { mobile?: boolean }) => (
        <div className="flex h-full flex-col">
            <div className="flex h-16 items-center justify-between px-5">
                <Link href="/" onClick={() => mobile && setSidebarOpen(false)}>
                    <Logo />
                </Link>
                {mobile && (
                    <button onClick={() => setSidebarOpen(false)} className="text-muted-foreground hover:text-foreground">
                        <X className="h-5 w-5" />
                    </button>
                )}
            </div>

            <nav className="flex-1 space-y-5 overflow-y-auto px-3 py-3">
                {sections.map((section, si) => {
                    const items = section.items.filter(i => hasPermission(i.permission));
                    if (items.length === 0) return null;
                    return (
                        <div key={si}>
                            {section.title && (
                                <p className="px-3 pb-1.5 text-[10px] font-bold uppercase tracking-[0.13em] text-muted-foreground/70">
                                    {section.title}
                                </p>
                            )}
                            <div className="space-y-0.5">
                                {items.map(item => {
                                    const Icon = item.icon;
                                    const active = isActive(currentUrl, item.href);
                                    return (
                                        <Link
                                            key={item.href}
                                            href={item.href}
                                            onClick={() => mobile && setSidebarOpen(false)}
                                            className={cn(
                                                'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                                                active
                                                    ? 'bg-brand-gradient text-white shadow-sm'
                                                    : 'text-muted-foreground hover:bg-secondary hover:text-foreground'
                                            )}
                                        >
                                            <Icon className={cn('h-[18px] w-[18px]', active ? 'text-white' : 'text-muted-foreground group-hover:text-foreground')} />
                                            {item.label}
                                        </Link>
                                    );
                                })}
                            </div>
                        </div>
                    );
                })}

                {isSuperAdmin && (
                    <div>
                        <p className="px-3 pb-1.5 text-[10px] font-bold uppercase tracking-[0.13em] text-muted-foreground/70">System</p>
                        <Link
                            href="/admin"
                            onClick={() => mobile && setSidebarOpen(false)}
                            className={cn(
                                'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                                isActive(currentUrl, '/admin')
                                    ? 'bg-brand-gradient text-white shadow-sm'
                                    : 'text-muted-foreground hover:bg-secondary hover:text-foreground'
                            )}
                        >
                            <Settings className={cn('h-[18px] w-[18px]', isActive(currentUrl, '/admin') ? 'text-white' : 'text-muted-foreground group-hover:text-foreground')} />
                            Admin
                        </Link>
                    </div>
                )}
            </nav>

            <div className="border-t border-border p-3">
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

    return (
        <div className="flex min-h-screen bg-background">
            {/* Mobile overlay */}
            {sidebarOpen && (
                <div className="fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-sm lg:hidden" onClick={() => setSidebarOpen(false)} />
            )}

            {/* Mobile sidebar */}
            <aside className={cn(
                'fixed inset-y-0 left-0 z-50 w-64 transform border-r border-border bg-card shadow-xl transition-transform duration-300 ease-in-out lg:hidden',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full'
            )}>
                <SidebarBody mobile />
            </aside>

            {/* Desktop sidebar */}
            <aside className="hidden w-64 shrink-0 border-r border-border bg-card lg:block">
                <div className="sticky top-0 h-screen">
                    <SidebarBody />
                </div>
            </aside>

            {/* Main column */}
            <div className="flex min-w-0 flex-1 flex-col">
                <header className="glass sticky top-0 z-30 flex h-16 items-center gap-2 border-b border-border px-4 sm:px-6">
                    <button className="lg:hidden" onClick={() => setSidebarOpen(true)}>
                        <Menu className="h-6 w-6 text-muted-foreground" />
                    </button>

                    <div className="ml-auto flex items-center gap-1.5 sm:gap-2">
                        <button
                            onClick={toggleDark}
                            title={dark ? 'Switch to light mode' : 'Switch to dark mode'}
                            className="flex h-9 w-9 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                        >
                            {dark ? <Sun className="h-[18px] w-[18px]" /> : <Moon className="h-[18px] w-[18px]" />}
                        </button>

                        <button className="relative flex h-9 w-9 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground" title="Notifications">
                            <Bell className="h-[18px] w-[18px]" />
                            {notifications_count > 0 && (
                                <span className="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-bold text-white ring-2 ring-card">
                                    {notifications_count > 9 ? '9+' : notifications_count}
                                </span>
                            )}
                        </button>

                        <div className="relative">
                            <button
                                onClick={() => setMenuOpen(v => !v)}
                                className="flex items-center gap-2 rounded-full py-1 pl-1 pr-2 transition-colors hover:bg-secondary"
                            >
                                <span className={cn('flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br text-[11px] font-bold text-white', avatarGradient(user?.name))}>
                                    {getInitials(user?.name)}
                                </span>
                                <span className="hidden text-sm font-medium text-foreground sm:inline">{user?.name?.split(' ')[0]}</span>
                                <ChevronDown className="h-4 w-4 text-muted-foreground" />
                            </button>

                            {menuOpen && (
                                <>
                                    <div className="fixed inset-0 z-10" onClick={() => setMenuOpen(false)} />
                                    <div className="absolute right-0 top-11 z-20 w-60 overflow-hidden rounded-xl border border-border bg-card py-1 shadow-xl ring-1 ring-black/5 dark:ring-white/10">
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

                <main key={currentUrl.split('?')[0]} className="animate-fade-in flex-1">
                    {children}
                </main>

                <footer className="border-t border-border py-4 text-center text-xs text-muted-foreground">
                    QuakeLogic Enterprise — © {new Date().getFullYear()}{' '}
                    <a href="https://quakelogic.net" target="_blank" rel="noreferrer" className="font-medium text-primary hover:underline">QuakeLogic Inc.</a>
                </footer>
            </div>
        </div>
    );
}
