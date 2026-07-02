import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ProcurementLayout } from '@/Components/layout/ProcurementLayout';
import { Card } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Pill } from '@/Components/ui/Pill';
import { Select } from '@/Components/ui/Select';
import { Modal } from '@/Components/ui/Modal';
import { ArrowLeft, ShieldCheck, Plus, Pencil, Trash2, GripVertical, PenLine } from 'lucide-react';

interface Step { name: string; approver_type: 'user' | 'role'; approver_user_id: number | null; approver_user?: string | null; approver_role: string | null; require_signature: boolean }
interface Flow { id: number; name: string; document_type: string; document_type_label: string; min_amount: number; is_active: boolean; steps: Step[] }
interface Props {
    flows: Flow[];
    documentTypes: { value: string; label: string }[];
    users: { id: number; name: string }[];
    roles: string[];
}

const emptyStep = (): Step => ({ name: '', approver_type: 'user', approver_user_id: null, approver_role: null, require_signature: false });

export default function ApprovalFlowsIndex({ flows, documentTypes, users, roles }: Props) {
    const [editing, setEditing] = useState<Flow | null>(null);
    const [open, setOpen] = useState(false);

    const startNew = () => { setEditing(null); setOpen(true); };
    const startEdit = (f: Flow) => { setEditing(f); setOpen(true); };

    const byType = documentTypes.map(dt => ({ ...dt, flows: flows.filter(f => f.document_type === dt.value) }));

    return (
        <ProcurementLayout>
            <Head title="Approval flows · Procurement" />
            <div className="mx-auto max-w-4xl px-4 py-6 sm:px-6">
                <Link href="/procurement" className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" /> Procurement
                </Link>

                <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-gradient text-white"><ShieldCheck className="h-5 w-5" /></div>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight text-foreground">Approval flows</h1>
                            <p className="text-sm text-muted-foreground">Multi-level, amount-tiered approval chains for purchase requests, orders, and bill payments.</p>
                        </div>
                    </div>
                    <Button icon={Plus} onClick={startNew}>New flow</Button>
                </div>

                <div className="space-y-6">
                    {byType.map(dt => (
                        <div key={dt.value}>
                            <h2 className="mb-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70">{dt.label}</h2>
                            {dt.flows.length === 0 ? (
                                <Card className="p-4 text-sm text-muted-foreground">No flow — {dt.label.toLowerCase()}s use the simple single approval.</Card>
                            ) : (
                                <div className="space-y-2">
                                    {dt.flows.map(f => (
                                        <Card key={f.id} className="p-4">
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="min-w-0">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <span className="font-medium text-foreground">{f.name}</span>
                                                        {!f.is_active && <Pill color="gray" label="Inactive" />}
                                                        <span className="text-xs text-muted-foreground">applies when total ≥ ${f.min_amount.toLocaleString()}</span>
                                                    </div>
                                                    <ol className="mt-2 flex flex-wrap gap-1.5">
                                                        {f.steps.map((s, i) => (
                                                            <li key={i} className="inline-flex items-center gap-1 rounded-md bg-secondary px-2 py-1 text-xs text-foreground">
                                                                <span className="font-semibold text-muted-foreground">{i + 1}.</span>
                                                                {s.approver_type === 'user' ? (s.approver_user ?? `User #${s.approver_user_id}`) : `Role: ${s.approver_role}`}
                                                                {s.require_signature && <PenLine className="h-3 w-3 text-primary" />}
                                                            </li>
                                                        ))}
                                                    </ol>
                                                </div>
                                                <div className="flex shrink-0 items-center gap-1">
                                                    <button onClick={() => startEdit(f)} className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground"><Pencil className="h-4 w-4" /></button>
                                                    <button onClick={() => confirm('Delete this flow?') && router.delete(`/procurement/approval-flows/${f.id}`, { preserveScroll: true })} className="rounded-md p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"><Trash2 className="h-4 w-4" /></button>
                                                </div>
                                            </div>
                                        </Card>
                                    ))}
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            </div>

            {open && <FlowEditor key={editing?.id ?? 'new'} flow={editing} documentTypes={documentTypes} users={users} roles={roles} onClose={() => setOpen(false)} />}
        </ProcurementLayout>
    );
}

function FlowEditor({ flow, documentTypes, users, roles, onClose }: { flow: Flow | null; documentTypes: { value: string; label: string }[]; users: { id: number; name: string }[]; roles: string[]; onClose: () => void }) {
    const form = useForm({
        name: flow?.name ?? '',
        document_type: flow?.document_type ?? documentTypes[0]?.value ?? 'purchase_request',
        min_amount: String(flow?.min_amount ?? 0),
        is_active: flow?.is_active ?? true,
        steps: (flow?.steps?.length ? flow.steps.map(s => ({ ...s })) : [emptyStep()]) as Step[],
    });

    const setStep = (i: number, patch: Partial<Step>) =>
        form.setData('steps', form.data.steps.map((s, idx) => (idx === i ? { ...s, ...patch } : s)));
    const addStep = () => form.setData('steps', [...form.data.steps, emptyStep()]);
    const removeStep = (i: number) => form.setData('steps', form.data.steps.filter((_, idx) => idx !== i));

    const save = () => {
        const opts = { preserveScroll: true, onSuccess: () => onClose() };
        if (flow) form.put(`/procurement/approval-flows/${flow.id}`, opts);
        else form.post('/procurement/approval-flows', opts);
    };

    const userOpts = users.map(u => ({ value: String(u.id), label: u.name }));
    const roleOpts = roles.map(r => ({ value: r, label: r }));
    const stepErr = (i: number, k: string) => (form.errors as Record<string, string>)[`steps.${i}.${k}`];

    return (
        <Modal open onClose={onClose} title={flow ? 'Edit approval flow' : 'New approval flow'} size="lg"
            footer={<>
                <Button variant="ghost" onClick={onClose} disabled={form.processing}>Cancel</Button>
                <Button onClick={save} disabled={form.processing}>{form.processing ? 'Saving…' : 'Save flow'}</Button>
            </>}>
            <div className="space-y-4">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div className="sm:col-span-2">
                        <label className="label">Name *</label>
                        <input className="input" placeholder="e.g. Large PO sign-off" value={form.data.name} onChange={e => form.setData('name', e.target.value)} />
                        {form.errors.name && <p className="mt-1 text-xs text-destructive">{form.errors.name}</p>}
                    </div>
                    <div>
                        <label className="label">Applies to *</label>
                        <Select className="w-full" value={form.data.document_type} onChange={v => form.setData('document_type', v)} options={documentTypes.map(d => ({ value: d.value, label: d.label }))} />
                    </div>
                    <div>
                        <label className="label">Applies when total ≥</label>
                        <input type="number" min="0" step="0.01" className="input" value={form.data.min_amount} onChange={e => form.setData('min_amount', e.target.value)} />
                        {form.errors.min_amount && <p className="mt-1 text-xs text-destructive">{form.errors.min_amount}</p>}
                    </div>
                </div>
                <label className="flex items-center gap-2 text-sm text-foreground">
                    <input type="checkbox" className="h-4 w-4 rounded border-border" checked={form.data.is_active} onChange={e => form.setData('is_active', e.target.checked)} />
                    Active
                </label>

                <div>
                    <div className="mb-1 flex items-center justify-between">
                        <label className="label mb-0">Approval steps (in order) *</label>
                        <button type="button" onClick={addStep} className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"><Plus className="h-3.5 w-3.5" /> Add step</button>
                    </div>
                    {form.errors.steps && <p className="mb-1 text-xs text-destructive">{form.errors.steps}</p>}
                    <div className="space-y-2">
                        {form.data.steps.map((s, i) => (
                            <div key={i} className="rounded-lg border border-border p-3">
                                <div className="flex items-center gap-2">
                                    <GripVertical className="h-4 w-4 text-muted-foreground/50" />
                                    <span className="text-xs font-bold text-muted-foreground">Step {i + 1}</span>
                                    <input className="input h-8 flex-1 text-sm" placeholder="Label (optional)" value={s.name ?? ''} onChange={e => setStep(i, { name: e.target.value })} />
                                    {form.data.steps.length > 1 && <button type="button" onClick={() => removeStep(i)} className="text-muted-foreground hover:text-destructive"><Trash2 className="h-4 w-4" /></button>}
                                </div>
                                <div className="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    <Select className="w-full" value={s.approver_type} onChange={v => setStep(i, { approver_type: v as 'user' | 'role', approver_user_id: null, approver_role: null })}
                                        options={[{ value: 'user', label: 'Specific person' }, { value: 'role', label: 'Anyone with role' }]} />
                                    {s.approver_type === 'user'
                                        ? <Select className="w-full" searchable placeholder="Select person…" value={s.approver_user_id ? String(s.approver_user_id) : ''} onChange={v => setStep(i, { approver_user_id: Number(v) })} options={userOpts} />
                                        : <Select className="w-full" searchable placeholder="Select role…" value={s.approver_role ?? ''} onChange={v => setStep(i, { approver_role: v })} options={roleOpts} />}
                                </div>
                                {(stepErr(i, 'approver_user_id') || stepErr(i, 'approver_role')) && <p className="mt-1 text-xs text-destructive">Choose an approver for this step.</p>}
                                <label className="mt-2 flex items-center gap-2 text-xs text-muted-foreground">
                                    <input type="checkbox" className="h-3.5 w-3.5 rounded border-border" checked={s.require_signature} onChange={e => setStep(i, { require_signature: e.target.checked })} />
                                    Require a digital signature at this step
                                </label>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </Modal>
    );
}
