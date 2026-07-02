import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Card } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Pill } from '@/Components/ui/Pill';
import { SignaturePad } from '@/Components/procurement/SignaturePad';
import { ShieldCheck, Check, X, PenLine } from 'lucide-react';

export interface ApprovalStepData {
    id: number;
    position: number;
    name: string | null;
    approver: string;
    require_signature: boolean;
    status: string;
    status_label: string;
    status_color: string;
    decided_by: string | null;
    decided_at: string | null;
    note: string | null;
    is_current: boolean;
    signature_url: string | null;
}

export interface ApprovalData {
    id: number;
    status: string;
    status_label: string;
    status_color: string;
    flow: string | null;
    submitted_at: string | null;
    can_act: boolean;
    steps: ApprovalStepData[];
}

interface Props {
    entity: 'purchase-requests' | 'purchase-orders' | 'bill-payments';
    id: number;
    approval: ApprovalData;
    /** Compact heading variant for embedding inside a payment row. */
    compact?: boolean;
}

/**
 * Renders a document's multi-level approval chain and, for the current eligible
 * approver, the approve/reject controls (with a signature pad when the step
 * requires one).
 */
export function ApprovalPanel({ entity, id, approval, compact }: Props) {
    const [rejecting, setRejecting] = useState(false);
    const current = approval.steps.find(s => s.is_current);
    const form = useForm<{ signature: string | null; note: string }>({ signature: null, note: '' });

    const approve = () => {
        form.transform(() => ({ signature: form.data.signature, note: form.data.note }));
        form.post(`/procurement/approvals/${entity}/${id}/approve`, { preserveScroll: true, onSuccess: () => form.reset() });
    };
    const reject = () => {
        form.transform(() => ({ note: form.data.note }));
        form.post(`/procurement/approvals/${entity}/${id}/reject`, { preserveScroll: true, onSuccess: () => { form.reset(); setRejecting(false); } });
    };

    const needsSig = current?.require_signature ?? false;
    const canSubmitApprove = !form.processing && (!needsSig || !!form.data.signature);

    const body = (
        <>
            <ol className="space-y-2.5">
                {approval.steps.map(s => (
                    <li key={s.id} className="flex gap-3">
                        <div className={`mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold ${
                            s.status === 'approved' ? 'bg-emerald-100 text-emerald-700'
                            : s.status === 'rejected' ? 'bg-red-100 text-red-700'
                            : s.is_current ? 'bg-amber-100 text-amber-700' : 'bg-secondary text-muted-foreground'}`}>
                            {s.status === 'approved' ? <Check className="h-3.5 w-3.5" /> : s.status === 'rejected' ? <X className="h-3.5 w-3.5" /> : s.position + 1}
                        </div>
                        <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span className="text-sm font-medium text-foreground">{s.name || `Step ${s.position + 1}`}</span>
                                <Pill color={s.status_color} label={s.status_label} />
                                {s.require_signature && <span className="inline-flex items-center gap-0.5 text-xs text-muted-foreground"><PenLine className="h-3 w-3" /> signature</span>}
                            </div>
                            <div className="text-xs text-muted-foreground">{s.approver}</div>
                            {s.decided_by && <div className="text-xs text-muted-foreground">{s.status === 'approved' ? 'Approved' : 'Rejected'} by {s.decided_by}{s.decided_at ? ` · ${new Date(s.decided_at).toLocaleDateString()}` : ''}</div>}
                            {s.note && <div className="mt-0.5 rounded bg-secondary/60 px-2 py-1 text-xs text-foreground">{s.note}</div>}
                            {s.signature_url && <img src={s.signature_url} alt="signature" className="mt-1 h-10 rounded border border-border bg-white" />}
                        </div>
                    </li>
                ))}
            </ol>

            {approval.can_act && current && (
                <div className="mt-4 border-t border-border pt-4">
                    <p className="mb-2 text-xs font-medium text-muted-foreground">Your decision on “{current.name || `Step ${current.position + 1}`}”</p>
                    {needsSig && (
                        <div className="mb-3">
                            <SignaturePad onChange={sig => form.setData('signature', sig)} />
                            <p className="mt-1 text-xs text-muted-foreground">A signature is required to approve this step.</p>
                        </div>
                    )}
                    <textarea className="input min-h-[56px] text-sm" placeholder="Note (optional)" value={form.data.note} onChange={e => form.setData('note', e.target.value)} />
                    <div className="mt-2 flex gap-2">
                        <Button icon={Check} onClick={approve} disabled={!canSubmitApprove}>{form.processing ? 'Saving…' : 'Approve'}</Button>
                        {rejecting
                            ? <Button variant="danger" icon={X} onClick={reject} disabled={form.processing}>Confirm reject</Button>
                            : <Button variant="ghost" icon={X} onClick={() => setRejecting(true)} disabled={form.processing}>Reject</Button>}
                    </div>
                </div>
            )}
        </>
    );

    if (compact) {
        return (
            <div className="mt-2 rounded-lg border border-border bg-secondary/20 p-3">
                <div className="mb-2 flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-muted-foreground/70">
                    <ShieldCheck className="h-3.5 w-3.5" /> Approval chain {approval.flow ? `· ${approval.flow}` : ''}
                    <Pill color={approval.status_color} label={approval.status_label} />
                </div>
                {body}
            </div>
        );
    }

    return (
        <Card className="p-5">
            <h2 className="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70">
                <ShieldCheck className="h-4 w-4" /> Approval chain
                {approval.flow && <span className="font-normal normal-case text-muted-foreground">· {approval.flow}</span>}
                <span className="ml-auto"><Pill color={approval.status_color} label={approval.status_label} /></span>
            </h2>
            {body}
        </Card>
    );
}
