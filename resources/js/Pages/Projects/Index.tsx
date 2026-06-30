import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ProjectsLayout } from '@/Components/layout/ProjectsLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Pagination } from '@/Components/ui/Pagination';
import { Select } from '@/Components/ui/Select';
import { SearchInput } from '@/Components/ui/SearchInput';
import { Pill } from '@/Components/ui/Pill';
import { Modal } from '@/Components/ui/Modal';
import { ProjectFormModal, EditableProject } from '@/Components/crm/ProjectFormModal';
import { cn, formatCurrency, formatDate, getInitials, avatarGradient } from '@/Lib/utils';
import { PaginatedResponse } from '@/Types';
import { FolderKanban, Plus, Settings, ListChecks, Users, Sparkles, AlertTriangle, CheckCircle2, Layers } from 'lucide-react';

interface ProjectRow extends EditableProject {
    id: number;
    name: string;
    project_number: string | null;
    status: string;
    status_label: string;
    status_color: string;
    progress: number;
    budget: number | null;
    due_date: string | null;
    company: string | null;
    owner: string | null;
    manager: string | null;
    created_via: string;
    from_proposal: boolean;
    tasks_count: number;
    completed_tasks_count: number;
    members_count: number;
}

interface Props {
    projects: PaginatedResponse<ProjectRow>;
    filters: Record<string, string>;
    stats: { total: number; open: number; completed: number; overdue: number };
    companies: Array<{ id: number; name: string }>;
    owners: Array<{ id: number; name: string }>;
    statuses: Array<{ value: string; label: string; color: string }>;
    awardableProposals: Array<{ id: number; number: string; name: string }>;
    linkableProposals: Array<{ id: number; number: string; name: string; status: string }>;
    can: { manage: boolean; manageAll: boolean; settings: boolean };
}

function StatCard({ icon: Icon, label, value, tone }: { icon: React.ComponentType<{ className?: string }>; label: string; value: number; tone: string }) {
    return (
        <div className="card-surface flex items-center gap-3 p-4">
            <span className={cn('flex h-10 w-10 items-center justify-center rounded-lg', tone)}>
                <Icon className="h-5 w-5" />
            </span>
            <div>
                <p className="text-2xl font-bold leading-none text-foreground">{value}</p>
                <p className="mt-1 text-xs text-muted-foreground">{label}</p>
            </div>
        </div>
    );
}

