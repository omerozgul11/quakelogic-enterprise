import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Modal } from '@/Components/ui/Modal';
import { Button } from '@/Components/ui/Button';
import { Select } from '@/Components/ui/Select';
import { NumberInput } from '@/Components/ui/NumberInput';
import { statusLabel } from '@/Lib/utils';

export interface EditableProject {
    id: number;
    name?: string;
    code?: string | null;
    status?: string;
    description?: string | null;
    notes?: string | null;
    address?: string | null;
    poc_name?: string | null;
    poc_role?: string | null;
    poc_phone?: string | null;
    poc_email?: string | null;
    reference_numbers?: string | null;
    logistics?: string | null;
    specs?: string | null;
    start_date?: string | null;
    due_date?: string | null;
    budget?: number | null;
    company_id?: number | null;
    owner_id?: number | null;
    manager_id?: number | null;
}

interface Props {
    open: boolean;
    onClose: () => void;
    project?: EditableProject | null;
    companies: Array<{ id: number; name: string }>;
    owners: Array<{ id: number; name: string }>;
    statuses: Array<{ value: string; label: string }>;
    /** Existing proposals (not yet linked) selectable as the related proposal on a new project. */
    proposals?: Array<{ id: number; number: string; name: string; status: string }>;
    /** Owner reassignment is admin-only. */
    canAdminister?: boolean;
}

