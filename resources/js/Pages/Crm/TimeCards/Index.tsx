import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { CrmLayout } from '@/Components/layout/CrmLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Select } from '@/Components/ui/Select';
import { Modal } from '@/Components/ui/Modal';
import { AnimatedNumber } from '@/Components/ui/AnimatedNumber';
import { Clock, Plus, Pencil, Trash2 } from 'lucide-react';

interface Row {
    id: number;
    user_id: number;
    user_name: string;
    date: string;
    weekday: string;
    date_key: string;
    clock_in: string;
    clock_in_edit: string;
    clock_out: string | null;
    clock_out_edit: string | null;
    minutes: number | null;
    is_open: boolean;
    note: string | null;
    source: string;
    can_edit: boolean;
}

interface Props {
    entries: Row[];
    filters: { from: string; to: string; user_id: number | null };
    summary: { total_minutes: number; total_days: number; entry_count: number; open_count: number };
    users: Array<{ id: number; name: string }>;
    can: { manageAll: boolean };
}

function fmtMinutes(mins: number): string {
    const total = Math.round(mins);
    const h = Math.floor(total / 60);
    const m = total % 60;
    return `${h}h ${String(m).padStart(2, '0')}m`;
}

function isoDate(d: Date): string {
    const x = new Date(d);
    x.setMinutes(x.getMinutes() - x.getTimezoneOffset());
    return x.toISOString().slice(0, 10);
}

function nowLocal(): string {
    const d = new Date();
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
    return d.toISOString().slice(0, 16);
}

