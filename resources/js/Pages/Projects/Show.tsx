import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useRef, useState } from 'react';
import { ProjectsLayout } from '@/Components/layout/ProjectsLayout';
import { Button } from '@/Components/ui/Button';
import { Pill } from '@/Components/ui/Pill';
import { Select } from '@/Components/ui/Select';
import { Modal, ConfirmDialog } from '@/Components/ui/Modal';
import { ProjectFormModal } from '@/Components/crm/ProjectFormModal';
import { cn, formatCurrency, formatDate, getInitials, avatarGradient } from '@/Lib/utils';
import {
    ArrowLeft, Pencil, Trash2, Plus, CalendarDays, Building2, FileText, Target, Users, ListChecks,
    Flag, Paperclip, StickyNote, DollarSign, History, Download, Upload, MessageSquare, Sparkles, UserPlus, X,
    Truck, ShoppingCart, MapPin, Phone, Mail, Hash, ExternalLink, Link2, ClipboardList,
} from 'lucide-react';

interface Option { value: string; label: string; color?: string }
interface Person { id: number; name: string }

interface Project {
    id: number; name: string; code?: string | null; project_number?: string | null;
    description?: string | null; notes?: string | null;
    address?: string | null; poc_name?: string | null; poc_role?: string | null; poc_phone?: string | null; poc_email?: string | null;
    reference_numbers?: string | null; logistics?: string | null; specs?: string | null;
    status: string; status_label: string; status_color: string; progress: number;
    budget: number | null; start_date: string | null; due_date: string | null; completed_at: string | null;
    created_via: string;
    company: string | null; company_id: number | null; contact: string | null;
    owner: string | null; owner_id: number | null; manager: string | null; manager_id: number | null;
    proposal: { id: number; number: string; name: string; status: string } | null;
    opportunity: { id: number; title: string } | null;
}
interface Task {
    id: number; title: string; description?: string | null; status: string; status_label: string; status_color: string;
    priority: string; priority_label: string; due_date: string | null; assigned_to: number | null; assignee: string | null;
    can_update: boolean; comments: Array<{ id: number; body: string; author: string | null; created_at: string }>;
}
interface Member { id: number; user_id: number; name: string | null; role: string; responsibility: string | null; is_active: boolean; added_by: string | null; added_at: string | null }
interface Milestone { id: number; title: string; description: string | null; due_date: string | null; completed_at: string | null; status: string; status_label: string; status_color: string }
interface Note { id: number; body: string; author: string | null; author_id: number | null; created_at: string }
interface ProjectFile { id: number; name: string; size: number; mime_type: string | null; source: string; uploaded_by: string | null; created_at: string }
interface Activity { id: number; action: string; description: string; user: string | null; created_at: string }
interface Invoice { id: number; number: string; kind: string; status: string; total: number; amount_paid: number; balance: number }
interface Vendor { id: number; category: string; category_label: string; category_color: string; company_name: string; contact_name: string | null; phone: string | null; email: string | null; notes: string | null }
interface PurchaseOrder { id: number; number: string; supplier: string | null; status: string; status_label: string; status_color: string; total: number; currency: string; order_date: string | null; expected_date: string | null }
interface AttachablePO { id: number; number: string; supplier: string | null; total: number }

interface Props {
    project: Project;
    tasks: Task[];
    members: Member[];
    milestones: Milestone[];
    notes: Note[];
    files: ProjectFile[];
    activities: Activity[];
    invoices: Invoice[];
    vendors: Vendor[];
    vendorCategories: Option[];
    purchaseOrders: PurchaseOrder[];
    attachablePurchaseOrders: AttachablePO[];
    canProcurement: boolean;
    financials: { budget: number; invoiced: number; paid: number; outstanding: number; remaining_budget: number };
    companies: Person[];
    owners: Person[];
    statuses: Option[];
    taskStatuses: Option[];
    priorities: Option[];
    milestoneStatuses: Option[];
    memberRoles: string[];
    can: { manage: boolean; manageTeam: boolean; administer: boolean; delete: boolean };
}

function timeAgo(iso: string | null): string {
    if (!iso) return '';
    const diff = Date.now() - new Date(iso).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h ago`;
    const days = Math.floor(hrs / 24);
    return days < 7 ? `${days}d ago` : new Date(iso).toLocaleDateString();
}

function fileSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1048576).toFixed(1)} MB`;
}

const PRIORITY_DOT: Record<string, string> = { low: 'bg-gray-400', medium: 'bg-blue-500', high: 'bg-amber-500', critical: 'bg-red-500' };

const TABS = [
    { key: 'overview', label: 'Overview', icon: ListChecks },
    { key: 'tasks', label: 'Tasks', icon: ListChecks },
    { key: 'team', label: 'Team', icon: Users },
    { key: 'timeline', label: 'Milestones', icon: Flag },
    { key: 'logistics', label: 'Logistics', icon: Truck },
    { key: 'pos', label: 'Purchase Orders', icon: ShoppingCart },
    { key: 'files', label: 'Files', icon: Paperclip },
    { key: 'notes', label: 'Notes', icon: StickyNote },
    { key: 'linked', label: 'Linked', icon: FileText },
    { key: 'financial', label: 'Financials', icon: DollarSign },
    { key: 'activity', label: 'Activity', icon: History },
] as const;

