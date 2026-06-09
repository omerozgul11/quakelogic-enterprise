import { Head, Link, router } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { StatCard } from '@/Components/ui/StatCard';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Pagination } from '@/Components/ui/Pagination';
import { Commission, PaginatedResponse } from '@/Types';
import { formatCurrency } from '@/Lib/utils';
import { DollarSign, Check, X, Clock, Settings } from 'lucide-react';

interface Props {
    commissions: PaginatedResponse<Commission & {
        user: { id: number; name: string };
        proposal: { id: number; proposal_number: string; project_name: string } | null;
    }>;
    filters?: Record<string, string>;
    can?: { approve?: boolean; viewAll?: boolean };
    totalAmount?: number;
    summary?: { total: number; pending: number; approved: number };
}

export default function CommissionsIndex({ commissions, filters = {}, can = {}, summary, totalAmount }: Props) {
    const totals = {
        total: summary?.total ?? totalAmount ?? 0,
        pending: summary?.pending ?? 0,
        approved: summary?.approved ?? 0,
    };
    const handleFilter = (key: string, value: string) => {
        router.get('/commissions', { ...filters, [key]: value || undefined }, { preserveState: true });
    };

    const handleApprove = (id: number) => {
        router.post(`/commissions/${id}/approve`);
    };

    return (
        <AppLayout>
            <Head title="Commissions" />
            <div className="p-6">
                <PageHeader
                    icon={DollarSign}
                    title="Commissions"
                    description={`${commissions.total} ${commissions.total === 1 ? 'record' : 'records'}`}
                    actions={
                        can.viewAll && (
                            <Button variant="secondary" icon={Settings} href="/commissions/rules">
                                Manage Rules
                            </Button>
                        )
                    }
                />

                {/* Summary Cards */}
                <div className="stagger grid grid-cols-1 gap-4 sm:grid-cols-3 mb-6">
                    <StatCard title="Total" value={formatCurrency(totals.total)} icon={DollarSign} tone="indigo" />
                    <StatCard title="Pending Approval" value={formatCurrency(totals.pending)} icon={Clock} tone="amber" />
                    <StatCard title="Approved" value={formatCurrency(totals.approved)} icon={Check} tone="emerald" />
                </div>

                {/* Filters */}
                <Card className="mb-4 p-4">
                    <div className="flex flex-wrap items-center gap-3">
                        <select value={filters.status ?? ''} onChange={e => handleFilter('status', e.target.value)} className="select">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="paid">Paid</option>
                            <option value="disputed">Disputed</option>
                        </select>
                        <input type="month" value={filters.period ?? ''} onChange={e => handleFilter('period', e.target.value)} className="input max-w-[12rem]" />
                        {Object.keys(filters).length > 0 && (
                            <button onClick={() => router.get('/commissions')} className="inline-flex items-center gap-1 text-sm font-medium text-destructive hover:underline">
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
                                    <th className="th">Person</th>
                                    <th className="th">Proposal</th>
                                    <th className="th">Period</th>
                                    <th className="th">Base</th>
                                    <th className="th">Commission</th>
                                    <th className="th">Status</th>
                                    {can.approve && <th className="th" />}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {commissions.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={can.approve ? 7 : 6}>
                                            <EmptyState
                                                icon={DollarSign}
                                                title="No commissions found"
                                                description="Try adjusting your filters to see commission records."
                                            />
                                        </td>
                                    </tr>
                                ) : commissions.data.map(c => (
                                    <tr key={c.id} className="row-link">
                                        <td className="td font-medium text-foreground">{c.user.name}</td>
                                        <td className="td">
                                            {c.proposal ? (
                                                <Link href={`/proposals/${c.proposal.id}`} className="font-mono text-sm text-primary hover:underline">
                                                    {c.proposal.proposal_number}
                                                </Link>
                                            ) : '—'}
                                        </td>
                                        <td className="td text-muted-foreground">{c.period_month}</td>
                                        <td className="td text-muted-foreground">{formatCurrency(c.base_amount)}</td>
                                        <td className="td font-semibold text-emerald-600">{formatCurrency(c.commission_amount)}</td>
                                        <td className="td"><StatusBadge status={c.status} /></td>
                                        {can.approve && (
                                            <td className="td">
                                                {c.status === 'pending' && (
                                                    <Button variant="success" size="sm" icon={Check} onClick={() => handleApprove(c.id)}>
                                                        Approve
                                                    </Button>
                                                )}
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination from={commissions.from} to={commissions.to} total={commissions.total} links={commissions.links} />
                </Card>
            </div>
        </AppLayout>
    );
}
