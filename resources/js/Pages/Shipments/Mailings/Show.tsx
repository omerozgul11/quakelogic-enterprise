import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import {
    ArrowLeft, RefreshCw, ExternalLink, MapPin, FileText, Pencil, Upload,
    Trash2, Download, Eye, File as FileIcon, Image as ImageIcon,
} from 'lucide-react';
import { ShipmentsLayout } from '@/Components/layout/ShipmentsLayout';
import { Pill } from '@/Components/ui/Pill';
import { Select } from '@/Components/ui/Select';
import { Combobox } from '@/Components/ui/Combobox';
import { CarrierField } from '@/Components/ui/CarrierField';
import { Modal } from '@/Components/ui/Modal';
import { formatDate, formatDateTime } from '@/Lib/utils';

interface LinkableProposal {
    id: number;
    label: string;
    due_date: string | null;
}

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
    carrier: string;
    carrier_label: string;
    tracking_url: string | null;
    reference_type: string | null;
    reference_type_label: string | null;
    scope: string;
    scope_label: string;
    scope_color: string;
    recipient_name: string | null;
    recipient_address: string | null;
    deadline: string | null;
    status: string;
    status_label: string;
    status_color: string;
    risk_label: string;
    risk_color: string;
    scheduled_delivery: string | null;
    delivered_at: string | null;
    delivered_on: string | null;
    received_by: string | null;
    auto_track: boolean;
    current_location: string | null;
    last_update: string | null;
    proof_url: string | null;
    proposal: { id: number; project_name: string; proposal_number: string | null } | null;
    created_by: string | null;
    created_at: string | null;
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
    { value: 'bill_of_lading', label: 'Bill of lading' },
    { value: 'customs', label: 'Customs form' },
    { value: 'receipt', label: 'Receipt' },
    { value: 'other', label: 'Other' },
];

const DOC_TYPE_LABELS: Record<string, string> = Object.fromEntries(DOC_TYPES.map(d => [d.value, d.label]));

const STATUS_OPTIONS = [
    { value: 'label_created', label: 'Label created' },
    { value: 'in_transit', label: 'In transit' },
    { value: 'out_for_delivery', label: 'Out for delivery' },
    { value: 'delivered', label: 'Delivered' },
    { value: 'exception', label: 'Exception' },
    { value: 'returned', label: 'Returned to sender' },
];

