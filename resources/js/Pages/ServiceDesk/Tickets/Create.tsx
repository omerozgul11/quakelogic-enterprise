import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { ServiceDeskLayout } from '@/Components/layout/ServiceDeskLayout';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Select } from '@/Components/ui/Select';
import { ArrowLeft, LifeBuoy } from 'lucide-react';

interface FormData {
    companies: { id: number; name: string }[];
    contacts: { id: number; first_name: string; last_name: string; company_id: number | null }[];
    assets: { id: number; asset_tag: string; name: string }[];
    products: { id: number; sku: string; name: string }[];
    users: { id: number; name: string }[];
}

interface Props {
    form: FormData;
    types: { value: string; label: string }[];
    priorities: { value: string; label: string }[];
}

const DISPOSITIONS = [
    { value: 'repair', label: 'Repair' }, { value: 'replace', label: 'Replace' },
    { value: 'refund', label: 'Refund' }, { value: 'reject', label: 'Reject' },
];

export default function TicketCreate({ form: opts, types, priorities }: Props) {
    const form = useForm({
        subject: '',
        description: '',
        type: 'support',
        priority: 'normal',
        channel: 'web',
        company_id: '',
        contact_id: '',
        asset_id: '',
        inventory_product_id: '',
        assigned_to: '',
        serial_number: '',
        rma_disposition: '',
    });

    const isRma = form.data.type === 'rma';
    const contactsForCompany = form.data.company_id
        ? opts.contacts.filter(c => String(c.company_id) === form.data.company_id)
        : opts.contacts;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/tickets/queue', { preserveScroll: true });
    };
    const err = (k: keyof typeof form.data) => form.errors[k as keyof typeof form.errors];

    return (
        <ServiceDeskLayout>
            <Head title="New Ticket · Service Desk" />
            <form onSubmit={submit} className="mx-auto max-w-2xl px-4 py-6 sm:px-6">
                <Link href="/tickets/queue" className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" /> Tickets
                </Link>

                <div className="mb-6 flex items-center gap-3">
                    <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-gradient text-white"><LifeBuoy className="h-5 w-5" /></div>
                    <h1 className="text-2xl font-bold tracking-tight text-foreground">New Ticket</h1>
                </div>

                <Card className="p-5">
                    <div className="space-y-4">
                        <div>
                            <label className="label">Subject *</label>
                            <input className="input" value={form.data.subject} onChange={e => form.setData('subject', e.target.value)} autoFocus />
                            {err('subject') && <p className="mt-1 text-xs text-destructive">{err('subject')}</p>}
                        </div>
                        <div className="grid grid-cols-3 gap-3">
                            <div><label className="label">Type</label><Select className="w-full" value={form.data.type} onChange={v => form.setData('type', v)} options={types} /></div>
                            <div><label className="label">Priority</label><Select className="w-full" value={form.data.priority} onChange={v => form.setData('priority', v)} options={priorities} /></div>
                            <div><label className="label">Channel</label><Select className="w-full" value={form.data.channel} onChange={v => form.setData('channel', v)} options={[{ value: 'web', label: 'Web' }, { value: 'email', label: 'Email' }, { value: 'phone', label: 'Phone' }, { value: 'portal', label: 'Portal' }]} /></div>
                        </div>
                        <div>
                            <label className="label">Description</label>
                            <textarea className="input min-h-[100px]" value={form.data.description} onChange={e => form.setData('description', e.target.value)} placeholder="What's the issue?" />
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="label">Client</label>
                                <Select className="w-full" value={form.data.company_id} placeholder="— None —" onChange={v => form.setData(d => ({ ...d, company_id: v, contact_id: '' }))} options={opts.companies.map(c => ({ value: String(c.id), label: c.name }))} />
                            </div>
                            <div>
                                <label className="label">Contact</label>
                                <Select className="w-full" value={form.data.contact_id} placeholder="— None —" onChange={v => form.setData('contact_id', v)} options={contactsForCompany.map(c => ({ value: String(c.id), label: `${c.first_name} ${c.last_name}` }))} />
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="label">Related asset</label>
                                <Select className="w-full" value={form.data.asset_id} placeholder="— None —" onChange={v => form.setData('asset_id', v)} options={opts.assets.map(a => ({ value: String(a.id), label: `${a.asset_tag} · ${a.name}` }))} />
                            </div>
                            <div>
                                <label className="label">Assign to</label>
                                <Select className="w-full" value={form.data.assigned_to} placeholder="— Unassigned —" onChange={v => form.setData('assigned_to', v)} options={opts.users.map(u => ({ value: String(u.id), label: u.name }))} />
                            </div>
                        </div>

                        {isRma && (
                            <div className="grid grid-cols-3 gap-3 rounded-lg border border-amber-200 bg-amber-50/50 p-3 dark:border-amber-900/50 dark:bg-amber-950/20">
                                <div className="col-span-1">
                                    <label className="label">Returned product</label>
                                    <Select className="w-full" value={form.data.inventory_product_id} placeholder="— None —" onChange={v => form.setData('inventory_product_id', v)} options={opts.products.map(p => ({ value: String(p.id), label: p.sku }))} />
                                </div>
                                <div><label className="label">Serial #</label><input className="input" value={form.data.serial_number} onChange={e => form.setData('serial_number', e.target.value)} /></div>
                                <div><label className="label">Disposition</label><Select className="w-full" value={form.data.rma_disposition} placeholder="—" onChange={v => form.setData('rma_disposition', v)} options={DISPOSITIONS} /></div>
                            </div>
                        )}

                        <div className="flex justify-end">
                            <Button type="submit" disabled={form.processing}>{form.processing ? 'Creating…' : 'Create Ticket'}</Button>
                        </div>
                    </div>
                </Card>
            </form>
        </ServiceDeskLayout>
    );
}
