import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Modal } from '@/Components/ui/Modal';
import { Button } from '@/Components/ui/Button';
import { Select } from '@/Components/ui/Select';

interface Props {
    open: boolean;
    onClose: () => void;
    assetId: number;
    types: { value: string; label: string }[];
}

const STATUSES = [
    { value: 'completed', label: 'Completed' },
    { value: 'scheduled', label: 'Scheduled' },
    { value: 'in_progress', label: 'In Progress' },
];

export function MaintenanceModal({ open, onClose, assetId, types }: Props) {
    const form = useForm({
        type: 'preventive',
        status: 'completed',
        description: '',
        cost: '',
        performed_at: new Date().toISOString().slice(0, 10),
        next_due_at: '',
        notes: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/assets/registry/${assetId}/maintenance`, { preserveScroll: true, onSuccess: () => { form.reset(); onClose(); } });
    };
    const err = (k: keyof typeof form.data) => form.errors[k as keyof typeof form.errors];

    return (
        <Modal
            open={open}
            onClose={onClose}
            title="Log Maintenance"
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>Cancel</Button>
                    <Button onClick={submit as unknown as () => void} disabled={form.processing}>{form.processing ? 'Saving…' : 'Add Record'}</Button>
                </>
            }
        >
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-2 gap-3">
                    <div><label className="label">Type</label><Select className="w-full" value={form.data.type} onChange={v => form.setData('type', v)} options={types} /></div>
                    <div><label className="label">Status</label><Select className="w-full" value={form.data.status} onChange={v => form.setData('status', v)} options={STATUSES} /></div>
                </div>
                <div>
                    <label className="label">Description *</label>
                    <textarea className="input min-h-[64px]" value={form.data.description} onChange={e => form.setData('description', e.target.value)} autoFocus />
                    {err('description') && <p className="mt-1 text-xs text-destructive">{err('description')}</p>}
                </div>
                <div className="grid grid-cols-3 gap-3">
                    <div><label className="label">Cost</label><input type="number" step="0.01" min="0" className="input" value={form.data.cost} onChange={e => form.setData('cost', e.target.value)} /></div>
                    <div><label className="label">Performed</label><input type="date" className="input" value={form.data.performed_at} onChange={e => form.setData('performed_at', e.target.value)} /></div>
                    <div><label className="label">Next due</label><input type="date" className="input" value={form.data.next_due_at} onChange={e => form.setData('next_due_at', e.target.value)} /></div>
                </div>
                <div>
                    <label className="label">Notes</label>
                    <input className="input" value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} />
                </div>
            </form>
        </Modal>
    );
}
