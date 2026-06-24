import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Link, usePage } from '@inertiajs/react';
import { SharedProps } from '@/Types';
import { cn } from '@/Lib/utils';
import { Logo } from '@/Components/ui/Logo';
import { ChevronDown, LayoutDashboard, FileText, Truck, LayoutGrid, ExternalLink, FolderKanban, ContactRound, Boxes, ShoppingCart, Factory, Cpu, BadgeCheck, LifeBuoy, Landmark, Receipt } from 'lucide-react';

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
    'badge-check': BadgeCheck,
    'life-buoy': LifeBuoy,
    landmark: Landmark,
    receipt: Receipt,
};

/**
 * Top-left launcher — the hub for every QuakeLogic product. The current app's
 * entries link internally via Inertia; other apps are separate deployments
 * reached by their own URL (a same-tab redirect).
 */
export function AppSwitcher({ onNavigate, activeKey }: { onNavigate?: () => void; activeKey?: string }) {
    const pageData = usePage<SharedProps>();
    const { app } = pageData.props;
    const path = pageData.url.split('?')[0];
    const [open, setOpen] = useState(false);
    const [pos, setPos] = useState<{ top: number; left: number } | null>(null);
    const wrapRef = useRef<HTMLDivElement>(null);
    const menuRef = useRef<HTMLDivElement>(null);

    // Anchor the portaled menu just below the launcher button.
    const place = () => {
        const rect = wrapRef.current?.getBoundingClientRect();
        if (rect) setPos({ top: rect.bottom + 8, left: rect.left });
    };

    const toggle = () => {
        if (open) { setOpen(false); return; }
        place();
        setOpen(true);
    };

    // The menu is portaled to <body> (so it escapes the sidebar's stacking
    // context and paints above all page content). That means outside-click
    // detection must allow clicks inside EITHER the button or the portaled menu;
    // scroll/resize re-anchor it. Escape closes.
    useEffect(() => {
        if (!open) return;
        const onDown = (e: MouseEvent) => {
            const t = e.target as Node;
            if (wrapRef.current?.contains(t) || menuRef.current?.contains(t)) return;
            setOpen(false);
        };
        const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') setOpen(false); };
        const onScroll = (e: Event) => {
            if (menuRef.current && e.target instanceof Node && menuRef.current.contains(e.target)) return;
            place();
        };
        const onResize = () => place();
        document.addEventListener('mousedown', onDown);
        document.addEventListener('keydown', onKey);
        document.addEventListener('scroll', onScroll, true);
        window.addEventListener('resize', onResize);
        return () => {
            document.removeEventListener('mousedown', onDown);
            document.removeEventListener('keydown', onKey);
            document.removeEventListener('scroll', onScroll, true);
            window.removeEventListener('resize', onResize);
        };
    }, [open]);

    const apps = app.switcher ?? [];

    // Internal sections of this app have a relative ('/') url. The active one is
    // the longest such url that prefixes the current path (so /shipments wins
    // over / on /shipments/mailings).
    const isInternal = (url: string) => url.startsWith('/');
    const matchesPath = (url: string) => url === '/' || path === url || path.startsWith(url + '/');
    const detectedUrl = apps
        .filter(a => isInternal(a.url) && matchesPath(a.url))
        .reduce((best, a) => (a.url.length > best.length ? a.url : best), '');
    // The active app is normally detected from the path, but a layout can declare
    // it explicitly via `activeKey` — e.g. the CRM shell hosts /finance, /inventory,
    // … which have no switcher entry of their own but still belong to "CRM".
    const resolvedActiveKey = activeKey ?? apps.find(a => isInternal(a.url) && a.url === detectedUrl)?.key;
    // The logo subtitle reflects the section you're in (e.g. "CRM", "Shipments").
    // The Proposals home shows no subtitle — just the "QuakeLogic" wordmark.
    const activeApp = apps.find(a => a.key === resolvedActiveKey);
    const logoSubtitle = activeApp && activeApp.key !== 'proposals' ? activeApp.name : undefined;
    const close = () => {
        setOpen(false);
        onNavigate?.();
    };

    return (
        <div ref={wrapRef} className="relative">
            <button
                onClick={toggle}
                title="Switch app"
                className="flex items-center gap-1.5 rounded-lg px-1.5 py-1 transition-colors hover:bg-secondary"
            >
                <Logo subtitle={logoSubtitle} />
                <ChevronDown className={cn('h-4 w-4 text-muted-foreground transition-transform', open && 'rotate-180')} />
            </button>

            {open && pos && createPortal(
                <div
                    ref={menuRef}
                    style={{ top: pos.top, left: pos.left }}
                    className="animate-dropdown origin-top-left fixed z-[140] w-64 overflow-hidden rounded-xl border border-border bg-card py-1 shadow-xl ring-1 ring-black/5 dark:ring-white/10">
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
                            const current = item.key === resolvedActiveKey;
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
                </div>,
                document.body,
            )}
        </div>
    );
}
