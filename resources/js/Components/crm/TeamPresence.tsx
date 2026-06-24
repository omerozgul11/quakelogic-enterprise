import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Users, Clock, LogOut, Palmtree, ChevronDown, Plus, X } from 'lucide-react';
import { Modal } from '@/Components/ui/Modal';
import { Button } from '@/Components/ui/Button';
import { Select } from '@/Components/ui/Select';
import { cn } from '@/Lib/utils';

type Status = 'in' | 'out' | 'leave';

interface PresenceMember {
    id: number;
    name: string;
    status: Status;
    since: string | null;
    leave: { id: number; type: string | null; until: string | null } | null;
}

export interface TeamPresenceData {
    total: number;
    clocked_in: number;
    clocked_out: number;
    on_leave: number;
    members: PresenceMember[];
    can_manage: boolean;
}

const STATUS_META: Record<Status, { label: string; dot: string; text: string }> = {
    in: { label: 'Clocked in', dot: 'bg-emerald-500', text: 'text-emerald-600 dark:text-emerald-400' },
    out: { label: 'Clocked out', dot: 'bg-muted-foreground/40', text: 'text-muted-foreground' },
    leave: { label: 'On leave', dot: 'bg-amber-500', text: 'text-amber-600 dark:text-amber-400' },
};

const LEAVE_TYPES = [
    { value: 'vacation', label: 'Vacation' },
    { value: 'sick', label: 'Sick' },
    { value: 'personal', label: 'Personal' },
    { value: 'other', label: 'Other' },
];

function today(): string {
    return new Date().toISOString().slice(0, 10);
}

