import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Truck } from 'lucide-react';
import { ShipmentsLayout } from '@/Components/layout/ShipmentsLayout';
import { Select } from '@/Components/ui/Select';

interface LinkableProposal {
    id: number;
    label: string;
    due_date: string | null;
}

interface Props {
    prefill: {
        proposal_submission_id: number | null;
        deadline: string | null;
        recipient_name: string | null;
        recipient_address: string | null;
    } | null;
    linkableProposals: LinkableProposal[];
}

export default function MailingsCreate({ prefill, linkableProposals }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        ups_tracking_number: '',
        carrier: 'ups',
        proposal_submission_id: prefill?.proposal_submission_id ? String(prefill.proposal_submission_id) : '',
        recipient_name: prefill?.recipient_name ?? '',
        recipient_address: prefill?.recipient_address ?? '',
        deadline: prefill?.deadline ?? '',
    });

    const onPickProposal = (id: string) => {
        setData('proposal_submission_id', id);
        const p = linkableProposals.find(x => String(x.id) === id);
        if (p?.due_date && !data.deadline) setData('deadline', p.due_date);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/shipments/mailings');
    };

    return (
        <ShipmentsLayout>
            <Head title="New mailing" />
            <div className="mx-auto max-w-2xl px-4 py-8 sm:px-6">
                <Link href="/shipments/mailings" className="mb-4 inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" /> Back to mailings
                </Link>

                <div className="mb-6 flex items-center gap-3">
                    <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-gradient text-white"><Truck className="h-5 w-5" /></span>
                    <h1 className="text-2xl font-bold tracking-tight text-foreground">New mailing</h1>
                </div>

                <form onSubmit={submit} className="card-surface space-y-5 p-6">
                    <div>
                        <label className="label">Carrier</label>
                        <Select
                            value={data.carrier}
                            onChange={v => setData('carrier', v)}
                            options={[{ value: 'ups', label: 'UPS' }]}
                            className="w-full"
                        />
                    </div>

                    <div>
                        <label htmlFor="tracking" className="label">Tracking number</label>
                        <input
                            id="tracking"
                            value={data.ups_tracking_number}
                            onChange={e => setData('ups_tracking_number', e.target.value)}
                            className="input font-mono"
                            placeholder="1Z999AA10123456784"
                            required
                            autoFocus
                        />
                        {errors.ups_tracking_number && <p className="mt-1.5 text-sm text-destructive">{errors.ups_tracking_number}</p>}
                    </div>

                    {linkableProposals.length > 0 && (
                        <div>
                            <label className="label">Link to proposal <span className="text-muted-foreground">(optional)</span></label>
                            <Select
                                value={data.proposal_submission_id}
                                onChange={onPickProposal}
                                options={linkableProposals.map(p => ({ value: String(p.id), label: p.label }))}
                                placeholder="— none —"
                                className="w-full"
                            />
                            {errors.proposal_submission_id && <p className="mt-1.5 text-sm text-destructive">{errors.proposal_submission_id}</p>}
                        </div>
                    )}

                    <div>
                        <label htmlFor="recipient" className="label">Recipient (agency)</label>
                        <input id="recipient" value={data.recipient_name} onChange={e => setData('recipient_name', e.target.value)} className="input" placeholder="Department of … Contracting Office" />
                    </div>

                    <div>
                        <label htmlFor="address" className="label">Recipient address</label>
                        <textarea id="address" value={data.recipient_address} onChange={e => setData('recipient_address', e.target.value)} className="input min-h-[84px]" placeholder={'123 Main St\nWashington, DC 20001'} />
                    </div>

                    <div>
                        <label htmlFor="deadline" className="label">Deadline <span className="text-muted-foreground">(proposal due date)</span></label>
                        <input id="deadline" type="date" value={data.deadline} onChange={e => setData('deadline', e.target.value)} className="input" />
                        {errors.deadline && <p className="mt-1.5 text-sm text-destructive">{errors.deadline}</p>}
                    </div>

                    <div className="flex items-center justify-end gap-3 pt-2">
                        <Link href="/shipments/mailings" className="rounded-full px-4 py-2 text-sm font-medium text-muted-foreground hover:text-foreground">Cancel</Link>
                        <button type="submit" disabled={processing} className="bg-brand-gradient shadow-glow rounded-full px-5 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 disabled:opacity-60">
                            {processing ? 'Creating…' : 'Create & track'}
                        </button>
                    </div>
                </form>
            </div>
        </ShipmentsLayout>
    );
}
