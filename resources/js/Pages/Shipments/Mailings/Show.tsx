import { Head, Link, router, useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import {
    ArrowLeft, RefreshCw, ExternalLink, MapPin, FileText, Pencil, Upload,
    Trash2, Download, Eye, File as FileIcon, Image as ImageIcon,
} from 'lucide-react';
import { ShipmentsLayout } from '@/Components/layout/ShipmentsLayout';
import { Pill } from '@/Components/ui/Pill';
import { Select } from '@/Components/ui/Select';
import { formatDate, formatDateTime } from '@/Lib/utils';

interface TrackingEvent {
    id: number;
    description: string;
    location: string | null;
    occurred_at: string;
}

interface Doc {
    id: number;
    name: string;
    type: string | null;
    size: string;
    is_image: boolean;
    download_url: string;
    preview_url: string;
    created_at: string | null;
}

interface Mailing {
    ulid: string;
    ups_tracking_number: string;
    carrier_label: string;
    tracking_url: string | null;
    scope: string;
    scope_label: string;
    scope_color: string;
    recipient_name: string | null;
    recipient_address: string | null;
    deadline: string | null;
    status_label: string;
    status_color: string;
    risk_label: string;
    risk_color: string;
    scheduled_delivery: string | null;
    delivered_at: string | null;
    received_by: string | null;
    proof_url: string | null;
    proposal: { id: number; project_name: string; proposal_number: string | null } | null;
    created_by: string | null;
    events: TrackingEvent[];
    documents: Doc[];
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div>
            <dt className="text-xs font-medium uppercase tracking-wider text-muted-foreground/70">{label}</dt>
            <dd className="mt-0.5 text-sm text-foreground">{children}</dd>
        </div>
    );
}

const DOC_TYPES = [
    { value: 'label', label: 'Shipping label' },
    { value: 'customs', label: 'Customs form' },
    { value: 'receipt', label: 'Receipt' },
    { value: 'other', label: 'Other' },
];

