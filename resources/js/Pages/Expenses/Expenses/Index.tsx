import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ExpenseLayout } from '@/Components/layout/ExpenseLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Pill } from '@/Components/ui/Pill';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Pagination } from '@/Components/ui/Pagination';
import { SearchInput } from '@/Components/ui/SearchInput';
import { Select } from '@/Components/ui/Select';
import { ExpenseFormModal, ExpenseFormOptions } from '@/Components/expenses/ExpenseFormModal';
import { formatCurrency, formatDate } from '@/Lib/utils';
import { PaginatedResponse } from '@/Types';
import { Receipt, Plus, ExternalLink, Paperclip, CircleDollarSign, AlertTriangle, CheckCircle2 } from 'lucide-react';

interface ExpenseRow {
    id: number; number: string; vendor: string | null; description: string | null;
    amount: number; currency: string; status: string; status_label: string; status_color: string;
    amount_paid: number; balance_due: number; payment_status: string; payment_status_label: string;
    payment_status_color: string; due_date: string | null; is_overdue: boolean;
    source: string; is_billable: boolean; expense_date: string | null; category: string | null;
    owner: string | null; attachments_count: number;
}

interface Summary { due_count: number; due_amount: number; overdue_count: number; paid_amount: number }

interface Props {
    expenses: PaginatedResponse<ExpenseRow>;
    filters: Record<string, string>;
    statuses: { value: string; label: string }[];
    paymentStatuses: { value: string; label: string }[];
    summary: Summary;
    formOptions: ExpenseFormOptions;
    can: { manage: boolean };
}

