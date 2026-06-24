import { useEffect, useRef, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Package, Layers, UploadCloud, RefreshCw, MapPin, Link2, ArrowUp, ArrowDown, X, Search, Download, Trash2, Loader2 } from 'lucide-react';
import { ShipmentsLayout } from '@/Components/layout/ShipmentsLayout';
import { Pill } from '@/Components/ui/Pill';
import { Select } from '@/Components/ui/Select';
import { Checkbox } from '@/Components/ui/Checkbox';
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
    label_created_at: string | null;
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
    filters: { status: string | null; filter: string | null; scope: string | null; carrier: string | null; q: string | null; sort: string; dir: string };
    carrierOptions: { value: string; label: string }[];
    reassignCarrierOptions: { value: string; label: string }[];
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

export default function MailingsIndex({ mailings, filters, carrierOptions, reassignCarrierOptions }: Props) {
    const [updating, setUpdating] = useState(false);
    const [matching, setMatching] = useState(false);
    const [navigating, setNavigating] = useState(false);
    const [bulkBusy, setBulkBusy] = useState(false);
    const [selected, setSelected] = useState<string[]>([]);
    // Local search box, initialised once from the server. We deliberately don't
    // re-sync from props while typing so an in-flight result can't clobber newer
    // keystrokes; explicit clears reset it below.
    const [search, setSearch] = useState(filters.q ?? '');
    const searchRef = useRef<HTMLInputElement>(null);

    // Navigate while preserving the other query params.
    const go = (changes: Record<string, string | undefined>) => {
        const params: Record<string, string> = {};
        if (filters.status) params.status = filters.status;
        if (filters.filter) params.filter = filters.filter;
        if (filters.scope) params.scope = filters.scope;
        if (filters.carrier) params.carrier = filters.carrier;
        if (filters.q) params.q = filters.q;
        if (filters.sort && filters.sort !== 'recent') params.sort = filters.sort;
        if (filters.dir && filters.dir !== 'desc') params.dir = filters.dir;
        for (const [k, v] of Object.entries(changes)) {
            if (v === undefined || v === '') delete params[k];
            else params[k] = v;
        }
        router.get('/shipments/mailings', params, {
            preserveScroll: true,
            preserveState: true,
            onStart: () => setNavigating(true),
            onFinish: () => setNavigating(false),
        });
    };

    // Debounce the search box → URL (300ms). Skips when unchanged from the server.
    useEffect(() => {
        if (search === (filters.q ?? '')) return;
        const id = window.setTimeout(() => go({ q: search || undefined }), 300);
        return () => window.clearTimeout(id);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    // "/" focuses the search box (unless already typing in a field).
    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            const tag = (e.target as HTMLElement)?.tagName;
            if (e.key === '/' && tag !== 'INPUT' && tag !== 'TEXTAREA' && tag !== 'SELECT') {
                e.preventDefault();
                searchRef.current?.focus();
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);

    // Status pills and the metric filter are alternative views — picking a status clears the metric filter.
    const setStatus = (status: string) => go({ status: status || undefined, filter: undefined });
    const setCarrier = (carrier: string) => go({ carrier: carrier || undefined });
    const setSort = (sort: string) => go({ sort: sort === 'recent' ? undefined : sort });
    const toggleDir = () => go({ dir: filters.dir === 'asc' ? undefined : 'asc' });
    const carrierLabel = (value: string) => carrierOptions.find(o => o.value === value)?.label ?? value;
    const clearSearch = () => setSearch('');
    const clearAll = () => { setSearch(''); go({ filter: undefined, scope: undefined, carrier: undefined, status: undefined, q: undefined }); };

    // Click a column header to sort by it; click again to flip direction.
    const sortBy = (key: string) => (filters.sort === key ? toggleDir() : go({ sort: key === 'recent' ? undefined : key, dir: undefined }));

    const updateAll = () => {
        setUpdating(true);
        router.post('/shipments/mailings/refresh-all', {}, { preserveScroll: true, onFinish: () => setUpdating(false) });
    };

    const matchProposals = () => {
        setMatching(true);
        router.post('/shipments/mailings/match-proposals', {}, { preserveScroll: true, onFinish: () => setMatching(false) });
    };

    // ── Bulk selection ──────────────────────────────────────────────────────
    const pageUlids = mailings.data.map(m => m.ulid);
    const allSelected = pageUlids.length > 0 && pageUlids.every(u => selected.includes(u));
    const someSelected = pageUlids.some(u => selected.includes(u));
    const toggleRow = (ulid: string) => setSelected(s => (s.includes(ulid) ? s.filter(u => u !== ulid) : [...s, ulid]));
    const toggleAll = () => setSelected(allSelected ? [] : pageUlids);

    const bulk = (url: string, extra: Record<string, unknown> = {}) => {
        setBulkBusy(true);
        router.post(url, { ulids: selected, ...extra }, {
            preserveScroll: true,
            onSuccess: () => setSelected([]),
            onFinish: () => setBulkBusy(false),
        });
    };
    const bulkRefresh = () => bulk('/shipments/mailings/bulk-refresh');
    const bulkReassign = (carrier: string) => { if (carrier) bulk('/shipments/mailings/bulk-reassign', { carrier }); };
    const bulkDelete = () => { if (confirm(`Delete ${selected.length} shipment(s)? This can't be undone from here.`)) bulk('/shipments/mailings/bulk-delete'); };

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

    // Build the export URL from the current filters (a plain download, not Inertia).
    const exportHref = () => {
        const p = new URLSearchParams();
        if (filters.status) p.set('status', filters.status);
        if (filters.filter) p.set('filter', filters.filter);
        if (filters.scope) p.set('scope', filters.scope);
        if (filters.carrier) p.set('carrier', filters.carrier);
        if (filters.q) p.set('q', filters.q);
        if (filters.sort && filters.sort !== 'recent') p.set('sort', filters.sort);
        if (filters.dir && filters.dir !== 'desc') p.set('dir', filters.dir);
        const qs = p.toString();
        return `/shipments/mailings/export${qs ? `?${qs}` : ''}`;
    };

    const SortHeader = ({ label, sortKey, className = '' }: { label: string; sortKey: string; className?: string }) => {
        const active = filters.sort === sortKey;
        return (
            <th className={className}>
                <button onClick={() => sortBy(sortKey)} className={`inline-flex items-center gap-1 font-semibold uppercase tracking-wider transition-colors hover:text-foreground ${active ? 'text-foreground' : ''}`}>
                    {label}
                    {active && (filters.dir === 'asc' ? <ArrowUp className="h-3 w-3" /> : <ArrowDown className="h-3 w-3" />)}
                </button>
            </th>
        );
    };

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
                        <a href={exportHref()} title="Download the current list as CSV" className="inline-flex items-center gap-2 rounded-full border border-border px-4 py-2 text-sm font-medium text-foreground transition hover:bg-secondary">
                            <Download className="h-4 w-4" /> Export
                        </a>
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
                    <div className="flex flex-wrap items-center gap-1.5">
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
                    <div className="flex flex-wrap items-center gap-2">
                        <div className="relative">
                            <Search className="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <input
                                ref={searchRef}
                                value={search}
                                onChange={e => setSearch(e.target.value)}
                                placeholder="Search (press /)"
                                aria-label="Search shipments by tracking number, recipient, or proposal"
                                className="h-8 w-52 rounded-lg border border-input bg-card pl-8 pr-7 text-xs text-foreground transition-colors placeholder:text-muted-foreground hover:border-muted-foreground/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/50"
                            />
                            {search && (
                                <button onClick={clearSearch} title="Clear search" className="absolute right-1.5 top-1/2 -translate-y-1/2 text-muted-foreground transition-colors hover:text-foreground">
                                    <X className="h-3.5 w-3.5" />
                                </button>
                            )}
                        </div>
                        {carrierOptions.length > 0 && (
                            <>
                                <span className="text-xs font-medium text-muted-foreground">Carrier</span>
                                <Select
                                    value={filters.carrier ?? ''}
                                    onChange={setCarrier}
                                    options={[{ value: '', label: 'All carriers' }, ...carrierOptions]}
                                    size="sm"
                                    className="min-w-[9.5rem]"
                                />
                            </>
                        )}
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

                {(filters.filter || filters.scope || filters.carrier || filters.q) && (
                    <div className="mb-4 flex flex-wrap items-center gap-2">
                        <span className="text-xs font-medium text-muted-foreground">Showing</span>
                        {filters.q && (
                            <button onClick={clearSearch} className="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary transition hover:bg-primary/20">
                                “{filters.q}” <X className="h-3 w-3" />
                            </button>
                        )}
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
                        {filters.carrier && (
                            <button onClick={() => go({ carrier: undefined })} className="inline-flex items-center gap-1.5 rounded-full bg-secondary px-3 py-1 text-xs font-medium text-foreground transition hover:bg-secondary/70">
                                {carrierLabel(filters.carrier)} <X className="h-3 w-3" />
                            </button>
                        )}
                        <button onClick={clearAll} className="text-xs font-medium text-muted-foreground hover:text-foreground">
                            Clear all
                        </button>
                    </div>
                )}

                {selected.length > 0 && (
                    <div className="mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-primary/30 bg-primary/5 px-4 py-2.5">
                        <span className="text-sm font-medium text-foreground">{selected.length} selected</span>
                        <button onClick={bulkRefresh} disabled={bulkBusy} className="inline-flex items-center gap-1.5 rounded-full border border-border bg-card px-3 py-1.5 text-xs font-medium text-foreground transition hover:bg-secondary disabled:opacity-60">
                            <RefreshCw className={`h-3.5 w-3.5 ${bulkBusy ? 'animate-spin' : ''}`} /> Refresh
                        </button>
                        {reassignCarrierOptions.length > 0 && (
                            <div className="flex items-center gap-1.5">
                                <span className="text-xs text-muted-foreground">Reassign to</span>
                                <Select value="" onChange={bulkReassign} options={reassignCarrierOptions} placeholder="Carrier…" size="sm" className="min-w-[9rem]" />
                            </div>
                        )}
                        <button onClick={bulkDelete} disabled={bulkBusy} className="inline-flex items-center gap-1.5 rounded-full border border-destructive/30 bg-card px-3 py-1.5 text-xs font-medium text-destructive transition hover:bg-destructive/10 disabled:opacity-60">
                            <Trash2 className="h-3.5 w-3.5" /> Delete
                        </button>
                        <button onClick={() => setSelected([])} className="ml-auto text-xs font-medium text-muted-foreground hover:text-foreground">
                            Clear selection
                        </button>
                    </div>
                )}

                <div className="card-surface relative overflow-hidden">
                    {navigating && (
                        <div className="absolute inset-0 z-10 flex items-start justify-center bg-card/40 pt-20">
                            <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
                        </div>
                    )}
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
                                        <th className="w-10 px-4 py-3">
                                            <Checkbox
                                                checked={allSelected}
                                                indeterminate={someSelected && !allSelected}
                                                onChange={toggleAll}
                                                ariaLabel="Select all on this page"
                                            />
                                        </th>
                                        <th className="px-4 py-3 font-semibold">Recipient / Tracking</th>
                                        <th className="hidden px-4 py-3 font-semibold sm:table-cell">Category</th>
                                        <SortHeader label="Status" sortKey="status" className="px-4 py-3" />
                                        <th className="px-4 py-3 font-semibold">On-time</th>
                                        <th className="hidden px-4 py-3 font-semibold md:table-cell">Proposal</th>
                                        <SortHeader label="Label created" sortKey="label_created" className="hidden px-4 py-3 lg:table-cell" />
                                        <SortHeader label="Deadline" sortKey="deadline" className="px-4 py-3 text-right" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {mailings.data.map(m => (
                                        <tr
                                            key={m.ulid}
                                            onClick={() => router.get(`/shipments/mailings/${m.ulid}`)}
                                            className={`cursor-pointer border-b border-border transition-colors last:border-0 hover:bg-secondary ${selected.includes(m.ulid) ? 'bg-primary/5' : ''}`}
                                        >
                                            <td className="px-4 py-3" onClick={e => e.stopPropagation()}>
                                                <Checkbox
                                                    checked={selected.includes(m.ulid)}
                                                    onChange={() => toggleRow(m.ulid)}
                                                    ariaLabel={`Select ${m.ups_tracking_number}`}
                                                />
                                            </td>
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
                                            <td className="hidden px-4 py-3 text-muted-foreground lg:table-cell">{formatDate(m.label_created_at)}</td>
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