export default function MailingsShow({ mailing }: { mailing: Mailing }) {
    const [refreshing, setRefreshing] = useState(false);
    const [editing, setEditing] = useState(false);
    const [docType, setDocType] = useState('label');
    const [uploading, setUploading] = useState(false);
    const fileRef = useRef<HTMLInputElement>(null);

    const form = useForm({
        recipient_name: mailing.recipient_name ?? '',
        recipient_address: mailing.recipient_address ?? '',
        deadline: mailing.deadline ?? '',
        scope: mailing.scope,
    });

    const refresh = () => {
        setRefreshing(true);
        router.post(`/shipments/mailings/${mailing.ulid}/refresh`, {}, { preserveScroll: true, onFinish: () => setRefreshing(false) });
    };

    const save = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(`/shipments/mailings/${mailing.ulid}`, { preserveScroll: true, onSuccess: () => setEditing(false) });
    };

    const onFile = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;
        setUploading(true);
        router.post(`/shipments/mailings/${mailing.ulid}/documents`, { file, document_type: docType }, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => { setUploading(false); if (fileRef.current) fileRef.current.value = ''; },
        });
    };

    const removeDoc = (id: number) => {
        if (!confirm('Remove this document?')) return;
        router.delete(`/shipments/mailings/${mailing.ulid}/documents/${id}`, { preserveScroll: true });
    };

    return (
        <ShipmentsLayout>
            <Head title={`Mailing ${mailing.ups_tracking_number}`} />
            <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6">
                <Link href="/shipments/mailings" className="mb-4 inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" /> Back to mailings
                </Link>

                <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="rounded-md bg-secondary px-2 py-0.5 text-xs font-semibold text-muted-foreground">{mailing.carrier_label}</span>
                            <h1 className="font-mono text-xl font-bold tracking-tight text-foreground">{mailing.ups_tracking_number}</h1>
                            {mailing.tracking_url && (
                                <a href={mailing.tracking_url} target="_blank" rel="noreferrer" title={`Open on ${mailing.carrier_label}`} className="text-muted-foreground hover:text-primary">
                                    <ExternalLink className="h-4 w-4" />
                                </a>
                            )}
                        </div>
                        <div className="mt-2 flex flex-wrap items-center gap-2">
                            <Pill color={mailing.scope_color} label={mailing.scope_label} />
                            <Pill color={mailing.status_color} label={mailing.status_label} />
                            <Pill color={mailing.risk_color} label={mailing.risk_label} />
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <button onClick={() => setEditing(v => !v)} className="inline-flex items-center gap-2 rounded-full border border-border px-4 py-2 text-sm font-medium text-foreground transition hover:bg-secondary">
                            <Pencil className="h-4 w-4" /> {editing ? 'Cancel' : 'Edit'}
                        </button>
                        <button onClick={refresh} disabled={refreshing} className="inline-flex items-center gap-2 rounded-full border border-border px-4 py-2 text-sm font-medium text-foreground transition hover:bg-secondary disabled:opacity-60">
                            <RefreshCw className={`h-4 w-4 ${refreshing ? 'animate-spin' : ''}`} /> Refresh
                        </button>
                    </div>
                </div>

                <div className="grid gap-6 md:grid-cols-3">
                    <div className="md:col-span-1">
                        <div className="card-surface p-5">
                            {editing ? (
                                <form onSubmit={save} className="space-y-4">
                                    <div>
                                        <label className="label">Category</label>
                                        <Select value={form.data.scope} onChange={v => form.setData('scope', v)} className="w-full"
                                            options={[{ value: 'domestic', label: 'Domestic' }, { value: 'international', label: 'International' }]} />
                                    </div>
                                    <div>
                                        <label className="label">Recipient</label>
                                        <input value={form.data.recipient_name} onChange={e => form.setData('recipient_name', e.target.value)} className="input" />
                                    </div>
                                    <div>
                                        <label className="label">Address</label>
                                        <textarea value={form.data.recipient_address} onChange={e => form.setData('recipient_address', e.target.value)} className="input min-h-[72px]" />
                                    </div>
                                    <div>
                                        <label className="label">Deadline</label>
                                        <input type="date" value={form.data.deadline} onChange={e => form.setData('deadline', e.target.value)} className="input" />
                                    </div>
                                    <button type="submit" disabled={form.processing} className="bg-brand-gradient shadow-glow w-full rounded-full py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 disabled:opacity-60">
                                        {form.processing ? 'Saving…' : 'Save changes'}
                                    </button>
                                </form>
                            ) : (
                                <dl className="space-y-4">
                                    <Field label="Category">{mailing.scope_label}</Field>
                                    <Field label="Recipient">{mailing.recipient_name ?? '—'}</Field>
                                    {mailing.recipient_address && <Field label="Address"><span className="whitespace-pre-line text-muted-foreground">{mailing.recipient_address}</span></Field>}
                                    <Field label="Deadline">{formatDate(mailing.deadline)}</Field>
                                    <Field label="Scheduled delivery">{formatDate(mailing.scheduled_delivery)}</Field>
                                    {mailing.delivered_at && <Field label="Delivered">{formatDateTime(mailing.delivered_at)}</Field>}
                                    {mailing.received_by && <Field label="Received by">{mailing.received_by}</Field>}
                                    {mailing.proposal && (
                                        <Field label="Proposal">
                                            <span className="inline-flex items-center gap-1.5"><FileText className="h-3.5 w-3.5 text-muted-foreground" />{mailing.proposal.proposal_number ?? mailing.proposal.project_name}</span>
                                        </Field>
                                    )}
                                    {mailing.proof_url && (
                                        <a href={mailing.proof_url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline">
                                            <ExternalLink className="h-4 w-4" /> Proof of delivery
                                        </a>
                                    )}
                                    {mailing.created_by && <p className="pt-1 text-xs text-muted-foreground">Added by {mailing.created_by}</p>}
                                </dl>
                            )}
                        </div>
                    </div>

                    <div className="space-y-6 md:col-span-2">
                        <div className="card-surface p-5">
                            <h2 className="mb-4 text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Tracking timeline</h2>
                            {mailing.events.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No tracking events yet.</p>
                            ) : (
                                <ol className="relative space-y-5 border-l border-border pl-5">
                                    {mailing.events.map((e, i) => (
                                        <li key={e.id} className="relative">
                                            <span className={`absolute -left-[1.45rem] top-1 h-2.5 w-2.5 rounded-full ring-4 ring-card ${i === 0 ? 'bg-primary' : 'bg-muted-foreground/40'}`} />
                                            <p className="text-sm font-medium text-foreground">{e.description}</p>
                                            <p className="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-muted-foreground">
                                                <span>{formatDateTime(e.occurred_at)}</span>
                                                {e.location && <span className="inline-flex items-center gap-1"><MapPin className="h-3 w-3" />{e.location}</span>}
                                            </p>
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </div>

                        <div className="card-surface p-5">
                            <div className="mb-4 flex items-center justify-between gap-3">
                                <h2 className="text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Documents</h2>
                                <div className="flex items-center gap-2">
                                    <Select value={docType} onChange={setDocType} options={DOC_TYPES} size="sm" />
                                    <input ref={fileRef} type="file" accept="application/pdf,image/png,image/jpeg" className="hidden" onChange={onFile} />
                                    <button onClick={() => fileRef.current?.click()} disabled={uploading}
                                        className="inline-flex items-center gap-1.5 rounded-full bg-brand-gradient px-3.5 py-1.5 text-xs font-semibold text-white transition hover:-translate-y-0.5 disabled:opacity-60">
                                        <Upload className="h-3.5 w-3.5" /> {uploading ? 'Uploading…' : 'Upload'}
                                    </button>
                                </div>
                            </div>

                            {mailing.documents.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No documents. Upload the label, customs forms, or receipts (PDF, PNG, JPEG).</p>
                            ) : (
                                <ul className="space-y-2">
                                    {mailing.documents.map(d => (
                                        <li key={d.id} className="flex items-center gap-3 rounded-lg border border-border px-3 py-2">
                                            <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-secondary text-muted-foreground">
                                                {d.is_image ? <ImageIcon className="h-4 w-4" /> : <FileIcon className="h-4 w-4" />}
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate text-sm font-medium text-foreground">{d.name}</span>
                                                <span className="block text-xs text-muted-foreground">{[d.type, d.size].filter(Boolean).join(' · ')}</span>
                                            </span>
                                            <a href={d.preview_url} target="_blank" rel="noreferrer" title="Preview" className="text-muted-foreground hover:text-primary"><Eye className="h-4 w-4" /></a>
                                            <a href={d.download_url} title="Download" className="text-muted-foreground hover:text-primary"><Download className="h-4 w-4" /></a>
                                            <button onClick={() => removeDoc(d.id)} title="Remove" className="text-muted-foreground hover:text-destructive"><Trash2 className="h-4 w-4" /></button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </ShipmentsLayout>
    );
}