export default function MailingsShow({ mailing, linkableProposals, carrierOptions, referenceTypeOptions }: { mailing: Mailing; linkableProposals: LinkableProposal[]; carrierOptions: { value: string; label: string }[]; referenceTypeOptions: { value: string; label: string }[] }) {
    const [refreshing, setRefreshing] = useState(false);
    const [editing, setEditing] = useState(false);
    const [docType, setDocType] = useState('label');
    const [uploading, setUploading] = useState(false);
    const fileRef = useRef<HTMLInputElement>(null);

    const form = useForm({
        ups_tracking_number: mailing.ups_tracking_number,
        carrier: mailing.carrier,
        reference_type: mailing.reference_type ?? 'orderNbr',
        proposal_submission_id: mailing.proposal?.id ? String(mailing.proposal.id) : '',
        status: mailing.status,
        auto_track: mailing.auto_track,
        recipient_name: mailing.recipient_name ?? '',
        recipient_address: mailing.recipient_address ?? '',
        deadline: mailing.deadline ?? '',
        scope: mailing.scope,
        scheduled_delivery: mailing.scheduled_delivery ?? '',
        delivered_at: mailing.delivered_on ?? '',
        received_by: mailing.received_by ?? '',
    });

    const proposalOptions = linkableProposals.map(p => ({ value: String(p.id), label: p.label }));

    // Keep this shipment's status/timeline current: re-pull every 5 minutes (the
    // server polls the carrier on the same cadence), but never while the user is
    // mid-edit or the tab is hidden.
    useEffect(() => {
        if (editing) return;
        const id = window.setInterval(() => {
            if (document.visibilityState === 'visible') {
                router.reload({ only: ['mailing'] });
            }
        }, 5 * 60 * 1000);
        return () => window.clearInterval(id);
    }, [editing]);

    // Changing the status by hand implies a manual override — pause UPS auto-sync
    // so the next poll doesn't revert it (the user can re-enable it below).
    const onStatusChange = (v: string) => {
        form.setData('status', v);
        if (v !== mailing.status) form.setData('auto_track', false);
    };

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
            <Head title={`Shipment ${mailing.ups_tracking_number}`} />
            <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6">
                <Link href="/shipments/mailings" className="mb-4 inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" /> Back to shipments
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
                        {mailing.current_location && (
                            <p className="mt-2 inline-flex flex-wrap items-center gap-1.5 text-sm text-muted-foreground">
                                <MapPin className="h-3.5 w-3.5 text-primary" /> Currently at {mailing.current_location}
                                {mailing.last_update && <span className="text-muted-foreground/60">· {formatDateTime(mailing.last_update)}</span>}
                            </p>
                        )}
                    </div>
                    <div className="flex items-center gap-2">
                        <button onClick={() => setEditing(true)} className="inline-flex items-center gap-2 rounded-full border border-border px-4 py-2 text-sm font-medium text-foreground transition hover:bg-secondary">
                            <Pencil className="h-4 w-4" /> Edit
                        </button>
                        <button onClick={refresh} disabled={refreshing} className="inline-flex items-center gap-2 rounded-full border border-border px-4 py-2 text-sm font-medium text-foreground transition hover:bg-secondary disabled:opacity-60">
                            <RefreshCw className={`h-4 w-4 ${refreshing ? 'animate-spin' : ''}`} /> Refresh
                        </button>
                    </div>
                </div>

                <div className="grid gap-6 md:grid-cols-3">
                    <div className="md:col-span-1">
                        <div className="card-surface p-5">
                            <Modal
                                open={editing}
                                onClose={() => setEditing(false)}
                                title="Edit shipment"
                                size="lg"
                                footer={
                                    <>
                                        <button type="button" onClick={() => setEditing(false)} className="rounded-full px-4 py-2 text-sm font-medium text-muted-foreground hover:text-foreground">Cancel</button>
                                        <button type="submit" form="mailing-edit-form" disabled={form.processing} className="bg-brand-gradient shadow-glow rounded-full px-5 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 disabled:opacity-60">
                                            {form.processing ? 'Saving…' : 'Save changes'}
                                        </button>
                                    </>
                                }
                            >
                                <form id="mailing-edit-form" onSubmit={save} className="space-y-4">
                                    <div>
                                        <label className="label">Tracking number</label>
                                        <input value={form.data.ups_tracking_number} onChange={e => form.setData('ups_tracking_number', e.target.value)} className="input font-mono" />
                                        {form.errors.ups_tracking_number && <p className="mt-1 text-xs text-destructive">{form.errors.ups_tracking_number}</p>}
                                    </div>
                                    <div className="grid grid-cols-2 gap-3">
                                        <div>
                                            <label className="label">Carrier</label>
                                            <CarrierField value={form.data.carrier} onChange={v => form.setData('carrier', v)} className="w-full" options={carrierOptions} />
                                        </div>
                                        <div>
                                            <label className="label">Status</label>
                                            <Select value={form.data.status} onChange={onStatusChange} className="w-full" options={STATUS_OPTIONS} />
                                        </div>
                                    </div>
                                    {form.data.carrier === 'jbhunt' && (
                                        <div>
                                            <label className="label">Reference type</label>
                                            <Select value={form.data.reference_type} onChange={v => form.setData('reference_type', v)} className="w-full" options={referenceTypeOptions} />
                                            <p className="mt-1 text-xs text-muted-foreground">What kind of number this is, so the J.B. Hunt link opens the right shipment.</p>
                                        </div>
                                    )}
                                    <div>
                                        <label className="label">Linked proposal</label>
                                        <Combobox value={form.data.proposal_submission_id} options={proposalOptions} onChange={v => form.setData('proposal_submission_id', v)} placeholder="Search proposals…" />
                                        <p className="mt-1 text-xs text-muted-foreground">Pick the right one, or clear (×) to unlink — overrides an auto-match.</p>
                                    </div>
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

                                    <div className="space-y-4 border-t border-border pt-4">
                                        <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground/70">Delivery details</p>
                                        <div>
                                            <label className="label">Scheduled delivery</label>
                                            <input type="date" value={form.data.scheduled_delivery} onChange={e => form.setData('scheduled_delivery', e.target.value)} className="input" />
                                        </div>
                                        {form.data.status === 'delivered' && (
                                            <>
                                                <div>
                                                    <label className="label">Delivered on</label>
                                                    <input type="date" value={form.data.delivered_at} onChange={e => form.setData('delivered_at', e.target.value)} className="input" />
                                                </div>
                                                <div>
                                                    <label className="label">Received by</label>
                                                    <input value={form.data.received_by} onChange={e => form.setData('received_by', e.target.value)} className="input" placeholder="Name at delivery" />
                                                </div>
                                            </>
                                        )}
                                    </div>

                                    <label className="flex cursor-pointer items-start gap-2.5 rounded-lg border border-border bg-secondary/30 p-3">
                                        <input type="checkbox" checked={form.data.auto_track} onChange={e => form.setData('auto_track', e.target.checked)} className="mt-0.5 h-4 w-4 rounded border-border text-primary focus:ring-primary" />
                                        <span className="text-sm">
                                            <span className="font-medium text-foreground">Auto-sync from carrier</span>
                                            <span className="mt-0.5 block text-xs text-muted-foreground">When on, the carrier poll updates this shipment. Turn off to keep your manual status — the carrier won't overwrite it.</span>
                                        </span>
                                    </label>

                                </form>
                            </Modal>

                            <dl className="space-y-4">
                                    <Field label="Category">{mailing.scope_label}</Field>
                                    {mailing.reference_type_label && <Field label="Tracked by">{mailing.reference_type_label}</Field>}
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
                                    {!mailing.auto_track && (
                                        <p className="inline-flex items-center gap-1.5 rounded-md bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                                            Manual status — carrier auto-sync off
                                        </p>
                                    )}
                                    <Field label="Added on">{formatDate(mailing.created_at)}</Field>
                                    {mailing.created_by && <p className="pt-1 text-xs text-muted-foreground">Added by {mailing.created_by}</p>}
                                </dl>
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
                                <p className="text-sm text-muted-foreground">No documents. Upload the bill of lading, shipping labels, customs forms, or receipts (PDF, PNG, JPEG).</p>
                            ) : (
                                <ul className="space-y-2">
                                    {mailing.documents.map(d => (
                                        <li key={d.id} className="flex items-center gap-3 rounded-lg border border-border px-3 py-2">
                                            <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-secondary text-muted-foreground">
                                                {d.is_image ? <ImageIcon className="h-4 w-4" /> : <FileIcon className="h-4 w-4" />}
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate text-sm font-medium text-foreground">{d.name}</span>
                                                <span className="block text-xs text-muted-foreground">{[d.type ? (DOC_TYPE_LABELS[d.type] ?? d.type) : null, d.size].filter(Boolean).join(' · ')}</span>
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
