import { useEffect, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Truck, AlertTriangle, Clock, CheckCircle2, Package, Plus, UploadCloud, RefreshCw, MapPin, ArrowRight } from 'lucide-react';
import { ShipmentsLayout } from '@/Components/layout/ShipmentsLayout';
import { Pill } from '@/Components/ui/Pill';
import { formatDate } from '@/Lib/utils';

interface RecentMailing {
    ulid: string;
    ups_tracking_number: string;
    recipient_name: string | null;
    scope_label: string;
    scope_color: string;
    status_label: string;
    status_color: string;
    risk_label: string;
    risk_color: string;
    deadline: string | null;
    label_created_at: string | null;
}

interface ShipmentIssue {
    ulid: string;
    ups_tracking_number: string;
    recipient_name: string | null;
    issue_label: string;
    issue_color: string;
    status_label: string;
    current_location: string | null;
    deadline: string | null;
}

interface Props {
    stats: { active: number; at_risk: number; delivered_late: number; delivered_on_time: number; on_time_rate: number | null };
    recent: RecentMailing[];
    issues: ShipmentIssue[];
    scope: string | null;
    scopeCounts: { all: number; domestic: number; international: number };
}

const TILES = [
    { key: 'active', label: 'Active shipments', icon: Truck, tone: 'text-blue-600' },
    { key: 'at_risk', label: 'At risk of late', icon: AlertTriangle, tone: 'text-amber-600' },
    { key: 'delivered_late', label: 'Delivered late', icon: Clock, tone: 'text-red-600' },
    { key: 'delivered_on_time', label: 'Delivered on time', icon: CheckCircle2, tone: 'text-emerald-600' },
] as const;

