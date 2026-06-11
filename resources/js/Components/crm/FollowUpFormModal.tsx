import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Modal } from '@/Components/ui/Modal';
import { Button } from '@/Components/ui/Button';
import { Select } from '@/Components/ui/Select';

interface Props {
    open: boolean;
    onClose: () => void;
    proposals: Array<{ id: number; proposal_number: string; project_name: string }>;
    contacts: Array<{ id: number; first_name: string; last_name: string }>;
    defaults?: { proposal_submission_id?: number; contact_id?: number };
}

const TYPES = ['email', 'call', 'meeting', 'task', 'review', 'general'];

function todayPlus(days: number): string {
    const d = new Date();
    d.setDate(d.getDate() + days);
    return d.toISOString().slice(0, 10);
}

export function FollowUpFormModal({ open, onClose, proposals, contacts, defaults }: Props) {
    const form = useForm({
        type: 'email',
        subject: '',
        message: '',
        scheduled_date: todayPlus(3),
        proposal_submission_id: defaults?.proposal_submission_id ? String(defaults.proposal_submission_id) : '',
        contact_id: defaults?.contact_id ? String(defaults.contact_id) : '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/follow-ups', { preserveScroll: true, onSuccess: () => { form.reset(); onClose(); } });
    };

    const err = (k: keyof typeof form.data) => form.errors[k as keyof typeof form.errors];

    return (
        <Modal
            open={open}
            onClose={onClose}
            title="Schedule Follow-Up"
            description="Create a reminder to follow up on a proposal or contact."
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>Cancel</Button>
                    <Button onClick={submit as unknown as () => void} disabled={form.processing}>
                        {form.processing ? 'Saving…' : 'Schedule'}
                    </Button>
                </>
            }
        >
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <label className="label">Subject *</label>
                    <input className="input" value={form.data.subject} onChange={e => form.setData('subject', e.target.value)} autoFocus placeholder="Check in on submitted proposal" />
                    {err('subject') && <p className="mt-1 text-xs text-destructive">{err('subject')}</p>}
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="label">Type</label>
                        <Select
                            className="w-full"
                            value={form.data.type}
                            onChange={v => form.setData('type', v)}
                            options={TYPES.map(t => ({ value: t, label: t.charAt(0).toUpperCase() + t.slice(1) }))}
                        />
                    </div>
                    <div>
                        <label className="label">Scheduled date *</label>
                        <input type="date" className="input" value={form.data.scheduled_date} onChange={e => form.setData('scheduled_date', e.target.value)} />
                        {err('scheduled_date') && <p className="mt-1 text-xs text-destructive">{err('scheduled_date')}</p>}
                    </div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="label">Linked proposal</label>
                        <Select
                            className="w-full"
                            value={form.data.proposal_submission_id}
                            onChange={v => form.setData('proposal_submission_id', v)}
                            placeholder="— None —"
                            options={proposals.map(p => ({ value: String(p.id), label: `${p.proposal_number} — ${p.project_name}` }))}
                        />
                    </div>
                    <div>
                        <label className="label">Linked contact</label>
                        <Select
                            className="w-full"
                            value={form.data.contact_id}
                            onChange={v => form.setData('contact_id', v)}
                            placeholder="— None —"
                            options={contacts.map(c => ({ value: String(c.id), label: `${c.first_name} ${c.last_name}` }))}
                        />
                    </div>
                </div>
                <div>
                    <label className="label">Message / notes</label>
                    <textarea className="input min-h-[80px]" value={form.data.message} onChange={e => form.setData('message', e.target.value)} />
                </div>
            </form>
        </Modal>
    );
}
