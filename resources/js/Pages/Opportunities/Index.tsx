import { Head, Link, router, useForm } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Pagination } from '@/Components/ui/Pagination';
import { formatCurrency, formatDate, getDueDateLabel, getDueDateColor } from '@/Lib/utils';
import { PaginatedResponse, Opportunity } from '@/Types';
import { Plus, Upload, Search, X, ExternalLink, Target } from 'lucide-react';
import { useState } from 'react';

interface Props {
    opportunities: PaginatedResponse<Opportunity>;
    filters: Record<string, string>;
    statuses: Array<{ value: string; label: string; color: string }>;
    sources: Array<{ value: string; label: string }>;
    can: { create: boolean; import: boolean };
}

export default function OpportunitiesIndex({ opportunities, filters, statuses, sources, can }: Props) {
    const [showImportModal, setShowImportModal] = useState(false);
    const { data, setData, post, processing } = useForm({ naics_codes: [] as string[], keywords: '' });

    const handleFilter = (key: string, value: string) => {
        router.get('/opportunities', { ...filters, [key]: value || undefined }, { preserveState: true });
    };

    const handleImport = (e: React.FormEvent) => {
        e.preventDefault();
        post('/opportunities/import/sam-gov', { onSuccess: () => setShowImportModal(false) });
    };

    return (
        <AppLayout>
            <Head title="Opportunities" />
            <div className="p-6">
                <PageHeader
                    icon={Target}
                    title="Opportunities"
                    description={`${opportunities.total} ${opportunities.total === 1 ? 'opportunity' : 'opportunities'} in your pipeline`}
                    actions={
                        <>
                            {can.import && (
                                <Button variant="secondary" icon={Upload} onClick={() => setShowImportModal(true)}>
                                    Import from SAM.gov
                                </Button>
                            )}
                            {can.create && (
                                <Button href="/opportunities/create" icon={Plus}>
                                    Add Opportunity
                                </Button>
                            )}
                        </>
                    }
                />

                {/* Filters */}
                <Card className="mb-4 p-4">
                    <div className="flex flex-wrap items-center gap-3">
                        <div className="relative min-w-[18rem] flex-1">
                            <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <input
                                type="text"
                                placeholder="Search title, number, agency…"
                                defaultValue={filters.search ?? ''}
                                onKeyDown={e => e.key === 'Enter' && handleFilter('search', (e.target as HTMLInputElement).value)}
                                className="input input-with-icon"
                            />
                        </div>
                        <select value={filters.status ?? ''} onChange={e => handleFilter('status', e.target.value)} className="select">
                            <option value="">All Statuses</option>
                            {statuses.map(s => <option key={s.value} value={s.value}>{s.label}</option>)}
                        </select>
                        <select value={filters.source ?? ''} onChange={e => handleFilter('source', e.target.value)} className="select">
                            <option value="">All Sources</option>
                            {sources.map(s => <option key={s.value} value={s.value}>{s.label}</option>)}
                        </select>
                        {Object.keys(filters).length > 0 && (
                            <button onClick={() => router.get('/opportunities')} className="inline-flex items-center gap-1 text-sm font-medium text-destructive hover:underline">
                                <X className="h-4 w-4" /> Clear
                            </button>
                        )}
                    </div>
                </Card>

                {/* Table */}
                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b border-border bg-secondary/40">
                                <tr>
                                    <th className="th">Title</th>
                                    <th className="th">Agency</th>
                                    <th className="th">Status</th>
                                    <th className="th">Value</th>
                                    <th className="th">Due Date</th>
                                    <th className="th">Source</th>
                                    <th className="th" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {opportunities.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={7}>
                                            <EmptyState
                                                icon={Target}
                                                title="No opportunities found"
                                                description="Try adjusting your filters, or import fresh opportunities from SAM.gov."
                                                action={can.import && <Button variant="secondary" icon={Upload} onClick={() => setShowImportModal(true)}>Import from SAM.gov</Button>}
                                            />
                                        </td>
                                    </tr>
                                ) : opportunities.data.map(opp => (
                                    <tr key={opp.id} className="row-link">
                                        <td className="td max-w-md">
                                            <Link href={`/opportunities/${opp.id}`} className="font-medium text-foreground hover:text-primary line-clamp-2">
                                                {opp.title}
                                            </Link>
                                            {opp.solicitation_number && (
                                                <p className="mt-0.5 text-xs text-muted-foreground">{opp.solicitation_number}</p>
                                            )}
                                        </td>
                                        <td className="td text-muted-foreground">{opp.agency_name ?? '—'}</td>
                                        <td className="td"><StatusBadge status={opp.status} /></td>
                                        <td className="td font-medium">{formatCurrency(opp.estimated_value)}</td>
                                        <td className="td">
                                            <span className={`text-sm font-medium ${getDueDateColor(opp.due_date)}`}>
                                                {opp.due_date ? getDueDateLabel(opp.due_date) : '—'}
                                            </span>
                                            {opp.due_date && <p className="text-xs text-muted-foreground">{formatDate(opp.due_date)}</p>}
                                        </td>
                                        <td className="td">
                                            <span className="chip">{opp.source?.replace(/_/g, ' ').toUpperCase()}</span>
                                        </td>
                                        <td className="td">
                                            <Link href={`/opportunities/${opp.id}`} className="text-muted-foreground transition-colors hover:text-primary">
                                                <ExternalLink className="h-4 w-4" />
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination from={opportunities.from} to={opportunities.to} total={opportunities.total} links={opportunities.links} />
                </Card>
            </div>

            {/* Import Modal */}
            {showImportModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div className="animate-fade-in fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onClick={() => setShowImportModal(false)} />
                    <div className="card-surface animate-scale-in relative w-full max-w-md p-6">
                        <h2 className="text-lg font-semibold text-foreground">Import from SAM.gov</h2>
                        <p className="mt-1 text-sm text-muted-foreground">Pull the latest federal opportunities into your pipeline.</p>
                        <form onSubmit={handleImport} className="mt-5 space-y-4">
                            <div>
                                <label className="label">Keywords (optional)</label>
                                <input
                                    type="text"
                                    value={data.keywords}
                                    onChange={e => setData('keywords', e.target.value)}
                                    placeholder="e.g., cybersecurity, cloud, AI"
                                    className="input"
                                />
                            </div>
                            <p className="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-700">
                                No SAM.gov API key configured — this uses the demo data client to showcase the import flow.
                            </p>
                            <div className="flex justify-end gap-3">
                                <Button type="button" variant="secondary" onClick={() => setShowImportModal(false)}>Cancel</Button>
                                <Button type="submit" disabled={processing}>{processing ? 'Importing…' : 'Start Import'}</Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