export function TeamPresence({ presence }: { presence: TeamPresenceData }) {
    // null = collapsed; otherwise the status bucket whose members are shown.
    const [openBucket, setOpenBucket] = useState<Status | 'all' | null>(null);
    const [leaveOpen, setLeaveOpen] = useState(false);

    const tiles: { key: Status | 'all'; label: string; value: number; icon: typeof Users; tone: string }[] = [
        { key: 'all', label: 'Team members', value: presence.total, icon: Users, tone: 'text-foreground' },
        { key: 'in', label: 'Clocked in', value: presence.clocked_in, icon: Clock, tone: STATUS_META.in.text },
        { key: 'out', label: 'Clocked out', value: presence.clocked_out, icon: LogOut, tone: STATUS_META.out.text },
        { key: 'leave', label: 'On leave', value: presence.on_leave, icon: Palmtree, tone: STATUS_META.leave.text },
    ];

    const toggle = (key: Status | 'all') => setOpenBucket(prev => (prev === key ? null : key));

    const shown = openBucket === null
        ? []
        : openBucket === 'all'
            ? presence.members
            : presence.members.filter(m => m.status === openBucket);

    const form = useForm({ user_id: '', type: 'vacation', start_date: today(), end_date: today(), note: '' });
    const submitLeave = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/crm/leave', {
            preserveScroll: true,
            onSuccess: () => { form.reset(); setLeaveOpen(false); },
        });
    };

    const removeLeave = (id: number) => {
        if (!confirm('Remove this leave?')) return;
        form.delete(`/crm/leave/${id}`, { preserveScroll: true });
    };

    const memberOptions = presence.members.map(m => ({ value: String(m.id), label: m.name }));
    const err = (k: string) => (form.errors as Record<string, string>)[k];

    return (
        <div className="card-surface p-4 sm:p-5">
            <div className="mb-3 flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <h2 className="text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Team presence</h2>
                    <span className="text-xs text-muted-foreground">
                        {presence.clocked_in} of {presence.total} working now
                    </span>
                </div>
                {presence.can_manage && (
                    <Button variant="secondary" size="sm" icon={Plus} onClick={() => setLeaveOpen(true)}>
                        Mark on leave
                    </Button>
                )}
            </div>

            <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                {tiles.map(t => {
                    const active = openBucket === t.key;
                    return (
                        <button
                            key={t.key}
                            type="button"
                            onClick={() => toggle(t.key)}
                            className={cn(
                                'group flex items-center gap-3 rounded-xl border px-3 py-2.5 text-left transition-colors',
                                active ? 'border-primary/40 bg-secondary' : 'border-border bg-secondary/40 hover:bg-secondary'
                            )}
                        >
                            <span className={cn('flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-background', t.tone)}>
                                <t.icon className="h-4 w-4" />
                            </span>
                            <span className="min-w-0">
                                <span className="block text-xl font-bold leading-none tabular-nums text-foreground">{t.value}</span>
                                <span className="mt-1 flex items-center gap-1 text-[11px] font-medium text-muted-foreground">
                                    {t.label}
                                    <ChevronDown className={cn('h-3 w-3 transition-transform', active && 'rotate-180')} />
                                </span>
                            </span>
                        </button>
                    );
                })}
            </div>

            {openBucket !== null && (
                <div className="mt-3 rounded-xl border border-border bg-secondary/30 p-2">
                    {shown.length === 0 ? (
                        <p className="px-2 py-3 text-center text-xs text-muted-foreground">Nobody here right now.</p>
                    ) : (
                        <ul className="divide-y divide-border/60">
                            {shown.map(m => {
                                const meta = STATUS_META[m.status];
                                return (
                                    <li key={m.id} className="flex items-center gap-2 px-2 py-2">
                                        <span className={cn('h-2 w-2 shrink-0 rounded-full', meta.dot)} />
                                        <span className="min-w-0 flex-1 truncate text-sm font-medium text-foreground">{m.name}</span>
                                        <span className={cn('text-xs', meta.text)}>
                                            {m.status === 'in' && m.since && `since ${m.since}`}
                                            {m.status === 'out' && meta.label}
                                            {m.status === 'leave' && m.leave && (
                                                <span className="capitalize">
                                                    {m.leave.type}{m.leave.until ? ` · until ${m.leave.until}` : ''}
                                                </span>
                                            )}
                                        </span>
                                        {presence.can_manage && m.status === 'leave' && m.leave && (
                                            <button
                                                type="button"
                                                onClick={() => removeLeave(m.leave!.id)}
                                                className="rounded-md p-1 text-muted-foreground transition-colors hover:bg-secondary hover:text-destructive"
                                                title="Remove leave"
                                            >
                                                <X className="h-3.5 w-3.5" />
                                            </button>
                                        )}
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </div>
            )}

            <Modal
                open={leaveOpen}
                onClose={() => setLeaveOpen(false)}
                title="Mark on leave"
                description="Record time-off for a team member."
                size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setLeaveOpen(false)}>Cancel</Button>
                        <Button onClick={submitLeave as unknown as () => void} disabled={form.processing}>
                            {form.processing ? 'Saving…' : 'Record leave'}
                        </Button>
                    </>
                }
            >
                <form onSubmit={submitLeave} className="space-y-4">
                    <div>
                        <label className="label">Team member *</label>
                        <Select className="w-full" value={form.data.user_id} onChange={v => form.setData('user_id', v)} options={memberOptions} placeholder="Select…" />
                        {err('user_id') && <p className="mt-1 text-xs text-destructive">{err('user_id')}</p>}
                    </div>
                    <div>
                        <label className="label">Type</label>
                        <Select className="w-full" value={form.data.type} onChange={v => form.setData('type', v)} options={LEAVE_TYPES} />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="label">From *</label>
                            <input type="date" className="input" value={form.data.start_date} onChange={e => form.setData('start_date', e.target.value)} />
                            {err('start_date') && <p className="mt-1 text-xs text-destructive">{err('start_date')}</p>}
                        </div>
                        <div>
                            <label className="label">To *</label>
                            <input type="date" className="input" value={form.data.end_date} min={form.data.start_date} onChange={e => form.setData('end_date', e.target.value)} />
                            {err('end_date') && <p className="mt-1 text-xs text-destructive">{err('end_date')}</p>}
                        </div>
                    </div>
                    <div>
                        <label className="label">Note</label>
                        <input className="input" value={form.data.note} onChange={e => form.setData('note', e.target.value)} placeholder="Optional reason or cover…" />
                    </div>
                </form>
            </Modal>
        </div>
    );
}
