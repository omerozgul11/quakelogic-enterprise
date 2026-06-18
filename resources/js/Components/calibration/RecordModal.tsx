import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Modal } from '@/Components/ui/Modal';
import { Button } from '@/Components/ui/Button';
import { Select } from '@/Components/ui/Select';

interface FormData {
    assets: { id: number; asset_tag: string; name: string; serial_number: string | null }[];
    products: { id: number; sku: string; name: string }[];
    users: { id: number; name: string }[];
}

interface Props {
    open: boolean;
    onClose: () => void;
    form: FormData;
    results: { value: string; label: string }[];
    presetAssetId?: number | null;
}

export function RecordModal({ open, onClose, form: opts, results, presetAssetId }: Props) {
    const form = useForm({
        asset_id: presetAssetId ? String(presetAssetId) : '',
        inventory_product_id: '',
        result: 'pass',
        nist_traceable: true,
        method: 'Comparison to NIST-traceable reference',
        standard_used: '',
        technician: '',
        serial_number: '',
        calibrated_at: new Date().toISOString().slice(0, 10),
        interval_months: '12',
        due_at: '',
        notes: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/calibration/certificates', { preserveScroll: true, onSuccess: () => { form.reset(); onClose(); } });
    };
    const err = (k: keyof typeof form.data) => form.errors[k as keyof typeof form.errors];

    return (
        <Modal
            open={open}
            onClose={onClose}
            size="lg"
            title="Record Calibration"
            description="Issue a calibration certificate. Linking an asset also logs it on that asset's maintenance history."
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>Cancel</Button>
                    <Button onClick={submit as unknown as () => void} disabled={form.processing}>
                        {form.processing ? 'Saving…' : 'Record Certificate'}
                    </Button>
                </>
            }
        >
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label className="label">Instrument (asset)</label>
                        <Select className="w-full" value={form.data.asset_id} placeholder="— None —" onChange={v => form.setData('asset_id', v)} options={opts.assets.map(a => ({ value: String(a.id), label: `${a.asset_tag} · ${a.name}` }))} />
                    </div>
                    <div>
                        <label className="label">…or product type</label>
                        <Select className="w-full" value={form.data.inventory_product_id} placeholder="— None —" onChange={v => form.setData('inventory_product_id', v)} options={opts.products.map(p => ({ value: String(p.id), label: `${p.sku} · ${p.name}` }))} />
                    </div>
                </div>
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div><label className="label">Result *</label><Select className="w-full" value={form.data.result} onChange={v => form.setData('result', v)} options={results} /></div>
                    <div><label className="label">Calibrated *</label><input type="date" className="input" value={form.data.calibrated_at} onChange={e => form.setData('calibrated_at', e.target.value)} />{err('calibrated_at') && <p className="mt-1 text-xs text-destructive">{err('calibrated_at')}</p>}</div>
                    <div><label className="label">Interval (mo)</label><input type="number" min="1" className="input" value={form.data.interval_months} onChange={e => form.setData('interval_months', e.target.value)} /></div>
                    <div><label className="label">Due (override)</label><input type="date" className="input" value={form.data.due_at} onChange={e => form.setData('due_at', e.target.value)} /></div>
                </div>
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div><label className="label">Serial #</label><input className="input" value={form.data.serial_number} onChange={e => form.setData('serial_number', e.target.value)} /></div>
                    <div><label className="label">Technician</label><input className="input" value={form.data.technician} onChange={e => form.setData('technician', e.target.value)} /></div>
                </div>
                <div>
                    <label className="label">Reference standard / equipment used</label>
                    <input className="input" value={form.data.standard_used} onChange={e => form.setData('standard_used', e.target.value)} placeholder="e.g. Reference accelerometer #A-12" />
                </div>
                <div>
                    <label className="label">Method</label>
                    <input className="input" value={form.data.method} onChange={e => form.setData('method', e.target.value)} />
                </div>
                <div>
                    <label className="label">Notes</label>
                    <textarea className="input min-h-[56px]" value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} />
                </div>
                <label className="flex items-center gap-2 text-sm text-foreground">
                    <input type="checkbox" checked={form.data.nist_traceable} onChange={e => form.setData('nist_traceable', e.target.checked)} /> NIST-traceable
                </label>
            </form>
        </Modal>
    );
}
