import { useForm } from '@inertiajs/react';
import { Modal } from '@/Components/ui/Modal';
import { Button } from '@/Components/ui/Button';
import { Select } from '@/Components/ui/Select';

export interface EditableFollowUp {
    id: number;
    title: string;
    notes?: string | null;
    due_date: string | null;
    priority: string;
    assigned_to: number | null;
}

interface Props {
    open: boolean;
    onClose: () => void;
    followUp?: EditableFollowUp | null;
    subject?: { type: 'lead' | 'company' | 'contact'; id: number } | null;
    owners: Array<{ id: number; name: string }>;
    currentUserId: number;
    priorities: string[];
}

function today(): string {
    return new Date().toISOString().slice(0, 10);
}

export function FollowUpModal({ open, onClose, followUp, subject, owners, currentUserId, priorities }: Props) {
    const form = useForm({
        title: followUp?.title ?? '',
        notes: followUp?.notes ?? '',
        due_date: followUp?.due_date ?? today(),
        priority: followUp?.priority ?? 'normal',
        assigned_to: String(followUp?.assigned_to ?? currentUserId),
        subject: subject?.type ?? '',
        subject_id: subject ? String(subject.id) : '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => { form.reset(); onClose(); } };
        if (followUp?.id) {
            form.patch(`/crm/follow-ups/${followUp.id}`, opts);
        } else {
            form.post('/crm/follow-ups', opts);
        }
    };

    const err = (k: string) => (form.errors as Record<string, string>)[k];

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={followUp ? 'Edit follow-up' : 'New follow-up'}
            size="sm"
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>Cancel</Button>
                    <Button onClick={submit as unknown as () => void} disabled={form.processing}>
                        {form.processing ? 'Saving…' : followUp ? 'Save' : 'Add follow-up'}
                    </Button>
                </>
            }
        >
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <label className="label">Title *</label>
                    <input className="input" value={form.data.title} onChange={e => form.setData('title', e.target.value)} placeholder="Call back, send quote…" autoFocus />
                    {err('title') && <p className="mt-1 text-xs text-destructive">{err('title')}</p>}
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="label">Due *</label>
                        <input type="date" className="input" value={form.data.due_date} onChange={e => form.setData('due_date', e.target.value)} />
                        {err('due_date') && <p className="mt-1 text-xs text-destructive">{err('due_date')}</p>}
                    </div>
                    <div>
                        <label className="label">Priority</label>
                        <Select className="w-full" value={form.data.priority} onChange={v => form.setData('priority', v)}
                            options={priorities.map(p => ({ value: p, label: p.charAt(0).toUpperCase() + p.slice(1) }))} />
                    </div>
                </div>
                <div>
                    <label className="label">Assigned to</label>
                    <Select className="w-full" value={form.data.assigned_to} onChange={v => form.setData('assigned_to', v)}
                        options={owners.map(o => ({ value: String(o.id), label: o.name }))} />
                </div>
                <div>
                    <label className="label">Notes</label>
                    <textarea rows={2} className="input resize-y" value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} placeholder="Optional detail…" />
                </div>
            </form>
        </Modal>
    );
}
