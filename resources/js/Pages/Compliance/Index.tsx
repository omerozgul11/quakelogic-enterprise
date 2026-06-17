import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { StatCard } from '@/Components/ui/StatCard';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Select } from '@/Components/ui/Select';
import { Modal, ConfirmDialog } from '@/Components/ui/Modal';
import { cn, formatDate } from '@/Lib/utils';
import { ShieldCheck, Plus, Pencil, Trash2, AlertTriangle, ExternalLink, UploadCloud, Sparkles } from 'lucide-react';

interface Item {
    id: number;
    type: string; type_label: string;
    name: string;
    identifier: string | null;
    status: string; status_label: string; status_color: string;
    issuer: string | null;
    issued_at: string | null;
    expires_at: string | null;
    renewal_interval: string | null;
    reference_url: string | null;
    notes: string | null;
    days_until_expiry: number | null;
    expiring_soon: boolean;
    is_expired: boolean;
}
interface Option { value: string; label: string }
interface Props {
    items: Item[];
    types: Option[];
    statuses: Option[];
    renewalIntervals: Option[];
    summary: { total: number; expiring_soon: number; expired: number };
    can: { manage: boolean };
}

const blank = { type: 'insurance', name: '', identifier: '', status: 'active', issuer: '', issued_at: '', expires_at: '', renewal_interval: '', reference_url: '', notes: '' };

