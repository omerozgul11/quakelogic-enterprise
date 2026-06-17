import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Layers } from 'lucide-react';
import { ShipmentsLayout } from '@/Components/layout/ShipmentsLayout';
import { Select } from '@/Components/ui/Select';

export default function MailingsBulk() {
    const { data, setData, post, processing, errors } = useForm({
        carrier: 'ups',
        scope: 'domestic',
        tracking_numbers: '',
        recipient_name: '',
        deadline: '',
    });

    const count = data.tracking_numbers.split(/[\s,]+/).map(s => s.trim()).filter(Boolean).length;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/shipments/mailings/bulk');
    };

    return (
        <ShipmentsLayout>
            <Head title="Bulk add shipments" />
            <div className="mx-auto max-w-2xl px-4 py-8 sm:px-6">
                <Link href="/shipments/mailings" className="mb-4 inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" /> Back to shipments
                </Link>

                <div className="mb-6 flex items-center gap-3">
                    <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-gradient text-white"><Layers className="h-5 w-5" /></span>
                    <h1 className="text-2xl font-bold tracking-tight text-foreground">Bulk add shipments</h1>
                </div>

                <p className="mb-5 text-sm text-muted-foreground">
                    Paste your existing tracking numbers — one per line (or comma-separated). Every
                    shipment is added right away; UPS status (delivered, in transit, etc.) loads in the
                    background and any still loading update automatically. Up to 100 at a time.
                </p>

                <form onSubmit={submit} className="card-surface space-y-5 p-6">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                            <label className="label">Category</label>
                            <Select
                                value={data.scope}
                                onChange={v => setData('scope', v)}
                                options={[{ value: 'domestic', label: 'Domestic' }, { value: 'international', label: 'International' }]}
                                className="w-full"
                            />
                        </div>
                    </div>

                    <div>
                        <label htmlFor="numbers" className="label">Tracking numbers {count > 0 && <span className="text-muted-foreground">({count})</span>}</label>
                        <textarea
                            id="numbers"
                            value={data.tracking_numbers}
                            onChange={e => setData('tracking_numbers', e.target.value)}
                            className="input min-h-[180px] font-mono text-sm"
                            placeholder={'1Z999AA10123456784\n1Z999AA10123456785\n1Z999AA10123456786'}
                            required
                            autoFocus
                        />
                        {errors.tracking_numbers && <p className="mt-1.5 text-sm text-destructive">{errors.tracking_numbers}</p>}
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label htmlFor="recipient" className="label">Recipient <span className="text-muted-foreground">(optional, applied to all)</span></label>
                            <input id="recipient" value={data.recipient_name} onChange={e => setData('recipient_name', e.target.value)} className="input" placeholder="Agency / office" />
                        </div>
                        <div>
                            <label htmlFor="deadline" className="label">Deadline <span className="text-muted-foreground">(optional)</span></label>
                            <input id="deadline" type="date" value={data.deadline} onChange={e => setData('deadline', e.target.value)} className="input" />
                        </div>
                    </div>

                    <div className="flex items-center justify-end gap-3 pt-2">
                        <Link href="/shipments/mailings" className="rounded-full px-4 py-2 text-sm font-medium text-muted-foreground hover:text-foreground">Cancel</Link>
                        <button type="submit" disabled={processing || count === 0} className="bg-brand-gradient shadow-glow rounded-full px-5 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 disabled:opacity-60">
                            {processing ? 'Fetching…' : `Add & track${count > 0 ? ` ${count}` : ''}`}
                        </button>
                    </div>
                </form>
            </div>
        </ShipmentsLayout>
    );
}
