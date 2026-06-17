import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Truck, ExternalLink, Plus, Link2, Unlink } from 'lucide-react';
import { Pill } from '@/Components/ui/Pill';
import { Select } from '@/Components/ui/Select';
import { Button } from '@/Components/ui/Button';
import { formatDate, formatDateTime } from '@/Lib/utils';

export interface MailTracking {
    canAccess: boolean;
    isMailed: boolean;
    mailing: {
        ulid: string;
        ups_tracking_number: string;
        status_label: string;
        status_color: string;
        risk_label: string;
        risk_color: string;
        deadline: string | null;
        scheduled_delivery: string | null;
        delivered_at: string | null;
        received_by: string | null;
        proof_url: string | null;
    } | null;
    linkableShipments?: Array<{ ulid: string; label: string }>;
}

/**
 * Dedicated Shipments section on the proposal page (two-way link with the
 * Shipments app): shows the linked UPS shipment's delivery status, and lets a
 * shipment-access user link an existing shipment, create one, or unlink.
 * Hidden entirely from users without shipment access (unless one is already linked).
 */
export function MailTrackingPanel({ tracking, proposalId }: { tracking?: MailTracking; proposalId: number }) {
    const [linkUlid, setLinkUlid] = useState('');
    if (!tracking) return null;
    const { canAccess, isMailed, mailing, linkableShipments = [] } = tracking;
    if (!mailing && !canAccess) return null;

    const linkExisting = () => {
        if (!linkUlid) return;
        router.post(`/proposals/${proposalId}/link-shipment`, { ulid: linkUlid }, { preserveScroll: true, onSuccess: () => setLinkUlid('') });
    };
    const unlink = () => {
        if (confirm('Unlink this shipment from the proposal? The shipment itself is kept.')) {
            router.post(`/proposals/${proposalId}/unlink-shipment`, {}, { preserveScroll: true });
        }
    };

    return (
        <div className="card-surface p-5">
            <div className="mb-3 flex items-center gap-2">
                <Truck className="h-4 w-4 text-primary" />
                <h3 className="text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Shipment</h3>
            </div>

            {mailing ? (
                <div className="space-y-3">
                    <div className="flex flex-wrap items-center gap-2">
                        <Pill color={mailing.status_color} label={mailing.status_label} />
                        <Pill color={mailing.risk_color} label={mailing.risk_label} />
                    </div>
                    <p className="font-mono text-xs text-muted-foreground">{mailing.ups_tracking_number}</p>
                    <dl className="space-y-1.5 text-sm">
                        <div className="flex justify-between gap-3">
                            <dt className="text-muted-foreground">Deadline</dt>
                            <dd className="text-foreground">{formatDate(mailing.deadline)}</dd>
                        </div>
                        {mailing.delivered_at ? (
                            <>
                                <div className="flex justify-between gap-3">
                                    <dt className="text-muted-foreground">Delivered</dt>
                                    <dd className="text-foreground">{formatDateTime(mailing.delivered_at)}</dd>
                                </div>
                                {mailing.received_by && (
                                    <div className="flex justify-between gap-3">
                                        <dt className="text-muted-foreground">Received by</dt>
                                        <dd className="text-foreground">{mailing.received_by}</dd>
                                    </div>
                                )}
                            </>
                        ) : (
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">Est. delivery</dt>
                                <dd className="text-foreground">{formatDate(mailing.scheduled_delivery)}</dd>
                            </div>
                        )}
                    </dl>
                    <div className="flex flex-wrap items-center gap-3 pt-1">
                        {mailing.proof_url && (
                            <a href={mailing.proof_url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline">
                                <ExternalLink className="h-3.5 w-3.5" /> Proof of delivery
                            </a>
                        )}
                        {canAccess && (
                            <Link href={`/shipments/mailings/${mailing.ulid}`} className="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline">
                                <Truck className="h-3.5 w-3.5" /> View tracking
                            </Link>
                        )}
                        {canAccess && (
                            <button onClick={unlink} className="ml-auto inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground transition-colors hover:text-destructive">
                                <Unlink className="h-3.5 w-3.5" /> Unlink
                            </button>
                        )}
                    </div>
                </div>
            ) : (
                <div className="space-y-4">
                    <p className="text-sm text-muted-foreground">
                        {isMailed ? 'Submitted by mail — no shipment linked yet.' : 'No shipment is linked to this proposal yet.'}
                    </p>

                    {linkableShipments.length > 0 && (
                        <div>
                            <label className="label">Link an existing shipment</label>
                            <div className="flex items-center gap-2">
                                <Select
                                    value={linkUlid}
                                    onChange={setLinkUlid}
                                    options={linkableShipments.map(s => ({ value: s.ulid, label: s.label }))}
                                    placeholder="Choose a shipment…"
                                    className="min-w-0 flex-1"
                                />
                                <Button size="sm" icon={Link2} onClick={linkExisting} disabled={!linkUlid}>Link</Button>
                            </div>
                        </div>
                    )}

                    <div>
                        <Link href={`/shipments/mailings/create?proposal=${proposalId}`} className="bg-brand-gradient shadow-glow inline-flex items-center gap-2 rounded-full px-3.5 py-1.5 text-sm font-semibold text-white transition hover:-translate-y-0.5">
                            <Plus className="h-4 w-4" /> Add new shipment
                        </Link>
                    </div>
                </div>
            )}
        </div>
    );
}
