import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Modal, ConfirmDialog } from '@/Components/ui/Modal';
import { Select } from '@/Components/ui/Select';
import { NumberInput } from '@/Components/ui/NumberInput';
import { Pill } from '@/Components/ui/Pill';
import { Tags, Plus, Pencil, Trash2, Ban } from 'lucide-react';

interface Group {
    id: number; name: string; keywords: string[]; naics_codes: string[];
    weight: number; is_exclusion: boolean; is_active: boolean; color: string | null;
}
interface Props {
    groups: Group[];
    priorities: Array<{ value: string; label: string; color: string }>;
}

const COLORS = ['red', 'amber', 'blue', 'indigo', 'cyan', 'green', 'purple', 'gray'];

export default function KeywordGroups({ groups }: Props) {
    const [modal, setModal] = useState<Group | null | 'new'>(null);
    const [deleting, setDeleting] = useState<Group | null>(null);

    return (
        <AppLayout>
            <Head title="Keyword Groups · Admin" />
            <div className="mx-auto max-w-5xl px-4 py-6 sm:px-6">
                <PageHeader
                    eyebrow="Admin"
                    title="Opportunity keyword groups"
                    description="Editable keywords that score incoming opportunities (BidPrime emails, SAM.gov) for QuakeLogic relevance. Exclusion groups mark matches Not Relevant."
                    icon={Tags}
                    actions={<Button icon={Plus} onClick={() => setModal('new')}>New group</Button>}
                />

                {groups.length === 0 ? (
                    <div className="card-surface p-10 text-center text-sm text-muted-foreground">No keyword groups yet — create one to start scoring opportunities.</div>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2">
                        {groups.map(g => (
                            <div key={g.id} className="card-surface p-5">
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h3 className="text-base font-semibold text-foreground">{g.name}</h3>
                                            {g.is_exclusion && <span className="inline-flex items-center gap-1 rounded-full bg-destructive/10 px-2 py-0.5 text-[11px] font-semibold text-destructive"><Ban className="h-3 w-3" /> Exclusion</span>}
                                            {!g.is_active && <span className="rounded-full bg-secondary px-2 py-0.5 text-[11px] font-semibold text-muted-foreground">Inactive</span>}
                                            {g.color && <Pill color={g.color} label={`weight ${g.weight}`} />}
                                            {!g.color && <span className="text-xs text-muted-foreground">weight {g.weight}</span>}
                                        </div>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-1">
                                        <button onClick={() => setModal(g)} className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground"><Pencil className="h-3.5 w-3.5" /></button>
                                        <button onClick={() => setDeleting(g)} className="rounded-md p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"><Trash2 className="h-3.5 w-3.5" /></button>
                                    </div>
                                </div>
                                <div className="mt-3 flex flex-wrap gap-1.5">
                                    {g.keywords.length === 0 && <span className="text-xs italic text-muted-foreground">No keywords yet.</span>}
                                    {g.keywords.map(k => <span key={k} className="rounded-full bg-secondary px-2 py-0.5 text-xs text-foreground">{k}</span>)}
                                </div>
                                {g.naics_codes.length > 0 && (
                                    <p className="mt-2 text-xs text-muted-foreground">NAICS: <span className="font-mono">{g.naics_codes.join(', ')}</span></p>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {modal && <GroupModal group={modal === 'new' ? null : modal} onClose={() => setModal(null)} />}
            <ConfirmDialog open={!!deleting} onClose={() => setDeleting(null)} onConfirm={() => { if (deleting) router.delete(`/admin/keyword-groups/${deleting.id}`, { preserveScroll: true, onFinish: () => setDeleting(null) }); }} title="Delete keyword group?" message={deleting ? <>Delete <span className="font-medium text-foreground">{deleting.name}</span>? Future imports won't score against it.</> : ''} />
        </AppLayout>
    );
}

const splitList = (s: string): string[] => s.split(/[\n,]+/).map(x => x.trim()).filter(Boolean);

function GroupModal({ group, onClose }: { group: Group | null; onClose: () => void }) {
    const isEdit = !!group;
    const form = useForm({
        name: group?.name ?? '',
        keywords: (group?.keywords ?? []).join('\n'),
        naics: (group?.naics_codes ?? []).join(', '),
        weight: String(group?.weight ?? 10),
        is_exclusion: group?.is_exclusion ?? false,
        is_active: group?.is_active ?? true,
        color: group?.color ?? '',
    });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform(d => ({
            name: d.name,
            keywords: splitList(d.keywords),
            naics_codes: splitList(d.naics),
            weight: Number(d.weight) || 10,
            is_exclusion: d.is_exclusion,
            is_active: d.is_active,
            color: d.color || null,
        }));
        const opts = { preserveScroll: true, onSuccess: () => { form.reset(); onClose(); } };
        if (isEdit) form.put(`/admin/keyword-groups/${group!.id}`, opts); else form.post('/admin/keyword-groups', opts);
    };
    return (
        <Modal open onClose={onClose} title={isEdit ? 'Edit Keyword Group' : 'New Keyword Group'}
            footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button onClick={submit as unknown as () => void} disabled={form.processing}>{form.processing ? 'Saving…' : isEdit ? 'Save' : 'Create'}</Button></>}>
            <form onSubmit={submit} className="space-y-4">
                <div><label className="label">Name *</label><input className="input" value={form.data.name} onChange={e => form.setData('name', e.target.value)} autoFocus placeholder="e.g. Seismic & Earthquake Monitoring" />{form.errors.name && <p className="mt-1 text-xs text-destructive">{form.errors.name}</p>}</div>
                <div>
                    <label className="label">Keywords <span className="font-normal text-muted-foreground">(one per line or comma-separated)</span></label>
                    <textarea className="input min-h-[96px]" value={form.data.keywords} onChange={e => form.setData('keywords', e.target.value)} placeholder={"seismic\naccelerometer\nshake table"} />
                </div>
                <div><label className="label">NAICS codes <span className="font-normal text-muted-foreground">(comma-separated, optional)</span></label><input className="input" value={form.data.naics} onChange={e => form.setData('naics', e.target.value)} placeholder="334513, 541330" /></div>
                <div className="grid grid-cols-2 gap-3">
                    <div><label className="label">Weight</label><NumberInput className="input" value={form.data.weight} onChange={e => form.setData('weight', e.target.value)} placeholder="10" /></div>
                    <div><label className="label">Colour</label><Select className="w-full" value={form.data.color} onChange={v => form.setData('color', v)} placeholder="— None —" options={COLORS.map(c => ({ value: c, label: c.charAt(0).toUpperCase() + c.slice(1) }))} /></div>
                </div>
                <div className="flex flex-wrap gap-4">
                    <label className="flex items-center gap-2 text-sm text-foreground">
                        <input type="checkbox" className="h-4 w-4 rounded border-input text-primary focus:ring-primary/50" checked={form.data.is_exclusion} onChange={e => form.setData('is_exclusion', e.target.checked)} />
                        Exclusion group (matches → Not Relevant)
                    </label>
                    <label className="flex items-center gap-2 text-sm text-foreground">
                        <input type="checkbox" className="h-4 w-4 rounded border-input text-primary focus:ring-primary/50" checked={form.data.is_active} onChange={e => form.setData('is_active', e.target.checked)} />
                        Active
                    </label>
                </div>
            </form>
        </Modal>
    );
}