export default function ExpensesIndex({ expenses, filters, statuses, paymentStatuses, summary, formOptions }: Props) {
    const [formOpen, setFormOpen] = useState(false);

    const apply = (patch: Record<string, string | undefined>) => {
        router.get('/expenses/list', { ...filters, ...patch }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const categoryOptions = formOptions.categories.map(c => ({ value: String(c.value), label: c.label }));
    const paymentOptions = [...paymentStatuses, { value: 'overdue', label: 'Overdue' }];

    return (
        <ExpenseLayout>
            <Head title="Expenses" />
            <div className="p-4 sm:p-6">
                <PageHeader
                    icon={Receipt}
                    title="Expenses"
                    description={`${expenses.total} ${expenses.total === 1 ? 'expense' : 'expenses'}`}
                    actions={<Button onClick={() => setFormOpen(true)} icon={Plus}>Add Expense</Button>}
                />

                <div className="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <button onClick={() => apply({ payment: 'due' })} className="flex items-center gap-3 rounded-xl border border-border bg-card p-4 text-left transition-colors hover:border-amber-400/60">
                        <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-100 text-amber-600 dark:bg-amber-900/40 dark:text-amber-300"><CircleDollarSign className="h-5 w-5" /></span>
                        <span>
                            <span className="block text-lg font-bold text-foreground">{formatCurrency(summary.due_amount, 'USD')}</span>
                            <span className="block text-xs text-muted-foreground">Outstanding · {summary.due_count} unpaid</span>
                        </span>
                    </button>
                    <button onClick={() => apply({ payment: 'overdue' })} className="flex items-center gap-3 rounded-xl border border-border bg-card p-4 text-left transition-colors hover:border-red-400/60">
                        <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-red-100 text-red-600 dark:bg-red-900/40 dark:text-red-300"><AlertTriangle className="h-5 w-5" /></span>
                        <span>
                            <span className="block text-lg font-bold text-foreground">{summary.overdue_count}</span>
                            <span className="block text-xs text-muted-foreground">Overdue invoices</span>
                        </span>
                    </button>
                    <button onClick={() => apply({ payment: 'paid' })} className="flex items-center gap-3 rounded-xl border border-border bg-card p-4 text-left transition-colors hover:border-emerald-400/60">
                        <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-300"><CheckCircle2 className="h-5 w-5" /></span>
                        <span>
                            <span className="block text-lg font-bold text-foreground">{formatCurrency(summary.paid_amount, 'USD')}</span>
                            <span className="block text-xs text-muted-foreground">Paid to date</span>
                        </span>
                    </button>
                </div>

                <Card className="mb-4 flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                    <SearchInput className="w-full sm:max-w-sm" initial={filters.search ?? ''} onSearch={v => apply({ search: v || undefined })} placeholder="Search vendor, number, description…" />
                    <Select value={filters.payment ?? ''} onChange={v => apply({ payment: v || undefined })} placeholder="All payments" options={paymentOptions} />
                    <Select value={filters.status ?? ''} onChange={v => apply({ status: v || undefined })} placeholder="All status" options={statuses} />
                    <Select value={filters.category ?? ''} onChange={v => apply({ category: v || undefined })} placeholder="All categories" options={categoryOptions} />
                </Card>

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b border-border bg-secondary/40">
                                <tr>
                                    <th className="th">Expense</th>
                                    <th className="th hidden sm:table-cell">Category</th>
                                    <th className="th hidden lg:table-cell">Due</th>
                                    <th className="th">Payment</th>
                                    <th className="th text-right">Amount</th>
                                    <th className="th" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {expenses.data.length === 0 ? (
                                    <tr><td colSpan={6}>
                                        <EmptyState icon={Receipt} title="No expenses found"
                                            description="Drop an invoice or record your first expense to start tracking spend."
                                            action={<Button onClick={() => setFormOpen(true)} icon={Plus}>Add Expense</Button>} />
                                    </td></tr>
                                ) : expenses.data.map(e => (
                                    <tr key={e.id} className="row-link">
                                        <td className="td">
                                            <Link href={`/expenses/list/${e.id}`} className="block">
                                                <span className="flex items-center gap-1.5 font-medium text-foreground hover:text-primary">
                                                    {e.vendor ?? e.description ?? e.number}
                                                    {e.attachments_count > 0 && <Paperclip className="h-3.5 w-3.5 text-muted-foreground" />}
                                                    {e.is_billable && <span className="rounded bg-emerald-100 px-1 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">BILLABLE</span>}
                                                    {e.source === 'quickbooks' && <span className="rounded bg-blue-100 px-1 text-[10px] font-semibold text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">QB</span>}
                                                </span>
                                                <span className="block font-mono text-xs text-muted-foreground">{e.number}</span>
                                            </Link>
                                        </td>
                                        <td className="td hidden text-muted-foreground sm:table-cell">{e.category ?? '—'}</td>
                                        <td className="td hidden lg:table-cell">
                                            {e.due_date ? (
                                                <span className={e.is_overdue ? 'font-medium text-red-600 dark:text-red-400' : 'text-muted-foreground'}>{formatDate(e.due_date)}</span>
                                            ) : <span className="text-muted-foreground">—</span>}
                                        </td>
                                        <td className="td">
                                            <div className="flex flex-col items-start gap-0.5">
                                                <Pill color={e.payment_status_color} label={e.payment_status_label} />
                                                {e.payment_status === 'partially_paid' && (
                                                    <span className="text-[11px] text-muted-foreground">{formatCurrency(e.balance_due, e.currency)} left</span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="td text-right font-semibold text-foreground">{formatCurrency(e.amount, e.currency)}</td>
                                        <td className="td">
                                            <div className="flex justify-end">
                                                <Link href={`/expenses/list/${e.id}`} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-secondary hover:text-primary" title="View">
                                                    <ExternalLink className="h-4 w-4" />
                                                </Link>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination from={expenses.from} to={expenses.to} total={expenses.total} links={expenses.links} />
                </Card>
            </div>

            {formOpen && <ExpenseFormModal open onClose={() => setFormOpen(false)} formOptions={formOptions} />}
        </ExpenseLayout>
    );
}
