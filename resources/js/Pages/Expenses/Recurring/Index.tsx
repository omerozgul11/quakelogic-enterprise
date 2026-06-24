import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { ExpenseLayout } from '@/Components/layout/ExpenseLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Pill } from '@/Components/ui/Pill';
import { EmptyState } from '@/Components/ui/EmptyState';
import { RecurringFormModal, RecurringFormOptions, EditableRecurring } from '@/Components/expenses/RecurringFormModal';
import { formatCurrency, formatDate } from '@/Lib/utils';
import { RefreshCw, Plus, Pencil, Trash2, Play } from 'lucide-react';

interface RecurringRow {
    id: number; name: string; vendor: string | null; amount: number; currency: string;
    frequency: string; frequency_label: string; frequency_color: string; interval_count: number;
    next_run_date: string | null; start_date: string | null; end_date: string | null;
    auto_approve: boolean; is_active: boolean; is_billable: boolean; payment_method: string | null;
    category: string | null; category_id: number | null; owner: string | null; expenses_count: number;
}

interface Props {
    recurring: RecurringRow[];
    formOptions: RecurringFormOptions;
    can: { manage: boolean };
}

export default function RecurringIndex({ recurring, formOptions, can }: Props) {
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<EditableRecurring | null>(null);

    const openEdit = (r: RecurringRow) => {
        setEditing({
            id: r.id, name: r.name, vendor: r.vendor, amount: r.amount, currency: r.currency,
            payment_method: r.payment_method, frequency: r.frequency, interval_count: r.interval_count,
            start_date: r.start_date, end_date: r.end_date, auto_approve: r.auto_approve,
            is_active: r.is_active, is_billable: r.is_billable, category_id: r.category_id,
        });
        setFormOpen(true);
    };
    const openCreate = () => { setEditing(null); setFormOpen(true); };

    const generateNow = (r: RecurringRow) => {
        if (confirm(`Generate an expense now from "${r.name}"?`)) router.post(`/expenses/recurring/${r.id}/generate`);
    };
    const destroy = (r: RecurringRow) => {
        if (confirm(`Remove recurring cost "${r.name}"?`)) router.delete(`/expenses/recurring/${r.id}`, { preserveScroll: true });
    };

    const cadence = (r: RecurringRow) => r.interval_count > 1 ? `Every ${r.interval_count} ${r.frequency}s` : r.frequency_label;

    return (
        <ExpenseLayout>
            <Head title="Recurring costs · Expenses" />
            <div className="p-4 sm:p-6">
                <PageHeader
                    icon={RefreshCw}
                    title="Recurring costs"
                    description="Subscriptions & fixed costs that generate an expense each period"
                    actions={can.manage && <Button onClick={openCreate} icon={Plus}>Add Recurring Cost</Button>}
                />

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b border-border bg-secondary/40">
                                <tr>
                                    <th className="th">Cost</th>
                                    <th className="th hidden sm:table-cell">Cadence</th>
                                    <th className="th hidden md:table-cell">Next run</th>
                                    <th className="th">State</th>
                                    <th className="th text-right">Amount</th>
                                    <th className="th" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {recurring.length === 0 ? (
                                    <tr><td colSpan={6}>
                                        <EmptyState icon={RefreshCw} title="No recurring costs"
                                            description="Register subscriptions, rent or payroll once and let them post automatically each period."
                                            action={can.manage && <Button onClick={openCreate} icon={Plus}>Add Recurring Cost</Button>} />
                                    </td></tr>
                                ) : recurring.map(r => (
                                    <tr key={r.id}>
                                        <td className="td">
                                            <span className="font-medium text-foreground">{r.name}</span>
                                            <span className="block text-xs text-muted-foreground">{r.category ?? 'Uncategorized'}{r.vendor ? ` · ${r.vendor}` : ''}</span>
                                        </td>
                                        <td className="td hidden sm:table-cell"><Pill color={r.frequency_color} label={cadence(r)} /></td>
                                        <td className="td hidden text-muted-foreground md:table-cell">{r.is_active ? formatDate(r.next_run_date) : '—'}</td>
                                        <td className="td">
                                            <div className="flex flex-wrap gap-1">
                                                <Pill color={r.is_active ? 'green' : 'gray'} label={r.is_active ? 'Active' : 'Paused'} />
                                                {r.auto_approve && <Pill color="indigo" label="Auto" />}
                                            </div>
                                        </td>
                                        <td className="td text-right font-semibold text-foreground">{formatCurrency(r.amount, r.currency)}</td>
                                        <td className="td">
                                            {can.manage && (
                                                <div className="flex justify-end gap-1">
                                                    <button onClick={() => generateNow(r)} className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-primary" title="Generate now"><Play className="h-4 w-4" /></button>
                                                    <button onClick={() => openEdit(r)} className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-primary" title="Edit"><Pencil className="h-4 w-4" /></button>
                                                    <button onClick={() => destroy(r)} className="rounded-md p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive" title="Delete"><Trash2 className="h-4 w-4" /></button>
                                                </div>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>

            {formOpen && <RecurringFormModal open onClose={() => setFormOpen(false)} recurring={editing} formOptions={formOptions} />}
        </ExpenseLayout>
    );
}
