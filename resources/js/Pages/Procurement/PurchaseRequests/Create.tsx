import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { ProcurementLayout } from '@/Components/layout/ProcurementLayout';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Select } from '@/Components/ui/Select';
import { LineItemsEditor, computeTotals, emptyLine, Line, ProductOpt } from '@/Components/procurement/LineItemsEditor';
import { CopyFromInvoice, SourceInvoice } from '@/Components/procurement/CopyFromInvoice';
import { formatCurrency } from '@/Lib/utils';
import { ArrowLeft, ClipboardList } from 'lucide-react';

interface Props {
    users: { id: number; name: string }[];
    projects: { id: number; name: string }[];
    products: ProductOpt[];
    sourceInvoices: SourceInvoice[];
}

export default function PurchaseRequestCreate({ users, projects, products, sourceInvoices }: Props) {
    const form = useForm({
        title: '',
        department: '',
        requester_id: '',
        crm_project_id: '',
        currency: 'USD',
        description: '',
        notes: '',
        items: [{ ...emptyLine }] as Line[],
    });

    const { subtotal, tax } = computeTotals(form.data.items);
    const total = subtotal + tax;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/procurement/purchase-requests', { preserveScroll: true });
    };

    return (
        <ProcurementLayout>
            <Head title="New Purchase Request · Procurement" />
            <form onSubmit={submit} className="mx-auto max-w-5xl px-4 py-6 sm:px-6">
                <Link href="/procurement/purchase-requests" className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" /> Purchase Requests
                </Link>

                <div className="mb-6 flex items-center gap-3">
                    <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-gradient text-white"><ClipboardList className="h-5 w-5" /></div>
                    <h1 className="text-2xl font-bold tracking-tight text-foreground">New Purchase Request</h1>
                </div>

                <CopyFromInvoice invoices={sourceInvoices} target="purchase-requests" />

                <Card className="mb-4 p-5">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="sm:col-span-2">
                            <label className="label">Title *</label>
                            <input className="input" autoFocus placeholder="What is this request for?" value={form.data.title} onChange={e => form.setData('title', e.target.value)} />
                            {form.errors.title && <p className="mt-1 text-xs text-destructive">{form.errors.title}</p>}
                        </div>
                        <div>
                            <label className="label">Requester</label>
                            <Select className="w-full" value={form.data.requester_id} placeholder="— Me —" searchable
                                onChange={v => form.setData('requester_id', v)} options={users.map(u => ({ value: String(u.id), label: u.name }))} />
                        </div>
                        <div>
                            <label className="label">Department</label>
                            <input className="input" placeholder="e.g. Engineering" value={form.data.department} onChange={e => form.setData('department', e.target.value)} />
                        </div>
                        <div>
                            <label className="label">Project</label>
                            <Select className="w-full" value={form.data.crm_project_id} placeholder="— None —" searchable
                                onChange={v => form.setData('crm_project_id', v)} options={projects.map(p => ({ value: String(p.id), label: p.name }))} />
                        </div>
                        <div>
                            <label className="label">Currency</label>
                            <input className="input uppercase" maxLength={3} value={form.data.currency} onChange={e => form.setData('currency', e.target.value.toUpperCase())} />
                        </div>
                        <div className="sm:col-span-2">
                            <label className="label">Description</label>
                            <textarea className="input min-h-[72px]" value={form.data.description} onChange={e => form.setData('description', e.target.value)} placeholder="Justification, specifications…" />
                        </div>
                    </div>
                </Card>

                <Card className="mb-4 overflow-hidden">
                    <div className="border-b border-border px-5 py-3"><h2 className="text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Requested items</h2></div>
                    <LineItemsEditor items={form.data.items} onChange={v => form.setData('items', v)} products={products} currency={form.data.currency} errors={form.errors as Record<string, string>} />
                </Card>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <Card className="p-5 lg:col-span-2">
                        <label className="label">Notes</label>
                        <textarea className="input min-h-[96px]" value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} placeholder="Anything the approver should know…" />
                    </Card>
                    <Card className="p-5">
                        <div className="space-y-2 text-sm">
                            <div className="flex items-center justify-between"><span className="text-muted-foreground">Subtotal</span><span className="text-foreground">{formatCurrency(subtotal, form.data.currency)}</span></div>
                            <div className="flex items-center justify-between"><span className="text-muted-foreground">Tax</span><span className="text-foreground">{formatCurrency(tax, form.data.currency)}</span></div>
                            <div className="flex items-center justify-between border-t border-border pt-2 text-base font-bold text-foreground"><span>Total</span><span>{formatCurrency(total, form.data.currency)}</span></div>
                        </div>
                        <Button type="submit" className="mt-4 w-full" disabled={form.processing}>{form.processing ? 'Creating…' : 'Create Request'}</Button>
                    </Card>
                </div>
            </form>
        </ProcurementLayout>
    );
}