export default function ComplianceIndex({ items, types, statuses, renewalIntervals, summary, can }: Props) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<Item | null>(null);
    const [deleting, setDeleting] = useState<Item | null>(null);
    const [dragging, setDragging] = useState(false);
    const [importing, setImporting] = useState(false);
    const form = useForm<typeof blank>({ ...blank });

    const importFile = (file: File | null | undefined) => {
        if (!file) return;
        setImporting(true);
        router.post('/compliance/import', { document: file }, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => { setImporting(false); setDragging(false); },
        });
    };

    const openAdd = () => { setEditing(null); form.setData({ ...blank }); setOpen(true); };
    const openEdit = (i: Item) => {
        setEditing(i);
        form.setData({
            type: i.type, name: i.name, identifier: i.identifier ?? '', status: i.status,
            issuer: i.issuer ?? '', issued_at: i.issued_at ?? '', expires_at: i.expires_at ?? '',
            renewal_interval: i.renewal_interval ?? '',
            reference_url: i.reference_url ?? '', notes: i.notes ?? '',
        });
        setOpen(true);
    };
    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => setOpen(false) };
        if (editing) form.put(`/compliance/${editing.id}`, opts);
        else form.post('/compliance', opts);
    };

    const expiryCell = (i: Item) => {
        const renew = i.renewal_interval ? <span className="text-[10px] text-muted-foreground">↻ renews {i.renewal_interval}</span> : null;
        if (!i.expires_at) return <div className="flex flex-col gap-0.5"><span className="text-muted-foreground">—</span>{renew}</div>;
        const cls = i.is_expired ? 'text-red-600 font-semibold' : i.expiring_soon ? 'text-amber-600 font-medium' : 'text-muted-foreground';
        return (
            <div className="flex flex-col gap-0.5">
                <span className={`inline-flex items-center gap-1 text-xs ${cls}`}>
                    {(i.is_expired || i.expiring_soon) && <AlertTriangle className="h-3.5 w-3.5" />}
                    {formatDate(i.expires_at)}
                </span>
                {renew}
            </div>
        );
    };

    return (
        <AppLayout>
            <Head title="Compliance" />
            <div className="p-4 sm:p-6">
                <PageHeader
                    icon={ShieldCheck}
                    title="Compliance Register"
                    description="W-9, insurance, ISO, SAM, CAGE, UEI, NDAs & vendor registrations — kept current with expiry tracking."
                    actions={can.manage && <Button onClick={openAdd} icon={Plus}>Add Item</Button>}
                />

                <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <StatCard title="Tracked Items" value={summary.total} icon={ShieldCheck} tone="indigo" />
                    <StatCard title="Expiring Soon" value={summary.expiring_soon} icon={AlertTriangle} tone="amber" subtitle="Within 45 days" />
                    <StatCard title="Expired" value={summary.expired} icon={AlertTriangle} tone="rose" />
                </div>

                {/* AI document import — drop a cert/registration and let the AI Brain fill it in */}
                {can.manage && (
                    <label
                        onDragOver={e => { e.preventDefault(); setDragging(true); }}
                        onDragLeave={() => setDragging(false)}
                        onDrop={e => { e.preventDefault(); importFile(e.dataTransfer.files?.[0]); }}
                        className={cn(
                            'mb-6 flex cursor-pointer flex-col items-center justify-center gap-1.5 rounded-2xl border-2 border-dashed px-6 py-7 text-center transition-colors',
                            dragging ? 'border-primary bg-primary/[0.04]' : 'border-border hover:border-primary/50 hover:bg-secondary/40',
                        )}
                    >
                        <input type="file" className="hidden" disabled={importing}
                            onChange={e => importFile(e.target.files?.[0])}
                            accept=".pdf,.doc,.docx,.txt,.png,.jpg,.jpeg" />
                        {importing ? (
                            <><Sparkles className="h-6 w-6 animate-pulse text-primary" /><p className="text-sm font-medium text-foreground">Reading document…</p></>
                        ) : (
                            <>
                                <UploadCloud className="h-6 w-6 text-muted-foreground" />
                                <p className="text-sm font-medium text-foreground">Drop a document to auto-fill a compliance item</p>
                                <p className="text-xs text-muted-foreground">W-9, insurance cert, SAM/registration… the AI reads it and creates the entry for you to review.</p>
                            </>
                        )}
                    </label>
                )}

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b border-border bg-secondary/40">
                                <tr>
                                    <th className="th">Item</th>
                                    <th className="th">Type</th>
                                    <th className="th">Status</th>
                                    <th className="th hidden md:table-cell">Identifier</th>
                                    <th className="th hidden sm:table-cell">Expires</th>
                                    <th className="th" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {items.length === 0 ? (
                                    <tr><td colSpan={6}><EmptyState icon={ShieldCheck} title="Nothing tracked yet" description="Add your W-9, insurance, ISO, SAM, CAGE/UEI and other compliance items to keep them current." action={can.manage && <Button onClick={openAdd} icon={Plus}>Add Item</Button>} /></td></tr>
                                ) : items.map(i => (
                                    <tr key={i.id} className="row-link">
                                        <td className="td">
                                            <p className="text-sm font-medium text-foreground">{i.name}</p>
                                            {i.issuer && <p className="text-[11px] text-muted-foreground">{i.issuer}</p>}
                                        </td>
                                        <td className="td text-muted-foreground">{i.type_label}</td>
                                        <td className="td"><StatusBadge status={i.status} label={i.status_label} /></td>
                                        <td className="td hidden md:table-cell font-mono text-xs text-muted-foreground">{i.identifier ?? '—'}</td>
                                        <td className="td hidden sm:table-cell">{expiryCell(i)}</td>
                                        <td className="td">
                                            <div className="flex items-center justify-end gap-1">
                                                {i.reference_url && (
                                                    <a href={i.reference_url} target="_blank" rel="noopener noreferrer" className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-primary" title="Open reference"><ExternalLink className="h-4 w-4" /></a>
                                                )}
                                                {can.manage && (
                                                    <>
                                                        <button onClick={() => openEdit(i)} className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground" title="Edit"><Pencil className="h-4 w-4" /></button>
                                                        <button onClick={() => setDeleting(i)} className="rounded-md p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive" title="Delete"><Trash2 className="h-4 w-4" /></button>
                                                    </>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>

            <Modal open={open} onClose={() => setOpen(false)} title={editing ? 'Edit compliance item' : 'Add compliance item'} size="lg">
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label className="label">Type *</label>
                            <Select value={form.data.type} onChange={v => form.setData('type', v)} options={types} />
                        </div>
                        <div>
                            <label className="label">Status *</label>
                            <Select value={form.data.status} onChange={v => form.setData('status', v)} options={statuses} />
                        </div>
                    </div>
                    <div>
                        <label className="label">Name *</label>
                        <input className="input" value={form.data.name} onChange={e => form.setData('name', e.target.value)} placeholder="e.g. General Liability Insurance" required />
                        {form.errors.name && <p className="mt-1 text-xs text-destructive">{form.errors.name}</p>}
                    </div>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label className="label">Identifier</label>
                            <input className="input" value={form.data.identifier} onChange={e => form.setData('identifier', e.target.value)} placeholder="Policy #, CAGE, UEI, cert #…" />
                        </div>
                        <div>
                            <label className="label">Issuer</label>
                            <input className="input" value={form.data.issuer} onChange={e => form.setData('issuer', e.target.value)} placeholder="Carrier / authority" />
                        </div>
                    </div>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label className="label">Issued</label>
                            <input type="date" className="input" value={form.data.issued_at} onChange={e => form.setData('issued_at', e.target.value)} />
                        </div>
                        <div>
                            <label className="label">Expires</label>
                            <input type="date" className="input" value={form.data.expires_at} onChange={e => form.setData('expires_at', e.target.value)} />
                        </div>
                    </div>
                    <div>
                        <label className="label">Renewal cadence</label>
                        <Select value={form.data.renewal_interval} onChange={v => form.setData('renewal_interval', v)} options={[{ value: '', label: 'None / one-time' }, ...renewalIntervals]} />
                    </div>
                    <div>
                        <label className="label">Reference URL</label>
                        <input className="input" value={form.data.reference_url} onChange={e => form.setData('reference_url', e.target.value)} placeholder="https://…" />
                        {form.errors.reference_url && <p className="mt-1 text-xs text-destructive">{form.errors.reference_url}</p>}
                    </div>
                    <div>
                        <label className="label">Notes</label>
                        <textarea className="input" rows={2} value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} />
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="secondary" onClick={() => setOpen(false)}>Cancel</Button>
                        <Button type="submit" disabled={form.processing}>{editing ? 'Save' : 'Add'}</Button>
                    </div>
                </form>
            </Modal>

            <ConfirmDialog
                open={!!deleting}
                onClose={() => setDeleting(null)}
                onConfirm={() => deleting && router.delete(`/compliance/${deleting.id}`, { preserveScroll: true, onFinish: () => setDeleting(null) })}
                title="Remove compliance item?"
                message={deleting ? `This removes "${deleting.name}" from the register.` : ''}
            />
        </AppLayout>
    );
}