export default function ProjectsIndex({ projects, filters, stats, companies, owners, statuses, awardableProposals, linkableProposals, can }: Props) {
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<ProjectRow | null>(null);
    const [fromProposalOpen, setFromProposalOpen] = useState(false);

    const handleFilter = (key: string, value: string) => {
        router.get('/projects', { ...filters, [key]: value || undefined }, { preserveState: true, preserveScroll: true, replace: true });
    };

    return (
        <ProjectsLayout>
            <Head title="Projects" />
            <div className="p-4 sm:p-6">
                <PageHeader
                    icon={FolderKanban}
                    title="Projects"
                    description="Deliver awarded work — logistics, vendors, tasks, milestones & POs"
                    actions={
                        <div className="flex items-center gap-2">
                            {can.settings && (
                                <Link href="/projects/settings">
                                    <Button variant="secondary" icon={Settings}>Settings</Button>
                                </Link>
                            )}
                            {can.manage && awardableProposals.length > 0 && (
                                <Button variant="secondary" icon={Sparkles} onClick={() => setFromProposalOpen(true)}>From Proposal</Button>
                            )}
                            {can.manage && <Button onClick={() => { setEditing(null); setFormOpen(true); }} icon={Plus}>New Project</Button>}
                        </div>
                    }
                />

                <div className="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <StatCard icon={Layers} label="Total projects" value={stats.total} tone="bg-blue-100 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300" />
                    <StatCard icon={ListChecks} label="In progress" value={stats.open} tone="bg-indigo-100 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300" />
                    <StatCard icon={CheckCircle2} label="Completed" value={stats.completed} tone="bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300" />
                    <StatCard icon={AlertTriangle} label="Overdue" value={stats.overdue} tone="bg-red-100 text-red-700 dark:bg-red-950/50 dark:text-red-300" />
                </div>

                <Card className="mb-4 p-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <SearchInput className="min-w-0 flex-1 sm:max-w-sm" initial={filters.search ?? ''} onSearch={v => handleFilter('search', v)} placeholder="Search name, number, serial #, tracking #, site, contact…" />
                        <Select value={filters.status ?? ''} onChange={v => handleFilter('status', v)} options={statuses.map(s => ({ value: s.value, label: s.label }))} placeholder="All statuses" className="w-full sm:w-48" />
                        {can.manageAll && (
                            <Select value={filters.owner ?? ''} onChange={v => handleFilter('owner', v)} options={owners.map(o => ({ value: String(o.id), label: o.name }))} placeholder="All owners" className="w-full sm:w-48" />
                        )}
                        <button
                            onClick={() => handleFilter('mine', filters.mine ? '' : '1')}
                            className={cn('rounded-lg border px-3 py-2 text-sm font-medium transition-colors',
                                filters.mine ? 'border-primary bg-primary/10 text-primary' : 'border-border text-muted-foreground hover:bg-secondary')}
                        >
                            My projects
                        </button>
                    </div>
                </Card>

                {projects.data.length === 0 ? (
                    <Card>
                        <EmptyState icon={FolderKanban} title="No projects found" description="Projects are created automatically when a proposal is awarded — or add one manually." action={can.manage && <Button onClick={() => { setEditing(null); setFormOpen(true); }} icon={Plus}>New Project</Button>} />
                    </Card>
                ) : (
                    <Card className="overflow-hidden p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-border text-left text-xs uppercase tracking-wider text-muted-foreground">
                                        <th className="px-4 py-3 font-semibold">Project</th>
                                        <th className="px-4 py-3 font-semibold">Client</th>
                                        <th className="px-4 py-3 font-semibold">Owner</th>
                                        <th className="px-4 py-3 font-semibold">Status</th>
                                        <th className="px-4 py-3 font-semibold">Progress</th>
                                        <th className="hidden px-4 py-3 font-semibold lg:table-cell">Budget</th>
                                        <th className="hidden px-4 py-3 font-semibold md:table-cell">Due</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {projects.data.map(p => (
                                        <tr key={p.id} className="border-b border-border/60 transition-colors last:border-0 hover:bg-secondary/40">
                                            <td className="px-4 py-3">
                                                <Link href={`/projects/${p.id}`} className="block min-w-0">
                                                    <span className="flex items-center gap-1.5 font-semibold text-foreground hover:text-primary">
                                                        {p.name}
                                                        {p.from_proposal && <span title="Created from a proposal" className="inline-flex"><Sparkles className="h-3.5 w-3.5 text-primary" /></span>}
                                                    </span>
                                                    <span className="flex items-center gap-2 text-xs text-muted-foreground">
                                                        {p.project_number && <span className="font-mono">{p.project_number}</span>}
                                                        <span className="inline-flex items-center gap-1"><ListChecks className="h-3 w-3" />{p.completed_tasks_count}/{p.tasks_count}</span>
                                                        <span className="inline-flex items-center gap-1"><Users className="h-3 w-3" />{p.members_count}</span>
                                                    </span>
                                                </Link>
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">{p.company ?? 'Internal'}</td>
                                            <td className="px-4 py-3">
                                                {p.owner ? (
                                                    <span className="inline-flex items-center gap-1.5 text-muted-foreground">
                                                        <span className={cn('flex h-6 w-6 items-center justify-center rounded-full bg-gradient-to-br text-[9px] font-bold text-white', avatarGradient(p.owner))}>{getInitials(p.owner)}</span>
                                                        <span className="hidden sm:inline">{p.owner}</span>
                                                    </span>
                                                ) : <span className="text-muted-foreground">—</span>}
                                            </td>
                                            <td className="px-4 py-3"><Pill color={p.status_color} label={p.status_label} /></td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-2">
                                                    <div className="h-1.5 w-16 overflow-hidden rounded-full bg-secondary">
                                                        <div className="h-full rounded-full bg-brand-gradient" style={{ width: `${p.progress}%` }} />
                                                    </div>
                                                    <span className="text-xs text-muted-foreground">{p.progress}%</span>
                                                </div>
                                            </td>
                                            <td className="hidden px-4 py-3 font-medium text-foreground lg:table-cell">{p.budget != null && p.budget > 0 ? formatCurrency(p.budget) : '—'}</td>
                                            <td className="hidden px-4 py-3 text-muted-foreground md:table-cell">{p.due_date ? formatDate(p.due_date) : '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Card>
                )}

                <div className="mt-4"><Pagination from={projects.from} to={projects.to} total={projects.total} links={projects.links} /></div>
            </div>

            {formOpen && (
                <ProjectFormModal
                    key={editing?.id ?? 'new'}
                    open
                    onClose={() => setFormOpen(false)}
                    project={editing}
                    companies={companies}
                    owners={owners}
                    statuses={statuses}
                    proposals={linkableProposals}
                    canAdminister={can.manageAll}
                />
            )}

            {fromProposalOpen && <FromProposalModal proposals={awardableProposals} onClose={() => setFromProposalOpen(false)} />}
        </ProjectsLayout>
    );
}

function FromProposalModal({ proposals, onClose }: { proposals: Array<{ id: number; number: string; name: string }>; onClose: () => void }) {
    const form = useForm({ proposal_submission_id: '', from_proposal: true });
    const [query, setQuery] = useState('');

    const create = () => {
        if (!form.data.proposal_submission_id) return;
        form.post('/projects', { onSuccess: () => onClose() });
    };

    const q = query.trim().toLowerCase();
    const visible = q
        ? proposals.filter(p => p.name.toLowerCase().includes(q) || p.number.toLowerCase().includes(q))
        : proposals;

    return (
        <Modal open onClose={onClose} title="Create project from proposal"
            description="Pick an awarded proposal. The project is linked back to it, copies its details, and is owned by the proposal owner."
            footer={<>
                <Button variant="ghost" onClick={onClose}>Cancel</Button>
                <Button onClick={create} disabled={form.processing || !form.data.proposal_submission_id}>{form.processing ? 'Creating…' : 'Create Project'}</Button>
            </>}>
            {proposals.length > 0 && (
                <SearchInput className="mb-3" initial="" onSearch={setQuery} delay={120} placeholder="Search by name or proposal number…" />
            )}
            <div className="max-h-80 space-y-1.5 overflow-y-auto">
                {visible.map(p => (
                    <button
                        key={p.id}
                        onClick={() => form.setData('proposal_submission_id', String(p.id))}
                        className={cn('flex w-full items-center gap-3 rounded-lg border px-3 py-2.5 text-left transition-colors',
                            form.data.proposal_submission_id === String(p.id) ? 'border-primary bg-primary/10' : 'border-border hover:bg-secondary')}
                    >
                        <span className="min-w-0 flex-1">
                            <span className="block truncate text-sm font-medium text-foreground">{p.name}</span>
                            <span className="block font-mono text-xs text-muted-foreground">{p.number}</span>
                        </span>
                    </button>
                ))}
                {proposals.length === 0 && <p className="py-6 text-center text-sm text-muted-foreground">No awarded proposals without a project.</p>}
                {proposals.length > 0 && visible.length === 0 && <p className="py-6 text-center text-sm text-muted-foreground">No proposals match “{query}”.</p>}
            </div>
        </Modal>
    );
}
