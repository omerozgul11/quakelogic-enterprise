import { Head, Link, router } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Pagination } from '@/Components/ui/Pagination';
import { formatCurrency, getDueDateLabel, getDueDateColor } from '@/Lib/utils';
import { PaginatedResponse, ProposalSubmission } from '@/Types';
import { Plus, Search, X, FileText, ExternalLink } from 'lucide-react';

interface Props {
    proposals: PaginatedResponse<ProposalSubmission>;
    filters: Record<string, string>;
    statuses: Array<{ value: string; label: string; color: string }>;
    can: { create: boolean };
}

export default function ProposalsIndex({ proposals, filters, statuses, can }: Props) {
    const handleFilter = (key: string, value: string) => {
        router.get('/proposals', { ...filters, [key]: value || undefined }, { preserveState: true });
    };

    return (
        <AppLayout>
            <Head title="Proposals" />
            <div className="p-6">
                <PageHeader
                    icon={FileText}
                    title="Proposals"
                    description={`${proposals.total} ${proposals.total === 1 ? 'proposal' : 'proposals'} total`}
                    actions={
                        can.create && (
                            <Button href="/proposals/create" icon={Plus}>
                                New Proposal
                            </Button>
                        )
                    }
                />

                {/* Filters */}
                <Card className="mb-4 p-4">
                    <div className="flex flex-wrap items-center gap-3">
                        <div className="relative min-w-[18rem] flex-1">
                            <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <input
                                type="text"
                                placeholder="Search proposals…"
                                defaultValue={filters.search ?? ''}
                                onKeyDown={e => e.key === 'Enter' && handleFilter('search', (e.target as HTMLInputElement).value)}
                                className="input input-with-icon"
                            />
                        </div>
                        <select value={filters.status ?? ''} onChange={e => handleFilter('status', e.target.value)} className="select">
                            <option value="">All Statuses</option>
                            {statuses.map(s => <option key={s.value} value={s.value}>{s.label}</option>)}
                        </select>
                        {Object.keys(filters).length > 0 && (
                            <button onClick={() => router.get('/proposals')} className="inline-flex items-center gap-1 text-sm font-medium text-destructive hover:underline">
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
                                    <th className="th">Proposal #</th>
                                    <th className="th">Project</th>
                                    <th className="th">Agency</th>
                                    <th className="th">Status</th>
                                    <th className="th">Value</th>
                                    <th className="th">Due Date</th>
                                    <th className="th">Owner</th>
                                    <th className="th" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {proposals.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={8}>
                                            <EmptyState
                                                icon={FileText}
                                                title="No proposals found"
                                                description="Create your first proposal to get started."
                                                action={can.create && <Button href="/proposals/create" icon={Plus}>New Proposal</Button>}
                                            />
                                        </td>
                                    </tr>
                                ) : proposals.data.map(proposal => (
                                    <tr key={proposal.id} className="row-link">
                                        <td className="td">
                                            <Link href={`/proposals/${proposal.id}`} className="font-mono text-sm font-medium text-primary hover:underline">
                                                {proposal.proposal_number}
                                            </Link>
                                        </td>
                                        <td className="td max-w-md">
                                            <p className="font-medium text-foreground line-clamp-2">{proposal.project_name}</p>
                                            {proposal.solicitation_number && (
                                                <p className="mt-0.5 text-xs text-muted-foreground">{proposal.solicitation_number}</p>
                                            )}
                                        </td>
                                        <td className="td text-muted-foreground">{proposal.agency?.name ?? '—'}</td>
                                        <td className="td">
                                            <StatusBadge status={typeof proposal.status === 'string' ? proposal.status : (proposal.status as any)?.value ?? 'draft'} />
                                        </td>
                                        <td className="td font-medium">
                                            {proposal.award_value ? (
                                                <span className="text-emerald-600">{formatCurrency(proposal.award_value)}</span>
                                            ) : formatCurrency(proposal.proposal_value)}
                                        </td>
                                        <td className="td">
                                            <span className={`text-sm font-medium ${getDueDateColor(proposal.due_date)}`}>
                                                {proposal.due_date ? getDueDateLabel(proposal.due_date) : '—'}
                                            </span>
                                        </td>
                                        <td className="td text-muted-foreground">{proposal.owner?.name ?? '—'}</td>
                                        <td className="td">
                                            <Link href={`/proposals/${proposal.id}`} className="text-muted-foreground transition-colors hover:text-primary">
                                                <ExternalLink className="h-4 w-4" />
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination from={proposals.from} to={proposals.to} total={proposals.total} links={proposals.links} />
                </Card>
            </div>
        </AppLayout>
    );
}