export default function TimeCards({ entries, filters, summary, users, can }: Props) {
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const [userId, setUserId] = useState(filters.user_id ? String(filters.user_id) : '');
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<Row | null>(null);

    const applyWith = (f: string, t: string, u: string) => {
        router.get('/crm/time-cards',
            { from: f, to: t, ...(can.manageAll && u ? { user_id: u } : {}) },
            { preserveScroll: true, preserveState: true, replace: true });
    };

    const quick = (kind: 'week' | 'month' | 'last30') => {
        const today = new Date();
        let start = new Date();
        if (kind === 'week') {
            const day = (today.getDay() + 6) % 7; // Monday = 0
            start.setDate(today.getDate() - day);
        } else if (kind === 'month') {
            start = new Date(today.getFullYear(), today.getMonth(), 1);
        } else {
            start.setDate(today.getDate() - 29);
        }
        const f = isoDate(start);
        const t = isoDate(today);
        setFrom(f); setTo(t);
        applyWith(f, t, userId);
    };

    const form = useForm({ user_id: '', clock_in: '', clock_out: '', note: '' });

    const openAdd = () => {
        setEditing(null);
        form.clearErrors();
        form.setData({ user_id: '', clock_in: nowLocal(), clock_out: '', note: '' });
        setModalOpen(true);
    };

    const openEdit = (row: Row) => {
        setEditing(row);
        form.clearErrors();
        form.setData({
            user_id: String(row.user_id),
            clock_in: row.clock_in_edit,
            clock_out: row.clock_out_edit ?? '',
            note: row.note ?? '',
        });
        setModalOpen(true);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => { setModalOpen(false); form.reset(); } };
        if (editing) form.put(`/crm/time-cards/${editing.id}`, opts);
        else form.post('/crm/time-cards', opts);
    };

    const remove = (row: Row) => {
        if (!window.confirm('Remove this time entry?')) return;
        router.delete(`/crm/time-cards/${row.id}`, { preserveScroll: true });
    };

    const err = (k: keyof typeof form.data) => form.errors[k as keyof typeof form.errors];

    return (
        <CrmLayout>
            <Head title="Time Cards" />
            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6">
                <PageHeader
                    icon={Clock}
                    title="Time Cards"
                    description="Review clocked shifts and totals."
                    actions={<Button icon={Plus} onClick={openAdd}>Add entry</Button>}
                />

                {/* Filters */}
                <div className="card-surface mb-6 flex flex-wrap items-end gap-3 p-4">
                    {can.manageAll && (
                        <div className="min-w-[180px]">
                            <label className="label">User</label>
                            <Select
                                className="w-full"
                                value={userId}
                                onChange={v => { setUserId(v); applyWith(from, to, v); }}
                                options={[{ value: '', label: 'All users' }, ...users.map(u => ({ value: String(u.id), label: u.name }))]}
                            />
                        </div>
                    )}
                    <div>
                        <label className="label">From</label>
                        <input type="date" className="input" value={from} onChange={e => setFrom(e.target.value)} />
                    </div>
                    <div>
                        <label className="label">To</label>
                        <input type="date" className="input" value={to} onChange={e => setTo(e.target.value)} />
                    </div>
                    <Button variant="secondary" onClick={() => applyWith(from, to, userId)}>Apply</Button>
                    <div className="flex items-center gap-1.5">
                        <button type="button" onClick={() => quick('week')} className="rounded-md border border-border px-2.5 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground">This week</button>
                        <button type="button" onClick={() => quick('month')} className="rounded-md border border-border px-2.5 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground">This month</button>
                        <button type="button" onClick={() => quick('last30')} className="rounded-md border border-border px-2.5 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground">Last 30 days</button>
                    </div>
                </div>

                {/* Summary */}
                <div className="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div className="card-surface p-4">
                        <p className="text-[11px] font-bold uppercase tracking-wider text-muted-foreground/70">Total time</p>
                        <p className="mt-1 font-mono text-2xl font-bold tabular-nums text-foreground"><AnimatedNumber value={summary.total_minutes} format={fmtMinutes} /></p>
                    </div>
                    <div className="card-surface p-4">
                        <p className="text-[11px] font-bold uppercase tracking-wider text-muted-foreground/70">Days</p>
                        <p className="mt-1 text-2xl font-bold tabular-nums text-foreground"><AnimatedNumber value={summary.total_days} /></p>
                    </div>
                    <div className="card-surface p-4">
                        <p className="text-[11px] font-bold uppercase tracking-wider text-muted-foreground/70">Entries</p>
                        <p className="mt-1 text-2xl font-bold tabular-nums text-foreground"><AnimatedNumber value={summary.entry_count} /></p>
                    </div>
                    <div className="card-surface p-4">
                        <p className="text-[11px] font-bold uppercase tracking-wider text-muted-foreground/70">Open now</p>
                        <p className="mt-1 text-2xl font-bold tabular-nums text-foreground"><AnimatedNumber value={summary.open_count} /></p>
                    </div>
                </div>

                {/* Table */}
                <div className="card-surface overflow-hidden">
                    {entries.length === 0 ? (
                        <p className="px-5 py-10 text-center text-sm text-muted-foreground">No time entries for this range.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground/70">
                                        {can.manageAll && <th className="px-4 py-3 font-semibold">User</th>}
                                        <th className="px-4 py-3 font-semibold">Date</th>
                                        <th className="px-4 py-3 font-semibold">Clock in</th>
                                        <th className="px-4 py-3 font-semibold">Clock out</th>
                                        <th className="px-4 py-3 font-semibold">Duration</th>
                                        <th className="px-4 py-3 font-semibold">Note</th>
                                        <th className="px-4 py-3" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {entries.map(row => (
                                        <tr key={row.id} className="border-b border-border last:border-0">
                                            {can.manageAll && <td className="px-4 py-3 font-medium text-foreground">{row.user_name}</td>}
                                            <td className="px-4 py-3 text-foreground">
                                                <span className="font-medium">{row.date}</span>
                                                <span className="ml-1.5 text-xs text-muted-foreground">{row.weekday}</span>
                                            </td>
                                            <td className="px-4 py-3 tabular-nums text-foreground">{row.clock_in}</td>
                                            <td className="px-4 py-3 tabular-nums text-foreground">
                                                {row.clock_out ?? <span className="text-emerald-600 dark:text-emerald-400">In progress</span>}
                                            </td>
                                            <td className="px-4 py-3 font-mono tabular-nums text-foreground">
                                                {row.minutes != null ? fmtMinutes(row.minutes) : '—'}
                                            </td>
                                            <td className="max-w-[260px] truncate px-4 py-3 text-muted-foreground">{row.note ?? '—'}</td>
                                            <td className="px-4 py-3 text-right">
                                                {row.can_edit && (
                                                    <div className="flex items-center justify-end gap-1">
                                                        <button onClick={() => openEdit(row)} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground" title="Edit"><Pencil className="h-4 w-4" /></button>
                                                        <button onClick={() => remove(row)} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive" title="Remove"><Trash2 className="h-4 w-4" /></button>
                                                    </div>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>

            <Modal
                open={modalOpen}
                onClose={() => setModalOpen(false)}
                size="lg"
                title={editing ? 'Edit time entry' : 'Add time entry'}
                description="Clock-out can be left blank for an in-progress shift."
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setModalOpen(false)}>Cancel</Button>
                        <Button onClick={submit as unknown as () => void} disabled={form.processing}>
                            {form.processing ? 'Saving…' : editing ? 'Save changes' : 'Add entry'}
                        </Button>
                    </>
                }
            >
                <form onSubmit={submit} className="space-y-4">
                    {can.manageAll && !editing && (
                        <div>
                            <label className="label">User</label>
                            <Select
                                className="w-full"
                                value={form.data.user_id}
                                onChange={v => form.setData('user_id', v)}
                                options={[{ value: '', label: 'Myself' }, ...users.map(u => ({ value: String(u.id), label: u.name }))]}
                            />
                        </div>
                    )}
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label className="label">Clock in *</label>
                            <input type="datetime-local" className="input" value={form.data.clock_in} onChange={e => form.setData('clock_in', e.target.value)} />
                            {err('clock_in') && <p className="mt-1 text-xs text-destructive">{err('clock_in')}</p>}
                        </div>
                        <div>
                            <label className="label">Clock out</label>
                            <input type="datetime-local" className="input" value={form.data.clock_out} onChange={e => form.setData('clock_out', e.target.value)} />
                            {err('clock_out') && <p className="mt-1 text-xs text-destructive">{err('clock_out')}</p>}
                        </div>
                    </div>
                    <div>
                        <label className="label">Note</label>
                        <input className="input" value={form.data.note} onChange={e => form.setData('note', e.target.value)} placeholder="Optional — e.g. on-site at client" />
                        {err('note') && <p className="mt-1 text-xs text-destructive">{err('note')}</p>}
                    </div>
                </form>
            </Modal>
        </CrmLayout>
    );
}
