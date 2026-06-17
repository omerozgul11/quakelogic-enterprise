import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Select } from '@/Components/ui/Select';
import { NumberInput } from '@/Components/ui/NumberInput';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { Modal } from '@/Components/ui/Modal';
import { formatCurrency, formatDate } from '@/Lib/utils';
import { FileSignature, Plus, Trash2, Check, Circle } from 'lucide-react';

interface Milestone { id: number; title: string; due_date: string | null; completed_at: string | null }
export interface ContractData {
    id: number;
    contract_number: string | null; po_number: string | null; invoice_number: string | null;
    stage: string; stage_label: string;
    payment_status: string; payment_label: string;
    contract_value: number | null; amount_invoiced: number | null; amount_paid: number | null;
    currency: string;
    signed_at: string | null; po_received_at: string | null; invoice_sent_at: string | null; paid_at: string | null;
    notes: string | null;
    milestones: Milestone[];
}
interface Option { value: string; label: string }
interface CurrencyOption { value: string; label: string; symbol: string }

interface Props {
    proposalId: number;
    contract: ContractData | null;
    options: { stages: Option[]; paymentStatuses: Option[] };
    currencies: CurrencyOption[];
    canEdit: boolean;
}

export function ContractPanel({ proposalId, contract, options, currencies, canEdit }: Props) {
    const [open, setOpen] = useState(false);
    const [milestoneTitle, setMilestoneTitle] = useState('');

    const form = useForm({
        contract_number: contract?.contract_number ?? '',
        po_number: contract?.po_number ?? '',
        invoice_number: contract?.invoice_number ?? '',
        stage: contract?.stage ?? 'contract_review',
        payment_status: contract?.payment_status ?? 'not_invoiced',
        contract_value: contract?.contract_value != null ? String(contract.contract_value) : '',
        amount_invoiced: contract?.amount_invoiced != null ? String(contract.amount_invoiced) : '',
        amount_paid: contract?.amount_paid != null ? String(contract.amount_paid) : '',
        currency: contract?.currency ?? 'USD',
        signed_at: contract?.signed_at ?? '',
        po_received_at: contract?.po_received_at ?? '',
        invoice_sent_at: contract?.invoice_sent_at ?? '',
        paid_at: contract?.paid_at ?? '',
        notes: contract?.notes ?? '',
    });

    const save = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(`/proposals/${proposalId}/contract`, { preserveScroll: true, onSuccess: () => setOpen(false) });
    };

    const addMilestone = (e: React.FormEvent) => {
        e.preventDefault();
        if (!contract || !milestoneTitle.trim()) return;
        router.post(`/contracts/${contract.id}/milestones`, { title: milestoneTitle }, {
            preserveScroll: true,
            onSuccess: () => setMilestoneTitle(''),
        });
    };
    const toggleMilestone = (m: Milestone) => {
        if (!contract) return;
        router.patch(`/contracts/${contract.id}/milestones/${m.id}`, { completed: !m.completed_at }, { preserveScroll: true });
    };
    const removeMilestone = (m: Milestone) => {
        if (!contract) return;
        router.delete(`/contracts/${contract.id}/milestones/${m.id}`, { preserveScroll: true });
    };

    const money = (v: number | null) => (v != null && v > 0 ? formatCurrency(v, contract?.currency) : '—');

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle className="flex items-center gap-2"><FileSignature className="h-4 w-4 text-primary" /> Contract & Financials</CardTitle>
                {canEdit && (
                    <Button size="sm" variant="secondary" onClick={() => setOpen(true)}>{contract ? 'Edit' : 'Add contract'}</Button>
                )}
            </CardHeader>
            <CardContent>
                {!contract ? (
                    <p className="py-2 text-sm text-muted-foreground">
                        No contract tracked yet. {canEdit ? 'Add contract details to track PO, invoice and payment through to paid.' : ''}
                    </p>
                ) : (
                    <div className="space-y-4">
                        <div className="flex flex-wrap items-center gap-2">
                            <StatusBadge status={contract.stage} label={contract.stage_label} />
                            <StatusBadge status={contract.payment_status} label={contract.payment_label} />
                        </div>

                        <div className="grid grid-cols-2 gap-x-4 gap-y-2.5 text-sm sm:grid-cols-3">
                            <Field label="Contract #" value={contract.contract_number} mono />
                            <Field label="PO #" value={contract.po_number} mono />
                            <Field label="Invoice #" value={contract.invoice_number} mono />
                            <Field label="Contract value" value={money(contract.contract_value)} />
                            <Field label="Invoiced" value={money(contract.amount_invoiced)} />
                            <Field label="Paid" value={money(contract.amount_paid)} />
                            <Field label="Signed" value={contract.signed_at ? formatDate(contract.signed_at) : null} />
                            <Field label="PO received" value={contract.po_received_at ? formatDate(contract.po_received_at) : null} />
                            <Field label="Invoice sent" value={contract.invoice_sent_at ? formatDate(contract.invoice_sent_at) : null} />
                            <Field label="Paid on" value={contract.paid_at ? formatDate(contract.paid_at) : null} />
                        </div>

                        {contract.notes && <p className="whitespace-pre-line rounded-lg bg-secondary/40 p-3 text-sm text-muted-foreground">{contract.notes}</p>}

                        {/* Delivery milestones */}
                        <div>
                            <p className="mb-2 text-xs font-bold uppercase tracking-wide text-muted-foreground">Delivery Milestones</p>
                            <div className="space-y-1.5">
                                {contract.milestones.length === 0 && <p className="text-sm text-muted-foreground">No milestones yet.</p>}
                                {contract.milestones.map(m => (
                                    <div key={m.id} className="flex items-center gap-2.5 rounded-lg border border-border px-3 py-2">
                                        <button onClick={() => canEdit && toggleMilestone(m)} disabled={!canEdit} className="shrink-0 text-muted-foreground hover:text-primary disabled:cursor-default" title={m.completed_at ? 'Mark incomplete' : 'Mark complete'}>
                                            {m.completed_at ? <Check className="h-4 w-4 text-emerald-500" /> : <Circle className="h-4 w-4" />}
                                        </button>
                                        <span className={`min-w-0 flex-1 truncate text-sm ${m.completed_at ? 'text-muted-foreground line-through' : 'text-foreground'}`}>{m.title}</span>
                                        {m.due_date && <span className="shrink-0 text-[11px] text-muted-foreground">{formatDate(m.due_date)}</span>}
                                        {canEdit && (
                                            <button onClick={() => removeMilestone(m)} className="shrink-0 text-muted-foreground hover:text-destructive" title="Remove"><Trash2 className="h-3.5 w-3.5" /></button>
                                        )}
                                    </div>
                                ))}
                            </div>
                            {canEdit && (
                                <form onSubmit={addMilestone} className="mt-2 flex items-center gap-2">
                                    <input className="input flex-1" placeholder="Add a milestone (e.g. FAT, SAT, installation)…" value={milestoneTitle} onChange={e => setMilestoneTitle(e.target.value)} />
                                    <Button type="submit" size="sm" icon={Plus} disabled={!milestoneTitle.trim()}>Add</Button>
                                </form>
                            )}
                        </div>
                    </div>
                )}
            </CardContent>

            <Modal open={open} onClose={() => setOpen(false)} title="Contract & financials" size="lg">
                <form onSubmit={save} className="space-y-4">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label className="label">Stage</label>
                            <Select value={form.data.stage} onChange={v => form.setData('stage', v)} options={options.stages} />
                        </div>
                        <div>
                            <label className="label">Payment status</label>
                            <Select value={form.data.payment_status} onChange={v => form.setData('payment_status', v)} options={options.paymentStatuses} />
                        </div>
                    </div>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div><label className="label">Contract #</label><input className="input" value={form.data.contract_number} onChange={e => form.setData('contract_number', e.target.value)} /></div>
                        <div><label className="label">PO #</label><input className="input" value={form.data.po_number} onChange={e => form.setData('po_number', e.target.value)} /></div>
                        <div><label className="label">Invoice #</label><input className="input" value={form.data.invoice_number} onChange={e => form.setData('invoice_number', e.target.value)} /></div>
                    </div>
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <div>
                            <label className="label">Currency</label>
                            <Select value={form.data.currency} onChange={v => form.setData('currency', v)} options={currencies.map(c => ({ value: c.value, label: c.value }))} />
                        </div>
                        <div><label className="label">Value</label><NumberInput className="input" value={form.data.contract_value} onChange={e => form.setData('contract_value', e.target.value)} /></div>
                        <div><label className="label">Invoiced</label><NumberInput className="input" value={form.data.amount_invoiced} onChange={e => form.setData('amount_invoiced', e.target.value)} /></div>
                        <div><label className="label">Paid</label><NumberInput className="input" value={form.data.amount_paid} onChange={e => form.setData('amount_paid', e.target.value)} /></div>
                    </div>
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <div><label className="label">Signed</label><input type="date" className="input" value={form.data.signed_at} onChange={e => form.setData('signed_at', e.target.value)} /></div>
                        <div><label className="label">PO received</label><input type="date" className="input" value={form.data.po_received_at} onChange={e => form.setData('po_received_at', e.target.value)} /></div>
                        <div><label className="label">Invoice sent</label><input type="date" className="input" value={form.data.invoice_sent_at} onChange={e => form.setData('invoice_sent_at', e.target.value)} /></div>
                        <div><label className="label">Paid on</label><input type="date" className="input" value={form.data.paid_at} onChange={e => form.setData('paid_at', e.target.value)} /></div>
                    </div>
                    <div><label className="label">Notes</label><textarea className="input" rows={3} value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} /></div>
                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="secondary" onClick={() => setOpen(false)}>Cancel</Button>
                        <Button type="submit" disabled={form.processing}>Save</Button>
                    </div>
                </form>
            </Modal>
        </Card>
    );
}

function Field({ label, value, mono }: { label: string; value: string | null; mono?: boolean }) {
    return (
        <div>
            <p className="text-[11px] uppercase tracking-wide text-muted-foreground">{label}</p>
            <p className={`text-sm text-foreground ${mono ? 'font-mono' : ''}`}>{value || '—'}</p>
        </div>
    );
}
