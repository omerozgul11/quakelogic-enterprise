import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { CrmLayout } from '@/Components/layout/CrmLayout';
import { Button } from '@/Components/ui/Button';
import { Pill } from '@/Components/ui/Pill';
import { Select } from '@/Components/ui/Select';
import { Modal, ConfirmDialog } from '@/Components/ui/Modal';
import { ProjectFormModal } from '@/Components/crm/ProjectFormModal';
import { cn, formatCurrency, formatDate, getInitials, avatarGradient } from '@/Lib/utils';
import { ArrowLeft, Pencil, Trash2, Plus, CalendarDays, Building2 } from 'lucide-react';

interface Project {
    id: number; name: string; code?: string | null; description?: string | null;
    status: string; status_label: string; status_color: string; progress: number;
    budget: number; start_date: string | null; due_date: string | null;
    company: string | null; company_id: number | null; owner: string | null; owner_id: number | null;
}
interface Task {
    id: number; title: string; description?: string | null; status: string; status_label: string; status_color: string;
    priority: string; due_date: string | null; assigned_to: number | null; assignee: string | null;
}
interface Option { value: string; label: string; color?: string }

interface Props {
    project: Project;
    tasks: Task[];
    companies: Array<{ id: number; name: string }>;
    owners: Array<{ id: number; name: string }>;
    statuses: Option[];
    taskStatuses: Option[];
    can: { manage: boolean };
}

const PRIORITIES = [
    { value: 'low', label: 'Low' }, { value: 'medium', label: 'Medium' }, { value: 'high', label: 'High' }, { value: 'urgent', label: 'Urgent' },
];
const PRIORITY_DOT: Record<string, string> = { low: 'bg-gray-400', medium: 'bg-blue-500', high: 'bg-amber-500', urgent: 'bg-red-500' };

