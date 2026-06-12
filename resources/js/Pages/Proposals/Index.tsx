import { Head, Link, router } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { Select } from '@/Components/ui/Select';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { DateFilter, DateFilterValue } from '@/Components/ui/DateFilter';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Pagination } from '@/Components/ui/Pagination';
import { cn, formatCurrency, getDueDateLabel, getDueDateColor } from '@/Lib/utils';
import { PaginatedResponse, ProposalSubmission } from '@/Types';
import { Plus, Search, X, FileText, ExternalLink, Trash2, KanbanSquare, ChevronUp, ChevronDown, ChevronsUpDown } from 'lucide-react';

interface Props {
    proposals: PaginatedResponse<ProposalSubmission>;
    filters: Record<string, string>;
    statuses: Array<{ value: string; label: string; color: string }>;
    can: { create: boolean; delete: boolean };
}

const DEFAULT_DIR: Record<string, 'asc' | 'desc'> = {
    name: 'asc', company: 'asc', owner: 'asc', status: 'asc', value: 'desc', due_date: 'asc', date: 'desc',
};

export default function ProposalsIndex({ proposals, filters, statuses, can }: Props) {
    const sort = typeof filters.sort === 'string' ? filters.sort : 'date';
    const direction = filters.direction === 'asc' ? 'asc' : 'desc';

    const setSort = (field: string) => {
        const dir = sort === field ? (direction === 'asc' ? 'desc' : 'asc') : (DEFAULT_DIR[field] ?? 'asc');
        router.get('/proposals', { ...filters, sort: field, direction: dir }, { preserveState: true, preserveScroll: true });
    };

    const SortHeader = ({ field, label, className }: { field: string; label: string; className?: string }) => (
        <th className={cn('th cursor-pointer select-none transition-colors hover:text-foreground', className)} onClick={() => setSort(field)}>
            <span className="inline-flex items-center gap-1">
                {label}
                {sort === field
                    ? (direction === 'asc' ? <ChevronUp className="h-3.5 w-3.5 text-primary" /> : <ChevronDown className="h-3.5 w-3.5 text-primary" />)
                    : <ChevronsUpDown className="h-3 w-3 text-muted-foreground/40" />}
            </span>
        </th>
    );

    const handleFilter = (key: string, value: string) => {
        router.get('/proposals', { ...filters, [key]: value || undefined }, { preserveState: true });
    };

    const handleDate = (v: DateFilterValue) => {
        router.get('/proposals', { ...filters, date_field: v.date_field, from: v.from, to: v.to }, { preserveState: true });
    };

    const handleDelete = (e: React.MouseEvent, proposal: ProposalSubmission) => {
        e.preventDefault();
        e.stopPropagation();
        if (confirm(`Delete proposal ${proposal.proposal_number}? This cannot be undone.`)) {
            router.delete(`/proposals/${proposal.id}`, { preserveScroll: true });
        }
    };

    return (
        <AppLayout>
            <Head title="Proposals" />
            <div className="p-4 sm:p-6">
                <PageHeader
                    icon={FileText}
                    title="Proposals"
                    description={`${proposals.total} ${proposals.total === 1 ? 'proposal' : 'proposals'} total`}
                    actions={
                        <>
                            <Button href="/proposals/board" variant="secondary" icon={KanbanSquare}>Board view</Button>
                            {can.create && (
                                <Button href="/proposals/create" icon={Plus}>
                                    New Proposal
                                </Button>
                            )}
                        </>
                    }
                />

                {/* Filters */}
                <Card className="mb-4 p-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                        <div className="relative min-w-0 flex-1 sm:min-w-[18rem]">
                            <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <input
                                type="text"
                                placeholder="Search proposals…"
                                defaultValue={filters.search ?? ''}
                                onKeyDown={e => e.key === 'Enter' && handleFilter('search', (e.target as HTMLInputElement).value)}
                                className="input input-with-icon"
                            />
                        </div>
                        <Select
                            value={filters.status ?? ''}
                            onChange={v => handleFilter('status', v)}
                            options={statuses.map(s => ({ value: s.value, label: s.label }))}
                            placeholder="All Statuses"
                            className="w-full sm:w-44"
                        />
                        <button onClick={() => router.get('/proposals')} className="inline-flex items-center gap-1 text-sm font-medium text-destructive hover:underline">
                            <X className="h-4 w-4" /> Clear
                        </button>
                    </div>
                    <div className="mt-3 border-t border-border pt-3">
                        <DateFilter
                            value={{ date_field: filters.date_field, from: filters.from, to: filters.to }}
                            onChange={handleDate}
                        />
                    </div>
                </Card>

                {/* Table */}
                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b border-border bg-secondary/40">
                                <tr>
                                    <th className="th">Proposal #</th>
                                    <SortHeader field="name" label="Project" />
                                    <SortHeader field="company" label="Company" className="hidden md:table-cell" />
                                    <SortHeader field="status" label="Status" />
                                    <SortHeader field="value" label="Value" />
                                    <SortHeader field="due_date" label="Due Date" className="hidden sm:table-cell" />
                                    <SortHeader field="owner" label="Owner" className="hidden lg:table-cell" />
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
                                        <td className="td hidden text-muted-foreground md:table-cell">{proposal.company?.name ?? proposal.agency?.name ?? '—'}</td>
                                        <td className="td">
                                            <StatusBadge status={typeof proposal.status === 'string' ? proposal.status : (proposal.status as any)?.value ?? 'in_progress'} />
                                        </td>
                                        <td className="td font-medium">
                                            {proposal.award_value ? (
                                                <span className="text-emerald-600">{formatCurrency(proposal.award_value, proposal.currency)}</span>
                                            ) : formatCurrency(proposal.proposal_value, proposal.currency)}
                                        </td>
                                        <td className="td hidden sm:table-cell">
                                            <span className={`text-sm font-medium ${getDueDateColor(proposal.due_date)}`}>
                                                {proposal.due_date ? getDueDateLabel(proposal.due_date) : '—'}
                                            </span>
                                        </td>
                                        <td className="td hidden text-muted-foreground lg:table-cell">{proposal.owner?.name ?? '—'}</td>
                                        <td className="td">
                                            <div className="flex items-center gap-2">
                                                <Link href={`/proposals/${proposal.id}`} title="Open" className="text-muted-foreground transition-colors hover:text-primary">
                                                    <ExternalLink className="h-4 w-4" />
                                                </Link>
                                                {can.delete && (
                                                    <button onClick={e => handleDelete(e, proposal)} title="Delete" className="text-muted-foreground transition-colors hover:text-destructive">
                                                        <Trash2 className="h-4 w-4" />
                                                    </button>
                                                )}
                                            </div>
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
