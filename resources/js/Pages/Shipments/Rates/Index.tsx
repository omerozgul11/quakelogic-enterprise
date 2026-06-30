import { useEffect, useRef, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { BadgeDollarSign, Plus, Search, X, Pencil, Trash2, Info, ArrowRight, Paperclip } from 'lucide-react';
import { ShipmentsLayout } from '@/Components/layout/ShipmentsLayout';
import { Pill } from '@/Components/ui/Pill';
import { Select } from '@/Components/ui/Select';
import { Pagination } from '@/Components/ui/Pagination';
import { formatDate } from '@/Lib/utils';

interface QuoteRow {
    ulid: string;
    reference: string | null;
    carrier: string;
    carrier_label: string;
    service_line: string | null;
    service_line_label: string | null;
    status: string;
    status_label: string;
    status_color: string;
    origin: string;
    destination: string;
    amount: number | null;
    currency: string;
    transit_days: number | null;
    estimated_delivery: string | null;
    expires_at: string | null;
    is_expired: boolean;
    quote_reference: string | null;
    service_level: string | null;
    source: string;
    has_document: boolean;
    mailing: { ulid: string; tracking: string | null } | null;
    created_at: string | null;
}

interface Props {
    quotes: {
        data: QuoteRow[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: { q: string; carrier: string; status: string };
    carrierOptions: { value: string; label: string }[];
    statusOptions: { value: string; label: string }[];
    stats: { total: number; quoted: number };
}

function money(amount: number | null, currency: string): string {
    if (amount === null) return '—';
    try {
        return new Intl.NumberFormat(undefined, { style: 'currency', currency: currency || 'USD' }).format(amount);
    } catch {
        return `${currency} ${amount.toFixed(2)}`;
    }
}

export default function RatesIndex({ quotes, filters, carrierOptions, statusOptions, stats }: Props) {
    const [search, setSearch] = useState(filters.q ?? '');
    const first = useRef(true);

    const apply = (next: Partial<Props['filters']>) => {
        const params = { q: search, carrier: filters.carrier, status: filters.status, ...next };
        router.get('/shipments/rates', Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '')), {
            preserveState: true, preserveScroll: true, replace: true,
        });
    };

    // Debounce the free-text search; skip the first run and no-op changes.
    useEffect(() => {
        if (first.current) { first.current = false; return; }
        if (search === (filters.q ?? '')) return;
        const t = setTimeout(() => apply({ q: search }), 300);
        return () => clearTimeout(t);
    }, [search]); // eslint-disable-line react-hooks/exhaustive-deps

    const remove = (q: QuoteRow) => {
        if (!confirm(`Remove this rate request${q.reference ? ` (${q.reference})` : ''}?`)) return;
        router.delete(`/shipments/rates/${q.ulid}`, { preserveScroll: true });
    };

    return (
        <ShipmentsLayout>
            <Head title="Rate quotes" />
            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6">
                <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-gradient text-white"><BadgeDollarSign className="h-5 w-5" /></span>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight text-foreground">Rate quotes</h1>
                            <p className="text-sm text-muted-foreground">{stats.total} request{stats.total === 1 ? '' : 's'} · {stats.quoted} quoted</p>
                        </div>
                    </div>
                    <Link href="/shipments/rates/create" className="bg-brand-gradient shadow-glow inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5">
                        <Plus className="h-4 w-4" /> New rate request
                    </Link>
                </div>

                <div className="mb-5 flex items-start gap-3 rounded-xl border border-border bg-secondary/40 px-4 py-3 text-sm text-muted-foreground">
                    <Info className="mt-0.5 h-4 w-4 shrink-0" />
                    <p>
                        Spot rates are quoted by email — open a request, hit <span className="font-medium text-foreground">Compose request email</span> to draft it,
                        then attach the PDF the carrier sends back. The app reads the price, transit and validity straight off the rate sheet for you to confirm.
                    </p>
                </div>

                <div className="mb-4 flex flex-wrap items-center gap-2">
                    <div className="relative min-w-[14rem] flex-1">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <input
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                            placeholder="Search reference, city, quote #…"
                            className="input pl-9 pr-9"
                        />
                        {search && (
                            <button onClick={() => setSearch('')} className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground">
                                <X className="h-4 w-4" />
                            </button>
                        )}
                    </div>
                    <Select
                        value={filters.carrier}
                        onChange={v => apply({ carrier: v })}
                        options={carrierOptions}
                        placeholder="All carriers"
                        className="w-44"
                    />
                    <Select
                        value={filters.status}
                        onChange={v => apply({ status: v })}
                        options={statusOptions}
                        placeholder="Any status"
                        className="w-40"
                    />
                </div>

                <div className="card-surface overflow-hidden">
                    {quotes.data.length === 0 ? (
                        <div className="px-4 py-16 text-center">
                            <BadgeDollarSign className="mx-auto mb-3 h-8 w-8 text-muted-foreground/40" />
                            <p className="text-sm font-medium text-foreground">No rate requests yet</p>
                            <p className="mt-1 text-sm text-muted-foreground">Create one to capture a carrier quote for a shipment.</p>
                            <Link href="/shipments/rates/create" className="mt-4 inline-flex items-center gap-1.5 rounded-full bg-secondary px-4 py-2 text-sm font-medium text-foreground hover:bg-secondary/70">
                                <Plus className="h-4 w-4" /> New rate request
                            </Link>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                        <th className="px-4 py-3 font-medium">Reference</th>
                                        <th className="px-4 py-3 font-medium">Carrier</th>
                                        <th className="px-4 py-3 font-medium">Lane</th>
                                        <th className="px-4 py-3 font-medium">Price</th>
                                        <th className="px-4 py-3 font-medium">Transit</th>
                                        <th className="px-4 py-3 font-medium">Status</th>
                                        <th className="px-4 py-3 text-right font-medium">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {quotes.data.map(q => (
                                        <tr key={q.ulid} className="border-b border-border/60 last:border-0 hover:bg-secondary/40">
                                            <td className="px-4 py-3">
                                                <Link href={`/shipments/rates/${q.ulid}/edit`} className="font-medium text-foreground hover:text-primary">
                                                    {q.reference || 'Untitled quote'}
                                                </Link>
                                                <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                                    {q.has_document && <Paperclip className="h-3 w-3" />}
                                                    <span>{q.service_line_label ?? 'General'}{q.service_level ? ` · ${q.service_level}` : ''}</span>
                                                    {q.mailing && <> · <Link href={`/shipments/mailings/${q.mailing.ulid}`} className="hover:text-primary">{q.mailing.tracking || 'shipment'}</Link></>}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-foreground">{q.carrier_label}</td>
                                            <td className="px-4 py-3">
                                                {q.origin || q.destination ? (
                                                    <span className="inline-flex items-center gap-1.5 text-foreground">
                                                        <span>{q.origin || '—'}</span>
                                                        <ArrowRight className="h-3.5 w-3.5 text-muted-foreground" />
                                                        <span>{q.destination || '—'}</span>
                                                    </span>
                                                ) : <span className="text-muted-foreground">—</span>}
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="font-semibold text-foreground">{money(q.amount, q.currency)}</span>
                                                {q.source === 'api' && <span className="ml-1.5 text-[11px] font-medium text-emerald-600 dark:text-emerald-400">live</span>}
                                                {q.expires_at && (
                                                    <div className={`text-xs ${q.is_expired ? 'text-red-500' : 'text-muted-foreground'}`}>
                                                        {q.is_expired ? 'Expired ' : 'Valid till '}{formatDate(q.expires_at)}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-foreground">
                                                {q.transit_days !== null ? `${q.transit_days} day${q.transit_days === 1 ? '' : 's'}` : '—'}
                                                {q.estimated_delivery && <div className="text-xs text-muted-foreground">ETA {formatDate(q.estimated_delivery)}</div>}
                                            </td>
                                            <td className="px-4 py-3"><Pill color={q.status_color} label={q.status_label} /></td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Link href={`/shipments/rates/${q.ulid}/edit`} title="Edit"
                                                        className="rounded-lg p-1.5 text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground">
                                                        <Pencil className="h-4 w-4" />
                                                    </Link>
                                                    <button onClick={() => remove(q)} title="Remove"
                                                        className="rounded-lg p-1.5 text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive">
                                                        <Trash2 className="h-4 w-4" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {quotes.data.length > 0 && (
                    <div className="mt-4">
                        <Pagination from={quotes.from} to={quotes.to} total={quotes.total} links={quotes.links} />
                    </div>
                )}
            </div>
        </ShipmentsLayout>
    );
}
