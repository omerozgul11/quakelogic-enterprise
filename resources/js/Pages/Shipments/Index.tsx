import { Head, Link, router } from '@inertiajs/react';
import { Truck, AlertTriangle, Clock, CheckCircle2, Package, Plus } from 'lucide-react';
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
}

interface Props {
    stats: { active: number; at_risk: number; delivered_late: number; delivered_on_time: number; on_time_rate: number | null };
    recent: RecentMailing[];
    scope: string | null;
    scopeCounts: { all: number; domestic: number; international: number };
}

const TILES = [
    { key: 'active', label: 'Active mailings', icon: Truck, tone: 'text-blue-600' },
    { key: 'at_risk', label: 'At risk of late', icon: AlertTriangle, tone: 'text-amber-600' },
    { key: 'delivered_late', label: 'Delivered late', icon: Clock, tone: 'text-red-600' },
    { key: 'delivered_on_time', label: 'Delivered on time', icon: CheckCircle2, tone: 'text-emerald-600' },
] as const;

export default function ShipmentsDashboard({ stats, recent, scope, scopeCounts }: Props) {
    const setScope = (s: string | null) =>
        router.get('/shipments', s ? { scope: s } : {}, { preserveScroll: true, preserveState: true });

    const tabs = [
        { key: null, label: 'All', count: scopeCounts.all },
        { key: 'domestic', label: 'Domestic', count: scopeCounts.domestic },
        { key: 'international', label: 'International', count: scopeCounts.international },
    ];

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
                    <Link href="/shipments/mailings/create" className="bg-brand-gradient shadow-glow inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5">
                        <Plus className="h-4 w-4" /> New mailing
                    </Link>
                </div>

                <div className="mb-5 inline-flex rounded-full border border-border bg-card p-1">
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

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {TILES.map(tile => {
                        const Icon = tile.icon;
                        return (
                            <div key={tile.key} className="rounded-xl border border-border bg-card p-5 shadow-soft">
                                <div className="flex items-center justify-between">
                                    <p className="text-sm font-medium text-muted-foreground">{tile.label}</p>
                                    <Icon className={`h-5 w-5 ${tile.tone}`} />
                                </div>
                                <p className="mt-3 text-3xl font-bold text-foreground">{stats[tile.key]}</p>
                            </div>
                        );
                    })}
                </div>

                <div className="mt-8">
                    <div className="mb-3 flex items-center justify-between">
                        <h2 className="text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Recent mailings</h2>
                        <Link href="/shipments/mailings" className="text-sm font-medium text-primary hover:underline">View all</Link>
                    </div>

                    {recent.length === 0 ? (
                        <div className="rounded-xl border border-dashed border-border bg-card/50 px-6 py-16 text-center">
                            <div className="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-secondary">
                                <Package className="h-7 w-7 text-muted-foreground" />
                            </div>
                            <h3 className="text-lg font-semibold text-foreground">No mailings yet</h3>
                            <p className="mx-auto mt-1.5 max-w-md text-sm text-muted-foreground">
                                Create a mailing from a mailed proposal to track its UPS shipment against the deadline.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-hidden rounded-xl border border-border bg-card shadow-soft">
                            {recent.map(m => (
                                <Link key={m.ulid} href={`/shipments/mailings/${m.ulid}`} className="flex items-center gap-4 border-b border-border px-4 py-3 transition-colors last:border-0 hover:bg-secondary">
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-sm font-medium text-foreground">{m.recipient_name ?? '—'}</span>
                                        <span className="block truncate font-mono text-xs text-muted-foreground">{m.ups_tracking_number}</span>
                                    </span>
                                    <span className="hidden sm:block"><Pill color={m.scope_color} label={m.scope_label} /></span>
                                    <Pill color={m.status_color} label={m.status_label} />
                                    <Pill color={m.risk_color} label={m.risk_label} />
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
