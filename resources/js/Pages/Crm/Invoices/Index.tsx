import { Head, Link, router } from '@inertiajs/react';
import { CrmLayout } from '@/Components/layout/CrmLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Pagination } from '@/Components/ui/Pagination';
import { Select } from '@/Components/ui/Select';
import { SearchInput } from '@/Components/ui/SearchInput';
import { Pill } from '@/Components/ui/Pill';
import { StatCard } from '@/Components/ui/StatCard';
import { formatCurrency, formatDate } from '@/Lib/utils';
import { PaginatedResponse } from '@/Types';
import { ReceiptText, Plus, FileText, Wallet, CircleDollarSign, AlertTriangle } from 'lucide-react';

interface InvoiceRow {
    id: number;
    number: string;
    kind: string;
    status: string;
    total: number | string;
    amount_paid: number | string;
    balance: number | string;
    currency: string;
    issue_date: string | null;
    due_date: string | null;
    company?: { id: number; name: string } | null;
}

interface Props {
    invoices: PaginatedResponse<InvoiceRow>;
    filters: Record<string, string>;
    stats: { outstanding: number; paid: number; draft_count: number; overdue_count: number };
    statuses: Array<{ value: string; label: string; color: string }>;
    can: { manage: boolean };
}

export default function InvoicesIndex({ invoices, filters, stats, statuses, can }: Props) {
    const statusMap = Object.fromEntries(statuses.map(s => [s.value, s]));

    const handleFilter = (key: string, value: string) => {
        router.get('/crm/invoices', { ...filters, [key]: value || undefined }, { preserveState: true, preserveScroll: true, replace: true });
    };

    return (
        <CrmLayout>
            <Head title="Invoices · CRM" />
            <div className="p-4 sm:p-6">
                <PageHeader
                    icon={ReceiptText}
                    title="Invoices & Estimates"
                    description={`${invoices.total} document${invoices.total === 1 ? '' : 's'}`}
                    actions={can.manage && (
                        <div className="flex items-center gap-2">
                            <Button variant="secondary" href="/crm/invoices/create?kind=estimate" icon={FileText}>New Estimate</Button>
                            <Button href="/crm/invoices/create?kind=invoice" icon={Plus}>New Invoice</Button>
                        </div>
                    )}
                />

                <div className="stagger mb-5 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard title="Outstanding" value={formatCurrency(stats.outstanding)} icon={Wallet} tone="amber" />
                    <StatCard title="Collected" value={formatCurrency(stats.paid)} icon={CircleDollarSign} tone="emerald" />
                    <StatCard title="Drafts" value={stats.draft_count} icon={FileText} tone="indigo" />
                    <StatCard title="Overdue" value={stats.overdue_count} icon={AlertTriangle} tone={stats.overdue_count ? 'rose' : 'teal'} />
                </div>

                <Card className="mb-4 p-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <SearchInput className="min-w-0 flex-1 sm:max-w-sm" initial={filters.search ?? ''} onSearch={v => handleFilter('search', v)} placeholder="Search number or client…" />
                        <Select value={filters.kind ?? ''} onChange={v => handleFilter('kind', v)} options={[{ value: 'invoice', label: 'Invoices' }, { value: 'estimate', label: 'Estimates' }]} placeholder="All types" className="w-full sm:w-40" />
                        <Select value={filters.status ?? ''} onChange={v => handleFilter('status', v)} options={statuses.map(s => ({ value: s.value, label: s.label }))} placeholder="All statuses" className="w-full sm:w-44" />
                    </div>
                </Card>

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b border-border bg-secondary/40">
                                <tr>
                                    <th className="th">Number</th>
                                    <th className="th">Client</th>
                                    <th className="th hidden sm:table-cell">Issued</th>
                                    <th className="th hidden md:table-cell">Due</th>
                                    <th className="th text-right">Total</th>
                                    <th className="th text-right hidden lg:table-cell">Balance</th>
                                    <th className="th">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {invoices.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={7}>
                                            <EmptyState icon={ReceiptText} title="Nothing here yet" description="Create your first estimate or invoice for a client." action={can.manage && <Button href="/crm/invoices/create?kind=invoice" icon={Plus}>New Invoice</Button>} />
                                        </td>
                                    </tr>
                                ) : invoices.data.map(inv => {
                                    const st = statusMap[inv.status];
                                    return (
                                        <tr key={inv.id} className="row-link cursor-pointer" onClick={() => router.visit(`/crm/invoices/${inv.id}`)}>
                                            <td className="td">
                                                <span className="font-mono text-sm font-medium text-foreground">{inv.number}</span>
                                                {inv.kind === 'estimate' && <span className="ml-2 chip">Estimate</span>}
                                            </td>
                                            <td className="td text-muted-foreground">{inv.company?.name ?? '—'}</td>
                                            <td className="td hidden text-muted-foreground sm:table-cell">{formatDate(inv.issue_date)}</td>
                                            <td className="td hidden text-muted-foreground md:table-cell">{formatDate(inv.due_date)}</td>
                                            <td className="td text-right font-medium text-foreground">{formatCurrency(inv.total, inv.currency)}</td>
                                            <td className="td hidden text-right text-muted-foreground lg:table-cell">{formatCurrency(inv.balance, inv.currency)}</td>
                                            <td className="td"><Pill color={st?.color ?? 'gray'} label={st?.label ?? inv.status} /></td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                    <Pagination from={invoices.from} to={invoices.to} total={invoices.total} links={invoices.links} />
                </Card>
            </div>
        </CrmLayout>
    );
}
