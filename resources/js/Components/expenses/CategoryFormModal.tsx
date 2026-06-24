import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Modal } from '@/Components/ui/Modal';
import { Button } from '@/Components/ui/Button';
import { Select } from '@/Components/ui/Select';
import { Checkbox } from '@/Components/ui/Checkbox';

export interface EditableCategory {
    id: number;
    name?: string;
    color?: string | null;
    budget_amount?: number | string | null;
    budget_period?: string;
    currency?: string;
    is_active?: boolean;
}

interface Props {
    open: boolean;
    onClose: () => void;
    category?: EditableCategory | null;
}

const PERIODS = [
    { value: 'monthly', label: 'Monthly' },
    { value: 'quarterly', label: 'Quarterly' },
    { value: 'yearly', label: 'Yearly' },
];

const COLORS = ['blue', 'green', 'indigo', 'amber', 'purple', 'teal', 'red', 'cyan', 'orange', 'slate'];

export function CategoryFormModal({ open, onClose, category }: Props) {
    const isEdit = !!category;
    const form = useForm({
        name: category?.name ?? '',
        color: category?.color ?? 'indigo',
        budget_amount: category?.budget_amount != null ? String(category.budget_amount) : '',
        budget_period: category?.budget_period ?? 'monthly',
        currency: category?.currency ?? 'USD',
        is_active: category?.is_active ?? true,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            budget_amount: data.budget_amount === '' ? null : Number(data.budget_amount),
        }));
        const opts = { preserveScroll: true, onSuccess: () => { if (!isEdit) form.reset(); onClose(); } };
        if (isEdit) form.put(`/expenses/categories/${category!.id}`, opts);
        else form.post('/expenses/categories', opts);
    };

    const err = (k: keyof typeof form.data) => form.errors[k as keyof typeof form.errors];

    return (
        <Modal
            open={open}
            onClose={onClose}
            size="md"
            title={isEdit ? 'Edit Category' : 'Add Category'}
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>Cancel</Button>
                    <Button onClick={submit as unknown as () => void} disabled={form.processing}>
                        {form.processing ? 'Saving…' : isEdit ? 'Save Changes' : 'Add Category'}
                    </Button>
                </>
            }
        >
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <label className="label">Name *</label>
                    <input className="input" value={form.data.name} onChange={e => form.setData('name', e.target.value)} autoFocus placeholder="e.g. Travel" />
                    {err('name') && <p className="mt-1 text-xs text-destructive">{err('name')}</p>}
                </div>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <label className="label">Budget</label>
                        <input type="number" step="0.01" min="0" className="input" value={form.data.budget_amount} onChange={e => form.setData('budget_amount', e.target.value)} placeholder="Optional" />
                        {err('budget_amount') && <p className="mt-1 text-xs text-destructive">{err('budget_amount')}</p>}
                    </div>
                    <div>
                        <label className="label">Period</label>
                        <Select className="w-full" value={form.data.budget_period} onChange={v => form.setData('budget_period', v)} options={PERIODS} />
                    </div>
                    <div>
                        <label className="label">Currency</label>
                        <input className="input uppercase" maxLength={3} value={form.data.currency} onChange={e => form.setData('currency', e.target.value.toUpperCase())} />
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label className="label">Color</label>
                        <Select className="w-full" value={form.data.color} onChange={v => form.setData('color', v)} options={COLORS.map(c => ({ value: c, label: c.charAt(0).toUpperCase() + c.slice(1) }))} />
                    </div>
                    <div className="flex items-end">
                        <div className="flex items-center gap-2 pb-2 text-sm text-foreground">
                            <Checkbox checked={form.data.is_active} onChange={v => form.setData('is_active', v)} ariaLabel="Active" />
                            <span className="cursor-pointer" onClick={() => form.setData('is_active', !form.data.is_active)}>Active</span>
                        </div>
                    </div>
                </div>
            </form>
        </Modal>
    );
}
