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
import { Receipt, Plus, ExternalLink, Paperclip } from 'lucide-react';

interface ExpenseRow {
    id: number; number: string; vendor: string | null; description: string | null;
    amount: number; currency: string; status: string; status_label: string; status_color: string;
    source: string; is_billable: boolean; expense_date: string | null; category: string | null;
    owner: string | null; attachments_count: number;
}

interface Props {
    expenses: PaginatedResponse<ExpenseRow>;
    filters: Record<string, string>;
    statuses: { value: string; label: string }[];
    formOptions: ExpenseFormOptions;
    can: { manage: boolean };
}

export default function ExpensesIndex({ expenses, filters, statuses, formOptions }: Props) {
    const [formOpen, setFormOpen] = useState(false);

    const apply = (patch: Record<string, string | undefined>) => {
        router.get('/expenses/list', { ...filters, ...patch }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const categoryOptions = formOptions.categories.map(c => ({ value: String(c.value), label: c.label }));

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

                <Card className="mb-4 flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                    <SearchInput className="w-full sm:max-w-sm" initial={filters.search ?? ''} onSearch={v => apply({ search: v || undefined })} placeholder="Search vendor, number, description…" />
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
                                    <th className="th hidden md:table-cell">Owner</th>
                                    <th className="th hidden lg:table-cell">Date</th>
                                    <th className="th">Status</th>
                                    <th className="th text-right">Amount</th>
                                    <th className="th" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {expenses.data.length === 0 ? (
                                    <tr><td colSpan={7}>
                                        <EmptyState icon={Receipt} title="No expenses found"
                                            description="Record your first expense to start tracking spend."
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
                                        <td className="td hidden text-muted-foreground md:table-cell">{e.owner ?? '—'}</td>
                                        <td className="td hidden text-muted-foreground lg:table-cell">{formatDate(e.expense_date)}</td>
                                        <td className="td"><Pill color={e.status_color} label={e.status_label} /></td>
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