export function ProjectFormModal({ open, onClose, project, companies, owners, statuses, proposals = [], canAdminister = false }: Props) {
    const isEdit = !!project;
    const form = useForm({
        name: project?.name ?? '',
        code: project?.code ?? '',
        proposal_submission_id: '',
        status: project?.status ?? 'new',
        description: project?.description ?? '',
        notes: project?.notes ?? '',
        address: project?.address ?? '',
        poc_name: project?.poc_name ?? '',
        poc_role: project?.poc_role ?? '',
        poc_phone: project?.poc_phone ?? '',
        poc_email: project?.poc_email ?? '',
        reference_numbers: project?.reference_numbers ?? '',
        logistics: project?.logistics ?? '',
        specs: project?.specs ?? '',
        start_date: project?.start_date ?? '',
        due_date: project?.due_date ?? '',
        budget: project?.budget != null ? String(project.budget) : '',
        company_id: project?.company_id ? String(project.company_id) : '',
        owner_id: project?.owner_id ? String(project.owner_id) : '',
        project_manager_id: project?.manager_id ? String(project.manager_id) : '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform(data => ({
            ...data,
            proposal_submission_id: data.proposal_submission_id || null,
            company_id: data.company_id || null,
            owner_id: data.owner_id || null,
            project_manager_id: data.project_manager_id || null,
            budget: data.budget || null,
            start_date: data.start_date || null,
            due_date: data.due_date || null,
        }));
        const opts = { preserveScroll: true, onSuccess: () => { form.reset(); onClose(); } };
        if (isEdit) form.put(`/projects/${project!.id}`, opts);
        else form.post('/projects', opts);
    };

    const err = (k: keyof typeof form.data) => form.errors[k as keyof typeof form.errors];

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={isEdit ? 'Edit Project' : 'New Project'}
            description={isEdit ? 'Update this project.' : 'Plan and track a new project.'}
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>Cancel</Button>
                    <Button onClick={submit as unknown as () => void} disabled={form.processing}>
                        {form.processing ? 'Saving…' : isEdit ? 'Save Changes' : 'Create Project'}
                    </Button>
                </>
            }
        >
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-[1fr_8rem] gap-3">
                    <div>
                        <label className="label">Project name *</label>
                        <input className="input" value={form.data.name} onChange={e => form.setData('name', e.target.value)} autoFocus />
                        {err('name') && <p className="mt-1 text-xs text-destructive">{err('name')}</p>}
                    </div>
                    <div>
                        <label className="label">Code</label>
                        <input className="input" value={form.data.code} onChange={e => form.setData('code', e.target.value)} placeholder="optional" />
                    </div>
                </div>
                {!isEdit && proposals.length > 0 && (
                    <div>
                        <label className="label">Related proposal</label>
                        <Select className="w-full" value={form.data.proposal_submission_id} onChange={v => form.setData('proposal_submission_id', v)}
                            placeholder="— None —" searchable searchPlaceholder="Search by name or number…"
                            options={proposals.map(p => ({ value: String(p.id), label: `${p.number} — ${p.name}${p.status ? ` (${statusLabel(p.status)})` : ''}` }))} />
                        {err('proposal_submission_id')
                            ? <p className="mt-1 text-xs text-destructive">{err('proposal_submission_id')}</p>
                            : <p className="mt-1 text-xs text-muted-foreground">Links this project to an existing proposal and its documents.</p>}
                    </div>
                )}
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="label">Client</label>
                        <Select className="w-full" value={form.data.company_id} onChange={v => form.setData('company_id', v)} placeholder="— None —"
                            searchable searchPlaceholder="Search clients…"
                            options={companies.map(c => ({ value: String(c.id), label: c.name }))} />
                    </div>
                    <div>
                        <label className="label">Status</label>
                        <Select className="w-full" value={form.data.status} onChange={v => form.setData('status', v)}
                            options={statuses.map(s => ({ value: s.value, label: s.label }))} />
                    </div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                    {canAdminister && (
                        <div>
                            <label className="label">Owner</label>
                            <Select className="w-full" value={form.data.owner_id} onChange={v => form.setData('owner_id', v)} placeholder="— Me —"
                                searchable searchPlaceholder="Search people…"
                                options={owners.map(o => ({ value: String(o.id), label: o.name }))} />
                        </div>
                    )}
                    <div>
                        <label className="label">Project manager</label>
                        <Select className="w-full" value={form.data.project_manager_id} onChange={v => form.setData('project_manager_id', v)} placeholder="— Unassigned —"
                            searchable searchPlaceholder="Search people…"
                            options={owners.map(o => ({ value: String(o.id), label: o.name }))} />
                    </div>
                    <div>
                        <label className="label">Budget</label>
                        <NumberInput className="input" value={form.data.budget} onChange={e => form.setData('budget', e.target.value)} placeholder="0.00" />
                    </div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="label">Start date</label>
                        <input type="date" className="input" value={form.data.start_date} onChange={e => form.setData('start_date', e.target.value)} />
                    </div>
                    <div>
                        <label className="label">Due date</label>
                        <input type="date" className="input" value={form.data.due_date} onChange={e => form.setData('due_date', e.target.value)} />
                    </div>
                </div>
                <div>
                    <label className="label">Description</label>
                    <textarea className="input min-h-[64px]" value={form.data.description} onChange={e => form.setData('description', e.target.value)} />
                </div>
                <div>
                    <label className="label">Notes</label>
                    <textarea className="input min-h-[48px]" value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} placeholder="Important internal notes…" />
                </div>

                <div className="border-t border-border pt-4">
                    <p className="mb-3 text-xs font-bold uppercase tracking-wider text-muted-foreground/70">Site, logistics &amp; specs</p>
                    <div className="space-y-4">
                        <div>
                            <label className="label">Site / delivery address</label>
                            <textarea className="input min-h-[48px]" value={form.data.address} onChange={e => form.setData('address', e.target.value)} placeholder="Street, city, state, ZIP…" />
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="label">Point of contact</label>
                                <input className="input" value={form.data.poc_name} onChange={e => form.setData('poc_name', e.target.value)} placeholder="Name" />
                            </div>
                            <div>
                                <label className="label">POC title / role</label>
                                <input className="input" value={form.data.poc_role} onChange={e => form.setData('poc_role', e.target.value)} placeholder="optional" />
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="label">POC phone</label>
                                <input className="input" value={form.data.poc_phone} onChange={e => form.setData('poc_phone', e.target.value)} placeholder="optional" />
                            </div>
                            <div>
                                <label className="label">POC email</label>
                                <input className="input" value={form.data.poc_email} onChange={e => form.setData('poc_email', e.target.value)} placeholder="optional" />
                                {err('poc_email') && <p className="mt-1 text-xs text-destructive">{err('poc_email')}</p>}
                            </div>
                        </div>
                        <div>
                            <label className="label">Contract / order reference numbers</label>
                            <textarea className="input min-h-[48px]" value={form.data.reference_numbers} onChange={e => form.setData('reference_numbers', e.target.value)} placeholder="Contract #, delivery order #, award #…" />
                        </div>
                        <div>
                            <label className="label">Logistics notes</label>
                            <textarea className="input min-h-[48px]" value={form.data.logistics} onChange={e => form.setData('logistics', e.target.value)} placeholder="Delivery windows, gate hours, equipment needed, freight terms…" />
                        </div>
                        <div>
                            <label className="label">Specifications</label>
                            <textarea className="input min-h-[64px]" value={form.data.specs} onChange={e => form.setData('specs', e.target.value)} placeholder="Detailed product / project specifications…" />
                        </div>
                    </div>
                </div>
            </form>
        </Modal>
    );
}