export default function ShipmentsDashboard({ stats, recent, issues, scope, scopeCounts }: Props) {
    const [updating, setUpdating] = useState(false);
    const updateAll = () => {
        setUpdating(true);
        router.post('/shipments/mailings/refresh-all', {}, { preserveScroll: true, onFinish: () => setUpdating(false) });
    };

    // Keep the counts live: re-fetch whenever the user returns to this tab/window
    // (Inertia otherwise restores a cached page on back-navigation, so the numbers
    // could lag behind imports, status edits, or an "Update all").
    useEffect(() => {
        const refresh = () => {
            if (document.visibilityState === 'visible') {
                router.reload({ only: ['stats', 'scopeCounts', 'recent', 'issues'] });
            }
        };
        document.addEventListener('visibilitychange', refresh);
        window.addEventListener('focus', refresh);

        // Auto-refresh the data every 5 minutes (the server polls UPS on the same
        // cadence) so the dashboard stays current without a manual reload.
        const id = window.setInterval(() => {
            if (document.visibilityState === 'visible') {
                router.reload({ only: ['stats', 'scopeCounts', 'recent', 'issues'] });
            }
        }, 5 * 60 * 1000);

        return () => {
            document.removeEventListener('visibilitychange', refresh);
            window.removeEventListener('focus', refresh);
            window.clearInterval(id);
        };
    }, []);

    const setScope = (s: string | null) =>
        router.get('/shipments', s ? { scope: s } : {}, { preserveScroll: true, preserveState: true });

    const tabs = [
        { key: null, label: 'All', count: scopeCounts.all },
        { key: 'domestic', label: 'Domestic', count: scopeCounts.domestic },
        { key: 'international', label: 'International', count: scopeCounts.international },
    ];

    // Each tile drills into the shipments list with the matching filter (and the
    // current domestic/international scope), so the list mirrors the tile's count.
    const tileHref = (key: string) => {
        const p = new URLSearchParams();
        if (key === 'active') p.set('status', 'active');
        else p.set('filter', key);
        if (scope) p.set('scope', scope);
        return `/shipments/mailings?${p.toString()}`;
    };

    return (
        <ShipmentsLayout>
            <Head title="Shipments" />
            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6">
                <div className="mb-6 flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-foreground">Shipments</h1>
                        <p className="mt-1 flex items-center gap-2 text-sm text-muted-foreground">
                            Delivery tracking for mailed proposals.
                            {stats.on_time_rate !== null && (
                                <span className="font-semibold text-emerald-600">{stats.on_time_rate}% on-time</span>
                            )}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <button onClick={updateAll} disabled={updating} className="inline-flex items-center gap-2 rounded-full border border-border px-4 py-2 text-sm font-medium text-foreground transition hover:bg-secondary disabled:opacity-60">
                            <RefreshCw className={`h-4 w-4 ${updating ? 'animate-spin' : ''}`} /> {updating ? 'Updating…' : 'Update all'}
                        </button>
                        <Link href="/shipments/mailings/import" className="inline-flex items-center gap-2 rounded-full border border-border px-4 py-2 text-sm font-medium text-foreground transition hover:bg-secondary">
                            <UploadCloud className="h-4 w-4" /> Import
                        </Link>
                        <Link href="/shipments/mailings/create" className="bg-brand-gradient shadow-glow inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5">
                            <Plus className="h-4 w-4" /> New shipment
                        </Link>
                    </div>
                </div>

                <div className="mb-5 inline-flex rounded-full border border-border/60 bg-card/60 p-1 backdrop-blur-md">
                    {tabs.map(t => (
                        <button
                            key={t.label}
                            onClick={() => setScope(t.key)}
                            className={`rounded-full px-4 py-1.5 text-sm font-medium transition-colors ${
                                (scope ?? null) === t.key ? 'bg-brand-gradient text-white shadow-sm' : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            {t.label} <span className="ml-1 opacity-70">{t.count}</span>
                        </button>
                    ))}
                </div>

                <div className="stagger grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {TILES.map(tile => {
                        const Icon = tile.icon;
                        return (
                            <Link key={tile.key} href={tileHref(tile.key)} className="card-surface card-hover group block p-5 transition-transform duration-200 hover:-translate-y-0.5">
                                <div className="flex items-center justify-between">
                                    <p className="text-sm font-medium text-muted-foreground">{tile.label}</p>
                                    <Icon className={`h-5 w-5 ${tile.tone}`} />
                                </div>
                                <p className="mt-3 text-3xl font-bold text-foreground">{stats[tile.key]}</p>
                                <span className="mt-2 inline-flex items-center gap-1 text-xs font-medium text-primary opacity-0 transition-opacity group-hover:opacity-100">
                                    View <ArrowRight className="h-3 w-3" />
                                </span>
                            </Link>
                        );
                    })}
                </div>

                <div className="mt-8">
                    <div className="mb-3 flex items-center justify-between">
                        <h2 className="text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Recent shipments</h2>
                        <Link href="/shipments/mailings" className="text-sm font-medium text-primary hover:underline">View all</Link>
                    </div>

                    {recent.length === 0 ? (
                        <div className="card-surface border-dashed px-6 py-16 text-center">
                            <div className="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-secondary">
                                <Package className="h-7 w-7 text-muted-foreground" />
                            </div>
                            <h3 className="text-lg font-semibold text-foreground">No shipments yet</h3>
                            <p className="mx-auto mt-1.5 max-w-md text-sm text-muted-foreground">
                                Add a shipment, import your tracking numbers, or link one from a mailed proposal to track UPS delivery against the deadline.
                            </p>
                        </div>
                    ) : (
                        <div className="card-surface overflow-hidden">
                            {recent.map(m => (
                                <Link key={m.ulid} href={`/shipments/mailings/${m.ulid}`} className="flex items-center gap-4 border-b border-border px-4 py-3 transition-colors last:border-0 hover:bg-secondary">
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-sm font-medium text-foreground">{m.recipient_name ?? '—'}</span>
                                        <span className="block truncate font-mono text-xs text-muted-foreground">{m.ups_tracking_number}</span>
                                    </span>
                                    <span className="hidden sm:block"><Pill color={m.scope_color} label={m.scope_label} /></span>
                                    <Pill color={m.status_color} label={m.status_label} />
                                    <Pill color={m.risk_color} label={m.risk_label} />
                                    <span className="hidden w-24 text-right text-xs text-muted-foreground sm:block">
                                        <span className="block text-[10px] uppercase tracking-wider text-muted-foreground/60">Label created</span>
                                        {formatDate(m.label_created_at)}
                                    </span>
                                    <span className="hidden w-24 text-right text-xs text-muted-foreground sm:block">
                                        <span className="block text-[10px] uppercase tracking-wider text-muted-foreground/60">Deadline</span>
                                        {formatDate(m.deadline)}
                                    </span>
                                </Link>
                            ))}
                        </div>
                    )}
                </div>

                <div className="mt-8">
                    <h2 className="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70">
                        <AlertTriangle className="h-4 w-4 text-amber-500" /> Needs attention
                        {issues.length > 0 && <span className="text-amber-600">({issues.length})</span>}
                    </h2>

                    {issues.length === 0 ? (
                        <div className="card-surface flex items-center gap-3 px-5 py-4 text-sm text-muted-foreground">
                            <CheckCircle2 className="h-5 w-5 text-emerald-500" /> No issues — every shipment is on track.
                        </div>
                    ) : (
                        <div className="card-surface overflow-hidden">
                            {issues.map(m => (
                                <Link key={m.ulid} href={`/shipments/mailings/${m.ulid}`} className="flex items-center gap-4 border-b border-border px-4 py-3 transition-colors last:border-0 hover:bg-secondary">
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-sm font-medium text-foreground">{m.recipient_name ?? '—'}</span>
                                        <span className="block truncate font-mono text-xs text-muted-foreground">{m.ups_tracking_number}</span>
                                    </span>
                                    <Pill color={m.issue_color} label={m.issue_label} />
                                    {m.current_location && (
                                        <span className="hidden max-w-[12rem] items-center gap-1 truncate text-xs text-muted-foreground md:flex">
                                            <MapPin className="h-3 w-3 shrink-0" /> {m.current_location}
                                        </span>
                                    )}
                                    <span className="hidden w-24 text-right text-xs text-muted-foreground sm:block">{formatDate(m.deadline)}</span>
                                </Link>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </ShipmentsLayout>
    );
}