export default function ProjectShow({ project, tasks, companies, owners, statuses, taskStatuses, can }: Props) {
    const [editOpen, setEditOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [taskModal, setTaskModal] = useState<Task | null | 'new'>(null);
    const [deletingTask, setDeletingTask] = useState<Task | null>(null);

    const moveTask = (task: Task, status: string) => {
        if (status === task.status) return;
        router.put(`/crm/projects/${project.id}/tasks/${task.id}`, { title: task.title, status, priority: task.priority, assigned_to: task.assigned_to, due_date: task.due_date }, { preserveScroll: true });
    };

    return (
        <CrmLayout>
            <Head title={`${project.name} · CRM`} />
            <div className="mx-auto max-w-6xl px-4 py-6 sm:px-6">
                <Link href="/crm/projects" className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" /> Projects
                </Link>

                <div className="card-surface mb-6 p-6">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div className="min-w-0">
                            <div className="flex items-center gap-2.5">
                                <h1 className="text-2xl font-bold tracking-tight text-foreground">{project.name}</h1>
                                <Pill color={project.status_color} label={project.status_label} />
                            </div>
                            <div className="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground">
                                {project.company && <span className="inline-flex items-center gap-1.5"><Building2 className="h-4 w-4" /> {project.company}</span>}
                                {project.code && <span className="font-mono text-xs">{project.code}</span>}
                                {project.due_date && <span className="inline-flex items-center gap-1.5"><CalendarDays className="h-4 w-4" /> Due {formatDate(project.due_date)}</span>}
                                {project.budget > 0 && <span className="font-medium text-foreground">{formatCurrency(project.budget)}</span>}
                            </div>
                        </div>
                        {can.manage && (
                            <div className="flex items-center gap-2">
                                <Button variant="secondary" icon={Pencil} onClick={() => setEditOpen(true)}>Edit</Button>
                                <Button variant="danger" icon={Trash2} onClick={() => setDeleting(true)}>Delete</Button>
                            </div>
                        )}
                    </div>

                    <div className="mt-5">
                        <div className="mb-1 flex items-center justify-between text-xs text-muted-foreground">
                            <span>Progress</span><span>{project.progress}% · {project.owner ?? 'Unassigned'}</span>
                        </div>
                        <div className="h-2.5 overflow-hidden rounded-full bg-secondary">
                            <div className="h-full rounded-full bg-brand-gradient transition-all" style={{ width: `${project.progress}%` }} />
                        </div>
                    </div>

                    {project.description && <p className="mt-4 whitespace-pre-line border-t border-border pt-4 text-sm text-muted-foreground">{project.description}</p>}
                </div>

                <div className="mb-3 flex items-center justify-between">
                    <h2 className="text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Tasks ({tasks.length})</h2>
                    {can.manage && <Button size="sm" icon={Plus} onClick={() => setTaskModal('new')}>Add Task</Button>}
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {taskStatuses.map(col => {
                        const colTasks = tasks.filter(t => t.status === col.value);
                        return (
                            <div key={col.value} className="rounded-xl bg-secondary/30 p-3">
                                <div className="mb-2 flex items-center gap-2 px-1 text-sm font-semibold text-foreground">
                                    {col.label} <span className="rounded-full bg-secondary px-1.5 text-xs text-muted-foreground">{colTasks.length}</span>
                                </div>
                                <div className="space-y-2">
                                    {colTasks.map(task => (
                                        <div key={task.id} className="rounded-lg border border-border bg-card p-3 shadow-sm">
                                            <div className="flex items-start gap-2">
                                                <span className={cn('mt-1 h-2 w-2 shrink-0 rounded-full', PRIORITY_DOT[task.priority] ?? PRIORITY_DOT.medium)} title={task.priority} />
                                                <button onClick={() => can.manage && setTaskModal(task)} className="min-w-0 flex-1 text-left text-sm font-medium text-foreground hover:text-primary">{task.title}</button>
                                            </div>
                                            <div className="mt-2 flex items-center justify-between">
                                                <span className="text-xs text-muted-foreground">{task.due_date ? formatDate(task.due_date) : ''}</span>
                                                {task.assignee && <span className={cn('flex h-6 w-6 items-center justify-center rounded-full bg-gradient-to-br text-[9px] font-bold text-white', avatarGradient(task.assignee))} title={task.assignee}>{getInitials(task.assignee)}</span>}
                                            </div>
                                            {can.manage && (
                                                <div className="mt-2.5 flex items-center gap-1.5 border-t border-border pt-2.5">
                                                    <Select size="sm" className="flex-1" value={task.status} onChange={v => moveTask(task, v)} options={taskStatuses.map(s => ({ value: s.value, label: s.label }))} />
                                                    <button onClick={() => setDeletingTask(task)} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive" title="Delete"><Trash2 className="h-4 w-4" /></button>
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                    {colTasks.length === 0 && <p className="px-1 py-3 text-center text-xs text-muted-foreground">—</p>}
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>

            {editOpen && <ProjectFormModal open onClose={() => setEditOpen(false)} project={project} companies={companies} owners={owners} statuses={statuses} />}
            {taskModal && <TaskModal projectId={project.id} task={taskModal === 'new' ? null : taskModal} owners={owners} taskStatuses={taskStatuses} onClose={() => setTaskModal(null)} />}

            <ConfirmDialog open={deleting} onClose={() => setDeleting(false)} onConfirm={() => router.delete(`/crm/projects/${project.id}`)} title="Delete project?" message={<>This removes <span className="font-medium text-foreground">{project.name}</span> and all its tasks.</>} />
            <ConfirmDialog open={!!deletingTask} onClose={() => setDeletingTask(null)} onConfirm={() => { if (deletingTask) router.delete(`/crm/projects/${project.id}/tasks/${deletingTask.id}`, { preserveScroll: true, onFinish: () => setDeletingTask(null) }); }} title="Delete task?" message={deletingTask ? <>Delete <span className="font-medium text-foreground">{deletingTask.title}</span>?</> : ''} />
        </CrmLayout>
    );
}

function TaskModal({ projectId, task, owners, taskStatuses, onClose }: { projectId: number; task: Task | null; owners: Array<{ id: number; name: string }>; taskStatuses: Option[]; onClose: () => void }) {
    const isEdit = !!task;
    const form = useForm({
        title: task?.title ?? '',
        description: task?.description ?? '',
        status: task?.status ?? 'open',
        priority: task?.priority ?? 'medium',
        due_date: task?.due_date ?? '',
        assigned_to: task?.assigned_to ? String(task.assigned_to) : '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform(d => ({ ...d, assigned_to: d.assigned_to || null, due_date: d.due_date || null }));
        const opts = { preserveScroll: true, onSuccess: () => { form.reset(); onClose(); } };
        if (isEdit) form.put(`/crm/projects/${projectId}/tasks/${task!.id}`, opts);
        else form.post(`/crm/projects/${projectId}/tasks`, opts);
    };

    return (
        <Modal open onClose={onClose} title={isEdit ? 'Edit Task' : 'Add Task'}
            footer={<>
                <Button variant="ghost" onClick={onClose}>Cancel</Button>
                <Button onClick={submit as unknown as () => void} disabled={form.processing}>{form.processing ? 'Saving…' : isEdit ? 'Save' : 'Add Task'}</Button>
            </>}>
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <label className="label">Task *</label>
                    <input className="input" value={form.data.title} onChange={e => form.setData('title', e.target.value)} autoFocus />
                    {form.errors.title && <p className="mt-1 text-xs text-destructive">{form.errors.title}</p>}
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="label">Status</label>
                        <Select className="w-full" value={form.data.status} onChange={v => form.setData('status', v)} options={taskStatuses.map(s => ({ value: s.value, label: s.label }))} />
                    </div>
                    <div>
                        <label className="label">Priority</label>
                        <Select className="w-full" value={form.data.priority} onChange={v => form.setData('priority', v)} options={PRIORITIES} />
                    </div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="label">Assignee</label>
                        <Select className="w-full" value={form.data.assigned_to} onChange={v => form.setData('assigned_to', v)} placeholder="— Unassigned —" options={owners.map(o => ({ value: String(o.id), label: o.name }))} />
                    </div>
                    <div>
                        <label className="label">Due date</label>
                        <input type="date" className="input" value={form.data.due_date} onChange={e => form.setData('due_date', e.target.value)} />
                    </div>
                </div>
                <div>
                    <label className="label">Description</label>
                    <textarea className="input min-h-[72px]" value={form.data.description} onChange={e => form.setData('description', e.target.value)} />
                </div>
            </form>
        </Modal>
    );
}
