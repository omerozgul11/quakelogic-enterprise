import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { ExpenseLayout } from '@/Components/layout/ExpenseLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Pill } from '@/Components/ui/Pill';
import { EmptyState } from '@/Components/ui/EmptyState';
import { CategoryFormModal, EditableCategory } from '@/Components/expenses/CategoryFormModal';
import { formatCurrency, cn } from '@/Lib/utils';
import { Tags, Plus, Pencil, Trash2, AlertTriangle } from 'lucide-react';

interface CategoryRow {
    id: number; name: string; color: string | null;
    budget_amount: number | null; budget_period: string; currency: string; is_active: boolean;
    expenses_count: number; spent_this_period: number; over_budget: boolean; pct: number | null;
}

interface Props {
    categories: CategoryRow[];
    can: { manage: boolean };
}

export default function CategoriesIndex({ categories, can }: Props) {
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<EditableCategory | null>(null);

    const openEdit = (c: CategoryRow) => {
        setEditing({ id: c.id, name: c.name, color: c.color, budget_amount: c.budget_amount, budget_period: c.budget_period, currency: c.currency, is_active: c.is_active });
        setFormOpen(true);
    };
    const openCreate = () => { setEditing(null); setFormOpen(true); };

    const destroy = (c: CategoryRow) => {
        if (confirm(`Delete category "${c.name}"?`)) router.delete(`/expenses/categories/${c.id}`, { preserveScroll: true });
    };

    return (
        <ExpenseLayout>
            <Head title="Categories · Expenses" />
            <div className="p-4 sm:p-6">
                <PageHeader
                    icon={Tags}
                    title="Categories"
                    description="Group spend and set budgets per category"
                    actions={can.manage && <Button onClick={openCreate} icon={Plus}>Add Category</Button>}
                />

                {categories.length === 0 ? (
                    <Card className="p-6">
                        <EmptyState icon={Tags} title="No categories yet"
                            description="Create categories like Travel, Software or Marketing to organize expenses and track budgets."
                            action={can.manage && <Button onClick={openCreate} icon={Plus}>Add Category</Button>} />
                    </Card>
                ) : (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {categories.map(c => (
                            <Card key={c.id} className="flex flex-col p-5">
                                <div className="flex items-start justify-between">
                                    <div className="min-w-0">
                                        <h3 className="flex items-center gap-2 font-semibold text-foreground">
                                            {c.name}
                                            {!c.is_active && <Pill color="gray" label="Inactive" />}
                                        </h3>
                                        <p className="mt-0.5 text-xs text-muted-foreground">{c.expenses_count} {c.expenses_count === 1 ? 'expense' : 'expenses'}</p>
                                    </div>
                                    {can.manage && (
                                        <div className="flex shrink-0 items-center gap-1">
                                            <button onClick={() => openEdit(c)} className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-primary" title="Edit"><Pencil className="h-4 w-4" /></button>
                                            <button onClick={() => destroy(c)} className="rounded-md p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive" title="Delete"><Trash2 className="h-4 w-4" /></button>
                                        </div>
                                    )}
                                </div>

                                <div className="mt-4">
                                    <div className="flex items-baseline justify-between text-sm">
                                        <span className="font-semibold text-foreground">{formatCurrency(c.spent_this_period, c.currency)}</span>
                                        {c.budget_amount != null
                                            ? <span className="text-xs text-muted-foreground">of {formatCurrency(c.budget_amount, c.currency)} / {c.budget_period}</span>
                                            : <span className="text-xs text-muted-foreground">No budget</span>}
                                    </div>
                                    {c.budget_amount != null && (
                                        <div className="mt-2 h-2 overflow-hidden rounded-full bg-secondary">
                                            <div className={cn('h-full rounded-full', c.over_budget ? 'bg-red-500' : 'bg-emerald-500')} style={{ width: `${c.pct ?? 0}%` }} />
                                        </div>
                                    )}
                                    {c.over_budget && (
                                        <p className="mt-2 flex items-center gap-1 text-xs font-medium text-red-600"><AlertTriangle className="h-3.5 w-3.5" /> Over budget this {c.budget_period === 'yearly' ? 'year' : c.budget_period === 'quarterly' ? 'quarter' : 'month'}</p>
                                    )}
                                </div>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            {formOpen && <CategoryFormModal open onClose={() => setFormOpen(false)} category={editing} />}
        </ExpenseLayout>
    );
}
