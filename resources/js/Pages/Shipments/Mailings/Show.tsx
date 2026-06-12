import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowLeft, RefreshCw, ExternalLink, MapPin, FileText } from 'lucide-react';
import { ShipmentsLayout } from '@/Components/layout/ShipmentsLayout';
import { Pill } from '@/Components/ui/Pill';
import { formatDate, formatDateTime } from '@/Lib/utils';

interface TrackingEvent {
    id: number;
    code: string | null;
    description: string;
    location: string | null;
    occurred_at: string;
}

interface Mailing {
    ulid: string;
    ups_tracking_number: string;
    carrier_label: string;
    tracking_url: string | null;
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
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div>
            <dt className="text-xs font-medium uppercase tracking-wider text-muted-foreground/70">{label}</dt>
            <dd className="mt-0.5 text-sm text-foreground">{children}</dd>
        </div>
    );
}

export default function MailingsShow({ mailing }: { mailing: Mailing }) {
    const [refreshing, setRefreshing] = useState(false);
    const refresh = () => {
        setRefreshing(true);
        router.post(`/shipments/mailings/${mailing.ulid}/refresh`, {}, {
            preserveScroll: true,
            onFinish: () => setRefreshing(false),
        });
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
                        <div className="mt-2 flex items-center gap-2">
                            <Pill color={mailing.status_color} label={mailing.status_label} />
                            <Pill color={mailing.risk_color} label={mailing.risk_label} />
                        </div>
                    </div>
                    <button onClick={refresh} disabled={refreshing} className="inline-flex items-center gap-2 rounded-full border border-border px-4 py-2 text-sm font-medium text-foreground transition hover:bg-secondary disabled:opacity-60">
                        <RefreshCw className={`h-4 w-4 ${refreshing ? 'animate-spin' : ''}`} /> Refresh
                    </button>
                </div>

                <div className="grid gap-6 md:grid-cols-3">
                    <div className="md:col-span-1">
                        <div className="card-surface space-y-4 p-5">
                            <Field label="Recipient">{mailing.recipient_name ?? '—'}</Field>
                            {mailing.recipient_address && (
                                <Field label="Address"><span className="whitespace-pre-line text-muted-foreground">{mailing.recipient_address}</span></Field>
                            )}
                            <Field label="Deadline">{formatDate(mailing.deadline)}</Field>
                            <Field label="Scheduled delivery">{formatDate(mailing.scheduled_delivery)}</Field>
                            {mailing.delivered_at && <Field label="Delivered">{formatDateTime(mailing.delivered_at)}</Field>}
                            {mailing.received_by && <Field label="Received by">{mailing.received_by}</Field>}
                            {mailing.proposal && (
                                <Field label="Proposal">
                                    <span className="inline-flex items-center gap-1.5">
                                        <FileText className="h-3.5 w-3.5 text-muted-foreground" />
                                        {mailing.proposal.proposal_number ?? mailing.proposal.project_name}
                                    </span>
                                </Field>
                            )}
                            {mailing.proof_url && (
                                <a href={mailing.proof_url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline">
                                    <ExternalLink className="h-4 w-4" /> Proof of delivery
                                </a>
                            )}
                            {mailing.created_by && <p className="pt-1 text-xs text-muted-foreground">Added by {mailing.created_by}</p>}
                        </div>
                    </div>

                    <div className="md:col-span-2">
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
                    </div>
                </div>
            </div>
        </ShipmentsLayout>
    );
}
