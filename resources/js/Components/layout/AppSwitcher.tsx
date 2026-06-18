import { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { SharedProps } from '@/Types';
import { cn } from '@/Lib/utils';
import { Logo } from '@/Components/ui/Logo';
import { ChevronDown, LayoutDashboard, FileText, Truck, LayoutGrid, ExternalLink, FolderKanban, ContactRound, Boxes, ShoppingCart, Factory, Cpu } from 'lucide-react';

const ICONS: Record<string, React.ComponentType<{ className?: string }>> = {
    'file-text': FileText,
    truck: Truck,
    'folder-kanban': FolderKanban,
    'contact-round': ContactRound,
    'layout-dashboard': LayoutDashboard,
    boxes: Boxes,
    'shopping-cart': ShoppingCart,
    factory: Factory,
    cpu: Cpu,
};

/**
 * Top-left launcher — the hub for every QuakeLogic product. The current app's
 * entries link internally via Inertia; other apps are separate deployments
 * reached by their own URL (a same-tab redirect).
 */
export function AppSwitcher({ onNavigate }: { onNavigate?: () => void }) {
    const pageData = usePage<SharedProps>();
    const { app } = pageData.props;
    const path = pageData.url.split('?')[0];
    const [open, setOpen] = useState(false);

    const apps = app.switcher ?? [];

    // Internal sections of this app have a relative ('/') url. The active one is
    // the longest such url that prefixes the current path (so /shipments wins
    // over / on /shipments/mailings).
    const isInternal = (url: string) => url.startsWith('/');
    const matchesPath = (url: string) => url === '/' || path === url || path.startsWith(url + '/');
    const activeInternalUrl = apps
        .filter(a => isInternal(a.url) && matchesPath(a.url))
        .reduce((best, a) => (a.url.length > best.length ? a.url : best), '');
    // The logo subtitle reflects the section you're in (e.g. "Shipments" on /shipments).
    const activeApp = apps.find(a => isInternal(a.url) && a.url === activeInternalUrl);
    const logoSubtitle = activeApp?.name ?? 'Proposals';
    const close = () => {
        setOpen(false);
        onNavigate?.();
    };

    return (
        <div className="relative">
            <button
                onClick={() => setOpen(v => !v)}
                title="Switch app"
                className="flex items-center gap-1.5 rounded-lg px-1.5 py-1 transition-colors hover:bg-secondary"
            >
                <Logo subtitle={logoSubtitle} />
                <ChevronDown className={cn('h-4 w-4 text-muted-foreground transition-transform', open && 'rotate-180')} />
            </button>

            {open && (
                <>
                    <div className="fixed inset-0 z-30" onClick={() => setOpen(false)} />
                    <div className="animate-dropdown origin-top-left absolute left-0 top-full z-40 mt-2 w-64 overflow-hidden rounded-xl border border-border bg-card py-1 shadow-xl ring-1 ring-black/5 dark:ring-white/10">
                        <Link
                            href="/"
                            onClick={close}
                            className="flex items-center gap-3 px-3 py-2 text-sm transition-colors hover:bg-secondary"
                        >
                            <LayoutDashboard className="h-[18px] w-[18px] text-muted-foreground" />
                            <span className="font-medium text-foreground">Dashboard</span>
                        </Link>

                        <div className="my-1 border-t border-border" />
                        <p className="px-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-muted-foreground/70">
                            Switch app
                        </p>

                        {apps.map(item => {
                            const Icon = ICONS[item.icon] ?? LayoutGrid;
                            const internal = isInternal(item.url);
                            const current = internal && item.url === activeInternalUrl;
                            const inner = (
                                <>
                                    <span className={cn(
                                        'flex h-9 w-9 shrink-0 items-center justify-center rounded-lg',
                                        current ? 'bg-brand-gradient text-white' : 'bg-secondary text-muted-foreground',
                                    )}>
                                        <Icon className="h-[18px] w-[18px]" />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="flex items-center gap-1.5">
                                            <span className="truncate text-sm font-medium text-foreground">{item.name}</span>
                                            {current ? (
                                                <span className="rounded-full bg-primary/10 px-1.5 py-0.5 text-[10px] font-semibold text-primary">Current</span>
                                            ) : !internal ? (
                                                <ExternalLink className="h-3 w-3 shrink-0 text-muted-foreground" />
                                            ) : null}
                                        </span>
                                        <span className="block truncate text-xs text-muted-foreground">{item.description}</span>
                                    </span>
                                </>
                            );
                            const className = 'flex w-full items-center gap-3 px-3 py-2 text-left transition-colors hover:bg-secondary';
                            return internal ? (
                                <Link key={item.key} href={item.url} onClick={close} className={className}>
                                    {inner}
                                </Link>
                            ) : (
                                <a key={item.key} href={item.url} onClick={close} className={className}>
                                    {inner}
                                </a>
                            );
                        })}
                    </div>
                </>
            )}
        </div>
    );
}
