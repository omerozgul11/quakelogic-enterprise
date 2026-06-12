import { Head, Link, router } from '@inertiajs/react';
import { Plus, Package, Layers } from 'lucide-react';
import { ShipmentsLayout } from '@/Components/layout/ShipmentsLayout';
import { Pill } from '@/Components/ui/Pill';
import { Pagination } from '@/Components/ui/Pagination';
import { formatDate } from '@/Lib/utils';

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
    filters: { status: string | null };
}

const STATUS_FILTERS = [
    { value: '', label: 'All' },
    { value: 'label_created', label: 'Label created' },
    { value: 'in_transit', label: 'In transit' },
    { value: 'out_for_delivery', label: 'Out for delivery' },
    { value: 'delivered', label: 'Delivered' },
    { value: 'exception', label: 'Exception' },
];

export default function MailingsIndex({ mailings, filters }: Props) {
    const setStatus = (status: string) =>
        router.get('/shipments/mailings', status ? { status } : {}, { preserveScroll: true, preserveState: true });

    return (
        <ShipmentsLayout>
            <Head title="Mailings" />
            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6">
                <div className="mb-6 flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-foreground">Mailings</h1>
                        <p className="mt-1 text-sm text-muted-foreground">{mailings.total} total</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Link href="/shipments/mailings/bulk" className="inline-flex items-center gap-2 rounded-full border border-border px-4 py-2 text-sm font-medium text-foreground transition hover:bg-secondary">
                            <Layers className="h-4 w-4" /> Bulk add
                        </Link>
                        <Link href="/shipments/mailings/create" className="bg-brand-gradient shadow-glow inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5">
                            <Plus className="h-4 w-4" /> New mailing
                        </Link>
                    </div>
                </div>

                <div className="mb-4 flex flex-wrap gap-1.5">
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

                <div className="overflow-hidden rounded-xl border border-border bg-card shadow-soft">
                    {mailings.data.length === 0 ? (
                        <div className="px-6 py-16 text-center">
                            <div className="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-secondary">
                                <Package className="h-7 w-7 text-muted-foreground" />
                            </div>
                            <p className="text-sm text-muted-foreground">No mailings match this filter.</p>
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
