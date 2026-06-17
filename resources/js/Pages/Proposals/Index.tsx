import { Head, Link, router } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { Select } from '@/Components/ui/Select';
import { SearchInput } from '@/Components/ui/SearchInput';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { DateFilter, DateFilterValue } from '@/Components/ui/DateFilter';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Pagination } from '@/Components/ui/Pagination';
import { cn, formatCurrency, getDueDateLabel, getDueDateColor, proposalTypeLabel, proposalTypeColor } from '@/Lib/utils';
import { PaginatedResponse, ProposalSubmission } from '@/Types';
import { Plus, Search, X, FileText, ExternalLink, Trash2, KanbanSquare, ChevronUp, ChevronDown, ChevronsUpDown, TrendingUp } from 'lucide-react';

interface MarginRollup {
    bid: number;
    cost: number;
    profit: number;
    margin: number | null;
    count: number;
    currency: string;
}

interface Props {
    proposals: PaginatedResponse<ProposalSubmission>;
    filters: Record<string, string>;
    statuses: Array<{ value: string; label: string; color: string }>;
    types: Array<{ value: string; label: string; description: string; has_value: boolean }>;
    margins: MarginRollup;
    can: { create: boolean; delete: boolean };
}

/** Per-bid dollar profit (bid − cost), or null when there's no bid or no cost estimate. */
function rowProfit(p: ProposalSubmission): number | null {
    const bid = Number(p.proposal_value ?? 0);
    const cost = Number(p.estimated_cost ?? 0);
    if (bid <= 0 || cost <= 0) return null;
    return Math.round((bid - cost) * 100) / 100;
}

const signClass = (v: number | null) =>
    v == null ? 'text-muted-foreground' : v > 0 ? 'text-emerald-600' : v < 0 ? 'text-red-600' : 'text-foreground';

const DEFAULT_DIR: Record<string, 'asc' | 'desc'> = {
    name: 'asc', company: 'asc', owner: 'asc', status: 'asc', value: 'desc', due_date: 'asc', date: 'desc',
};

export default function ProposalsIndex({ proposals, filters, statuses, types, margins, can }: Props) {
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
        router.get('/proposals', { ...filters, [key]: value || undefined }, { preserveState: true, preserveScroll: true, replace: true });
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
                        <SearchInput
                            className="min-w-0 flex-1 sm:min-w-[18rem]"
                            initial={filters.search ?? ''}
                            onSearch={v => handleFilter('search', v)}
                            placeholder="Search proposals…"
                        />
                        <Select
                            value={filters.type ?? ''}
                            onChange={v => handleFilter('type', v)}
                            options={types.map(t => ({ value: t.value, label: t.label }))}
                            placeholder="All Types"
                            className="w-full sm:w-40"
                        />
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

                {/* Overall profit-margin roll-up across the filtered set (USD) */}
                {margins.count > 0 && (
                    <Card className="mb-4 p-4">
                        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div className="flex items-center gap-2 text-sm font-semibold text-foreground">
                                <TrendingUp className="h-4 w-4 text-primary" />
                                Estimated profit margin
                                <span className="text-xs font-normal text-muted-foreground">
                                    across {margins.count} {margins.count === 1 ? 'proposal' : 'proposals'} with a cost estimate · USD
                                </span>
                            </div>
                            <div className="grid grid-cols-2 gap-x-6 gap-y-2 sm:grid-cols-4 sm:gap-x-8">
                                <div>
                                    <p className="text-[11px] uppercase tracking-wide text-muted-foreground">Total bid</p>
                                    <p className="text-base font-bold tabular-nums text-foreground">{formatCurrency(margins.bid, 'USD')}</p>
                                </div>
                                <div>
                                    <p className="text-[11px] uppercase tracking-wide text-muted-foreground">Est. cost</p>
                                    <p className="text-base font-bold tabular-nums text-foreground">{formatCurrency(margins.cost, 'USD')}</p>
                                </div>
                                <div>
                                    <p className="text-[11px] uppercase tracking-wide text-muted-foreground">Potential profit</p>
                                    <p className={`text-base font-bold tabular-nums ${signClass(margins.profit)}`}>{formatCurrency(margins.profit, 'USD')}</p>
                                </div>
                                <div>
                                    <p className="text-[11px] uppercase tracking-wide text-muted-foreground">Blended margin</p>
                                    <p className={`text-base font-bold tabular-nums ${signClass(margins.margin)}`}>{margins.margin != null ? `${margins.margin}%` : '—'}</p>
                                </div>
                            </div>
                        </div>
                    </Card>
                )}

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
                                    <th className="th hidden sm:table-cell">Profit</th>
                                    <SortHeader field="due_date" label="Due Date" className="hidden sm:table-cell" />
                                    <SortHeader field="owner" label="Owner" className="hidden lg:table-cell" />
                                    <th className="th" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {proposals.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={9}>
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
                                            <div className="flex items-start gap-2">
                                                <span className={`mt-0.5 inline-flex shrink-0 items-center rounded-full px-1.5 py-0.5 text-[10px] font-semibold ${proposalTypeColor(proposal.proposal_type)}`}>
                                                    {proposalTypeLabel(proposal.proposal_type)}
                                                </span>
                                                <p className="font-medium text-foreground line-clamp-2">{proposal.project_name}</p>
                                            </div>
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
                                        <td className="td hidden font-medium sm:table-cell">
                                            {(() => {
                                                const profit = rowProfit(proposal);
                                                return profit != null
                                                    ? <span className={`text-sm font-semibold tabular-nums ${signClass(profit)}`}>{formatCurrency(profit, proposal.currency)}</span>
                                                    : <span className="text-sm text-muted-foreground">—</span>;
                                            })()}
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
