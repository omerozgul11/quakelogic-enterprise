import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { CrmLayout } from '@/Components/layout/CrmLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Select } from '@/Components/ui/Select';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Modal } from '@/Components/ui/Modal';
import { Zap, Plus, Pencil, Trash2, Play, Pause, ArrowRight, X } from 'lucide-react';
import { cn } from '@/Lib/utils';

interface Option { value: string; label: string }
interface AutomationAction { type: string; [k: string]: string | number | boolean | null | undefined }
interface Conditions { stage?: string; source?: string; min_value?: string | number }
interface Automation {
    id: number; name: string; is_active: boolean;
    trigger_event: string; trigger_label: string;
    conditions: Conditions; actions: AutomationAction[];
    run_count: number; last_run_at: string | null;
}
interface Options {
    triggers: Option[]; stages: Option[]; sources: string[]; priorities: string[];
    actionTypes: Option[]; users: Array<{ id: number; name: string }>;
}
interface Props { automations: Automation[]; canManage: boolean; options: Options }

const ACTION_LABEL: Record<string, string> = {
    create_followup: 'Create follow-up', notify: 'Notify', assign_owner: 'Assign owner', log_activity: 'Log note',
};

export default function AutomationsIndex({ automations, canManage, options }: Props) {
    const [editing, setEditing] = useState<Automation | null>(null);
    const [builderOpen, setBuilderOpen] = useState(false);

    const openNew = () => { setEditing(null); setBuilderOpen(true); };
    const openEdit = (a: Automation) => { setEditing(a); setBuilderOpen(true); };

    return (
        <CrmLayout>
            <Head title="Automations · CRM" />
            <div className="mx-auto max-w-4xl px-4 py-6 sm:px-6">
                <PageHeader
                    icon={Zap}
                    title="Automations"
                    description="Run actions automatically when leads are created or change stage."
                    actions={canManage && <Button icon={Plus} onClick={openNew}>New automation</Button>}
                />

                {automations.length === 0 ? (
                    <EmptyState icon={Zap} title="No automations yet"
                        description="Create a rule — e.g. when a lead reaches Proposal, create a follow-up and notify the owner."
                        action={canManage && <Button icon={Plus} onClick={openNew}>New automation</Button>} />
                ) : (
                    <div className="space-y-3">
                        {automations.map(a => (
                            <div key={a.id} className={cn('card-surface p-4', !a.is_active && 'opacity-60')}>
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <div className="flex items-center gap-2">
                                            <span className={cn('h-2 w-2 rounded-full', a.is_active ? 'bg-emerald-500' : 'bg-muted-foreground/40')} />
                                            <h3 className="truncate text-sm font-semibold text-foreground">{a.name}</h3>
                                        </div>
                                        <p className="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
                                            <span className="rounded bg-secondary px-1.5 py-0.5 font-medium text-foreground">{a.trigger_label}</span>
                                            {condSummary(a.conditions, options) && <><ArrowRight className="h-3 w-3" /> <span>{condSummary(a.conditions, options)}</span></>}
                                            <ArrowRight className="h-3 w-3" />
                                            {a.actions.map((ac, i) => <span key={i} className="rounded bg-primary/10 px-1.5 py-0.5 font-medium text-primary">{ACTION_LABEL[ac.type] ?? ac.type}</span>)}
                                        </p>
                                        <p className="mt-1.5 text-xs text-muted-foreground">Ran {a.run_count}×{a.last_run_at ? ` · last ${new Date(a.last_run_at).toLocaleDateString()}` : ''}</p>
                                    </div>
                                    {canManage && (
                                        <div className="flex shrink-0 items-center gap-0.5">
                                            <button onClick={() => router.post(`/crm/automations/${a.id}/toggle`, {}, { preserveScroll: true })} className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground" title={a.is_active ? 'Pause' : 'Activate'}>
                                                {a.is_active ? <Pause className="h-4 w-4" /> : <Play className="h-4 w-4" />}
                                            </button>
                                            <button onClick={() => openEdit(a)} className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground" title="Edit"><Pencil className="h-4 w-4" /></button>
                                            <button onClick={() => { if (confirm('Delete this automation?')) router.delete(`/crm/automations/${a.id}`, { preserveScroll: true }); }} className="rounded-md p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive" title="Delete"><Trash2 className="h-4 w-4" /></button>
                                        </div>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {builderOpen && canManage && (
                <AutomationBuilder open onClose={() => setBuilderOpen(false)} automation={editing} options={options} />
            )}
        </CrmLayout>
    );
}

function condSummary(c: Conditions, options: Options): string {
    const parts: string[] = [];
    if (c.stage) parts.push(`stage = ${options.stages.find(s => s.value === c.stage)?.label ?? c.stage}`);
    if (c.source) parts.push(`source = ${c.source}`);
    if (c.min_value) parts.push(`value ≥ ${c.min_value}`);
    return parts.join(' & ');
}

function blankAction(type: string): AutomationAction {
    switch (type) {
        case 'create_followup': return { type, title: 'Follow up', due_in_days: 2, priority: 'normal', assign: 'owner' };
        case 'notify': return { type, to: 'owner', message: '' };
        case 'assign_owner': return { type, user_id: '' };
        default: return { type: 'log_activity', body: '' };
    }
}

function AutomationBuilder({ open, onClose, automation, options }: { open: boolean; onClose: () => void; automation: Automation | null; options: Options }) {
    const form = useForm<{ name: string; is_active: boolean; trigger_event: string; conditions: Conditions; actions: AutomationAction[] }>({
        name: automation?.name ?? '',
        is_active: automation?.is_active ?? true,
        trigger_event: automation?.trigger_event ?? 'lead.stage_changed',
        conditions: {
            stage: automation?.conditions?.stage ?? '',
            source: automation?.conditions?.source ?? '',
            min_value: automation?.conditions?.min_value != null ? String(automation.conditions.min_value) : '',
        },
        actions: automation?.actions?.length ? automation.actions : [blankAction('create_followup')],
    });

    const setCond = (k: keyof Conditions, v: string) => form.setData('conditions', { ...form.data.conditions, [k]: v });
    const setAction = (i: number, patch: Partial<AutomationAction>) =>
        form.setData('actions', form.data.actions.map((a, idx) => idx === i ? { ...a, ...patch } : a));
    const changeActionType = (i: number, type: string) =>
        form.setData('actions', form.data.actions.map((a, idx) => idx === i ? blankAction(type) : a));
    const addAction = () => form.setData('actions', [...form.data.actions, blankAction('notify')]);
    const removeAction = (i: number) => form.setData('actions', form.data.actions.filter((_, idx) => idx !== i));

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => { form.reset(); onClose(); } };
        if (automation) form.put(`/crm/automations/${automation.id}`, opts);
        else form.post('/crm/automations', opts);
    };

    const err = (k: string) => (form.errors as Record<string, string>)[k];
    const userOpts = options.users.map(u => ({ value: String(u.id), label: u.name }));
    const assignOpts = [{ value: 'owner', label: 'Lead owner' }, { value: 'creator', label: 'Lead creator' }, ...userOpts];
    const notifyOpts = [{ value: 'owner', label: 'Lead owner' }, ...userOpts];

    return (
        <Modal open={open} onClose={onClose} title={automation ? 'Edit automation' : 'New automation'} size="lg"
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>Cancel</Button>
                    <Button onClick={submit as unknown as () => void} disabled={form.processing}>{form.processing ? 'Saving…' : automation ? 'Save' : 'Create'}</Button>
                </>
            }
        >
            <form onSubmit={submit} className="space-y-5">
                <div>
                    <label className="label">Name *</label>
                    <input className="input" value={form.data.name} onChange={e => form.setData('name', e.target.value)} placeholder="e.g. Nudge owner on Proposal stage" autoFocus />
                    {err('name') && <p className="mt-1 text-xs text-destructive">{err('name')}</p>}
                </div>

                <div className="rounded-xl border border-border p-3">
                    <p className="mb-2 text-xs font-bold uppercase tracking-wider text-muted-foreground/70">When</p>
                    <Select className="w-full" value={form.data.trigger_event} onChange={v => form.setData('trigger_event', v)} options={options.triggers} />
                    <div className="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-3">
                        <div>
                            <label className="label">Stage</label>
                            <Select className="w-full" value={form.data.conditions.stage ?? ''} onChange={v => setCond('stage', v)} placeholder="Any stage" options={options.stages} />
                        </div>
                        <div>
                            <label className="label">Source</label>
                            <Select className="w-full" value={form.data.conditions.source ?? ''} onChange={v => setCond('source', v)} placeholder="Any source" options={options.sources.map(s => ({ value: s, label: s }))} />
                        </div>
                        <div>
                            <label className="label">Min value</label>
                            <input type="number" min="0" className="input" value={form.data.conditions.min_value ?? ''} onChange={e => setCond('min_value', e.target.value)} placeholder="Any" />
                        </div>
                    </div>
                </div>

                <div className="rounded-xl border border-border p-3">
                    <div className="mb-2 flex items-center justify-between">
                        <p className="text-xs font-bold uppercase tracking-wider text-muted-foreground/70">Then do</p>
                        <button type="button" onClick={addAction} className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"><Plus className="h-3.5 w-3.5" /> Add action</button>
                    </div>
                    {err('actions') && <p className="mb-2 text-xs text-destructive">{err('actions')}</p>}
                    <div className="space-y-3">
                        {form.data.actions.map((a, i) => (
                            <div key={i} className="rounded-lg border border-border bg-secondary/30 p-3">
                                <div className="flex items-center gap-2">
                                    <Select className="flex-1" value={a.type} onChange={v => changeActionType(i, v)} options={options.actionTypes} />
                                    {form.data.actions.length > 1 && (
                                        <button type="button" onClick={() => removeAction(i)} className="rounded p-1 text-muted-foreground hover:text-destructive"><X className="h-4 w-4" /></button>
                                    )}
                                </div>
                                <div className="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    {a.type === 'create_followup' && (<>
                                        <input className="input" placeholder="Follow-up title" value={String(a.title ?? '')} onChange={e => setAction(i, { title: e.target.value })} />
                                        <input type="number" min="0" className="input" placeholder="Due in N days" value={String(a.due_in_days ?? '')} onChange={e => setAction(i, { due_in_days: e.target.value })} />
                                        <Select value={String(a.priority ?? 'normal')} onChange={v => setAction(i, { priority: v })} options={options.priorities.map(p => ({ value: p, label: p }))} />
                                        <Select value={String(a.assign ?? 'owner')} onChange={v => setAction(i, { assign: v })} options={assignOpts} />
                                    </>)}
                                    {a.type === 'notify' && (<>
                                        <Select value={String(a.to ?? 'owner')} onChange={v => setAction(i, { to: v })} options={notifyOpts} />
                                        <input className="input" placeholder="Message (optional)" value={String(a.message ?? '')} onChange={e => setAction(i, { message: e.target.value })} />
                                    </>)}
                                    {a.type === 'assign_owner' && (
                                        <Select value={String(a.user_id ?? '')} onChange={v => setAction(i, { user_id: v })} placeholder="Choose user" options={userOpts} />
                                    )}
                                    {a.type === 'log_activity' && (
                                        <input className="input sm:col-span-2" placeholder="Note to log on the lead" value={String(a.body ?? '')} onChange={e => setAction(i, { body: e.target.value })} />
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                <label className="flex cursor-pointer items-center gap-2 text-sm">
                    <input type="checkbox" checked={form.data.is_active} onChange={e => form.setData('is_active', e.target.checked)} className="h-4 w-4 accent-[var(--primary)]" />
                    <span className="text-foreground">Active</span>
                </label>
            </form>
        </Modal>
    );
}