export default function ProjectShow(props: Props) {
    const { project, can } = props;
    const [tab, setTab] = useState<typeof TABS[number]['key']>('overview');
    const [editOpen, setEditOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);

    const base = `/projects/${project.id}`;

    const tabCount = (key: typeof TABS[number]['key']): number | null => {
        switch (key) {
            case 'tasks': return props.tasks.length;
            case 'team': return props.members.length;
            case 'timeline': return props.milestones.length;
            case 'logistics': return props.vendors.length;
            case 'pos': return props.purchaseOrders.length;
            case 'files': return props.files.length;
            case 'notes': return props.notes.length;
            default: return null;
        }
    };

    return (
        <ProjectsLayout>
            <Head title={`${project.name} · Projects`} />
            <div className="mx-auto max-w-6xl px-4 py-6 sm:px-6">
                <Link href="/projects" className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" /> Projects
                </Link>

                <div className="card-surface mb-5 p-6">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2.5">
                                <h1 className="text-2xl font-bold tracking-tight text-foreground">{project.name}</h1>
                                <Pill color={project.status_color} label={project.status_label} />
                                {project.created_via === 'automatic' && (
                                    <span className="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-semibold text-primary"><Sparkles className="h-3 w-3" /> Auto from award</span>
                                )}
                            </div>
                            <div className="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground">
                                {project.project_number && <span className="font-mono text-xs">{project.project_number}</span>}
                                {project.company && <span className="inline-flex items-center gap-1.5"><Building2 className="h-4 w-4" /> {project.company}</span>}
                                {project.due_date && <span className="inline-flex items-center gap-1.5"><CalendarDays className="h-4 w-4" /> Due {formatDate(project.due_date)}</span>}
                                {project.budget != null && project.budget > 0 && <span className="font-medium text-foreground">{formatCurrency(project.budget)}</span>}
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            {can.manage && <Button variant="secondary" icon={Pencil} onClick={() => setEditOpen(true)}>Edit</Button>}
                            {can.delete && <Button variant="danger" icon={Trash2} onClick={() => setDeleting(true)}>Delete</Button>}
                        </div>
                    </div>

                    <div className="mt-5">
                        <div className="mb-1 flex items-center justify-between text-xs text-muted-foreground">
                            <span>Progress</span><span>{project.progress}%</span>
                        </div>
                        <div className="h-2.5 overflow-hidden rounded-full bg-secondary">
                            <div className="h-full rounded-full bg-brand-gradient transition-all" style={{ width: `${project.progress}%` }} />
                        </div>
                    </div>
                </div>

                <div className="mb-5 flex gap-1 overflow-x-auto border-b border-border">
                    {TABS.map(t => {
                        const Icon = t.icon;
                        const active = tab === t.key;
                        const count = tabCount(t.key);
                        return (
                            <button key={t.key} onClick={() => setTab(t.key)}
                                className={cn('flex shrink-0 items-center gap-1.5 border-b-2 px-3 py-2.5 text-sm font-medium transition-colors',
                                    active ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground')}>
                                <Icon className="h-4 w-4" /> {t.label}
                                {count !== null && count > 0 && <span className="rounded-full bg-secondary px-1.5 text-xs text-muted-foreground">{count}</span>}
                            </button>
                        );
                    })}
                </div>

                {tab === 'overview' && <OverviewTab {...props} />}
                {tab === 'tasks' && <TasksTab {...props} base={base} />}
                {tab === 'team' && <TeamTab {...props} base={base} />}
                {tab === 'timeline' && <TimelineTab {...props} base={base} />}
                {tab === 'logistics' && <LogisticsTab {...props} base={base} />}
                {tab === 'pos' && <PurchaseOrdersTab {...props} base={base} />}
                {tab === 'files' && <FilesTab {...props} base={base} />}
                {tab === 'notes' && <NotesTab {...props} base={base} />}
                {tab === 'linked' && <LinkedTab project={project} />}
                {tab === 'financial' && <FinancialTab {...props} />}
                {tab === 'activity' && <ActivityTab activities={props.activities} />}
            </div>

            {editOpen && <ProjectFormModal open onClose={() => setEditOpen(false)} project={project} companies={props.companies} owners={props.owners} statuses={props.statuses} canAdminister={can.administer} />}
            <ConfirmDialog open={deleting} onClose={() => setDeleting(false)} onConfirm={() => router.delete(base)} title="Delete project?" message={<>This removes <span className="font-medium text-foreground">{project.name}</span> and all its tasks, team, files & history.</>} />
        </ProjectsLayout>
    );
}

/* ── Overview ───────────────────────────────────────────────────────────── */
function OverviewTab({ project, members, activities }: Props) {
    const facts: Array<[string, React.ReactNode]> = [
        ['Owner', project.owner ?? '—'],
        ['Project manager', project.manager ?? '—'],
        ['Client', project.company ?? 'Internal'],
        ['Contact', project.contact ?? '—'],
        ['Start date', project.start_date ? formatDate(project.start_date) : '—'],
        ['Due date', project.due_date ? formatDate(project.due_date) : '—'],
        ['Budget', project.budget != null && project.budget > 0 ? formatCurrency(project.budget) : '—'],
        ['Team size', String(members.filter(m => m.is_active).length)],
    ];
    return (
        <div className="grid gap-5 lg:grid-cols-3">
            <div className="space-y-5 lg:col-span-2">
                <div className="card-surface p-5">
                    <h3 className="mb-3 text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Details</h3>
                    <dl className="grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-4">
                        {facts.map(([k, v]) => (
                            <div key={k}><dt className="text-xs text-muted-foreground">{k}</dt><dd className="mt-0.5 text-sm font-medium text-foreground">{v}</dd></div>
                        ))}
                    </dl>
                </div>
                {project.description && (
                    <div className="card-surface p-5">
                        <h3 className="mb-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Scope</h3>
                        <p className="whitespace-pre-line text-sm text-muted-foreground">{project.description}</p>
                    </div>
                )}
                {project.specs && (
                    <div className="card-surface p-5">
                        <h3 className="mb-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Specifications</h3>
                        <p className="whitespace-pre-line text-sm text-muted-foreground">{project.specs}</p>
                    </div>
                )}
                {project.notes && (
                    <div className="card-surface p-5">
                        <h3 className="mb-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Notes</h3>
                        <p className="whitespace-pre-line text-sm text-muted-foreground">{project.notes}</p>
                    </div>
                )}
            </div>
            <div className="card-surface p-5">
                <h3 className="mb-3 text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Recent activity</h3>
                <ActivityList activities={activities.slice(0, 8)} compact />
            </div>
        </div>
    );
}

/* ── Logistics: site / POC / reference numbers / vendors ─────────────────── */
function LogisticsTab({ project, vendors, vendorCategories, base, can }: Props & { base: string }) {
    const [modal, setModal] = useState<Vendor | null | 'new'>(null);
    const [deleting, setDeleting] = useState<Vendor | null>(null);

    const hasSite = project.address || project.poc_name || project.poc_phone || project.poc_email || project.reference_numbers;

    return (
        <div className="space-y-5">
            <div className="grid gap-5 lg:grid-cols-2">
                <div className="card-surface p-5">
                    <h3 className="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70"><MapPin className="h-4 w-4" /> Site & point of contact</h3>
                    {hasSite ? (
                        <dl className="space-y-3 text-sm">
                            {project.address && <div><dt className="text-xs text-muted-foreground">Address</dt><dd className="mt-0.5 whitespace-pre-line font-medium text-foreground">{project.address}</dd></div>}
                            {(project.poc_name || project.poc_role) && (
                                <div><dt className="text-xs text-muted-foreground">Point of contact</dt>
                                    <dd className="mt-0.5 font-medium text-foreground">{project.poc_name}{project.poc_role ? <span className="font-normal text-muted-foreground"> · {project.poc_role}</span> : ''}</dd>
                                </div>
                            )}
                            {project.poc_phone && <div className="flex items-center gap-2"><Phone className="h-3.5 w-3.5 text-muted-foreground" /><a href={`tel:${project.poc_phone}`} className="text-sm font-medium text-primary hover:underline">{project.poc_phone}</a></div>}
                            {project.poc_email && <div className="flex items-center gap-2"><Mail className="h-3.5 w-3.5 text-muted-foreground" /><a href={`mailto:${project.poc_email}`} className="text-sm font-medium text-primary hover:underline">{project.poc_email}</a></div>}
                        </dl>
                    ) : <p className="text-sm text-muted-foreground">No site / contact details yet. {can.manage && 'Use Edit to add them.'}</p>}
                </div>

                <div className="card-surface p-5">
                    <h3 className="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70"><Hash className="h-4 w-4" /> Reference numbers & logistics</h3>
                    {project.reference_numbers || project.logistics ? (
                        <div className="space-y-3 text-sm">
                            {project.reference_numbers && <div><dt className="text-xs text-muted-foreground">Contract / order reference numbers</dt><dd className="mt-0.5 whitespace-pre-line font-medium text-foreground">{project.reference_numbers}</dd></div>}
                            {project.logistics && <div><dt className="text-xs text-muted-foreground">Logistics notes</dt><dd className="mt-0.5 whitespace-pre-line text-muted-foreground">{project.logistics}</dd></div>}
                        </div>
                    ) : <p className="text-sm text-muted-foreground">No reference numbers or logistics notes yet. {can.manage && 'Use Edit to add them.'}</p>}
                </div>
            </div>

            <div>
                <div className="mb-3 flex items-center justify-between">
                    <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70"><Truck className="h-4 w-4" /> Vendor contacts ({vendors.length})</h2>
                    {can.manage && <Button size="sm" icon={Plus} onClick={() => setModal('new')}>Add Vendor</Button>}
                </div>
                {vendors.length === 0 ? (
                    <div className="card-surface p-8 text-center text-sm text-muted-foreground">No vendor contacts yet — add the forklift, trucking, crane and other field vendors for this project.</div>
                ) : (
                    <div className="grid gap-3 sm:grid-cols-2">
                        {vendors.map(v => (
                            <div key={v.id} className="card-surface p-4">
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <div className="flex items-center gap-2"><p className="truncate text-sm font-semibold text-foreground">{v.company_name}</p><Pill color={v.category_color} label={v.category_label} /></div>
                                        {v.contact_name && <p className="mt-0.5 text-xs text-muted-foreground">{v.contact_name}</p>}
                                    </div>
                                    {can.manage && (
                                        <div className="flex shrink-0 items-center gap-1">
                                            <button onClick={() => setModal(v)} className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground"><Pencil className="h-3.5 w-3.5" /></button>
                                            <button onClick={() => setDeleting(v)} className="rounded-md p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"><Trash2 className="h-3.5 w-3.5" /></button>
                                        </div>
                                    )}
                                </div>
                                <div className="mt-2 space-y-1">
                                    {v.phone && <a href={`tel:${v.phone}`} className="flex items-center gap-2 text-xs text-primary hover:underline"><Phone className="h-3 w-3" />{v.phone}</a>}
                                    {v.email && <a href={`mailto:${v.email}`} className="flex items-center gap-2 text-xs text-primary hover:underline"><Mail className="h-3 w-3" />{v.email}</a>}
                                </div>
                                {v.notes && <p className="mt-2 whitespace-pre-line border-t border-border pt-2 text-xs text-muted-foreground">{v.notes}</p>}
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {modal && <VendorModal base={base} vendor={modal === 'new' ? null : modal} categories={vendorCategories} onClose={() => setModal(null)} />}
            <ConfirmDialog open={!!deleting} onClose={() => setDeleting(null)} onConfirm={() => { if (deleting) router.delete(`${base}/vendors/${deleting.id}`, { preserveScroll: true, onFinish: () => setDeleting(null) }); }} title="Remove vendor?" message={deleting ? <>Remove <span className="font-medium text-foreground">{deleting.company_name}</span>?</> : ''} />
        </div>
    );
}

function VendorModal({ base, vendor, categories, onClose }: { base: string; vendor: Vendor | null; categories: Option[]; onClose: () => void }) {
    const isEdit = !!vendor;
    const form = useForm({
        category: vendor?.category ?? 'trucking',
        company_name: vendor?.company_name ?? '',
        contact_name: vendor?.contact_name ?? '',
        phone: vendor?.phone ?? '',
        email: vendor?.email ?? '',
        notes: vendor?.notes ?? '',
    });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => { form.reset(); onClose(); } };
        if (isEdit) form.put(`${base}/vendors/${vendor!.id}`, opts); else form.post(`${base}/vendors`, opts);
    };
    return (
        <Modal open onClose={onClose} title={isEdit ? 'Edit Vendor' : 'Add Vendor'}
            footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button onClick={submit as unknown as () => void} disabled={form.processing}>{form.processing ? 'Saving…' : isEdit ? 'Save' : 'Add Vendor'}</Button></>}>
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-2 gap-3">
                    <div><label className="label">Type</label><Select className="w-full" value={form.data.category} onChange={v => form.setData('category', v)} options={categories.map(c => ({ value: c.value, label: c.label }))} /></div>
                    <div><label className="label">Company *</label><input className="input" value={form.data.company_name} onChange={e => form.setData('company_name', e.target.value)} autoFocus />{form.errors.company_name && <p className="mt-1 text-xs text-destructive">{form.errors.company_name}</p>}</div>
                </div>
                <div><label className="label">Contact name</label><input className="input" value={form.data.contact_name} onChange={e => form.setData('contact_name', e.target.value)} placeholder="optional" /></div>
                <div className="grid grid-cols-2 gap-3">
                    <div><label className="label">Phone</label><input className="input" value={form.data.phone} onChange={e => form.setData('phone', e.target.value)} placeholder="optional" /></div>
                    <div><label className="label">Email</label><input className="input" value={form.data.email} onChange={e => form.setData('email', e.target.value)} placeholder="optional" />{form.errors.email && <p className="mt-1 text-xs text-destructive">{form.errors.email}</p>}</div>
                </div>
                <div><label className="label">Notes</label><textarea className="input min-h-[56px]" value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} placeholder="Rates, availability, gate hours…" /></div>
            </form>
        </Modal>
    );
}

/* ── Purchase orders (Procurement links) ─────────────────────────────────── */
function PurchaseOrdersTab({ purchaseOrders, attachablePurchaseOrders, canProcurement, base, can }: Props & { base: string }) {
    const [attaching, setAttaching] = useState(false);
    const [detaching, setDetaching] = useState<PurchaseOrder | null>(null);

    return (
        <div>
            <div className="mb-3 flex items-center justify-between">
                <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70"><ShoppingCart className="h-4 w-4" /> Purchase orders ({purchaseOrders.length})</h2>
                {can.manage && (
                    <div className="flex items-center gap-2">
                        {canProcurement && <a href="/procurement/purchase-orders/create"><Button size="sm" variant="secondary" icon={Plus}>New PO</Button></a>}
                        <Button size="sm" icon={Link2} onClick={() => setAttaching(true)} disabled={attachablePurchaseOrders.length === 0}>Link PO</Button>
                    </div>
                )}
            </div>
            {purchaseOrders.length === 0 ? (
                <div className="card-surface p-8 text-center text-sm text-muted-foreground">
                    No purchase orders linked yet. {can.manage && (attachablePurchaseOrders.length > 0 ? 'Link an existing PO from Procurement.' : 'Create a PO in Procurement, then link it here.')}
                </div>
            ) : (
                <div className="card-surface divide-y divide-border p-0">
                    {purchaseOrders.map(po => {
                        const inner = (
                            <>
                                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-secondary text-muted-foreground"><ClipboardList className="h-4 w-4" /></span>
                                <div className="min-w-0 flex-1">
                                    <p className="flex items-center gap-2 text-sm font-medium text-foreground"><span className="font-mono">{po.number}</span><Pill color={po.status_color} label={po.status_label} /></p>
                                    <p className="text-xs text-muted-foreground">{po.supplier ?? 'No supplier'}{po.order_date ? ` · ${formatDate(po.order_date)}` : ''}{po.expected_date ? ` · expected ${formatDate(po.expected_date)}` : ''}</p>
                                </div>
                                <span className="text-sm font-semibold text-foreground">{formatCurrency(po.total)}</span>
                            </>
                        );
                        return (
                            <div key={po.id} className="flex items-center gap-3 px-4 py-3">
                                {canProcurement ? (
                                    <a href={`/procurement/purchase-orders/${po.id}`} className="flex min-w-0 flex-1 items-center gap-3 hover:opacity-80">{inner}</a>
                                ) : <div className="flex min-w-0 flex-1 items-center gap-3">{inner}</div>}
                                {can.manage && <button onClick={() => setDetaching(po)} className="rounded-md p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive" title="Unlink"><X className="h-4 w-4" /></button>}
                            </div>
                        );
                    })}
                </div>
            )}

            {attaching && <AttachPoModal base={base} options={attachablePurchaseOrders} onClose={() => setAttaching(false)} />}
            <ConfirmDialog open={!!detaching} onClose={() => setDetaching(null)} onConfirm={() => { if (detaching) router.delete(`${base}/purchase-orders/${detaching.id}`, { preserveScroll: true, onFinish: () => setDetaching(null) }); }} title="Unlink purchase order?" message={detaching ? <>Unlink <span className="font-mono font-medium text-foreground">{detaching.number}</span> from this project? The PO itself is kept in Procurement.</> : ''} />
        </div>
    );
}

function AttachPoModal({ base, options, onClose }: { base: string; options: AttachablePO[]; onClose: () => void }) {
    const form = useForm({ purchase_order_id: '' });
    const submit = () => {
        if (!form.data.purchase_order_id) return;
        form.post(`${base}/purchase-orders`, { preserveScroll: true, onSuccess: () => onClose() });
    };
    return (
        <Modal open onClose={onClose} title="Link a purchase order"
            description="Attach an existing Procurement purchase order to this project."
            footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button onClick={submit} disabled={form.processing || !form.data.purchase_order_id}>Link PO</Button></>}>
            <div className="max-h-80 space-y-1.5 overflow-y-auto">
                {options.map(po => (
                    <button key={po.id} onClick={() => form.setData('purchase_order_id', String(po.id))}
                        className={cn('flex w-full items-center gap-3 rounded-lg border px-3 py-2.5 text-left transition-colors',
                            form.data.purchase_order_id === String(po.id) ? 'border-primary bg-primary/10' : 'border-border hover:bg-secondary')}>
                        <span className="min-w-0 flex-1">
                            <span className="block font-mono text-sm font-medium text-foreground">{po.number}</span>
                            <span className="block truncate text-xs text-muted-foreground">{po.supplier ?? 'No supplier'}</span>
                        </span>
                        <span className="text-sm font-medium text-foreground">{formatCurrency(po.total)}</span>
                    </button>
                ))}
                {options.length === 0 && <p className="py-6 text-center text-sm text-muted-foreground">No unlinked purchase orders available.</p>}
            </div>
        </Modal>
    );
}

/* ── Tasks ──────────────────────────────────────────────────────────────── */
function TasksTab({ tasks, taskStatuses, priorities, owners, base, can }: Props & { base: string }) {
    const [taskModal, setTaskModal] = useState<Task | null | 'new'>(null);
    const [deletingTask, setDeletingTask] = useState<Task | null>(null);
    const [openTask, setOpenTask] = useState<Task | null>(null);

    const moveTask = (task: Task, status: string) => {
        if (status === task.status) return;
        router.put(`${base}/tasks/${task.id}`, { title: task.title, status, priority: task.priority, assigned_to: task.assigned_to, due_date: task.due_date }, { preserveScroll: true });
    };

    return (
        <div>
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
                                            <span className={cn('mt-1 h-2 w-2 shrink-0 rounded-full', PRIORITY_DOT[task.priority] ?? PRIORITY_DOT.medium)} title={task.priority_label} />
                                            <button onClick={() => setOpenTask(task)} className="min-w-0 flex-1 text-left text-sm font-medium text-foreground hover:text-primary">{task.title}</button>
                                        </div>
                                        <div className="mt-2 flex items-center justify-between">
                                            <span className="inline-flex items-center gap-2 text-xs text-muted-foreground">
                                                {task.due_date ? formatDate(task.due_date) : ''}
                                                {task.comments.length > 0 && <span className="inline-flex items-center gap-0.5"><MessageSquare className="h-3 w-3" />{task.comments.length}</span>}
                                            </span>
                                            {task.assignee && <span className={cn('flex h-6 w-6 items-center justify-center rounded-full bg-gradient-to-br text-[9px] font-bold text-white', avatarGradient(task.assignee))} title={task.assignee}>{getInitials(task.assignee)}</span>}
                                        </div>
                                        {(can.manage || task.can_update) && (
                                            <div className="mt-2.5 flex items-center gap-1.5 border-t border-border pt-2.5">
                                                <Select size="sm" className="flex-1" value={task.status} onChange={v => moveTask(task, v)} options={taskStatuses.map(s => ({ value: s.value, label: s.label }))} />
                                                {can.manage && <button onClick={() => setTaskModal(task)} className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground" title="Edit"><Pencil className="h-3.5 w-3.5" /></button>}
                                                {can.manage && <button onClick={() => setDeletingTask(task)} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive" title="Delete"><Trash2 className="h-3.5 w-3.5" /></button>}
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

            {taskModal && <TaskModal base={base} task={taskModal === 'new' ? null : taskModal} owners={owners} taskStatuses={taskStatuses} priorities={priorities} onClose={() => setTaskModal(null)} />}
            {openTask && <TaskDetailModal base={base} task={tasks.find(t => t.id === openTask.id) ?? openTask} onClose={() => setOpenTask(null)} />}
            <ConfirmDialog open={!!deletingTask} onClose={() => setDeletingTask(null)} onConfirm={() => { if (deletingTask) router.delete(`${base}/tasks/${deletingTask.id}`, { preserveScroll: true, onFinish: () => setDeletingTask(null) }); }} title="Delete task?" message={deletingTask ? <>Delete <span className="font-medium text-foreground">{deletingTask.title}</span>?</> : ''} />
        </div>
    );
}

function TaskModal({ base, task, owners, taskStatuses, priorities, onClose }: { base: string; task: Task | null; owners: Person[]; taskStatuses: Option[]; priorities: Option[]; onClose: () => void }) {
    const isEdit = !!task;
    const form = useForm({
        title: task?.title ?? '', description: task?.description ?? '', status: task?.status ?? 'open',
        priority: task?.priority ?? 'medium', due_date: task?.due_date ?? '', assigned_to: task?.assigned_to ? String(task.assigned_to) : '',
    });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform(d => ({ ...d, assigned_to: d.assigned_to || null, due_date: d.due_date || null }));
        const opts = { preserveScroll: true, onSuccess: () => { form.reset(); onClose(); } };
        if (isEdit) form.put(`${base}/tasks/${task!.id}`, opts); else form.post(`${base}/tasks`, opts);
    };
    return (
        <Modal open onClose={onClose} title={isEdit ? 'Edit Task' : 'Add Task'}
            footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button onClick={submit as unknown as () => void} disabled={form.processing}>{form.processing ? 'Saving…' : isEdit ? 'Save' : 'Add Task'}</Button></>}>
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <label className="label">Task *</label>
                    <input className="input" value={form.data.title} onChange={e => form.setData('title', e.target.value)} autoFocus />
                    {form.errors.title && <p className="mt-1 text-xs text-destructive">{form.errors.title}</p>}
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div><label className="label">Status</label><Select className="w-full" value={form.data.status} onChange={v => form.setData('status', v)} options={taskStatuses.map(s => ({ value: s.value, label: s.label }))} /></div>
                    <div><label className="label">Priority</label><Select className="w-full" value={form.data.priority} onChange={v => form.setData('priority', v)} options={priorities.map(p => ({ value: p.value, label: p.label }))} /></div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div><label className="label">Assignee</label><Select className="w-full" value={form.data.assigned_to} onChange={v => form.setData('assigned_to', v)} placeholder="— Unassigned —" options={owners.map(o => ({ value: String(o.id), label: o.name }))} /></div>
                    <div><label className="label">Due date</label><input type="date" className="input" value={form.data.due_date} onChange={e => form.setData('due_date', e.target.value)} /></div>
                </div>
                <div><label className="label">Description</label><textarea className="input min-h-[72px]" value={form.data.description} onChange={e => form.setData('description', e.target.value)} /></div>
            </form>
        </Modal>
    );
}

function TaskDetailModal({ base, task, onClose }: { base: string; task: Task; onClose: () => void }) {
    const form = useForm({ body: '' });
    const addComment = () => {
        if (!form.data.body.trim()) return;
        form.post(`${base}/tasks/${task.id}/comments`, { preserveScroll: true, onSuccess: () => form.reset() });
    };
    return (
        <Modal open onClose={onClose} title={task.title}
            footer={<Button variant="ghost" onClick={onClose}>Close</Button>}>
            <div className="space-y-4">
                <div className="flex flex-wrap items-center gap-2 text-xs">
                    <Pill color={task.status_color} label={task.status_label} />
                    <span className="inline-flex items-center gap-1 text-muted-foreground"><span className={cn('h-2 w-2 rounded-full', PRIORITY_DOT[task.priority])} /> {task.priority_label}</span>
                    {task.due_date && <span className="text-muted-foreground">Due {formatDate(task.due_date)}</span>}
                    {task.assignee && <span className="text-muted-foreground">· {task.assignee}</span>}
                </div>
                {task.description && <p className="whitespace-pre-line text-sm text-muted-foreground">{task.description}</p>}
                <div className="border-t border-border pt-3">
                    <h4 className="mb-2 text-xs font-bold uppercase tracking-wider text-muted-foreground/70">Comments ({task.comments.length})</h4>
                    <div className="space-y-2.5">
                        {task.comments.map(c => (
                            <div key={c.id} className="flex gap-2.5">
                                <span className={cn('flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gradient-to-br text-[9px] font-bold text-white', avatarGradient(c.author))}>{getInitials(c.author)}</span>
                                <div className="min-w-0 flex-1 rounded-lg bg-secondary/40 px-3 py-2">
                                    <p className="text-xs"><span className="font-semibold text-foreground">{c.author ?? 'Unknown'}</span> <span className="text-muted-foreground">{timeAgo(c.created_at)}</span></p>
                                    <p className="mt-0.5 whitespace-pre-line text-sm text-foreground">{c.body}</p>
                                </div>
                            </div>
                        ))}
                        {task.comments.length === 0 && <p className="text-sm text-muted-foreground">No comments yet.</p>}
                    </div>
                    <div className="mt-3 flex gap-2">
                        <input className="input flex-1" placeholder="Write a comment…" value={form.data.body} onChange={e => form.setData('body', e.target.value)} onKeyDown={e => { if (e.key === 'Enter') { e.preventDefault(); addComment(); } }} />
                        <Button onClick={addComment} disabled={form.processing || !form.data.body.trim()}>Send</Button>
                    </div>
                </div>
            </div>
        </Modal>
    );
}

/* ── Team ───────────────────────────────────────────────────────────────── */
function TeamTab({ members, owners, memberRoles, base, can }: Props & { base: string }) {
    const [addOpen, setAddOpen] = useState(false);
    const [removing, setRemoving] = useState<Member | null>(null);

    return (
        <div>
            <div className="mb-3 flex items-center justify-between">
                <h2 className="text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Team ({members.length})</h2>
                {can.manageTeam && <Button size="sm" icon={UserPlus} onClick={() => setAddOpen(true)}>Add Member</Button>}
            </div>
            <div className="card-surface divide-y divide-border p-0">
                {members.map(m => (
                    <div key={m.id} className="flex items-center gap-3 px-4 py-3">
                        <span className={cn('flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br text-[11px] font-bold text-white', avatarGradient(m.name))}>{getInitials(m.name)}</span>
                        <div className="min-w-0 flex-1">
                            <p className="flex items-center gap-2 text-sm font-medium text-foreground">{m.name}{!m.is_active && <span className="rounded bg-secondary px-1.5 text-[10px] font-semibold uppercase text-muted-foreground">Inactive</span>}</p>
                            <p className="text-xs text-muted-foreground"><span className="capitalize">{m.role}</span>{m.responsibility ? ` · ${m.responsibility}` : ''}{m.added_at ? ` · since ${formatDate(m.added_at)}` : ''}</p>
                        </div>
                        {can.manageTeam && (
                            <div className="flex items-center gap-1.5">
                                <button onClick={() => router.put(`${base}/members/${m.id}`, { role: m.role, responsibility: m.responsibility, is_active: !m.is_active }, { preserveScroll: true })}
                                    className="rounded-md px-2 py-1 text-xs font-medium text-muted-foreground hover:bg-secondary">{m.is_active ? 'Deactivate' : 'Reactivate'}</button>
                                <button onClick={() => setRemoving(m)} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive" title="Remove"><Trash2 className="h-4 w-4" /></button>
                            </div>
                        )}
                    </div>
                ))}
                {members.length === 0 && <p className="px-4 py-8 text-center text-sm text-muted-foreground">No team members yet.</p>}
            </div>

            {addOpen && <AddMemberModal base={base} owners={owners.filter(o => !members.some(m => m.user_id === o.id))} memberRoles={memberRoles} onClose={() => setAddOpen(false)} />}
            <ConfirmDialog open={!!removing} onClose={() => setRemoving(null)} onConfirm={() => { if (removing) router.delete(`${base}/members/${removing.id}`, { preserveScroll: true, onFinish: () => setRemoving(null) }); }} title="Remove team member?" message={removing ? <>Remove <span className="font-medium text-foreground">{removing.name}</span> from this project?</> : ''} />
        </div>
    );
}

function AddMemberModal({ base, owners, memberRoles, onClose }: { base: string; owners: Person[]; memberRoles: string[]; onClose: () => void }) {
    const form = useForm({ user_id: '', role: 'member', responsibility: '' });
    const submit = () => {
        if (!form.data.user_id) return;
        form.post(`${base}/members`, { preserveScroll: true, onSuccess: () => onClose() });
    };
    return (
        <Modal open onClose={onClose} title="Add team member"
            footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button onClick={submit} disabled={form.processing || !form.data.user_id}>Add</Button></>}>
            <div className="space-y-4">
                <div><label className="label">User *</label><Select className="w-full" value={form.data.user_id} onChange={v => form.setData('user_id', v)} placeholder="Select a user…" options={owners.map(o => ({ value: String(o.id), label: o.name }))} /></div>
                <div className="grid grid-cols-2 gap-3">
                    <div><label className="label">Role</label><Select className="w-full" value={form.data.role} onChange={v => form.setData('role', v)} options={memberRoles.map(r => ({ value: r, label: r.charAt(0).toUpperCase() + r.slice(1) }))} /></div>
                    <div><label className="label">Responsibility</label><input className="input" value={form.data.responsibility} onChange={e => form.setData('responsibility', e.target.value)} placeholder="optional" /></div>
                </div>
            </div>
        </Modal>
    );
}

/* ── Timeline / Milestones ──────────────────────────────────────────────── */
function TimelineTab({ milestones, milestoneStatuses, base, can }: Props & { base: string }) {
    const [modal, setModal] = useState<Milestone | null | 'new'>(null);
    const [deleting, setDeleting] = useState<Milestone | null>(null);
    return (
        <div>
            <div className="mb-3 flex items-center justify-between">
                <h2 className="text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Milestones ({milestones.length})</h2>
                {can.manage && <Button size="sm" icon={Plus} onClick={() => setModal('new')}>Add Milestone</Button>}
            </div>
            <div className="relative space-y-3 pl-4">
                <div className="absolute left-[7px] top-1 h-[calc(100%-0.5rem)] w-px bg-border" />
                {milestones.map(m => (
                    <div key={m.id} className="relative">
                        <span className={cn('absolute -left-4 top-1.5 h-3.5 w-3.5 rounded-full border-2 border-card', m.status === 'completed' ? 'bg-emerald-500' : m.status === 'in_progress' ? 'bg-indigo-500' : 'bg-gray-300 dark:bg-gray-600')} />
                        <div className="card-surface flex items-start justify-between gap-3 p-4">
                            <div className="min-w-0">
                                <div className="flex items-center gap-2"><p className="text-sm font-semibold text-foreground">{m.title}</p><Pill color={m.status_color} label={m.status_label} /></div>
                                {m.description && <p className="mt-1 whitespace-pre-line text-sm text-muted-foreground">{m.description}</p>}
                                <p className="mt-1 text-xs text-muted-foreground">{m.due_date ? `Due ${formatDate(m.due_date)}` : 'No due date'}{m.completed_at ? ` · Completed ${formatDate(m.completed_at)}` : ''}</p>
                            </div>
                            {can.manage && (
                                <div className="flex shrink-0 items-center gap-1">
                                    <button onClick={() => setModal(m)} className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground"><Pencil className="h-3.5 w-3.5" /></button>
                                    <button onClick={() => setDeleting(m)} className="rounded-md p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"><Trash2 className="h-3.5 w-3.5" /></button>
                                </div>
                            )}
                        </div>
                    </div>
                ))}
                {milestones.length === 0 && <p className="py-8 text-center text-sm text-muted-foreground">No milestones yet.</p>}
            </div>

            {modal && <MilestoneModal base={base} milestone={modal === 'new' ? null : modal} statuses={milestoneStatuses} onClose={() => setModal(null)} />}
            <ConfirmDialog open={!!deleting} onClose={() => setDeleting(null)} onConfirm={() => { if (deleting) router.delete(`${base}/milestones/${deleting.id}`, { preserveScroll: true, onFinish: () => setDeleting(null) }); }} title="Delete milestone?" message={deleting ? <>Delete <span className="font-medium text-foreground">{deleting.title}</span>?</> : ''} />
        </div>
    );
}

function MilestoneModal({ base, milestone, statuses, onClose }: { base: string; milestone: Milestone | null; statuses: Option[]; onClose: () => void }) {
    const isEdit = !!milestone;
    const form = useForm({ title: milestone?.title ?? '', description: milestone?.description ?? '', due_date: milestone?.due_date ?? '', status: milestone?.status ?? 'pending' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform(d => ({ ...d, due_date: d.due_date || null }));
        const opts = { preserveScroll: true, onSuccess: () => { form.reset(); onClose(); } };
        if (isEdit) form.put(`${base}/milestones/${milestone!.id}`, opts); else form.post(`${base}/milestones`, opts);
    };
    return (
        <Modal open onClose={onClose} title={isEdit ? 'Edit Milestone' : 'Add Milestone'}
            footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button onClick={submit as unknown as () => void} disabled={form.processing}>{form.processing ? 'Saving…' : isEdit ? 'Save' : 'Add'}</Button></>}>
            <form onSubmit={submit} className="space-y-4">
                <div><label className="label">Title *</label><input className="input" value={form.data.title} onChange={e => form.setData('title', e.target.value)} autoFocus />{form.errors.title && <p className="mt-1 text-xs text-destructive">{form.errors.title}</p>}</div>
                <div className="grid grid-cols-2 gap-3">
                    <div><label className="label">Due date</label><input type="date" className="input" value={form.data.due_date} onChange={e => form.setData('due_date', e.target.value)} /></div>
                    <div><label className="label">Status</label><Select className="w-full" value={form.data.status} onChange={v => form.setData('status', v)} options={statuses.map(s => ({ value: s.value, label: s.label }))} /></div>
                </div>
                <div><label className="label">Description</label><textarea className="input min-h-[64px]" value={form.data.description} onChange={e => form.setData('description', e.target.value)} /></div>
            </form>
        </Modal>
    );
}

/* ── Files ──────────────────────────────────────────────────────────────── */
function FilesTab({ files, base, can }: Props & { base: string }) {
    const fileInput = useRef<HTMLInputElement>(null);
    const form = useForm<{ file: File | null }>({ file: null });
    const [deleting, setDeleting] = useState<ProjectFile | null>(null);

    const upload = (f: File) => {
        form.transform(() => ({ file: f }));
        form.post(`${base}/files`, { preserveScroll: true, forceFormData: true, onSuccess: () => { form.reset(); if (fileInput.current) fileInput.current.value = ''; } });
    };

    return (
        <div>
            <div className="mb-3 flex items-center justify-between">
                <h2 className="text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Files ({files.length})</h2>
                {can.manage && (
                    <>
                        <input ref={fileInput} type="file" className="hidden" onChange={e => { const f = e.target.files?.[0]; if (f) upload(f); }} />
                        <Button size="sm" icon={Upload} onClick={() => fileInput.current?.click()} disabled={form.processing}>{form.processing ? 'Uploading…' : 'Upload'}</Button>
                    </>
                )}
            </div>
            {form.errors.file && <p className="mb-2 text-xs text-destructive">{form.errors.file}</p>}
            <div className="card-surface divide-y divide-border p-0">
                {files.map(f => (
                    <div key={f.id} className="flex items-center gap-3 px-4 py-3">
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-secondary text-muted-foreground"><FileText className="h-4 w-4" /></span>
                        <div className="min-w-0 flex-1">
                            <p className="flex items-center gap-2 truncate text-sm font-medium text-foreground">{f.name}{f.source === 'proposal' && <span className="inline-flex items-center gap-0.5 rounded bg-primary/10 px-1.5 text-[10px] font-semibold text-primary"><Sparkles className="h-2.5 w-2.5" />From proposal</span>}</p>
                            <p className="text-xs text-muted-foreground">{fileSize(f.size)}{f.uploaded_by ? ` · ${f.uploaded_by}` : ''} · {timeAgo(f.created_at)}</p>
                        </div>
                        <a href={`${base}/files/${f.id}/download`} className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground" title="Download"><Download className="h-4 w-4" /></a>
                        {can.manage && <button onClick={() => setDeleting(f)} className="rounded-md p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive" title="Delete"><Trash2 className="h-4 w-4" /></button>}
                    </div>
                ))}
                {files.length === 0 && <p className="px-4 py-8 text-center text-sm text-muted-foreground">No files attached.</p>}
            </div>
            <ConfirmDialog open={!!deleting} onClose={() => setDeleting(null)} onConfirm={() => { if (deleting) router.delete(`${base}/files/${deleting.id}`, { preserveScroll: true, onFinish: () => setDeleting(null) }); }} title="Delete file?" message={deleting ? <>Delete <span className="font-medium text-foreground">{deleting.name}</span>?</> : ''} />
        </div>
    );
}

/* ── Notes ──────────────────────────────────────────────────────────────── */
function NotesTab({ notes, base }: Props & { base: string }) {
    const form = useForm({ body: '' });
    const submit = () => { if (!form.data.body.trim()) return; form.post(`${base}/notes`, { preserveScroll: true, onSuccess: () => form.reset() }); };
    return (
        <div>
            <div className="card-surface mb-4 p-4">
                <textarea className="input min-h-[64px]" placeholder="Add a note or status update…" value={form.data.body} onChange={e => form.setData('body', e.target.value)} />
                <div className="mt-2 flex justify-end"><Button size="sm" onClick={submit} disabled={form.processing || !form.data.body.trim()}>Post Note</Button></div>
            </div>
            <div className="space-y-3">
                {notes.map(n => (
                    <div key={n.id} className="card-surface flex items-start gap-3 p-4">
                        <span className={cn('flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br text-[10px] font-bold text-white', avatarGradient(n.author))}>{getInitials(n.author)}</span>
                        <div className="min-w-0 flex-1">
                            <p className="text-xs"><span className="font-semibold text-foreground">{n.author ?? 'Unknown'}</span> <span className="text-muted-foreground">{timeAgo(n.created_at)}</span></p>
                            <p className="mt-1 whitespace-pre-line text-sm text-foreground">{n.body}</p>
                        </div>
                        <button onClick={() => router.delete(`${base}/notes/${n.id}`, { preserveScroll: true })} className="rounded-md p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive" title="Delete"><X className="h-4 w-4" /></button>
                    </div>
                ))}
                {notes.length === 0 && <p className="py-8 text-center text-sm text-muted-foreground">No notes yet.</p>}
            </div>
        </div>
    );
}

/* ── Linked Proposal / Quote ────────────────────────────────────────────── */
function LinkedTab({ project }: { project: Project }) {
    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <div className="card-surface p-5">
                <h3 className="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70"><FileText className="h-4 w-4" /> Proposal</h3>
                {project.proposal ? (
                    <Link href={`/proposals/${project.proposal.id}`} className="block rounded-lg border border-border p-3 transition-colors hover:bg-secondary">
                        <p className="flex items-center gap-1.5 font-mono text-xs text-muted-foreground">{project.proposal.number}<ExternalLink className="h-3 w-3" /></p>
                        <p className="mt-0.5 text-sm font-medium text-foreground">{project.proposal.name}</p>
                        <p className="mt-1 text-xs capitalize text-muted-foreground">{String(project.proposal.status).replace('_', ' ')}</p>
                    </Link>
                ) : <p className="text-sm text-muted-foreground">Not linked to a proposal.</p>}
            </div>
            <div className="card-surface p-5">
                <h3 className="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70"><Target className="h-4 w-4" /> Opportunity</h3>
                {project.opportunity ? (
                    <Link href={`/opportunities/${project.opportunity.id}`} className="block rounded-lg border border-border p-3 transition-colors hover:bg-secondary">
                        <p className="flex items-center gap-1.5 text-sm font-medium text-foreground">{project.opportunity.title}<ExternalLink className="h-3 w-3 text-muted-foreground" /></p>
                    </Link>
                ) : <p className="text-sm text-muted-foreground">Not linked to an opportunity.</p>}
            </div>
            <div className="card-surface p-5 sm:col-span-2">
                <h3 className="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70"><Building2 className="h-4 w-4" /> Customer</h3>
                {project.company_id ? (
                    <Link href={`/crm/clients/${project.company_id}`} className="inline-flex items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm font-medium text-foreground transition-colors hover:bg-secondary">
                        <Building2 className="h-4 w-4 text-muted-foreground" /> {project.company}<ExternalLink className="h-3 w-3 text-muted-foreground" />
                    </Link>
                ) : <p className="text-sm text-muted-foreground">Internal project — no customer record.</p>}
            </div>
        </div>
    );
}

/* ── Financial Summary ──────────────────────────────────────────────────── */
function FinancialTab({ financials, invoices }: Props) {
    const cards: Array<[string, number, string]> = [
        ['Awarded budget', financials.budget, 'text-foreground'],
        ['Invoiced', financials.invoiced, 'text-blue-600 dark:text-blue-400'],
        ['Collected', financials.paid, 'text-emerald-600 dark:text-emerald-400'],
        ['Outstanding', financials.outstanding, 'text-amber-600 dark:text-amber-400'],
    ];
    return (
        <div className="space-y-5">
            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                {cards.map(([label, value, tone]) => (
                    <div key={label} className="card-surface p-4">
                        <p className="text-xs text-muted-foreground">{label}</p>
                        <p className={cn('mt-1 text-xl font-bold', tone)}>{formatCurrency(value)}</p>
                    </div>
                ))}
            </div>
            <div className="card-surface p-0">
                <div className="flex items-center justify-between border-b border-border px-4 py-3">
                    <h3 className="text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Invoices & estimates</h3>
                    <span className="text-xs text-muted-foreground">Remaining budget: <span className="font-medium text-foreground">{formatCurrency(financials.remaining_budget)}</span></span>
                </div>
                <div className="divide-y divide-border">
                    {invoices.map(i => (
                        <Link key={i.id} href={`/crm/invoices/${i.id}`} className="flex items-center gap-3 px-4 py-3 transition-colors hover:bg-secondary">
                            <span className="font-mono text-xs text-muted-foreground">{i.number}</span>
                            <span className="rounded bg-secondary px-1.5 text-[10px] font-semibold uppercase text-muted-foreground">{i.kind}</span>
                            <span className="ml-auto text-sm font-medium text-foreground">{formatCurrency(i.total)}</span>
                            <span className="text-xs text-muted-foreground">{i.balance > 0 ? `${formatCurrency(i.balance)} due` : 'Paid'}</span>
                        </Link>
                    ))}
                    {invoices.length === 0 && <p className="px-4 py-8 text-center text-sm text-muted-foreground">No invoices linked to this project yet.</p>}
                </div>
            </div>
        </div>
    );
}

/* ── Activity ───────────────────────────────────────────────────────────── */
function ActivityTab({ activities }: { activities: Activity[] }) {
    return (
        <div className="card-surface p-5">
            <h3 className="mb-4 text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Activity log</h3>
            <ActivityList activities={activities} />
        </div>
    );
}

function ActivityList({ activities, compact = false }: { activities: Activity[]; compact?: boolean }) {
    if (activities.length === 0) return <p className="text-sm text-muted-foreground">No activity yet.</p>;
    return (
        <ol className="space-y-3">
            {activities.map(a => (
                <li key={a.id} className="flex gap-3">
                    <span className="mt-1 h-2 w-2 shrink-0 rounded-full bg-primary/60" />
                    <div className="min-w-0">
                        <p className={cn('text-foreground', compact ? 'text-xs' : 'text-sm')}>{a.description}</p>
                        <p className="text-xs text-muted-foreground">{a.user ?? 'System'} · {timeAgo(a.created_at)}</p>
                    </div>
                </li>
            ))}
        </ol>
    );
}
