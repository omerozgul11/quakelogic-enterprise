import { Link } from '@inertiajs/react';
import { Truck, ExternalLink, Plus } from 'lucide-react';
import { Pill } from '@/Components/ui/Pill';
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
}

/**
 * The Shipments two-way link surfaced on the proposal page: shows the mailed
 * submission's UPS delivery status, or (for mail submissions without tracking)
 * a prompt to start tracking. Renders nothing when irrelevant.
 */
export function MailTrackingPanel({ tracking, proposalId }: { tracking?: MailTracking; proposalId: number }) {
    if (!tracking) return null;
    const { canAccess, isMailed, mailing } = tracking;
    if (!mailing && !(isMailed && canAccess)) return null;

    return (
        <div className="rounded-xl border border-border bg-card p-5 shadow-soft">
            <div className="mb-3 flex items-center gap-2">
                <Truck className="h-4 w-4 text-primary" />
                <h3 className="text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Mail tracking</h3>
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
                    </div>
                </div>
            ) : (
                <div className="space-y-3">
                    <p className="text-sm text-muted-foreground">Submitted by mail — no UPS tracking yet.</p>
                    <Link href={`/shipments/mailings/create?proposal=${proposalId}`} className="bg-brand-gradient shadow-glow inline-flex items-center gap-2 rounded-full px-3.5 py-1.5 text-sm font-semibold text-white transition hover:-translate-y-0.5">
                        <Plus className="h-4 w-4" /> Add mail tracking
                    </Link>
                </div>
            )}
        </div>
    );
}
