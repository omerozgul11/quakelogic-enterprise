import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { CrmLayout } from '@/Components/layout/CrmLayout';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Select } from '@/Components/ui/Select';
import { NumberInput } from '@/Components/ui/NumberInput';
import { formatCurrency } from '@/Lib/utils';
import { ArrowLeft, Plus, Trash2 } from 'lucide-react';

interface InvoiceItem { description: string; quantity: number | string; unit_price: number | string }
interface Invoice {
    id: number; number: string; kind: string; status: string;
    company_id: number | null; crm_project_id: number | null;
    issue_date: string | null; due_date: string | null;
    tax_rate: number; discount_amount: number; currency: string;
    notes: string | null; terms: string | null;
    items: InvoiceItem[];
}

interface Props {
    invoice: Invoice | null;
    kind: string;
    companies: Array<{ id: number; name: string }>;
    projects: Array<{ id: number; name: string }>;
    statuses: Array<{ value: string; label: string; color: string }>;
}

export default function InvoiceForm({ invoice, kind, companies, projects, statuses }: Props) {
    const isEdit = !!invoice;
    const form = useForm({
        kind: invoice?.kind ?? kind,
        status: invoice?.status ?? 'draft',
        company_id: invoice?.company_id ? String(invoice.company_id) : '',
        crm_project_id: invoice?.crm_project_id ? String(invoice.crm_project_id) : '',
        issue_date: invoice?.issue_date ?? '',
        due_date: invoice?.due_date ?? '',
        tax_rate: invoice?.tax_rate != null ? String(invoice.tax_rate) : '0',
        discount_amount: invoice?.discount_amount != null ? String(invoice.discount_amount) : '0',
        currency: invoice?.currency ?? 'USD',
        notes: invoice?.notes ?? '',
        terms: invoice?.terms ?? '',
        create_project: false as boolean,
        items: invoice?.items?.length
            ? invoice.items.map(i => ({ description: i.description, quantity: String(i.quantity), unit_price: String(i.unit_price) }))
            : [{ description: '', quantity: '1', unit_price: '0' }],
    });

    const items = form.data.items;
    const setItem = (i: number, key: keyof InvoiceItem, value: string) => {
        const next = items.map((it, idx) => (idx === i ? { ...it, [key]: value } : it));
        form.setData('items', next);
    };
    const addItem = () => form.setData('items', [...items, { description: '', quantity: '1', unit_price: '0' }]);
    const removeItem = (i: number) => form.setData('items', items.filter((_, idx) => idx !== i));

    const subtotal = items.reduce((s, it) => s + (parseFloat(String(it.quantity)) || 0) * (parseFloat(String(it.unit_price)) || 0), 0);
    const taxAmount = subtotal * (parseFloat(form.data.tax_rate) || 0) / 100;
    const total = Math.max(0, subtotal + taxAmount - (parseFloat(form.data.discount_amount) || 0));

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform(d => ({
            ...d,
            company_id: d.company_id || null,
            crm_project_id: d.crm_project_id || null,
            issue_date: d.issue_date || null,
            due_date: d.due_date || null,
            items: d.items.filter(it => it.description.trim() !== ''),
        }));
        if (isEdit) form.put(`/crm/invoices/${invoice!.id}`);
        else form.post('/crm/invoices');
    };

    const isEstimate = form.data.kind === 'estimate';
    const label = isEstimate ? 'Estimate' : 'Invoice';

    return (
        <CrmLayout>
            <Head title={isEdit ? `Edit ${invoice!.number}` : `New ${label}`} />
            <form onSubmit={submit} className="mx-auto max-w-4xl px-4 py-6 sm:px-6">
                <Link href={isEdit ? `/crm/invoices/${invoice!.id}` : '/crm/invoices'} className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" /> {isEdit ? invoice!.number : 'Invoices'}
                </Link>

                <div className="mb-5 flex items-center justify-between gap-4">
                    <h1 className="text-2xl font-bold tracking-tight text-foreground">{isEdit ? `Edit ${invoice!.number}` : `New ${label}`}</h1>
                    <Button type="submit" disabled={form.processing}>{form.processing ? 'Saving…' : isEdit ? 'Save Changes' : `Create ${label}`}</Button>
                </div>

                <Card className="mb-4 p-5">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label className="label">Type</label>
                            <Select className="w-full" value={form.data.kind} onChange={v => form.setData('kind', v)} options={[{ value: 'invoice', label: 'Invoice' }, { value: 'estimate', label: 'Estimate' }]} />
                        </div>
                        <div>
                            <label className="label">Client</label>
                            <Select className="w-full" value={form.data.company_id} onChange={v => form.setData('company_id', v)} placeholder="— None —" options={companies.map(c => ({ value: String(c.id), label: c.name }))} />
                        </div>
                        <div>
                            <label className="label">Project</label>
                            <Select className="w-full" value={form.data.crm_project_id}
                                onChange={v => form.setData(d => ({ ...d, crm_project_id: v, create_project: v ? false : d.create_project }))}
                                placeholder="— None —" options={projects.map(p => ({ value: String(p.id), label: p.name }))} />
                            {!form.data.crm_project_id && (
                                <label className="mt-1.5 flex items-center gap-2 text-xs text-muted-foreground">
                                    <input type="checkbox" className="h-3.5 w-3.5 rounded border-border" checked={form.data.create_project}
                                        onChange={e => form.setData('create_project', e.target.checked)} />
                                    Create a new project for this invoice
                                </label>
                            )}
                        </div>
                        <div>
                            <label className="label">Status</label>
                            <Select className="w-full" value={form.data.status} onChange={v => form.setData('status', v)} options={statuses.map(s => ({ value: s.value, label: s.label }))} />
                        </div>
                        <div>
                            <label className="label">{isEstimate ? 'Date' : 'Issue date'}</label>
                            <input type="date" className="input" value={form.data.issue_date} onChange={e => form.setData('issue_date', e.target.value)} />
                        </div>
                        <div>
                            <label className="label">{isEstimate ? 'Valid until' : 'Due date'}</label>
                            <input type="date" className="input" value={form.data.due_date} onChange={e => form.setData('due_date', e.target.value)} />
                        </div>
                    </div>
                </Card>

                <Card className="mb-4 overflow-hidden">
                    <div className="border-b border-border px-5 py-3"><h2 className="text-sm font-semibold text-foreground">Line items</h2></div>
                    <div className="divide-y divide-border">
                        {items.map((it, i) => (
                            <div key={i} className="flex items-start gap-2 px-5 py-3">
                                <div className="flex-1">
                                    <input className="input" placeholder="Description" value={it.description} onChange={e => setItem(i, 'description', e.target.value)} />
                                </div>
                                <div className="w-20">
                                    <NumberInput className="input text-right" placeholder="Qty" value={String(it.quantity)} onChange={e => setItem(i, 'quantity', e.target.value)} />
                                </div>
                                <div className="w-28">
                                    <NumberInput className="input text-right" placeholder="Price" value={String(it.unit_price)} onChange={e => setItem(i, 'unit_price', e.target.value)} />
                                </div>
                                <div className="w-28 pt-2 text-right text-sm font-medium text-foreground">
                                    {formatCurrency((parseFloat(String(it.quantity)) || 0) * (parseFloat(String(it.unit_price)) || 0), form.data.currency)}
                                </div>
                                <button type="button" onClick={() => removeItem(i)} className="mt-1.5 rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive" title="Remove">
                                    <Trash2 className="h-4 w-4" />
                                </button>
                            </div>
                        ))}
                    </div>
                    <div className="px-5 py-3">
                        <button type="button" onClick={addItem} className="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline"><Plus className="h-4 w-4" /> Add line</button>
                    </div>
                </Card>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <Card className="p-5">
                        <div className="space-y-3">
                            <div>
                                <label className="label">Notes</label>
                                <textarea className="input min-h-[72px]" value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} placeholder="Visible to the client" />
                            </div>
                            <div>
                                <label className="label">Terms</label>
                                <textarea className="input min-h-[56px]" value={form.data.terms} onChange={e => form.setData('terms', e.target.value)} placeholder="Payment terms, conditions…" />
                            </div>
                        </div>
                    </Card>

                    <Card className="p-5">
                        <div className="space-y-2.5 text-sm">
                            <div className="flex items-center justify-between"><span className="text-muted-foreground">Subtotal</span><span className="font-medium text-foreground">{formatCurrency(subtotal, form.data.currency)}</span></div>
                            <div className="flex items-center justify-between gap-3">
                                <span className="text-muted-foreground">Tax rate %</span>
                                <NumberInput className="input h-9 w-24 text-right" value={form.data.tax_rate} onChange={e => form.setData('tax_rate', e.target.value)} />
                            </div>
                            <div className="flex items-center justify-between"><span className="text-muted-foreground">Tax</span><span className="font-medium text-foreground">{formatCurrency(taxAmount, form.data.currency)}</span></div>
                            <div className="flex items-center justify-between gap-3">
                                <span className="text-muted-foreground">Discount</span>
                                <NumberInput className="input h-9 w-28 text-right" value={form.data.discount_amount} onChange={e => form.setData('discount_amount', e.target.value)} />
                            </div>
                            <div className="flex items-center justify-between gap-3">
                                <span className="text-muted-foreground">Currency</span>
                                <input className="input h-9 w-24 text-right uppercase" maxLength={3} value={form.data.currency} onChange={e => form.setData('currency', e.target.value.toUpperCase())} />
                            </div>
                            <div className="flex items-center justify-between border-t border-border pt-3 text-base"><span className="font-semibold text-foreground">Total</span><span className="font-bold text-foreground">{formatCurrency(total, form.data.currency)}</span></div>
                        </div>
                    </Card>
                </div>
            </form>
        </CrmLayout>
    );
}
