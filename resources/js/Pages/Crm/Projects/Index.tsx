import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { CrmLayout } from '@/Components/layout/CrmLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Pagination } from '@/Components/ui/Pagination';
import { Select } from '@/Components/ui/Select';
import { SearchInput } from '@/Components/ui/SearchInput';
import { Pill } from '@/Components/ui/Pill';
import { ProjectFormModal, EditableProject } from '@/Components/crm/ProjectFormModal';
import { cn, formatCurrency, formatDate, getInitials, avatarGradient } from '@/Lib/utils';
import { PaginatedResponse } from '@/Types';
import { FolderKanban, Plus, Pencil, ListChecks, CalendarDays } from 'lucide-react';

interface ProjectRow extends EditableProject {
    id: number;
    name: string;
    status: string;
    status_label: string;
    status_color: string;
    progress: number;
    budget: number | null;
    due_date: string | null;
    company: string | null;
    owner: string | null;
    tasks_count: number;
    completed_tasks_count: number;
}

interface Props {
    projects: PaginatedResponse<ProjectRow>;
    filters: Record<string, string>;
    companies: Array<{ id: number; name: string }>;
    owners: Array<{ id: number; name: string }>;
    statuses: Array<{ value: string; label: string; color: string }>;
    can: { manage: boolean };
}

export default function ProjectsIndex({ projects, filters, companies, owners, statuses, can }: Props) {
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<ProjectRow | null>(null);

    const handleFilter = (key: string, value: string) => {
        router.get('/crm/projects', { ...filters, [key]: value || undefined }, { preserveState: true, preserveScroll: true, replace: true });
    };

    return (
        <CrmLayout>
            <Head title="Projects · CRM" />
            <div className="p-4 sm:p-6">
                <PageHeader
                    icon={FolderKanban}
                    title="Projects"
                    description={`${projects.total} ${projects.total === 1 ? 'project' : 'projects'}`}
                    actions={can.manage && <Button onClick={() => { setEditing(null); setFormOpen(true); }} icon={Plus}>New Project</Button>}
                />

                <Card className="mb-4 p-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <SearchInput className="min-w-0 flex-1 sm:max-w-sm" initial={filters.search ?? ''} onSearch={v => handleFilter('search', v)} placeholder="Search projects…" />
                        <Select value={filters.status ?? ''} onChange={v => handleFilter('status', v)} options={statuses.map(s => ({ value: s.value, label: s.label }))} placeholder="All statuses" className="w-full sm:w-48" />
                    </div>
                </Card>

                {projects.data.length === 0 ? (
                    <Card>
                        <EmptyState icon={FolderKanban} title="No projects found" description="Plan and track delivery work for your clients." action={can.manage && <Button onClick={() => { setEditing(null); setFormOpen(true); }} icon={Plus}>New Project</Button>} />
                    </Card>
                ) : (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {projects.data.map(p => (
                            <div key={p.id} className="card-surface card-hover group flex flex-col p-5">
                                <div className="flex items-start justify-between gap-2">
                                    <Link href={`/crm/projects/${p.id}`} className="min-w-0">
                                        <h3 className="truncate text-base font-semibold text-foreground group-hover:text-primary">{p.name}</h3>
                                        <p className="truncate text-xs text-muted-foreground">{p.company ?? 'Internal'}{p.code ? ` · ${p.code}` : ''}</p>
                                    </Link>
                                    <Pill color={p.status_color} label={p.status_label} />
                                </div>

                                <div className="mt-4">
                                    <div className="mb-1 flex items-center justify-between text-xs text-muted-foreground">
                                        <span>Progress</span><span>{p.progress}%</span>
                                    </div>
                                    <div className="h-2 overflow-hidden rounded-full bg-secondary">
                                        <div className="h-full rounded-full bg-brand-gradient transition-all" style={{ width: `${p.progress}%` }} />
                                    </div>
                                </div>

                                <div className="mt-4 flex items-center justify-between border-t border-border pt-3 text-xs text-muted-foreground">
                                    <span className="inline-flex items-center gap-1.5"><ListChecks className="h-3.5 w-3.5" /> {p.completed_tasks_count}/{p.tasks_count}</span>
                                    {p.due_date && <span className="inline-flex items-center gap-1.5"><CalendarDays className="h-3.5 w-3.5" /> {formatDate(p.due_date)}</span>}
                                    {p.budget != null && p.budget > 0 && <span className="font-medium text-foreground">{formatCurrency(p.budget)}</span>}
                                </div>

                                <div className="mt-3 flex items-center justify-between">
                                    {p.owner ? (
                                        <span className="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
                                            <span className={cn('flex h-5 w-5 items-center justify-center rounded-full bg-gradient-to-br text-[8px] font-bold text-white', avatarGradient(p.owner))}>{getInitials(p.owner)}</span>
                                            {p.owner}
                                        </span>
                                    ) : <span className="text-xs text-muted-foreground">Unassigned</span>}
                                    {can.manage && (
                                        <button onClick={() => { setEditing(p); setFormOpen(true); }} className="rounded-md p-1.5 text-muted-foreground opacity-0 transition-all hover:bg-secondary hover:text-foreground group-hover:opacity-100" title="Edit">
                                            <Pencil className="h-4 w-4" />
                                        </button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                <div className="mt-4"><Pagination from={projects.from} to={projects.to} total={projects.total} links={projects.links} /></div>
            </div>

            {formOpen && <ProjectFormModal key={editing?.id ?? 'new'} open onClose={() => setFormOpen(false)} project={editing} companies={companies} owners={owners} statuses={statuses} />}
        </CrmLayout>
    );
}
