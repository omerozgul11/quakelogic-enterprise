import { useEffect, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Package, Layers, UploadCloud, RefreshCw, MapPin, Link2, ArrowUp, ArrowDown, X } from 'lucide-react';
import { ShipmentsLayout } from '@/Components/layout/ShipmentsLayout';
import { Pill } from '@/Components/ui/Pill';
import { Select } from '@/Components/ui/Select';
import { Pagination } from '@/Components/ui/Pagination';
import { formatDate } from '@/Lib/utils';

const SORT_OPTIONS = [
    { value: 'recent', label: 'Date added' },
    { value: 'status', label: 'Status' },
    { value: 'delivered', label: 'Delivery date' },
    { value: 'scheduled', label: 'Estimated delivery' },
    { value: 'label_created', label: 'Shipping date (label created)' },
    { value: 'deadline', label: 'Deadline' },
];

interface MailingRow {
    ulid: string;
    ups_tracking_number: string;
    recipient_name: string | null;
    scope_label: string;
    scope_color: string;
    status_label: string;
    status_color: string;
    risk_label: string;
    risk_color: string;
    current_location: string | null;
    deadline: string | null;
    proposal: { proposal_number: string | null; project_name: string } | null;
}

interface Props {
    mailings: {
        data: MailingRow[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: { status: string | null; filter: string | null; scope: string | null; sort: string; dir: string };
}

const FILTER_LABELS: Record<string, string> = {
    at_risk: 'At risk of late',
    delivered_late: 'Delivered late',
    delivered_on_time: 'Delivered on time',
};

const SCOPE_LABELS: Record<string, string> = { domestic: 'Domestic', international: 'International' };

const STATUS_FILTERS = [
    { value: '', label: 'All' },
    { value: 'label_created', label: 'Label created' },
    { value: 'in_transit', label: 'In transit' },
    { value: 'out_for_delivery', label: 'Out for delivery' },
    { value: 'delivered', label: 'Delivered' },
    { value: 'exception', label: 'Exception' },
];

export default function MailingsIndex({ mailings, filters }: Props) {
    const [updating, setUpdating] = useState(false);
    const [matching, setMatching] = useState(false);

    // Navigate while preserving the other query params (status + filter + scope + sort + dir).
    const go = (changes: Record<string, string | undefined>) => {
        const params: Record<string, string> = {};
        if (filters.status) params.status = filters.status;
        if (filters.filter) params.filter = filters.filter;
        if (filters.scope) params.scope = filters.scope;
        if (filters.sort && filters.sort !== 'recent') params.sort = filters.sort;
        if (filters.dir && filters.dir !== 'desc') params.dir = filters.dir;
        for (const [k, v] of Object.entries(changes)) {
            if (v === undefined || v === '') delete params[k];
            else params[k] = v;
        }
        router.get('/shipments/mailings', params, { preserveScroll: true, preserveState: true });
    };

    // Status pills and the metric filter are alternative views — picking a status clears the metric filter.
    const setStatus = (status: string) => go({ status: status || undefined, filter: undefined });
    const setSort = (sort: string) => go({ sort: sort === 'recent' ? undefined : sort });
    const toggleDir = () => go({ dir: filters.dir === 'asc' ? undefined : 'asc' });

    const updateAll = () => {
        setUpdating(true);
        router.post('/shipments/mailings/refresh-all', {}, { preserveScroll: true, onFinish: () => setUpdating(false) });
    };

    const matchProposals = () => {
        setMatching(true);
        router.post('/shipments/mailings/match-proposals', {}, { preserveScroll: true, onFinish: () => setMatching(false) });
    };

    // Auto-refresh the list every 5 minutes (the server polls UPS on the same
    // cadence). Keeps the current page/filter/sort via the preserved URL.
    useEffect(() => {
        const id = window.setInterval(() => {
            if (document.visibilityState === 'visible') {
                router.reload({ only: ['mailings'] });
            }
        }, 5 * 60 * 1000);
        return () => window.clearInterval(id);
    }, []);

    return (
        <ShipmentsLayout>
            <Head title="Shipments" />
            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6">
                <div className="mb-6 flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-foreground">Shipments</h1>
                        <p className="mt-1 text-sm text-muted-foreground">{mailings.total} total</p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <button onClick={updateAll} disabled={updating} className="inline-flex items-center gap-2 rounded-full border border-border px-4 py-2 text-sm font-medium text-foreground transition hover:bg-secondary disabled:opacity-60">
                            <RefreshCw className={`h-4 w-4 ${updating ? 'animate-spin' : ''}`} /> {updating ? 'Updating…' : 'Update all'}
                        </button>
                        <button onClick={matchProposals} disabled={matching} title="Auto-link unlinked shipments to matching proposals" className="inline-flex items-center gap-2 rounded-full border border-border px-4 py-2 text-sm font-medium text-foreground transition hover:bg-secondary disabled:opacity-60">
                            <Link2 className={`h-4 w-4 ${matching ? 'animate-pulse' : ''}`} /> {matching ? 'Matching…' : 'Match proposals'}
                        </button>
                        <Link href="/shipments/mailings/import" className="inline-flex items-center gap-2 rounded-full border border-border px-4 py-2 text-sm font-medium text-foreground transition hover:bg-secondary">
                            <UploadCloud className="h-4 w-4" /> Import
                        </Link>
                        <Link href="/shipments/mailings/bulk" className="inline-flex items-center gap-2 rounded-full border border-border px-4 py-2 text-sm font-medium text-foreground transition hover:bg-secondary">
                            <Layers className="h-4 w-4" /> Bulk add
                        </Link>
                        <Link href="/shipments/mailings/create" className="bg-brand-gradient shadow-glow inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5">
                            <Plus className="h-4 w-4" /> New shipment
                        </Link>
                    </div>
                </div>

                <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div className="flex flex-wrap gap-1.5">
                        {STATUS_FILTERS.map(f => (
                            <button
                                key={f.value}
                                onClick={() => setStatus(f.value)}
                                className={`rounded-full px-3 py-1 text-xs font-medium transition-colors ${
                                    (filters.status ?? '') === f.value
                                        ? 'bg-brand-gradient text-white'
                                        : 'bg-secondary text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                {f.label}
                            </button>
                        ))}
                    </div>
                    <div className="flex items-center gap-2">
                        <span className="text-xs font-medium text-muted-foreground">Sort by</span>
                        <Select value={filters.sort} onChange={setSort} options={SORT_OPTIONS} size="sm" className="min-w-[11rem]" />
                        <button
                            onClick={toggleDir}
                            title={filters.dir === 'asc' ? 'Ascending — click for descending' : 'Descending — click for ascending'}
                            className="flex h-8 w-8 items-center justify-center rounded-lg border border-border text-muted-foreground transition hover:bg-secondary hover:text-foreground"
                        >
                            {filters.dir === 'asc' ? <ArrowUp className="h-4 w-4" /> : <ArrowDown className="h-4 w-4" />}
                        </button>
                    </div>
                </div>

                {(filters.filter || filters.scope) && (
                    <div className="mb-4 flex flex-wrap items-center gap-2">
                        <span className="text-xs font-medium text-muted-foreground">Showing</span>
                        {filters.filter && (
                            <button onClick={() => go({ filter: undefined })} className="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary transition hover:bg-primary/20">
                                {FILTER_LABELS[filters.filter] ?? filters.filter} <X className="h-3 w-3" />
                            </button>
                        )}
                        {filters.scope && (
                            <button onClick={() => go({ scope: undefined })} className="inline-flex items-center gap-1.5 rounded-full bg-secondary px-3 py-1 text-xs font-medium text-foreground transition hover:bg-secondary/70">
                                {SCOPE_LABELS[filters.scope] ?? filters.scope} <X className="h-3 w-3" />
                            </button>
                        )}
                        <button onClick={() => go({ filter: undefined, scope: undefined, status: undefined })} className="text-xs font-medium text-muted-foreground hover:text-foreground">
                            Clear all
                        </button>
                    </div>
                )}

                <div className="card-surface overflow-hidden">
                    {mailings.data.length === 0 ? (
                        <div className="px-6 py-16 text-center">
                            <div className="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-secondary">
                                <Package className="h-7 w-7 text-muted-foreground" />
                            </div>
                            <p className="text-sm text-muted-foreground">No shipments match this filter.</p>
                        </div>
                    ) : (
                        <>
                            <table className="w-full text-sm">
                                <thead className="border-b border-border bg-secondary/50 text-left text-xs uppercase tracking-wider text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-3 font-semibold">Recipient / Tracking</th>
                                        <th className="hidden px-4 py-3 font-semibold sm:table-cell">Category</th>
                                        <th className="px-4 py-3 font-semibold">Status</th>
                                        <th className="px-4 py-3 font-semibold">On-time</th>
                                        <th className="hidden px-4 py-3 font-semibold md:table-cell">Proposal</th>
                                        <th className="px-4 py-3 text-right font-semibold">Deadline</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {mailings.data.map(m => (
                                        <tr
                                            key={m.ulid}
                                            onClick={() => router.get(`/shipments/mailings/${m.ulid}`)}
                                            className="cursor-pointer border-b border-border transition-colors last:border-0 hover:bg-secondary"
                                        >
                                            <td className="px-4 py-3">
                                                <div className="font-medium text-foreground">{m.recipient_name ?? '—'}</div>
                                                <div className="font-mono text-xs text-muted-foreground">{m.ups_tracking_number}</div>
                                                {m.current_location && (
                                                    <div className="mt-0.5 inline-flex items-center gap-1 text-xs text-muted-foreground/80">
                                                        <MapPin className="h-3 w-3" /> {m.current_location}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="hidden px-4 py-3 sm:table-cell"><Pill color={m.scope_color} label={m.scope_label} /></td>
                                            <td className="px-4 py-3"><Pill color={m.status_color} label={m.status_label} /></td>
                                            <td className="px-4 py-3"><Pill color={m.risk_color} label={m.risk_label} /></td>
                                            <td className="hidden px-4 py-3 text-muted-foreground md:table-cell">
                                                {m.proposal ? (m.proposal.proposal_number ?? m.proposal.project_name) : '—'}
                                            </td>
                                            <td className="px-4 py-3 text-right text-muted-foreground">{formatDate(m.deadline)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            <Pagination from={mailings.from} to={mailings.to} total={mailings.total} links={mailings.links} />
                        </>
                    )}
                </div>
            </div>
        </ShipmentsLayout>
    );
}
