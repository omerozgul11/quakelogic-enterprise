import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Plus, Check, Pencil, Trash2, CalendarClock } from 'lucide-react';
import { Pill } from '@/Components/ui/Pill';
import { FollowUpModal, EditableFollowUp } from '@/Components/crm/FollowUpModal';
import { cn, formatDate } from '@/Lib/utils';

export interface FollowUpRow {
    id: number;
    title: string;
    notes?: string | null;
    due_date: string | null;
    priority: string;
    status: string;
    is_overdue: boolean;
    assigned_to: number | null;
    assignee: string | null;
}

const PRIORITY_COLOR: Record<string, string> = { low: 'gray', normal: 'blue', high: 'rose' };

export function FollowUpItem({ f, onEdit }: { f: FollowUpRow; onEdit?: (f: FollowUpRow) => void }) {
    const done = f.status === 'done';
    const toggle = () => router.post(`/crm/follow-ups/${f.id}/complete`, {}, { preserveScroll: true });
    const remove = () => { if (confirm('Remove this follow-up?')) router.delete(`/crm/follow-ups/${f.id}`, { preserveScroll: true }); };

    return (
        <div className="group flex items-start gap-3 rounded-lg border border-border px-3 py-2.5">
            <button
                type="button"
                onClick={toggle}
                title={done ? 'Reopen' : 'Mark done'}
                className={cn(
                    'mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border transition-colors',
                    done ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-muted-foreground/40 hover:border-emerald-500'
                )}
            >
                {done && <Check className="h-3 w-3" />}
            </button>
            <div className="min-w-0 flex-1">
                <p className={cn('text-sm font-medium', done ? 'text-muted-foreground line-through' : 'text-foreground')}>{f.title}</p>
                <div className="mt-1 flex flex-wrap items-center gap-2 text-xs">
                    {f.due_date && (
                        <span className={cn('inline-flex items-center gap-1', !done && f.is_overdue ? 'font-semibold text-rose-600 dark:text-rose-400' : 'text-muted-foreground')}>
                            <CalendarClock className="h-3 w-3" />
                            {formatDate(f.due_date)}{!done && f.is_overdue ? ' · overdue' : ''}
                        </span>
                    )}
                    {f.priority !== 'normal' && <Pill color={PRIORITY_COLOR[f.priority] ?? 'gray'} label={f.priority} />}
                    {f.assignee && <span className="text-muted-foreground">· {f.assignee}</span>}
                </div>
                {f.notes && <p className="mt-1 whitespace-pre-wrap text-xs text-muted-foreground">{f.notes}</p>}
            </div>
            <div className="flex shrink-0 items-center gap-0.5 opacity-0 transition-opacity group-hover:opacity-100">
                {onEdit && (
                    <button type="button" onClick={() => onEdit(f)} className="rounded p-1 text-muted-foreground hover:text-foreground" title="Edit"><Pencil className="h-3.5 w-3.5" /></button>
                )}
                <button type="button" onClick={remove} className="rounded p-1 text-muted-foreground hover:text-destructive" title="Remove"><Trash2 className="h-3.5 w-3.5" /></button>
            </div>
        </div>
    );
}

interface Props {
    subject: { type: 'lead' | 'company' | 'contact'; id: number };
    followUps: FollowUpRow[];
    owners: Array<{ id: number; name: string }>;
    currentUserId: number;
    priorities: string[];
}

export function FollowUpPanel({ subject, followUps, owners, currentUserId, priorities }: Props) {
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<FollowUpRow | null>(null);

    const openNew = () => { setEditing(null); setModalOpen(true); };
    const openEdit = (f: FollowUpRow) => { setEditing(f); setModalOpen(true); };

    const editable: EditableFollowUp | null = editing && {
        id: editing.id, title: editing.title, notes: editing.notes,
        due_date: editing.due_date, priority: editing.priority, assigned_to: editing.assigned_to,
    };

    const open = followUps.filter(f => f.status === 'open');
    const done = followUps.filter(f => f.status === 'done');

    return (
        <div className="card-surface p-5">
            <div className="mb-3 flex items-center justify-between">
                <h2 className="text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Follow-ups</h2>
                <button type="button" onClick={openNew} className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline">
                    <Plus className="h-3.5 w-3.5" /> Add
                </button>
            </div>

            {followUps.length === 0 ? (
                <p className="py-4 text-center text-sm text-muted-foreground">No follow-ups — schedule a call-back or next step.</p>
            ) : (
                <div className="space-y-2">
                    {open.map(f => <FollowUpItem key={f.id} f={f} onEdit={openEdit} />)}
                    {done.length > 0 && (
                        <details className="mt-2">
                            <summary className="cursor-pointer text-xs font-medium text-muted-foreground">Completed ({done.length})</summary>
                            <div className="mt-2 space-y-2">
                                {done.map(f => <FollowUpItem key={f.id} f={f} onEdit={openEdit} />)}
                            </div>
                        </details>
                    )}
                </div>
            )}

            {modalOpen && (
                <FollowUpModal
                    open
                    onClose={() => setModalOpen(false)}
                    followUp={editable}
                    subject={editing ? null : subject}
                    owners={owners}
                    currentUserId={currentUserId}
                    priorities={priorities}
                />
            )}
        </div>
    );
}
