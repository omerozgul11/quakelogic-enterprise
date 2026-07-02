import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useRef, useState } from 'react';
import { ProjectsLayout } from '@/Components/layout/ProjectsLayout';
import { Button } from '@/Components/ui/Button';
import { Pill } from '@/Components/ui/Pill';
import { Select } from '@/Components/ui/Select';
import { NumberInput } from '@/Components/ui/NumberInput';
import { Modal, ConfirmDialog } from '@/Components/ui/Modal';
import { ProjectFormModal } from '@/Components/crm/ProjectFormModal';
import { cn, formatCurrency, formatDate, getInitials, avatarGradient } from '@/Lib/utils';
import {
    ArrowLeft, Pencil, Trash2, Plus, CalendarDays, Building2, FileText, Target, Users, ListChecks,
    Flag, Paperclip, StickyNote, DollarSign, History, Download, Upload, MessageSquare, Sparkles, UserPlus, X,
    Truck, ShoppingCart, MapPin, Phone, Mail, Hash, ExternalLink, Link2, ClipboardList,
    MapPinned, ShieldAlert, Star, AlertTriangle, Navigation, Zap, Wifi, Droplets, Wind, Forklift, Construction,
    Clock, BadgeCheck, Smartphone, Package, Boxes, Weight, CalendarClock, ShieldCheck,
    Wrench, CheckSquare, Square, Plane, Hotel, Car, TrainFront, Wallet, SquareParking, Receipt,
    Folder, FolderOpen, FolderPlus, FileUp, HardHat, Hospital, QrCode, RotateCcw, ChevronDown, ChevronRight, Signature,
    LayoutDashboard,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

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
    ai_briefing?: string | null; ai_briefing_at?: string | null; ai_briefing_by?: string | null;
}
interface Task {
    id: number; title: string; description?: string | null; status: string; status_label: string; status_color: string;
    priority: string; priority_label: string; due_date: string | null; assigned_to: number | null; assignee: string | null;
    can_update: boolean; comments: Array<{ id: number; body: string; author: string | null; created_at: string }>;
}
interface Member { id: number; user_id: number; name: string | null; role: string; responsibility: string | null; is_active: boolean; added_by: string | null; added_at: string | null }
interface Milestone { id: number; title: string; description: string | null; due_date: string | null; completed_at: string | null; status: string; status_label: string; status_color: string }
interface Note { id: number; body: string; author: string | null; author_id: number | null; created_at: string }
interface FileVersion { id: number; version: number; size: number; uploaded_by: string | null; created_at: string; is_current: boolean }
interface ProjectFile { id: number; name: string; size: number; mime_type: string | null; source: string; uploaded_by: string | null; created_at: string; folder_id: number | null; version: number; versions: FileVersion[] }
interface Folder { id: number; name: string }
interface Activity { id: number; action: string; description: string; user: string | null; created_at: string }
interface Invoice { id: number; number: string; kind: string; status: string; total: number; amount_paid: number; balance: number }
interface Expense { id: number; number: string; vendor: string | null; description: string | null; category: string | null; amount: number; currency: string; status: string; status_label: string | null; status_color: string | null; expense_date: string | null }
interface Vendor { id: number; category: string; category_label: string; category_color: string; company_name: string; contact_name: string | null; phone: string | null; email: string | null; notes: string | null }
interface PurchaseOrder { id: number; number: string; supplier: string | null; status: string; status_label: string; status_color: string; total: number; currency: string; order_date: string | null; expected_date: string | null }
interface AttachablePO { id: number; number: string; supplier: string | null; total: number }
interface ProjectSite {
    id: number; name: string; is_primary: boolean;
    address: string | null; latitude: number | null; longitude: number | null; maps_url: string | null;
    access_instructions: string | null; loading_dock: string | null; parking: string | null; working_hours: string | null; gate_hours: string | null;
    security_requirements: string | null; badge_required: boolean | null; escort_required: boolean | null; ppe_required: string | null;
    forklift_available: boolean | null; crane_available: boolean | null; internet_available: boolean | null; power_available: boolean | null;
    water_available: boolean | null; compressed_air_available: boolean | null; utilities_notes: string | null; environmental_conditions: string | null;
    hazards: string | null; lockout_tagout: string | null; high_voltage: boolean | null; confined_space: boolean | null; fall_protection: boolean | null;
    chemical_hazards: string | null; emergency_assembly_point: string | null; nearest_hospital: string | null; hospital_phone: string | null;
    police_phone: string | null; fire_phone: string | null; site_safety_contact: string | null; notes: string | null;
}
interface SiteContact {
    id: number; category: string; category_label: string; category_color: string;
    name: string; title: string | null; company: string | null; phone: string | null; mobile: string | null; email: string | null;
    preferred_contact_method: string | null; availability: string | null; is_emergency: boolean; crm_project_site_id: number | null; notes: string | null;
}
interface Equipment {
    id: number; name: string; product: string | null; model: string | null; revision: string | null;
    serial_number: string | null; firmware: string | null; software_version: string | null; asset_tag: string | null; quantity: number;
    power: string | null; voltage: string | null; weight: string | null; dimensions: string | null;
    center_of_gravity: string | null; lift_points: string | null; rigging_instructions: string | null; installation_location: string | null;
    calibration_status: string | null; calibration_due: string | null; warranty_status: string | null; warranty_expires: string | null;
    crm_project_shipment_id: number | null; asset_id: number | null; asset: string | null; notes: string | null;
}
interface Shipment {
    id: number; direction: string; carrier: string | null; carrier_label: string | null; carrier_color: string | null;
    service: string | null; tracking_number: string | null; tracking_url: string | null;
    status: string | null; status_label: string | null; status_color: string | null;
    shipped_date: string | null; expected_arrival: string | null; arrived_date: string | null;
    crate_number: string | null; package_count: number | null; pallet_info: string | null;
    weight: string | null; gross_weight: string | null; net_weight: string | null; shipping_weight: string | null; dimensions: string | null;
    bill_of_lading: string | null; packing_list: string | null; forklift_instructions: string | null; lift_points: string | null;
    shock_indicator: string | null; tilt_indicator: string | null; notes: string | null;
}
interface ExecutionRecord {
    id: number; type: string; type_label: string; type_color: string;
    title: string; status: string; status_label: string; status_color: string;
    scheduled_date: string | null; completed_date: string | null;
    performed_by: number | null; performer: string | null; crm_project_site_id: number | null;
    summary: string | null; outcome: string | null; customer_visible: boolean; notes: string | null;
}
interface ChecklistItem { id: number; text: string; is_done: boolean; position: number; done_by: string | null; done_at: string | null }
interface Checklist { id: number; title: string; description: string | null; sort_order: number; items: ChecklistItem[]; done_count: number; total_count: number }
interface Travel {
    id: number; type: string; type_label: string; type_color: string; title: string; status: string | null;
    traveler: string | null; traveler_id: number | null; traveler_name: string | null;
    provider: string | null; confirmation_number: string | null;
    start_at: string | null; end_at: string | null; from_location: string | null; to_location: string | null;
    cost: number | null; currency: string | null; booking_url: string | null; notes: string | null;
}
interface Signoff {
    id: number; type: string; type_label: string; type_color: string;
    signer_name: string; signer_title: string | null; signer_email: string | null;
    statement: string | null; signature_data: string | null; signed_at: string | null;
    captured_by: string | null; execution_record: string | null; crm_project_execution_record_id: number | null; notes: string | null;
}
type SignoffType = { value: string; label: string; color: string; statement: string };

interface Props {
    project: Project;
    tasks: Task[];
    members: Member[];
    milestones: Milestone[];
    notes: Note[];
    files: ProjectFile[];
    folders: Folder[];
    activities: Activity[];
    invoices: Invoice[];
    expenses: Expense[];
    expenseCategories: { value: number; label: string }[];
    vendors: Vendor[];
    vendorCategories: Option[];
    sites: ProjectSite[];
    siteContacts: SiteContact[];
    contactCategories: Option[];
    equipment: Equipment[];
    shipments: Shipment[];
    shipmentStatuses: Option[];
    carriers: Option[];
    linkableAssets: Array<{ value: string; label: string }>;
    executionRecords: ExecutionRecord[];
    executionTypes: Option[];
    executionStatuses: Option[];
    checklists: Checklist[];
    travel: Travel[];
    travelTypes: Option[];
    signoffs: Signoff[];
    signoffTypes: SignoffType[];
    purchaseOrders: PurchaseOrder[];
    attachablePurchaseOrders: AttachablePO[];
    canProcurement: boolean;
    financials: { budget: number; invoiced: number; paid: number; outstanding: number; remaining_budget: number; spent: number; margin: number };
    companies: Person[];
    owners: Person[];
    statuses: Option[];
    taskStatuses: Option[];
    priorities: Option[];
    milestoneStatuses: Option[];
    memberRoles: string[];
    can: { manage: boolean; manageTeam: boolean; administer: boolean; delete: boolean; addExpense: boolean };
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
    { key: 'dashboard', label: 'Dashboard', icon: LayoutDashboard },
    { key: 'overview', label: 'Overview', icon: ListChecks },
    { key: 'site', label: 'Site & Safety', icon: MapPinned },
    { key: 'equipment', label: 'Equipment & Shipments', icon: Package },
    { key: 'execution', label: 'Execution', icon: Wrench },
    { key: 'signoff', label: 'Sign-off', icon: Signature },
    { key: 'travel', label: 'Travel', icon: Plane },
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

type TabKey = typeof TABS[number]['key'];

export default function ProjectShow(props: Props) {
    const { project, can } = props;
    const [tab, setTab] = useState<TabKey>('dashboard');
    const [editOpen, setEditOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);

    const base = `/projects/${project.id}`;

    const tabCount = (key: typeof TABS[number]['key']): number | null => {
        switch (key) {
            case 'site': return props.sites.length + props.siteContacts.length;
            case 'equipment': return props.equipment.length + props.shipments.length;
            case 'execution': return props.executionRecords.length + props.checklists.length;
            case 'signoff': return props.signoffs.length;
            case 'travel': return props.travel.length;
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
                            <a href={`${base}/field-packet`}><Button variant="secondary" icon={Download}>Field Packet</Button></a>
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

                <div className="flex flex-col gap-5 lg:flex-row lg:items-start">
                    {/* Project sub-navigation — its own left menu, separate from the app's main menu. */}
                    <nav className="lg:w-56 lg:shrink-0">
                        <div className="lg:hidden">
                            <Select className="w-full" value={tab} onChange={v => setTab(v as typeof TABS[number]['key'])}
                                options={TABS.map(t => { const c = tabCount(t.key); return { value: t.key, label: c ? `${t.label} (${c})` : t.label }; })} />
                        </div>
                        <div className="sticky top-4 hidden lg:flex lg:flex-col lg:gap-0.5 lg:rounded-xl lg:border lg:border-border lg:bg-card lg:p-2">
                            {TABS.map(t => {
                                const Icon = t.icon;
                                const active = tab === t.key;
                                const count = tabCount(t.key);
                                return (
                                    <button key={t.key} onClick={() => setTab(t.key)}
                                        className={cn('flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                                            active ? 'bg-secondary text-foreground' : 'text-muted-foreground hover:bg-secondary/60 hover:text-foreground')}>
                                        <Icon className={cn('h-4 w-4 shrink-0', active && 'text-primary')} />
                                        <span className="truncate">{t.label}</span>
                                        {count !== null && count > 0 && <span className="ml-auto rounded-full bg-background px-1.5 text-xs text-muted-foreground">{count}</span>}
                                    </button>
                                );
                            })}
                        </div>
                    </nav>

                    <div className="min-w-0 flex-1">
                        {tab === 'dashboard' && <DashboardTab {...props} base={base} onNavigate={setTab} />}
                        {tab === 'overview' && <OverviewTab {...props} base={base} />}
                        {tab === 'site' && <SiteSafetyTab {...props} base={base} />}
                        {tab === 'equipment' && <EquipmentShipmentsTab {...props} base={base} />}
                        {tab === 'execution' && <ExecutionTab {...props} base={base} />}
                        {tab === 'signoff' && <SignoffTab {...props} base={base} />}
                        {tab === 'travel' && <TravelTab {...props} base={base} />}
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
                </div>
            </div>

            {editOpen && <ProjectFormModal open onClose={() => setEditOpen(false)} project={project} companies={props.companies} owners={props.owners} statuses={props.statuses} canAdminister={can.administer} />}
            <ConfirmDialog open={deleting} onClose={() => setDeleting(false)} onConfirm={() => router.delete(base)} title="Delete project?" message={<>This removes <span className="font-medium text-foreground">{project.name}</span> and all its tasks, team, files & history.</>} />
        </ProjectsLayout>
    );
}

/* ── Dashboard (at-a-glance landing) ────────────────────────────────────── */
function StatTile({ icon: Icon, label, value, sub, tone, onClick }: { icon: LucideIcon; label: string; value: React.ReactNode; sub?: React.ReactNode; tone?: string; onClick?: () => void }) {
    const body = (
        <>
            <div className="flex items-center gap-2 text-muted-foreground">
                <Icon className="h-4 w-4" />
                <span className="text-xs font-medium">{label}</span>
                {onClick && <ChevronRight className="ml-auto h-4 w-4 opacity-0 transition-opacity group-hover:opacity-100" />}
            </div>
            <p className={cn('mt-2 text-2xl font-bold', tone ?? 'text-foreground')}>{value}</p>
            {sub && <p className="mt-0.5 text-xs text-muted-foreground">{sub}</p>}
        </>
    );
    if (onClick) {
        return (
            <button type="button" onClick={onClick} className="card-surface group p-4 text-left transition-colors hover:border-primary/40 hover:bg-secondary/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/50">
                {body}
            </button>
        );
    }
    return <div className="card-surface p-4">{body}</div>;
}

function SectionCard({ icon: Icon, title, action, children }: { icon: LucideIcon; title: string; action?: React.ReactNode; children: React.ReactNode }) {
    return (
        <div className="card-surface p-5">
            <div className="mb-3 flex items-center justify-between gap-2">
                <h3 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70"><Icon className="h-4 w-4" /> {title}</h3>
                {action}
            </div>
            {children}
        </div>
    );
}

function DashboardTab({ project, members, expenses, activities, notes, tasks, financials, purchaseOrders, attachablePurchaseOrders, canProcurement, expenseCategories, can, base, onNavigate }: Props & { base: string; onNavigate: (key: TabKey) => void }) {
    const [addExpenseOpen, setAddExpenseOpen] = useState(false);
    const [detaching, setDetaching] = useState<PurchaseOrder | null>(null);
    const linkForm = useForm({ purchase_order_id: '' });

    const activeMembers = members.filter(m => m.is_active);
    const doneTasks = tasks.filter(t => t.status === 'completed').length;
    const poTotal = purchaseOrders.reduce((s, po) => s + po.total, 0);
    const recentExpenses = expenses.slice(0, 5);
    const recentNotes = notes.slice(0, 3);

    // Searchable options — typing a PO number or supplier name filters the list.
    const poOptions = attachablePurchaseOrders.map(po => ({
        value: String(po.id),
        label: `${po.number}${po.supplier ? ` · ${po.supplier}` : ''} — ${formatCurrency(po.total)}`,
    }));
    const linkPo = () => {
        if (!linkForm.data.purchase_order_id) return;
        linkForm.post(`${base}/purchase-orders`, { preserveScroll: true, onSuccess: () => linkForm.reset() });
    };

    const facts: Array<[string, React.ReactNode]> = [
        ['Owner', project.owner ?? '—'],
        ['Project manager', project.manager ?? '—'],
        ['Client', project.company ?? 'Internal'],
        ['Start date', project.start_date ? formatDate(project.start_date) : '—'],
        ['Due date', project.due_date ? formatDate(project.due_date) : '—'],
        ['Budget', project.budget != null && project.budget > 0 ? formatCurrency(project.budget) : '—'],
    ];

    return (
        <div className="space-y-5">
            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <StatTile icon={ListChecks} label="Progress" value={`${project.progress}%`} sub={`${doneTasks}/${tasks.length} tasks done`} onClick={() => onNavigate('tasks')} />
                <StatTile icon={Users} label="Team" value={activeMembers.length} sub={activeMembers.length === 1 ? 'active member' : 'active members'} onClick={() => onNavigate('team')} />
                <StatTile icon={Receipt} label="Expenses" value={formatCurrency(financials.spent)} sub={`${expenses.length} logged`} tone="text-rose-600 dark:text-rose-400" onClick={() => onNavigate('financial')} />
                <StatTile icon={ShoppingCart} label="Purchase orders" value={formatCurrency(poTotal)} sub={`${purchaseOrders.length} linked`} onClick={() => onNavigate('pos')} />
            </div>

            <div className="grid gap-5 lg:grid-cols-3">
                <div className="space-y-5 lg:col-span-2">
                    <SectionCard icon={ClipboardList} title="Overview"
                        action={<button onClick={() => onNavigate('overview')} className="text-xs font-medium text-primary hover:underline">Details</button>}>
                        <dl className="grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-3">
                            {facts.map(([k, v]) => (
                                <div key={k}><dt className="text-xs text-muted-foreground">{k}</dt><dd className="mt-0.5 text-sm font-medium text-foreground">{v}</dd></div>
                            ))}
                        </dl>
                    </SectionCard>

                    <SectionCard icon={Users} title={`Team (${activeMembers.length})`}
                        action={<button onClick={() => onNavigate('team')} className="text-xs font-medium text-primary hover:underline">Manage</button>}>
                        {activeMembers.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No team members yet.</p>
                        ) : (
                            <div className="flex flex-wrap gap-2">
                                {activeMembers.slice(0, 8).map(m => (
                                    <div key={m.id} className="flex items-center gap-2 rounded-full bg-secondary/50 py-1 pl-1 pr-3">
                                        <span className={cn('flex h-7 w-7 items-center justify-center rounded-full bg-gradient-to-br text-[10px] font-bold text-white', avatarGradient(m.name))}>{getInitials(m.name)}</span>
                                        <span className="text-xs"><span className="font-medium text-foreground">{m.name}</span><span className="capitalize text-muted-foreground"> · {m.role}</span></span>
                                    </div>
                                ))}
                                {activeMembers.length > 8 && <span className="self-center text-xs text-muted-foreground">+{activeMembers.length - 8} more</span>}
                            </div>
                        )}
                    </SectionCard>

                    <SectionCard icon={ShoppingCart} title={`Purchase orders (${purchaseOrders.length})`}
                        action={purchaseOrders.length > 0 ? <button onClick={() => onNavigate('pos')} className="text-xs font-medium text-primary hover:underline">View all</button> : undefined}>
                        {can.manage && (
                            <div className="mb-3 flex flex-col gap-2 sm:flex-row">
                                <Select className="min-w-0 flex-1" searchable searchPlaceholder="Type a PO number or supplier…"
                                    placeholder={poOptions.length ? 'Link a purchase order…' : 'No unlinked POs available'}
                                    value={linkForm.data.purchase_order_id} onChange={v => linkForm.setData('purchase_order_id', v)} options={poOptions} />
                                <Button icon={Link2} onClick={linkPo} disabled={linkForm.processing || !linkForm.data.purchase_order_id}>Link</Button>
                            </div>
                        )}
                        {purchaseOrders.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No purchase orders linked yet.{can.manage && ' Search above to link one.'}</p>
                        ) : (
                            <div className="divide-y divide-border">
                                {purchaseOrders.slice(0, 5).map(po => {
                                    const inner = (
                                        <>
                                            <div className="min-w-0 flex-1">
                                                <p className="flex items-center gap-2 text-sm font-medium text-foreground"><span className="font-mono">{po.number}</span><Pill color={po.status_color} label={po.status_label} /></p>
                                                <p className="truncate text-xs text-muted-foreground">{po.supplier ?? 'No supplier'}{po.order_date ? ` · ${formatDate(po.order_date)}` : ''}</p>
                                            </div>
                                            <span className="text-sm font-semibold text-foreground">{formatCurrency(po.total)}</span>
                                        </>
                                    );
                                    return (
                                        <div key={po.id} className="flex items-center gap-3 py-2.5">
                                            {canProcurement ? (
                                                <a href={`/procurement/purchase-orders/${po.id}`} className="flex min-w-0 flex-1 items-center gap-3 hover:opacity-80">{inner}</a>
                                            ) : <div className="flex min-w-0 flex-1 items-center gap-3">{inner}</div>}
                                            {can.manage && <button onClick={() => setDetaching(po)} className="rounded-md p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive" title="Unlink"><X className="h-4 w-4" /></button>}
                                        </div>
                                    );
                                })}
                                {purchaseOrders.length > 5 && <button onClick={() => onNavigate('pos')} className="w-full pt-2.5 text-xs font-medium text-primary hover:underline">View all {purchaseOrders.length} purchase orders</button>}
                            </div>
                        )}
                    </SectionCard>

                    <SectionCard icon={Receipt} title="Expenses"
                        action={<div className="flex items-center gap-3"><span className="text-xs text-muted-foreground">Total {formatCurrency(financials.spent)}</span>{can.addExpense && <button onClick={() => setAddExpenseOpen(true)} className="text-xs font-medium text-primary hover:underline">Add</button>}</div>}>
                        {recentExpenses.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No expenses linked to this project yet.</p>
                        ) : (
                            <div className="divide-y divide-border">
                                {recentExpenses.map(e => (
                                    <Link key={e.id} href={`/expenses/${e.id}`} className="flex items-center gap-3 py-2.5 transition-colors hover:opacity-80">
                                        <span className="font-mono text-xs text-muted-foreground">{e.number}</span>
                                        <span className="min-w-0 flex-1 truncate text-sm text-foreground">{e.description || e.vendor || '—'}</span>
                                        {e.status_label && <Pill color={e.status_color ?? 'gray'} label={e.status_label} />}
                                        <span className="text-sm font-medium text-foreground">{formatCurrency(e.amount, e.currency)}</span>
                                    </Link>
                                ))}
                                {expenses.length > recentExpenses.length && <button onClick={() => onNavigate('financial')} className="w-full pt-2.5 text-xs font-medium text-primary hover:underline">View all {expenses.length} expenses</button>}
                            </div>
                        )}
                    </SectionCard>
                </div>

                <div className="space-y-5">
                    <SectionCard icon={History} title="Updates"
                        action={activities.length > 8 ? <button onClick={() => onNavigate('activity')} className="text-xs font-medium text-primary hover:underline">All</button> : undefined}>
                        <ActivityList activities={activities.slice(0, 8)} compact />
                    </SectionCard>

                    <SectionCard icon={StickyNote} title="Notes"
                        action={<button onClick={() => onNavigate('notes')} className="text-xs font-medium text-primary hover:underline">All</button>}>
                        {recentNotes.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No notes yet.</p>
                        ) : (
                            <ul className="space-y-3">
                                {recentNotes.map(n => (
                                    <li key={n.id} className="text-sm">
                                        <p className="line-clamp-3 whitespace-pre-line text-foreground">{n.body}</p>
                                        <p className="mt-0.5 text-xs text-muted-foreground">{n.author ?? 'Unknown'} · {timeAgo(n.created_at)}</p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </SectionCard>
                </div>
            </div>

            {addExpenseOpen && <AddExpenseModal projectId={project.id} categories={expenseCategories} onClose={() => setAddExpenseOpen(false)} />}
            <ConfirmDialog open={!!detaching} onClose={() => setDetaching(null)} onConfirm={() => { if (detaching) router.delete(`${base}/purchase-orders/${detaching.id}`, { preserveScroll: true, onFinish: () => setDetaching(null) }); }} title="Unlink purchase order?" message={detaching ? <>Unlink <span className="font-mono font-medium text-foreground">{detaching.number}</span> from this project? The PO itself is kept in Procurement.</> : ''} />
        </div>
    );
}

/* ── Overview ───────────────────────────────────────────────────────────── */
function BriefingMarkdown({ text }: { text: string }) {
    return (
        <div className="space-y-0.5">
            {text.split(/\r?\n/).map((raw, i) => {
                const t = raw.trim();
                if (!t) return null;
                const clean = t.replace(/\*\*(.+?)\*\*/g, '$1');
                if (clean.startsWith('## ')) return <p key={i} className="mt-3 text-sm font-bold text-foreground">{clean.slice(3)}</p>;
                if (clean.startsWith('# ')) return <p key={i} className="mt-3 text-sm font-bold text-foreground">{clean.slice(2)}</p>;
                const m = clean.match(/^[-*]\s+(.*)$/);
                if (m) return <p key={i} className="ml-3 text-sm text-muted-foreground">• {m[1]}</p>;
                return <p key={i} className="text-sm text-muted-foreground">{clean}</p>;
            })}
        </div>
    );
}

function BriefingCard({ project, can, base }: { project: Project; can: Props['can']; base: string }) {
    const [generating, setGenerating] = useState(false);
    const generate = () => router.post(`${base}/briefing`, {}, { preserveScroll: true, onStart: () => setGenerating(true), onFinish: () => setGenerating(false) });
    return (
        <div className="card-surface p-5">
            <div className="mb-3 flex items-center justify-between gap-2">
                <h3 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70"><Sparkles className="h-4 w-4 text-primary" /> AI field briefing</h3>
                {can.manage && <Button size="sm" variant="secondary" icon={Sparkles} onClick={generate} disabled={generating}>{generating ? 'Generating…' : project.ai_briefing ? 'Regenerate' : 'Generate'}</Button>}
            </div>
            {project.ai_briefing ? (
                <>
                    <BriefingMarkdown text={project.ai_briefing} />
                    {project.ai_briefing_at && <p className="mt-3 border-t border-border pt-2 text-xs text-muted-foreground">Generated {timeAgo(project.ai_briefing_at)}{project.ai_briefing_by ? ` by ${project.ai_briefing_by}` : ''}</p>}
                </>
            ) : (
                <p className="text-sm text-muted-foreground">{generating ? 'Generating a pre-departure briefing from the site, equipment, shipments, execution and travel data…' : 'No briefing yet. Generate a pre-departure summary of everything captured for this project.'}</p>
            )}
        </div>
    );
}

function OverviewTab({ project, members, activities, can, base }: Props & { base: string }) {
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
                <BriefingCard project={project} can={can} base={base} />
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

/* ── Files (folders + version history) ──────────────────────────────────── */
function FolderChip({ active, onClick, label, count, icon: Icon }: { active: boolean; onClick: () => void; label: string; count: number; icon: LucideIcon }) {
    return (
        <button onClick={onClick} className={cn('inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium transition-colors',
            active ? 'bg-primary text-white' : 'bg-secondary text-muted-foreground hover:text-foreground')}>
            <Icon className="h-3.5 w-3.5" /> {label} <span className={cn('rounded-full px-1', active ? 'bg-white/20' : 'bg-background')}>{count}</span>
        </button>
    );
}

function FilesTab({ files, folders, base, can }: Props & { base: string }) {
    const fileInput = useRef<HTMLInputElement>(null);
    const versionInput = useRef<HTMLInputElement>(null);
    const versionTarget = useRef<ProjectFile | null>(null);
    const [folder, setFolder] = useState<number | 'all' | 'unfiled'>('all');
    const [dragOver, setDragOver] = useState(false);
    const [uploading, setUploading] = useState(false);
    const [expanded, setExpanded] = useState<number | null>(null);
    const [folderModal, setFolderModal] = useState<Folder | null | 'new'>(null);
    const [deletingFolder, setDeletingFolder] = useState<Folder | null>(null);
    const [deleting, setDeleting] = useState<ProjectFile | null>(null);

    const upload = (file: File, extra: Record<string, string | number> = {}) => {
        router.post(`${base}/files`, { file, ...extra }, { preserveScroll: true, forceFormData: true, onStart: () => setUploading(true), onFinish: () => setUploading(false) });
    };
    const uploadHere = (list: FileList | null) => {
        if (!list) return;
        const extra: Record<string, string | number> = typeof folder === 'number' ? { crm_project_folder_id: folder } : {};
        Array.from(list).forEach(f => upload(f, extra));
    };
    const moveFile = (f: ProjectFile, v: string) => router.patch(`${base}/files/${f.id}/move`, { crm_project_folder_id: v || null }, { preserveScroll: true });

    const folderCount = (id: number) => files.filter(f => f.folder_id === id).length;
    const visible = files.filter(f => folder === 'all' ? true : folder === 'unfiled' ? f.folder_id == null : f.folder_id === folder);
    const activeFolder = typeof folder === 'number' ? folders.find(x => x.id === folder) : null;

    return (
        <div>
            <div className="mb-3 flex flex-wrap items-center gap-2">
                <FolderChip active={folder === 'all'} onClick={() => setFolder('all')} label="All" count={files.length} icon={Folder} />
                <FolderChip active={folder === 'unfiled'} onClick={() => setFolder('unfiled')} label="Unfiled" count={files.filter(f => f.folder_id == null).length} icon={Folder} />
                {folders.map(fl => (
                    <FolderChip key={fl.id} active={folder === fl.id} onClick={() => setFolder(fl.id)} label={fl.name} count={folderCount(fl.id)} icon={folder === fl.id ? FolderOpen : Folder} />
                ))}
                {can.manage && <button onClick={() => setFolderModal('new')} className="inline-flex items-center gap-1 rounded-full border border-dashed border-border px-2.5 py-1 text-xs font-medium text-muted-foreground hover:border-primary hover:text-primary"><FolderPlus className="h-3.5 w-3.5" /> New folder</button>}
                {activeFolder && can.manage && (
                    <span className="ml-auto flex items-center gap-1">
                        <button onClick={() => setFolderModal(activeFolder)} className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground" title="Rename folder"><Pencil className="h-3.5 w-3.5" /></button>
                        <button onClick={() => setDeletingFolder(activeFolder)} className="rounded-md p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive" title="Delete folder"><Trash2 className="h-3.5 w-3.5" /></button>
                    </span>
                )}
            </div>

            {can.manage && (
                <div onDragOver={e => { e.preventDefault(); setDragOver(true); }} onDragLeave={() => setDragOver(false)}
                    onDrop={e => { e.preventDefault(); setDragOver(false); uploadHere(e.dataTransfer.files); }}
                    className={cn('mb-4 flex flex-col items-center justify-center rounded-xl border-2 border-dashed px-4 py-6 text-center transition-colors', dragOver ? 'border-primary bg-primary/5' : 'border-border')}>
                    <Upload className="mb-1.5 h-5 w-5 text-muted-foreground" />
                    <p className="text-sm text-muted-foreground">{uploading ? 'Uploading…' : <>Drag &amp; drop files here{activeFolder ? ` into "${activeFolder.name}"` : ''}, or <button onClick={() => fileInput.current?.click()} className="font-medium text-primary hover:underline">browse</button></>}</p>
                    <input ref={fileInput} type="file" multiple className="hidden" onChange={e => { uploadHere(e.target.files); if (fileInput.current) fileInput.current.value = ''; }} />
                    <input ref={versionInput} type="file" className="hidden" onChange={e => { const f = e.target.files?.[0]; const t = versionTarget.current; if (f && t) upload(f, { parent_file_id: t.id }); versionTarget.current = null; if (versionInput.current) versionInput.current.value = ''; }} />
                </div>
            )}

            <div className="card-surface divide-y divide-border p-0">
                {visible.map(f => (
                    <div key={f.id}>
                        <div className="flex items-center gap-3 px-4 py-3">
                            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-secondary text-muted-foreground"><FileText className="h-4 w-4" /></span>
                            <div className="min-w-0 flex-1">
                                <p className="flex flex-wrap items-center gap-2 text-sm font-medium text-foreground"><span className="truncate">{f.name}</span>
                                    {f.versions.length > 1 && <span className="rounded-full bg-secondary px-1.5 text-[10px] font-semibold text-muted-foreground">v{f.version}</span>}
                                    {f.source === 'proposal' && <span className="inline-flex items-center gap-0.5 rounded bg-primary/10 px-1.5 text-[10px] font-semibold text-primary"><Sparkles className="h-2.5 w-2.5" />From proposal</span>}
                                </p>
                                <p className="text-xs text-muted-foreground">{fileSize(f.size)}{f.uploaded_by ? ` · ${f.uploaded_by}` : ''} · {timeAgo(f.created_at)}</p>
                            </div>
                            <div className="flex shrink-0 items-center gap-1">
                                {can.manage && folders.length > 0 && (
                                    <Select size="sm" className="hidden w-32 sm:block" value={f.folder_id ? String(f.folder_id) : ''} onChange={v => moveFile(f, v)} placeholder="Unfiled" options={folders.map(fl => ({ value: String(fl.id), label: fl.name }))} />
                                )}
                                {f.versions.length > 1 && <button onClick={() => setExpanded(expanded === f.id ? null : f.id)} className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground" title="Version history">{expanded === f.id ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}</button>}
                                {can.manage && <button onClick={() => { versionTarget.current = f; versionInput.current?.click(); }} className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground" title="Upload new version"><FileUp className="h-4 w-4" /></button>}
                                <a href={`${base}/files/${f.id}/download`} className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground" title="Download"><Download className="h-4 w-4" /></a>
                                {can.manage && <button onClick={() => setDeleting(f)} className="rounded-md p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive" title="Delete"><Trash2 className="h-4 w-4" /></button>}
                            </div>
                        </div>
                        {expanded === f.id && (
                            <div className="bg-secondary/30 px-4 pb-3 pl-16">
                                {f.versions.map(v => (
                                    <div key={v.id} className="flex items-center gap-2 py-1 text-xs">
                                        <span className={cn('font-semibold', v.is_current ? 'text-primary' : 'text-muted-foreground')}>v{v.version}{v.is_current ? ' · current' : ''}</span>
                                        <span className="text-muted-foreground">{fileSize(v.size)}{v.uploaded_by ? ` · ${v.uploaded_by}` : ''} · {timeAgo(v.created_at)}</span>
                                        <span className="ml-auto flex items-center gap-1">
                                            <a href={`${base}/files/${v.id}/download`} className="rounded p-1 text-muted-foreground hover:bg-secondary hover:text-foreground" title="Download this version"><Download className="h-3.5 w-3.5" /></a>
                                            {can.manage && !v.is_current && <button onClick={() => router.patch(`${base}/files/${v.id}/restore-version`, {}, { preserveScroll: true })} className="rounded p-1 text-muted-foreground hover:bg-secondary hover:text-foreground" title="Make current"><RotateCcw className="h-3.5 w-3.5" /></button>}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                ))}
                {visible.length === 0 && <p className="px-4 py-8 text-center text-sm text-muted-foreground">No files{folder !== 'all' ? ' here' : ' attached'}.</p>}
            </div>

            {folderModal && <FolderModal base={base} folder={folderModal === 'new' ? null : folderModal} onClose={() => setFolderModal(null)} />}
            <ConfirmDialog open={!!deletingFolder} onClose={() => setDeletingFolder(null)} onConfirm={() => { if (deletingFolder) router.delete(`${base}/folders/${deletingFolder.id}`, { preserveScroll: true, onFinish: () => { setDeletingFolder(null); setFolder('all'); } }); }} title="Delete folder?" message={deletingFolder ? <>Delete <span className="font-medium text-foreground">{deletingFolder.name}</span>? Its files are kept and moved to Unfiled.</> : ''} />
            <ConfirmDialog open={!!deleting} onClose={() => setDeleting(null)} onConfirm={() => { if (deleting) router.delete(`${base}/files/${deleting.id}`, { preserveScroll: true, onFinish: () => setDeleting(null) }); }} title="Delete file?" message={deleting ? <>Delete <span className="font-medium text-foreground">{deleting.name}</span>{deleting.versions.length > 1 ? ' (current version)' : ''}?</> : ''} />
        </div>
    );
}

function FolderModal({ base, folder, onClose }: { base: string; folder: Folder | null; onClose: () => void }) {
    const isEdit = !!folder;
    const form = useForm({ name: folder?.name ?? '' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => { form.reset(); onClose(); } };
        if (isEdit) form.put(`${base}/folders/${folder!.id}`, opts); else form.post(`${base}/folders`, opts);
    };
    return (
        <Modal open onClose={onClose} title={isEdit ? 'Rename Folder' : 'New Folder'}
            footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button onClick={submit as unknown as () => void} disabled={form.processing}>{form.processing ? 'Saving…' : isEdit ? 'Save' : 'Create'}</Button></>}>
            <form onSubmit={submit}>
                <label className="label">Folder name *</label>
                <input className="input" value={form.data.name} onChange={e => form.setData('name', e.target.value)} autoFocus placeholder="e.g. Contracts, CAD Drawings, FAT/SAT" />
                {form.errors.name && <p className="mt-1 text-xs text-destructive">{form.errors.name}</p>}
            </form>
        </Modal>
    );
}

/* ── Digital sign-offs ──────────────────────────────────────────────────── */
function SignoffTab({ signoffs, signoffTypes, executionRecords, base, can }: Props & { base: string }) {
    const [modal, setModal] = useState(false);
    const [deleting, setDeleting] = useState<Signoff | null>(null);
    return (
        <div>
            <div className="mb-3 flex items-center justify-between">
                <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70"><Signature className="h-4 w-4" /> Sign-offs ({signoffs.length})</h2>
                {can.manage && <Button size="sm" icon={Plus} onClick={() => setModal(true)}>Add Sign-off</Button>}
            </div>
            {signoffs.length === 0 ? (
                <div className="card-surface p-8 text-center text-sm text-muted-foreground">No sign-offs captured yet — record customer acceptance, QA and commissioning approvals with a signature and timestamp.</div>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2">
                    {signoffs.map(s => <SignoffCard key={s.id} signoff={s} can={can} onDelete={() => setDeleting(s)} />)}
                </div>
            )}
            {modal && <SignoffModal base={base} types={signoffTypes} records={executionRecords} onClose={() => setModal(false)} />}
            <ConfirmDialog open={!!deleting} onClose={() => setDeleting(null)} onConfirm={() => { if (deleting) router.delete(`${base}/signoffs/${deleting.id}`, { preserveScroll: true, onFinish: () => setDeleting(null) }); }} title="Remove sign-off?" message={deleting ? <>Remove the {deleting.type_label} sign-off by <span className="font-medium text-foreground">{deleting.signer_name}</span>?</> : ''} />
        </div>
    );
}

function SignoffCard({ signoff: s, can, onDelete }: { signoff: Signoff; can: Props['can']; onDelete: () => void }) {
    return (
        <div className="card-surface p-4">
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2"><Pill color={s.type_color} label={s.type_label} /><p className="text-sm font-semibold text-foreground">{s.signer_name}</p></div>
                    {(s.signer_title || s.signer_email) && <p className="mt-0.5 text-xs text-muted-foreground">{[s.signer_title, s.signer_email].filter(Boolean).join(' · ')}</p>}
                </div>
                {can.manage && <button onClick={onDelete} className="rounded-md p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive" title="Remove"><Trash2 className="h-3.5 w-3.5" /></button>}
            </div>
            {s.statement && <p className="mt-2 text-sm italic text-muted-foreground">&ldquo;{s.statement}&rdquo;</p>}
            {s.signature_data && <img src={s.signature_data} alt={`Signature of ${s.signer_name}`} className="mt-2 h-20 w-full rounded-md border border-border bg-white object-contain" />}
            <p className="mt-2 border-t border-border pt-2 text-xs text-muted-foreground">
                Signed {s.signed_at ? fmtDateTime(s.signed_at) : '—'}{s.execution_record ? ` · re: ${s.execution_record}` : ''}{s.captured_by ? ` · recorded by ${s.captured_by}` : ''}
            </p>
        </div>
    );
}

function SignaturePad({ onChange }: { onChange: (data: string) => void }) {
    const ref = useRef<HTMLCanvasElement>(null);
    const drawing = useRef(false);
    const dirty = useRef(false);
    const point = (e: React.MouseEvent | React.TouchEvent) => {
        const c = ref.current!; const r = c.getBoundingClientRect();
        const t = 'touches' in e ? e.touches[0] : (e as React.MouseEvent);
        return { x: (t.clientX - r.left) * (c.width / r.width), y: (t.clientY - r.top) * (c.height / r.height) };
    };
    const start = (e: React.MouseEvent | React.TouchEvent) => { drawing.current = true; const ctx = ref.current!.getContext('2d')!; const p = point(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); };
    const move = (e: React.MouseEvent | React.TouchEvent) => {
        if (!drawing.current) return;
        if ('touches' in e) e.preventDefault();
        const ctx = ref.current!.getContext('2d')!; const p = point(e);
        ctx.lineTo(p.x, p.y); ctx.strokeStyle = '#1f2433'; ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.stroke();
        dirty.current = true;
    };
    const end = () => { if (drawing.current && dirty.current) onChange(ref.current!.toDataURL('image/png')); drawing.current = false; };
    const clear = () => { const c = ref.current!; c.getContext('2d')!.clearRect(0, 0, c.width, c.height); dirty.current = false; onChange(''); };
    return (
        <div>
            <canvas ref={ref} width={520} height={150}
                className="w-full touch-none rounded-lg border border-input bg-white"
                onMouseDown={start} onMouseMove={move} onMouseUp={end} onMouseLeave={end}
                onTouchStart={start} onTouchMove={move} onTouchEnd={end} />
            <div className="mt-1 flex items-center justify-between text-xs text-muted-foreground"><span>Sign in the box above (optional)</span><button type="button" onClick={clear} className="font-medium hover:text-foreground">Clear</button></div>
        </div>
    );
}

function SignoffModal({ base, types, records, onClose }: { base: string; types: SignoffType[]; records: ExecutionRecord[]; onClose: () => void }) {
    const nowLocal = () => { const d = new Date(); const p = (n: number) => String(n).padStart(2, '0'); return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`; };
    const defaults = types.map(t => t.statement);
    const form = useForm({
        type: types[0]?.value ?? 'customer',
        signer_name: '', signer_title: '', signer_email: '',
        statement: types[0]?.statement ?? '',
        signature_data: '',
        signed_at: nowLocal(),
        crm_project_execution_record_id: '',
        notes: '',
    });
    const onType = (v: string) => {
        const newDef = types.find(t => t.value === v)?.statement ?? '';
        form.setData(d => ({ ...d, type: v, statement: (d.statement.trim() === '' || defaults.includes(d.statement.trim())) ? newDef : d.statement }));
    };
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform(d => ({ ...d, crm_project_execution_record_id: d.crm_project_execution_record_id || null, signature_data: d.signature_data || null }));
        form.post(`${base}/signoffs`, { preserveScroll: true, onSuccess: () => { form.reset(); onClose(); } });
    };
    return (
        <Modal open onClose={onClose} title="Add Sign-off"
            description="Capture an acceptance or approval with a signature and timestamp."
            footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button onClick={submit as unknown as () => void} disabled={form.processing}>{form.processing ? 'Saving…' : 'Record Sign-off'}</Button></>}>
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-2 gap-3">
                    <div><label className="label">Type</label><Select className="w-full" value={form.data.type} onChange={onType} options={types.map(t => ({ value: t.value, label: t.label }))} /></div>
                    <div><label className="label">Signed at</label><input type="datetime-local" className="input" value={form.data.signed_at} onChange={e => form.setData('signed_at', e.target.value)} /></div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div><label className="label">Signer name *</label><input className="input" value={form.data.signer_name} onChange={e => form.setData('signer_name', e.target.value)} autoFocus />{form.errors.signer_name && <p className="mt-1 text-xs text-destructive">{form.errors.signer_name}</p>}</div>
                    <div><label className="label">Title</label><input className="input" value={form.data.signer_title} onChange={e => form.setData('signer_title', e.target.value)} placeholder="optional" /></div>
                </div>
                <div><label className="label">Email</label><input className="input" value={form.data.signer_email} onChange={e => form.setData('signer_email', e.target.value)} placeholder="optional" />{form.errors.signer_email && <p className="mt-1 text-xs text-destructive">{form.errors.signer_email}</p>}</div>
                <div><label className="label">Statement</label><textarea className="input min-h-[56px]" value={form.data.statement} onChange={e => form.setData('statement', e.target.value)} /></div>
                {records.length > 0 && <div><label className="label">Related record</label><Select className="w-full" value={form.data.crm_project_execution_record_id} onChange={v => form.setData('crm_project_execution_record_id', v)} placeholder="— None —" options={records.map(r => ({ value: String(r.id), label: `${r.type_label}: ${r.title}` }))} /></div>}
                <div><label className="label">Signature</label><SignaturePad onChange={v => form.setData('signature_data', v)} /></div>
                <div><label className="label">Notes</label><textarea className="input min-h-[40px]" value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} placeholder="optional" /></div>
            </form>
        </Modal>
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
function FinancialTab({ project, financials, invoices, expenses, expenseCategories, can }: Props) {
    const [addOpen, setAddOpen] = useState(false);
    const cards: Array<[string, number, string]> = [
        ['Awarded budget', financials.budget, 'text-foreground'],
        ['Invoiced', financials.invoiced, 'text-blue-600 dark:text-blue-400'],
        ['Collected', financials.paid, 'text-emerald-600 dark:text-emerald-400'],
        ['Outstanding', financials.outstanding, 'text-amber-600 dark:text-amber-400'],
        ['Expenses', financials.spent, 'text-rose-600 dark:text-rose-400'],
        ['Margin', financials.margin, financials.margin >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'],
    ];
    return (
        <div className="space-y-5">
            <div className="grid grid-cols-2 gap-3 lg:grid-cols-3">
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

            <div className="card-surface p-0">
                <div className="flex items-center justify-between border-b border-border px-4 py-3">
                    <h3 className="text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Expenses</h3>
                    <div className="flex items-center gap-3">
                        <span className="text-xs text-muted-foreground">Total spent: <span className="font-medium text-foreground">{formatCurrency(financials.spent)}</span></span>
                        {can.addExpense && <Button size="sm" variant="secondary" onClick={() => setAddOpen(true)}>+ Add expense</Button>}
                    </div>
                </div>
                <div className="divide-y divide-border">
                    {expenses.map(e => (
                        <Link key={e.id} href={`/expenses/${e.id}`} className="flex items-center gap-3 px-4 py-3 transition-colors hover:bg-secondary" data-no-row-link>
                            <span className="font-mono text-xs text-muted-foreground">{e.number}</span>
                            <span className="min-w-0 truncate text-sm text-foreground">{e.description || e.vendor || '—'}{e.category ? <span className="text-muted-foreground"> · {e.category}</span> : null}</span>
                            {e.status_label && <Pill color={e.status_color ?? 'gray'} label={e.status_label} />}
                            <span className="ml-auto text-sm font-medium text-foreground">{formatCurrency(e.amount, e.currency)}</span>
                        </Link>
                    ))}
                    {expenses.length === 0 && <p className="px-4 py-8 text-center text-sm text-muted-foreground">No expenses linked to this project yet.</p>}
                </div>
            </div>

            {addOpen && <AddExpenseModal projectId={project.id} categories={expenseCategories} onClose={() => setAddOpen(false)} />}
        </div>
    );
}

function AddExpenseModal({ projectId, categories, onClose }: { projectId: number; categories: { value: number; label: string }[]; onClose: () => void }) {
    const form = useForm({ description: '', vendor: '', amount: '', currency: 'USD', expense_date: new Date().toISOString().slice(0, 10), expense_category_id: '', notes: '' });
    const submit = () => form.post(`/projects/${projectId}/expenses`, { preserveScroll: true, onSuccess: () => onClose() });

    return (
        <Modal open onClose={onClose} title="Add expense" description="Logs a draft expense linked to this project."
            footer={<>
                <Button variant="ghost" onClick={onClose} disabled={form.processing}>Cancel</Button>
                <Button onClick={submit} disabled={form.processing || !form.data.description || !form.data.amount}>{form.processing ? 'Saving…' : 'Add expense'}</Button>
            </>}>
            <div className="space-y-3">
                <div><label className="label">Description *</label><input className="input" autoFocus value={form.data.description} onChange={e => form.setData('description', e.target.value)} />{form.errors.description && <p className="mt-1 text-xs text-destructive">{form.errors.description}</p>}</div>
                <div className="grid grid-cols-2 gap-3">
                    <div><label className="label">Amount *</label><input type="number" step="0.01" min="0" className="input" value={form.data.amount} onChange={e => form.setData('amount', e.target.value)} />{form.errors.amount && <p className="mt-1 text-xs text-destructive">{form.errors.amount}</p>}</div>
                    <div><label className="label">Currency</label><input className="input uppercase" maxLength={3} value={form.data.currency} onChange={e => form.setData('currency', e.target.value.toUpperCase())} /></div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div><label className="label">Vendor</label><input className="input" value={form.data.vendor} onChange={e => form.setData('vendor', e.target.value)} /></div>
                    <div><label className="label">Date</label><input type="date" className="input" value={form.data.expense_date} onChange={e => form.setData('expense_date', e.target.value)} /></div>
                </div>
                <div>
                    <label className="label">Category</label>
                    <Select className="w-full" value={form.data.expense_category_id} placeholder="— None —" onChange={v => form.setData('expense_category_id', v)} options={categories.map(c => ({ value: String(c.value), label: c.label }))} />
                </div>
                <div><label className="label">Notes</label><textarea className="input min-h-[60px]" value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} /></div>
            </div>
        </Modal>
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

/* ── Site & Safety briefing ─────────────────────────────────────────────── */
const triInit = (v: boolean | null | undefined): string => (v === true ? '1' : v === false ? '0' : '');
const triParse = (s: string): boolean | null => (s === '1' ? true : s === '0' ? false : null);
const SITE_BOOLS = [
    'badge_required', 'escort_required', 'forklift_available', 'crane_available', 'internet_available',
    'power_available', 'water_available', 'compressed_air_available', 'high_voltage', 'confined_space', 'fall_protection',
] as const;

function Detail({ label, value, pre = false }: { label: string; value: React.ReactNode; pre?: boolean }) {
    if (value === null || value === undefined || value === '') return null;
    return <div><dt className="text-xs text-muted-foreground">{label}</dt><dd className={cn('mt-0.5 text-sm font-medium text-foreground', pre && 'whitespace-pre-line')}>{value}</dd></div>;
}

function AmenityPill({ label, value, icon: Icon }: { label: string; value: boolean | null; icon: LucideIcon }) {
    if (value === null || value === undefined) return null;
    return (
        <span className={cn('inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium',
            value ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400' : 'bg-secondary text-muted-foreground line-through')}>
            <Icon className="h-3 w-3" /> {label}
        </span>
    );
}

function SiteSafetyTab({ sites, siteContacts, contactCategories, base, can }: Props & { base: string }) {
    const [siteModal, setSiteModal] = useState<ProjectSite | null | 'new'>(null);
    const [deletingSite, setDeletingSite] = useState<ProjectSite | null>(null);
    const [contactModal, setContactModal] = useState<SiteContact | null | 'new'>(null);
    const [deletingContact, setDeletingContact] = useState<SiteContact | null>(null);

    const emergency = siteContacts.filter(c => c.is_emergency);
    const regular = siteContacts.filter(c => !c.is_emergency);
    const siteName = (id: number | null) => sites.find(s => s.id === id)?.name ?? null;

    return (
        <div className="space-y-8">
            {/* Sites */}
            <section>
                <div className="mb-3 flex items-center justify-between">
                    <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70"><MapPinned className="h-4 w-4" /> Installation sites ({sites.length})</h2>
                    {can.manage && <Button size="sm" icon={Plus} onClick={() => setSiteModal('new')}>Add Site</Button>}
                </div>
                {sites.length === 0 ? (
                    <div className="card-surface p-8 text-center text-sm text-muted-foreground">
                        No site captured yet — add the installation site so the field team has access, utilities, hazards and emergency info before they leave.
                    </div>
                ) : (
                    <div className="space-y-4">
                        {sites.map(s => (
                            <SiteCard key={s.id} site={s} base={base} can={can}
                                onEdit={() => setSiteModal(s)} onDelete={() => setDeletingSite(s)} />
                        ))}
                    </div>
                )}
            </section>

            {/* Site contacts */}
            <section>
                <div className="mb-3 flex items-center justify-between">
                    <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70"><Users className="h-4 w-4" /> Site contacts ({siteContacts.length})</h2>
                    {can.manage && <Button size="sm" icon={Plus} onClick={() => setContactModal('new')}>Add Contact</Button>}
                </div>
                {siteContacts.length === 0 ? (
                    <div className="card-surface p-8 text-center text-sm text-muted-foreground">
                        No contacts yet — add the procurement, facilities, IT, security, receiving and emergency people to call on site.
                    </div>
                ) : (
                    <div className="space-y-4">
                        {emergency.length > 0 && (
                            <div>
                                <p className="mb-2 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-red-600 dark:text-red-400"><AlertTriangle className="h-3.5 w-3.5" /> Emergency</p>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {emergency.map(c => <ContactCard key={c.id} contact={c} siteName={siteName(c.crm_project_site_id)} can={can} onEdit={() => setContactModal(c)} onDelete={() => setDeletingContact(c)} />)}
                                </div>
                            </div>
                        )}
                        <div className="grid gap-3 sm:grid-cols-2">
                            {regular.map(c => <ContactCard key={c.id} contact={c} siteName={siteName(c.crm_project_site_id)} can={can} onEdit={() => setContactModal(c)} onDelete={() => setDeletingContact(c)} />)}
                        </div>
                    </div>
                )}
            </section>

            {siteModal && <SiteModal base={base} site={siteModal === 'new' ? null : siteModal} onClose={() => setSiteModal(null)} />}
            {contactModal && <ContactModal base={base} contact={contactModal === 'new' ? null : contactModal} categories={contactCategories} sites={sites} onClose={() => setContactModal(null)} />}
            <ConfirmDialog open={!!deletingSite} onClose={() => setDeletingSite(null)} onConfirm={() => { if (deletingSite) router.delete(`${base}/sites/${deletingSite.id}`, { preserveScroll: true, onFinish: () => setDeletingSite(null) }); }} title="Remove site?" message={deletingSite ? <>Remove <span className="font-medium text-foreground">{deletingSite.name}</span> and its briefing details?</> : ''} />
            <ConfirmDialog open={!!deletingContact} onClose={() => setDeletingContact(null)} onConfirm={() => { if (deletingContact) router.delete(`${base}/contacts/${deletingContact.id}`, { preserveScroll: true, onFinish: () => setDeletingContact(null) }); }} title="Remove contact?" message={deletingContact ? <>Remove <span className="font-medium text-foreground">{deletingContact.name}</span>?</> : ''} />
        </div>
    );
}

function SiteCard({ site, base, can, onEdit, onDelete }: { site: ProjectSite; base: string; can: Props['can']; onEdit: () => void; onDelete: () => void }) {
    const directions = site.maps_url
        ?? (site.latitude != null && site.longitude != null ? `https://www.google.com/maps?q=${site.latitude},${site.longitude}` : null)
        ?? (site.address ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(site.address)}` : null);

    const hasAccess = site.access_instructions || site.loading_dock || site.parking || site.working_hours || site.gate_hours;
    const hasSecurity = site.badge_required != null || site.escort_required != null || site.ppe_required || site.security_requirements;
    const hasUtilities = [site.forklift_available, site.crane_available, site.power_available, site.internet_available, site.water_available, site.compressed_air_available].some(v => v != null) || site.utilities_notes || site.environmental_conditions;
    const hasSafety = site.hazards || site.lockout_tagout || site.chemical_hazards || site.emergency_assembly_point || [site.high_voltage, site.confined_space, site.fall_protection].some(Boolean);
    const hasEmergency = site.nearest_hospital || site.hospital_phone || site.police_phone || site.fire_phone || site.site_safety_contact;

    return (
        <div className="card-surface p-5">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <MapPin className="h-4 w-4 shrink-0 text-primary" />
                        <h3 className="text-base font-semibold text-foreground">{site.name}</h3>
                        {site.is_primary && <span className="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-semibold text-primary"><Star className="h-3 w-3" /> Primary</span>}
                    </div>
                    {site.address && <p className="mt-1 whitespace-pre-line text-sm text-muted-foreground">{site.address}</p>}
                    {directions && <a href={directions} target="_blank" rel="noopener noreferrer" className="mt-1 inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"><Navigation className="h-3 w-3" /> Directions</a>}
                </div>
                {can.manage && (
                    <div className="flex shrink-0 items-center gap-1">
                        {!site.is_primary && <button onClick={() => router.put(`${base}/sites/${site.id}`, { ...site, is_primary: true }, { preserveScroll: true })} className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground" title="Make primary"><Star className="h-3.5 w-3.5" /></button>}
                        <button onClick={onEdit} className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground" title="Edit"><Pencil className="h-3.5 w-3.5" /></button>
                        <button onClick={onDelete} className="rounded-md p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive" title="Remove"><Trash2 className="h-3.5 w-3.5" /></button>
                    </div>
                )}
            </div>

            {/* At-a-glance flags */}
            {(site.badge_required || site.escort_required || site.high_voltage || site.confined_space || site.fall_protection || site.ppe_required) && (
                <div className="mt-3 flex flex-wrap gap-1.5">
                    {site.badge_required && <span className="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-400"><BadgeCheck className="h-3 w-3" /> Badge required</span>}
                    {site.escort_required && <span className="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-400"><Users className="h-3 w-3" /> Escort required</span>}
                    {site.ppe_required && <span className="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-400"><HardHat className="h-3 w-3" /> PPE: {site.ppe_required}</span>}
                    {site.high_voltage && <span className="inline-flex items-center gap-1 rounded-full bg-red-500/10 px-2 py-0.5 text-xs font-medium text-red-700 dark:text-red-400"><Zap className="h-3 w-3" /> High voltage</span>}
                    {site.confined_space && <span className="inline-flex items-center gap-1 rounded-full bg-red-500/10 px-2 py-0.5 text-xs font-medium text-red-700 dark:text-red-400"><AlertTriangle className="h-3 w-3" /> Confined space</span>}
                    {site.fall_protection && <span className="inline-flex items-center gap-1 rounded-full bg-red-500/10 px-2 py-0.5 text-xs font-medium text-red-700 dark:text-red-400"><AlertTriangle className="h-3 w-3" /> Fall protection</span>}
                </div>
            )}

            {(hasAccess || hasSecurity || hasUtilities || hasSafety || hasEmergency || site.notes) && (
                <div className="mt-4 grid gap-5 border-t border-border pt-4 sm:grid-cols-2">
                    {hasAccess && (
                        <div>
                            <h4 className="mb-2 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-muted-foreground/70"><Clock className="h-3.5 w-3.5" /> Access & hours</h4>
                            <dl className="space-y-2">
                                <Detail label="Access instructions" value={site.access_instructions} pre />
                                <Detail label="Loading dock" value={site.loading_dock} />
                                <Detail label="Parking" value={site.parking} />
                                <Detail label="Working hours" value={site.working_hours} />
                                <Detail label="Gate hours" value={site.gate_hours} />
                            </dl>
                        </div>
                    )}
                    {hasSecurity && (
                        <div>
                            <h4 className="mb-2 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-muted-foreground/70"><ShieldAlert className="h-3.5 w-3.5" /> Security & PPE</h4>
                            <dl className="space-y-2">
                                <Detail label="Badge required" value={site.badge_required == null ? null : site.badge_required ? 'Yes' : 'No'} />
                                <Detail label="Escort required" value={site.escort_required == null ? null : site.escort_required ? 'Yes' : 'No'} />
                                <Detail label="PPE required" value={site.ppe_required} />
                                <Detail label="Security requirements" value={site.security_requirements} pre />
                            </dl>
                        </div>
                    )}
                    {hasUtilities && (
                        <div>
                            <h4 className="mb-2 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-muted-foreground/70"><Zap className="h-3.5 w-3.5" /> Utilities & resources</h4>
                            <div className="flex flex-wrap gap-1.5">
                                <AmenityPill label="Forklift" value={site.forklift_available} icon={Forklift} />
                                <AmenityPill label="Crane" value={site.crane_available} icon={Construction} />
                                <AmenityPill label="Power" value={site.power_available} icon={Zap} />
                                <AmenityPill label="Internet" value={site.internet_available} icon={Wifi} />
                                <AmenityPill label="Water" value={site.water_available} icon={Droplets} />
                                <AmenityPill label="Compressed air" value={site.compressed_air_available} icon={Wind} />
                            </div>
                            <dl className="mt-2 space-y-2">
                                <Detail label="Utilities notes" value={site.utilities_notes} pre />
                                <Detail label="Environmental conditions" value={site.environmental_conditions} pre />
                            </dl>
                        </div>
                    )}
                    {hasSafety && (
                        <div>
                            <h4 className="mb-2 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-muted-foreground/70"><AlertTriangle className="h-3.5 w-3.5" /> Safety</h4>
                            <dl className="space-y-2">
                                <Detail label="Hazards" value={site.hazards} pre />
                                <Detail label="Lockout / tagout" value={site.lockout_tagout} pre />
                                <Detail label="Chemical hazards" value={site.chemical_hazards} pre />
                                <Detail label="Emergency assembly point" value={site.emergency_assembly_point} />
                            </dl>
                        </div>
                    )}
                    {hasEmergency && (
                        <div className="rounded-lg border border-red-500/30 bg-red-500/5 p-3 sm:col-span-2">
                            <h4 className="mb-2 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-red-600 dark:text-red-400"><Hospital className="h-3.5 w-3.5" /> Emergency services</h4>
                            <dl className="grid grid-cols-2 gap-x-4 gap-y-2 sm:grid-cols-4">
                                <Detail label="Nearest hospital" value={site.nearest_hospital} />
                                <Detail label="Hospital phone" value={site.hospital_phone ? <a href={`tel:${site.hospital_phone}`} className="text-primary hover:underline">{site.hospital_phone}</a> : null} />
                                <Detail label="Police" value={site.police_phone ? <a href={`tel:${site.police_phone}`} className="text-primary hover:underline">{site.police_phone}</a> : null} />
                                <Detail label="Fire" value={site.fire_phone ? <a href={`tel:${site.fire_phone}`} className="text-primary hover:underline">{site.fire_phone}</a> : null} />
                                <Detail label="Site safety contact" value={site.site_safety_contact} />
                            </dl>
                        </div>
                    )}
                    {site.notes && (
                        <div className="sm:col-span-2">
                            <h4 className="mb-2 text-xs font-bold uppercase tracking-wider text-muted-foreground/70">Notes</h4>
                            <p className="whitespace-pre-line text-sm text-muted-foreground">{site.notes}</p>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

function ContactCard({ contact, siteName, can, onEdit, onDelete }: { contact: SiteContact; siteName: string | null; can: Props['can']; onEdit: () => void; onDelete: () => void }) {
    return (
        <div className={cn('card-surface p-4', contact.is_emergency && 'border-red-500/40')}>
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <p className="truncate text-sm font-semibold text-foreground">{contact.name}</p>
                        <Pill color={contact.category_color} label={contact.category_label} />
                    </div>
                    {(contact.title || contact.company) && <p className="mt-0.5 text-xs text-muted-foreground">{[contact.title, contact.company].filter(Boolean).join(' · ')}</p>}
                </div>
                {can.manage && (
                    <div className="flex shrink-0 items-center gap-1">
                        <button onClick={onEdit} className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground"><Pencil className="h-3.5 w-3.5" /></button>
                        <button onClick={onDelete} className="rounded-md p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"><Trash2 className="h-3.5 w-3.5" /></button>
                    </div>
                )}
            </div>
            <div className="mt-2 space-y-1">
                {contact.phone && <a href={`tel:${contact.phone}`} className="flex items-center gap-2 text-xs text-primary hover:underline"><Phone className="h-3 w-3" /> {contact.phone}</a>}
                {contact.mobile && <a href={`tel:${contact.mobile}`} className="flex items-center gap-2 text-xs text-primary hover:underline"><Smartphone className="h-3 w-3" /> {contact.mobile}</a>}
                {contact.email && <a href={`mailto:${contact.email}`} className="flex items-center gap-2 text-xs text-primary hover:underline"><Mail className="h-3 w-3" /> {contact.email}</a>}
            </div>
            {(contact.availability || contact.preferred_contact_method || siteName) && (
                <p className="mt-2 text-xs text-muted-foreground">
                    {contact.preferred_contact_method && <span>Prefers {contact.preferred_contact_method}</span>}
                    {contact.preferred_contact_method && (contact.availability || siteName) ? ' · ' : ''}
                    {contact.availability}
                    {contact.availability && siteName ? ' · ' : ''}
                    {siteName && <span className="inline-flex items-center gap-1"><MapPin className="h-3 w-3" />{siteName}</span>}
                </p>
            )}
            {contact.notes && <p className="mt-2 whitespace-pre-line border-t border-border pt-2 text-xs text-muted-foreground">{contact.notes}</p>}
        </div>
    );
}

function SiteModal({ base, site, onClose }: { base: string; site: ProjectSite | null; onClose: () => void }) {
    const isEdit = !!site;
    const form = useForm({
        name: site?.name ?? '',
        address: site?.address ?? '',
        latitude: site?.latitude != null ? String(site.latitude) : '',
        longitude: site?.longitude != null ? String(site.longitude) : '',
        maps_url: site?.maps_url ?? '',
        access_instructions: site?.access_instructions ?? '',
        loading_dock: site?.loading_dock ?? '',
        parking: site?.parking ?? '',
        working_hours: site?.working_hours ?? '',
        gate_hours: site?.gate_hours ?? '',
        security_requirements: site?.security_requirements ?? '',
        badge_required: triInit(site?.badge_required),
        escort_required: triInit(site?.escort_required),
        ppe_required: site?.ppe_required ?? '',
        forklift_available: triInit(site?.forklift_available),
        crane_available: triInit(site?.crane_available),
        internet_available: triInit(site?.internet_available),
        power_available: triInit(site?.power_available),
        water_available: triInit(site?.water_available),
        compressed_air_available: triInit(site?.compressed_air_available),
        utilities_notes: site?.utilities_notes ?? '',
        environmental_conditions: site?.environmental_conditions ?? '',
        hazards: site?.hazards ?? '',
        lockout_tagout: site?.lockout_tagout ?? '',
        high_voltage: triInit(site?.high_voltage),
        confined_space: triInit(site?.confined_space),
        fall_protection: triInit(site?.fall_protection),
        chemical_hazards: site?.chemical_hazards ?? '',
        emergency_assembly_point: site?.emergency_assembly_point ?? '',
        nearest_hospital: site?.nearest_hospital ?? '',
        hospital_phone: site?.hospital_phone ?? '',
        police_phone: site?.police_phone ?? '',
        fire_phone: site?.fire_phone ?? '',
        site_safety_contact: site?.site_safety_contact ?? '',
        notes: site?.notes ?? '',
    });
    type SiteForm = typeof form.data;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform(d => {
            const out: Record<string, unknown> = { ...d, latitude: d.latitude || null, longitude: d.longitude || null };
            SITE_BOOLS.forEach(k => { out[k] = triParse(d[k as keyof SiteForm] as string); });
            return out;
        });
        const opts = { preserveScroll: true, onSuccess: () => { form.reset(); onClose(); } };
        if (isEdit) form.put(`${base}/sites/${site!.id}`, opts); else form.post(`${base}/sites`, opts);
    };

    const tri = (k: keyof SiteForm, label: string) => (
        <div>
            <label className="label">{label}</label>
            <Select className="w-full" value={form.data[k] as string} onChange={v => form.setData(k, v as SiteForm[typeof k])}
                options={[{ value: '', label: 'Unknown' }, { value: '1', label: 'Yes' }, { value: '0', label: 'No' }]} />
        </div>
    );
    const text = (k: keyof SiteForm, label: string, placeholder = 'optional') => (
        <div>
            <label className="label">{label}</label>
            <input className="input" value={form.data[k] as string} onChange={e => form.setData(k, e.target.value as SiteForm[typeof k])} placeholder={placeholder} />
        </div>
    );
    const area = (k: keyof SiteForm, label: string, placeholder = '') => (
        <div>
            <label className="label">{label}</label>
            <textarea className="input min-h-[48px]" value={form.data[k] as string} onChange={e => form.setData(k, e.target.value as SiteForm[typeof k])} placeholder={placeholder} />
        </div>
    );

    return (
        <Modal open onClose={onClose} title={isEdit ? 'Edit Site' : 'Add Site'}
            description="Everything the field team needs before they leave — access, security, utilities, hazards and emergency info."
            footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button onClick={submit as unknown as () => void} disabled={form.processing}>{form.processing ? 'Saving…' : isEdit ? 'Save Site' : 'Add Site'}</Button></>}>
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <label className="label">Site name *</label>
                    <input className="input" value={form.data.name} onChange={e => form.setData('name', e.target.value)} autoFocus placeholder="e.g. Main Plant — Building C" />
                    {form.errors.name && <p className="mt-1 text-xs text-destructive">{form.errors.name}</p>}
                </div>
                {area('address', 'Address', 'Street, city, state, ZIP…')}
                <div className="grid grid-cols-3 gap-3">
                    {text('latitude', 'Latitude')}
                    {text('longitude', 'Longitude')}
                    {text('maps_url', 'Maps link', 'https://…')}
                </div>
                {form.errors.maps_url && <p className="-mt-2 text-xs text-destructive">{form.errors.maps_url}</p>}

                <div className="border-t border-border pt-3">
                    <p className="mb-3 text-xs font-bold uppercase tracking-wider text-muted-foreground/70">Access & hours</p>
                    <div className="space-y-4">
                        {area('access_instructions', 'Access instructions', 'Gate codes, dock door, check-in desk…')}
                        <div className="grid grid-cols-2 gap-3">
                            {text('loading_dock', 'Loading dock')}
                            {text('parking', 'Parking')}
                            {text('working_hours', 'Working hours')}
                            {text('gate_hours', 'Gate hours')}
                        </div>
                    </div>
                </div>

                <div className="border-t border-border pt-3">
                    <p className="mb-3 text-xs font-bold uppercase tracking-wider text-muted-foreground/70">Security & PPE</p>
                    <div className="space-y-4">
                        <div className="grid grid-cols-3 gap-3">
                            {tri('badge_required', 'Badge required')}
                            {tri('escort_required', 'Escort required')}
                            {text('ppe_required', 'PPE required')}
                        </div>
                        {area('security_requirements', 'Security requirements', 'ID checks, clearances, sign-in…')}
                    </div>
                </div>

                <div className="border-t border-border pt-3">
                    <p className="mb-3 text-xs font-bold uppercase tracking-wider text-muted-foreground/70">Utilities & site resources</p>
                    <div className="grid grid-cols-3 gap-3">
                        {tri('forklift_available', 'Forklift')}
                        {tri('crane_available', 'Crane')}
                        {tri('power_available', 'Power')}
                        {tri('internet_available', 'Internet')}
                        {tri('water_available', 'Water')}
                        {tri('compressed_air_available', 'Compressed air')}
                    </div>
                    <div className="mt-4 space-y-4">
                        {area('utilities_notes', 'Utilities notes', 'Voltage, amperage, connection points…')}
                        {area('environmental_conditions', 'Environmental conditions', 'Temperature, dust, cleanroom, outdoor…')}
                    </div>
                </div>

                <div className="border-t border-border pt-3">
                    <p className="mb-3 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-muted-foreground/70"><ShieldAlert className="h-3.5 w-3.5" /> Safety</p>
                    <div className="space-y-4">
                        {area('hazards', 'Hazards', 'Known site hazards…')}
                        <div className="grid grid-cols-3 gap-3">
                            {tri('high_voltage', 'High voltage')}
                            {tri('confined_space', 'Confined space')}
                            {tri('fall_protection', 'Fall protection')}
                        </div>
                        {area('lockout_tagout', 'Lockout / tagout')}
                        {area('chemical_hazards', 'Chemical hazards')}
                        {text('emergency_assembly_point', 'Emergency assembly point')}
                    </div>
                </div>

                <div className="border-t border-border pt-3">
                    <p className="mb-3 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-muted-foreground/70"><Hospital className="h-3.5 w-3.5" /> Emergency services</p>
                    <div className="space-y-4">
                        {text('nearest_hospital', 'Nearest hospital', 'Name & address')}
                        <div className="grid grid-cols-3 gap-3">
                            {text('hospital_phone', 'Hospital phone')}
                            {text('police_phone', 'Police')}
                            {text('fire_phone', 'Fire')}
                        </div>
                        {text('site_safety_contact', 'Site safety contact', 'Name & phone')}
                    </div>
                </div>

                {area('notes', 'Notes')}
            </form>
        </Modal>
    );
}

function ContactModal({ base, contact, categories, sites, onClose }: { base: string; contact: SiteContact | null; categories: Option[]; sites: ProjectSite[]; onClose: () => void }) {
    const isEdit = !!contact;
    const form = useForm({
        category: contact?.category ?? 'procurement',
        name: contact?.name ?? '',
        title: contact?.title ?? '',
        company: contact?.company ?? '',
        phone: contact?.phone ?? '',
        mobile: contact?.mobile ?? '',
        email: contact?.email ?? '',
        preferred_contact_method: contact?.preferred_contact_method ?? '',
        availability: contact?.availability ?? '',
        is_emergency: contact?.is_emergency ?? false,
        crm_project_site_id: contact?.crm_project_site_id ? String(contact.crm_project_site_id) : '',
        notes: contact?.notes ?? '',
    });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform(d => ({ ...d, crm_project_site_id: d.crm_project_site_id || null, preferred_contact_method: d.preferred_contact_method || null }));
        const opts = { preserveScroll: true, onSuccess: () => { form.reset(); onClose(); } };
        if (isEdit) form.put(`${base}/contacts/${contact!.id}`, opts); else form.post(`${base}/contacts`, opts);
    };
    return (
        <Modal open onClose={onClose} title={isEdit ? 'Edit Contact' : 'Add Contact'}
            footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button onClick={submit as unknown as () => void} disabled={form.processing}>{form.processing ? 'Saving…' : isEdit ? 'Save' : 'Add Contact'}</Button></>}>
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-2 gap-3">
                    <div><label className="label">Role</label><Select className="w-full" value={form.data.category} onChange={v => form.setData('category', v)} searchable searchPlaceholder="Search roles…" options={categories.map(c => ({ value: c.value, label: c.label }))} /></div>
                    <div><label className="label">Name *</label><input className="input" value={form.data.name} onChange={e => form.setData('name', e.target.value)} autoFocus />{form.errors.name && <p className="mt-1 text-xs text-destructive">{form.errors.name}</p>}</div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div><label className="label">Title</label><input className="input" value={form.data.title} onChange={e => form.setData('title', e.target.value)} placeholder="optional" /></div>
                    <div><label className="label">Company</label><input className="input" value={form.data.company} onChange={e => form.setData('company', e.target.value)} placeholder="optional" /></div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div><label className="label">Phone</label><input className="input" value={form.data.phone} onChange={e => form.setData('phone', e.target.value)} placeholder="optional" /></div>
                    <div><label className="label">Mobile</label><input className="input" value={form.data.mobile} onChange={e => form.setData('mobile', e.target.value)} placeholder="optional" /></div>
                </div>
                <div><label className="label">Email</label><input className="input" value={form.data.email} onChange={e => form.setData('email', e.target.value)} placeholder="optional" />{form.errors.email && <p className="mt-1 text-xs text-destructive">{form.errors.email}</p>}</div>
                <div className="grid grid-cols-2 gap-3">
                    <div><label className="label">Preferred contact</label><Select className="w-full" value={form.data.preferred_contact_method} onChange={v => form.setData('preferred_contact_method', v)} placeholder="— Any —" options={[{ value: 'phone', label: 'Phone' }, { value: 'mobile', label: 'Mobile' }, { value: 'email', label: 'Email' }, { value: 'any', label: 'Any' }]} /></div>
                    <div><label className="label">Availability</label><input className="input" value={form.data.availability} onChange={e => form.setData('availability', e.target.value)} placeholder="e.g. Mon–Fri 8–5" /></div>
                </div>
                {sites.length > 0 && (
                    <div><label className="label">Site</label><Select className="w-full" value={form.data.crm_project_site_id} onChange={v => form.setData('crm_project_site_id', v)} placeholder="— Not site-specific —" options={sites.map(s => ({ value: String(s.id), label: s.name }))} /></div>
                )}
                <label className="flex items-center gap-2 text-sm text-foreground">
                    <input type="checkbox" className="h-4 w-4 rounded border-input text-primary focus:ring-primary/50" checked={form.data.is_emergency} onChange={e => form.setData('is_emergency', e.target.checked)} />
                    Emergency contact
                </label>
                <div><label className="label">Notes</label><textarea className="input min-h-[48px]" value={form.data.notes ?? ''} onChange={e => form.setData('notes', e.target.value)} placeholder="optional" /></div>
            </form>
        </Modal>
    );
}

/* ── Equipment & Shipments ──────────────────────────────────────────────── */
const directionLabel = (d: string): string => ({ inbound: 'Inbound', outbound: 'Outbound', internal: 'Internal' }[d] ?? d);

function IndicatorPill({ label, value }: { label: string; value: string | null }) {
    if (!value) return null;
    if (value === 'tripped') return <span className="inline-flex items-center gap-1 rounded-full bg-red-500/10 px-2 py-0.5 text-xs font-medium text-red-700 dark:text-red-400"><AlertTriangle className="h-3 w-3" /> {label}: tripped</span>;
    if (value === 'intact') return <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-400"><ShieldCheck className="h-3 w-3" /> {label}: intact</span>;
    return <span className="inline-flex items-center gap-1 rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-muted-foreground">{label}: none</span>;
}

function EquipmentShipmentsTab({ equipment, shipments, shipmentStatuses, carriers, linkableAssets, base, can }: Props & { base: string }) {
    const [eqModal, setEqModal] = useState<Equipment | null | 'new'>(null);
    const [delEq, setDelEq] = useState<Equipment | null>(null);
    const [shModal, setShModal] = useState<Shipment | null | 'new'>(null);
    const [delSh, setDelSh] = useState<Shipment | null>(null);

    const shipmentLabel = (id: number | null): string | null => {
        if (!id) return null;
        const s = shipments.find(x => x.id === id);
        if (!s) return null;
        return s.crate_number ? `Crate ${s.crate_number}` : (s.tracking_number || s.carrier_label || `Shipment #${s.id}`);
    };
    const eqCount = (id: number) => equipment.filter(e => e.crm_project_shipment_id === id).length;

    return (
        <div className="space-y-8">
            <section>
                <div className="mb-3 flex items-center justify-between">
                    <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70"><Boxes className="h-4 w-4" /> Equipment ({equipment.length})</h2>
                    {can.manage && <Button size="sm" icon={Plus} onClick={() => setEqModal('new')}>Add Equipment</Button>}
                </div>
                {equipment.length === 0 ? (
                    <div className="card-surface p-8 text-center text-sm text-muted-foreground">
                        No equipment listed yet — add the units being installed with their serials, rigging data, calibration and warranty.
                    </div>
                ) : (
                    <div className="space-y-4">
                        {equipment.map(e => (
                            <EquipmentCard key={e.id} item={e} shipmentName={shipmentLabel(e.crm_project_shipment_id)} can={can}
                                onEdit={() => setEqModal(e)} onDelete={() => setDelEq(e)} />
                        ))}
                    </div>
                )}
            </section>

            <section>
                <div className="mb-3 flex items-center justify-between">
                    <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70"><Truck className="h-4 w-4" /> Shipments ({shipments.length})</h2>
                    {can.manage && <Button size="sm" icon={Plus} onClick={() => setShModal('new')}>Add Shipment</Button>}
                </div>
                {shipments.length === 0 ? (
                    <div className="card-surface p-8 text-center text-sm text-muted-foreground">
                        No shipments tracked yet — add the crates and freight moving equipment to site (carrier, tracking, BOL, weights, shock/tilt indicators).
                    </div>
                ) : (
                    <div className="space-y-4">
                        {shipments.map(s => (
                            <ShipmentCard key={s.id} shipment={s} itemCount={eqCount(s.id)} can={can}
                                onEdit={() => setShModal(s)} onDelete={() => setDelSh(s)} />
                        ))}
                    </div>
                )}
            </section>

            {eqModal && <EquipmentModal base={base} item={eqModal === 'new' ? null : eqModal} shipments={shipments} assets={linkableAssets} onClose={() => setEqModal(null)} />}
            {shModal && <ShipmentModal base={base} shipment={shModal === 'new' ? null : shModal} carriers={carriers} statuses={shipmentStatuses} onClose={() => setShModal(null)} />}
            <ConfirmDialog open={!!delEq} onClose={() => setDelEq(null)} onConfirm={() => { if (delEq) router.delete(`${base}/equipment/${delEq.id}`, { preserveScroll: true, onFinish: () => setDelEq(null) }); }} title="Remove equipment?" message={delEq ? <>Remove <span className="font-medium text-foreground">{delEq.name}</span>?</> : ''} />
            <ConfirmDialog open={!!delSh} onClose={() => setDelSh(null)} onConfirm={() => { if (delSh) router.delete(`${base}/shipments/${delSh.id}`, { preserveScroll: true, onFinish: () => setDelSh(null) }); }} title="Remove shipment?" message={delSh ? <>Remove this shipment{delSh.tracking_number ? <> (<span className="font-mono">{delSh.tracking_number}</span>)</> : ''}?</> : ''} />
        </div>
    );
}

function EquipmentCard({ item, shipmentName, can, onEdit, onDelete }: { item: Equipment; shipmentName: string | null; can: Props['can']; onEdit: () => void; onDelete: () => void }) {
    const hasSpecs = item.product || item.model || item.revision || item.serial_number || item.firmware || item.software_version || item.asset_tag
        || item.power || item.voltage || item.weight || item.dimensions || item.center_of_gravity || item.lift_points || item.installation_location;
    return (
        <div className="card-surface p-5">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <Boxes className="h-4 w-4 shrink-0 text-primary" />
                        <h3 className="text-base font-semibold text-foreground">{item.name}</h3>
                        {item.quantity > 1 && <span className="rounded-full bg-secondary px-2 py-0.5 text-xs font-semibold text-muted-foreground">×{item.quantity}</span>}
                    </div>
                    {(item.model || item.serial_number) && (
                        <p className="mt-0.5 text-xs text-muted-foreground">
                            {item.model && <span>{item.model}</span>}
                            {item.model && item.serial_number ? ' · ' : ''}
                            {item.serial_number && <span className="font-mono">S/N {item.serial_number}</span>}
                        </p>
                    )}
                </div>
                {can.manage && (
                    <div className="flex shrink-0 items-center gap-1">
                        <button onClick={onEdit} className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground"><Pencil className="h-3.5 w-3.5" /></button>
                        <button onClick={onDelete} className="rounded-md p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"><Trash2 className="h-3.5 w-3.5" /></button>
                    </div>
                )}
            </div>

            {hasSpecs && (
                <dl className="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 border-t border-border pt-3 sm:grid-cols-3">
                    <Detail label="Product" value={item.product} />
                    <Detail label="Revision" value={item.revision} />
                    <Detail label="Asset tag" value={item.asset_tag} />
                    <Detail label="Firmware" value={item.firmware} />
                    <Detail label="Software" value={item.software_version} />
                    <Detail label="Installation location" value={item.installation_location} />
                    <Detail label="Power" value={item.power} />
                    <Detail label="Voltage" value={item.voltage} />
                    <Detail label="Weight" value={item.weight} />
                    <Detail label="Dimensions" value={item.dimensions} />
                    <Detail label="Center of gravity" value={item.center_of_gravity} />
                    <Detail label="Lift points" value={item.lift_points} />
                </dl>
            )}

            {item.rigging_instructions && (
                <div className="mt-3">
                    <h4 className="mb-1 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-muted-foreground/70"><Weight className="h-3.5 w-3.5" /> Rigging instructions</h4>
                    <p className="whitespace-pre-line text-sm text-foreground">{item.rigging_instructions}</p>
                </div>
            )}

            {(item.calibration_status || item.calibration_due || item.warranty_status || item.warranty_expires || shipmentName || item.asset) && (
                <div className="mt-3 flex flex-wrap gap-1.5 border-t border-border pt-3">
                    {(item.calibration_status || item.calibration_due) && (
                        <span className="inline-flex items-center gap-1 rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-foreground"><ShieldCheck className="h-3 w-3 text-muted-foreground" /> Cal: {item.calibration_status ?? '—'}{item.calibration_due ? ` · due ${formatDate(item.calibration_due)}` : ''}</span>
                    )}
                    {(item.warranty_status || item.warranty_expires) && (
                        <span className="inline-flex items-center gap-1 rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-foreground"><CalendarClock className="h-3 w-3 text-muted-foreground" /> Warranty: {item.warranty_status ?? '—'}{item.warranty_expires ? ` · to ${formatDate(item.warranty_expires)}` : ''}</span>
                    )}
                    {shipmentName && <span className="inline-flex items-center gap-1 rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-foreground"><Truck className="h-3 w-3 text-muted-foreground" /> {shipmentName}</span>}
                    {item.asset && <span className="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary"><QrCode className="h-3 w-3" /> {item.asset}</span>}
                </div>
            )}
            {item.notes && <p className="mt-3 whitespace-pre-line border-t border-border pt-3 text-sm text-muted-foreground">{item.notes}</p>}
        </div>
    );
}

function ShipmentCard({ shipment: s, itemCount, can, onEdit, onDelete }: { shipment: Shipment; itemCount: number; can: Props['can']; onEdit: () => void; onDelete: () => void }) {
    const hasSchedule = s.shipped_date || s.expected_arrival || s.arrived_date;
    const hasPackaging = s.crate_number || s.package_count != null || s.pallet_info || s.weight || s.gross_weight || s.net_weight || s.shipping_weight || s.dimensions || s.bill_of_lading || s.packing_list;
    return (
        <div className="card-surface p-5">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <Truck className="h-4 w-4 shrink-0 text-primary" />
                        <h3 className="text-base font-semibold text-foreground">{s.carrier_label ?? 'Shipment'}</h3>
                        {s.status_label && <Pill color={s.status_color ?? 'gray'} label={s.status_label} />}
                        <span className="rounded-full bg-secondary px-2 py-0.5 text-[11px] font-medium text-muted-foreground">{directionLabel(s.direction)}</span>
                        {itemCount > 0 && <span className="rounded-full bg-secondary px-2 py-0.5 text-[11px] font-medium text-muted-foreground">{itemCount} item{itemCount === 1 ? '' : 's'}</span>}
                    </div>
                    {s.tracking_number && (
                        <p className="mt-0.5 flex items-center gap-1.5 text-xs text-muted-foreground">
                            <span className="font-mono">{s.tracking_number}</span>
                            {s.tracking_url && <a href={s.tracking_url} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-0.5 font-medium text-primary hover:underline">Track <ExternalLink className="h-3 w-3" /></a>}
                        </p>
                    )}
                </div>
                {can.manage && (
                    <div className="flex shrink-0 items-center gap-1">
                        <button onClick={onEdit} className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground"><Pencil className="h-3.5 w-3.5" /></button>
                        <button onClick={onDelete} className="rounded-md p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"><Trash2 className="h-3.5 w-3.5" /></button>
                    </div>
                )}
            </div>

            {(s.shock_indicator || s.tilt_indicator) && (
                <div className="mt-3 flex flex-wrap gap-1.5">
                    <IndicatorPill label="Shock" value={s.shock_indicator} />
                    <IndicatorPill label="Tilt" value={s.tilt_indicator} />
                </div>
            )}

            {(hasSchedule || hasPackaging || s.service) && (
                <dl className="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 border-t border-border pt-3 sm:grid-cols-3">
                    <Detail label="Service" value={s.service} />
                    <Detail label="Shipped" value={s.shipped_date ? formatDate(s.shipped_date) : null} />
                    <Detail label="Expected" value={s.expected_arrival ? formatDate(s.expected_arrival) : null} />
                    <Detail label="Arrived" value={s.arrived_date ? formatDate(s.arrived_date) : null} />
                    <Detail label="Crate #" value={s.crate_number} />
                    <Detail label="Packages" value={s.package_count != null ? String(s.package_count) : null} />
                    <Detail label="Pallet" value={s.pallet_info} />
                    <Detail label="Weight" value={s.weight} />
                    <Detail label="Gross weight" value={s.gross_weight} />
                    <Detail label="Net weight" value={s.net_weight} />
                    <Detail label="Shipping weight" value={s.shipping_weight} />
                    <Detail label="Dimensions" value={s.dimensions} />
                    <Detail label="Bill of lading" value={s.bill_of_lading} />
                    <Detail label="Packing list" value={s.packing_list} />
                </dl>
            )}

            {(s.forklift_instructions || s.lift_points) && (
                <div className="mt-3 space-y-2 border-t border-border pt-3">
                    {s.forklift_instructions && <div><h4 className="mb-1 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-muted-foreground/70"><Forklift className="h-3.5 w-3.5" /> Forklift instructions</h4><p className="whitespace-pre-line text-sm text-foreground">{s.forklift_instructions}</p></div>}
                    {s.lift_points && <div><h4 className="mb-1 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-muted-foreground/70"><Weight className="h-3.5 w-3.5" /> Lift points</h4><p className="whitespace-pre-line text-sm text-foreground">{s.lift_points}</p></div>}
                </div>
            )}
            {s.notes && <p className="mt-3 whitespace-pre-line border-t border-border pt-3 text-sm text-muted-foreground">{s.notes}</p>}
        </div>
    );
}

function EquipmentModal({ base, item, shipments, assets, onClose }: { base: string; item: Equipment | null; shipments: Shipment[]; assets: Array<{ value: string; label: string }>; onClose: () => void }) {
    const isEdit = !!item;
    const form = useForm({
        name: item?.name ?? '',
        product: item?.product ?? '',
        model: item?.model ?? '',
        revision: item?.revision ?? '',
        quantity: String(item?.quantity ?? 1),
        serial_number: item?.serial_number ?? '',
        firmware: item?.firmware ?? '',
        software_version: item?.software_version ?? '',
        asset_tag: item?.asset_tag ?? '',
        power: item?.power ?? '',
        voltage: item?.voltage ?? '',
        weight: item?.weight ?? '',
        dimensions: item?.dimensions ?? '',
        center_of_gravity: item?.center_of_gravity ?? '',
        lift_points: item?.lift_points ?? '',
        rigging_instructions: item?.rigging_instructions ?? '',
        installation_location: item?.installation_location ?? '',
        calibration_status: item?.calibration_status ?? '',
        calibration_due: item?.calibration_due ?? '',
        warranty_status: item?.warranty_status ?? '',
        warranty_expires: item?.warranty_expires ?? '',
        crm_project_shipment_id: item?.crm_project_shipment_id ? String(item.crm_project_shipment_id) : '',
        asset_id: item?.asset_id ? String(item.asset_id) : '',
        notes: item?.notes ?? '',
    });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform(d => ({
            ...d,
            quantity: d.quantity ? Number(d.quantity) : 1,
            crm_project_shipment_id: d.crm_project_shipment_id || null,
            asset_id: d.asset_id || null,
            calibration_due: d.calibration_due || null,
            warranty_expires: d.warranty_expires || null,
        }));
        const opts = { preserveScroll: true, onSuccess: () => { form.reset(); onClose(); } };
        if (isEdit) form.put(`${base}/equipment/${item!.id}`, opts); else form.post(`${base}/equipment`, opts);
    };
    const shipmentOpt = (s: Shipment) => s.crate_number ? `Crate ${s.crate_number}` : (s.tracking_number || s.carrier_label || `Shipment #${s.id}`);
    return (
        <Modal open onClose={onClose} title={isEdit ? 'Edit Equipment' : 'Add Equipment'}
            description="Identity, rigging data, calibration & warranty for a unit being installed."
            footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button onClick={submit as unknown as () => void} disabled={form.processing}>{form.processing ? 'Saving…' : isEdit ? 'Save' : 'Add Equipment'}</Button></>}>
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-[1fr_5rem] gap-3">
                    <div><label className="label">Name *</label><input className="input" value={form.data.name} onChange={e => form.setData('name', e.target.value)} autoFocus placeholder="e.g. Triaxial Seismometer" />{form.errors.name && <p className="mt-1 text-xs text-destructive">{form.errors.name}</p>}</div>
                    <div><label className="label">Qty</label><NumberInput className="input" value={form.data.quantity} onChange={e => form.setData('quantity', e.target.value)} /></div>
                </div>
                <div className="grid grid-cols-3 gap-3">
                    <div><label className="label">Product</label><input className="input" value={form.data.product} onChange={e => form.setData('product', e.target.value)} placeholder="optional" /></div>
                    <div><label className="label">Model</label><input className="input" value={form.data.model} onChange={e => form.setData('model', e.target.value)} placeholder="optional" /></div>
                    <div><label className="label">Revision</label><input className="input" value={form.data.revision} onChange={e => form.setData('revision', e.target.value)} placeholder="optional" /></div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div><label className="label">Serial number</label><input className="input" value={form.data.serial_number} onChange={e => form.setData('serial_number', e.target.value)} placeholder="optional" /></div>
                    <div><label className="label">Asset tag</label><input className="input" value={form.data.asset_tag} onChange={e => form.setData('asset_tag', e.target.value)} placeholder="optional" /></div>
                    <div><label className="label">Firmware</label><input className="input" value={form.data.firmware} onChange={e => form.setData('firmware', e.target.value)} placeholder="optional" /></div>
                    <div><label className="label">Software version</label><input className="input" value={form.data.software_version} onChange={e => form.setData('software_version', e.target.value)} placeholder="optional" /></div>
                </div>

                <div className="border-t border-border pt-3">
                    <p className="mb-3 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-muted-foreground/70"><Weight className="h-3.5 w-3.5" /> Physical & rigging</p>
                    <div className="space-y-4">
                        <div className="grid grid-cols-3 gap-3">
                            <div><label className="label">Power</label><input className="input" value={form.data.power} onChange={e => form.setData('power', e.target.value)} placeholder="e.g. 1.2 kW" /></div>
                            <div><label className="label">Voltage</label><input className="input" value={form.data.voltage} onChange={e => form.setData('voltage', e.target.value)} placeholder="e.g. 120V" /></div>
                            <div><label className="label">Weight</label><input className="input" value={form.data.weight} onChange={e => form.setData('weight', e.target.value)} placeholder="e.g. 240 lbs" /></div>
                            <div><label className="label">Dimensions</label><input className="input" value={form.data.dimensions} onChange={e => form.setData('dimensions', e.target.value)} placeholder="L×W×H" /></div>
                            <div><label className="label">Center of gravity</label><input className="input" value={form.data.center_of_gravity} onChange={e => form.setData('center_of_gravity', e.target.value)} placeholder="optional" /></div>
                            <div><label className="label">Lift points</label><input className="input" value={form.data.lift_points} onChange={e => form.setData('lift_points', e.target.value)} placeholder="optional" /></div>
                        </div>
                        <div><label className="label">Rigging instructions</label><textarea className="input min-h-[56px]" value={form.data.rigging_instructions} onChange={e => form.setData('rigging_instructions', e.target.value)} placeholder="How to lift, sling points, do-not-tilt…" /></div>
                    </div>
                </div>

                <div className="border-t border-border pt-3">
                    <p className="mb-3 text-xs font-bold uppercase tracking-wider text-muted-foreground/70">Placement & status</p>
                    <div className="space-y-4">
                        <div><label className="label">Installation location</label><input className="input" value={form.data.installation_location} onChange={e => form.setData('installation_location', e.target.value)} placeholder="Room / rack / pad…" /></div>
                        <div className="grid grid-cols-2 gap-3">
                            <div><label className="label">Calibration status</label><input className="input" value={form.data.calibration_status} onChange={e => form.setData('calibration_status', e.target.value)} placeholder="e.g. Calibrated" /></div>
                            <div><label className="label">Calibration due</label><input type="date" className="input" value={form.data.calibration_due} onChange={e => form.setData('calibration_due', e.target.value)} /></div>
                            <div><label className="label">Warranty status</label><input className="input" value={form.data.warranty_status} onChange={e => form.setData('warranty_status', e.target.value)} placeholder="e.g. In warranty" /></div>
                            <div><label className="label">Warranty expires</label><input type="date" className="input" value={form.data.warranty_expires} onChange={e => form.setData('warranty_expires', e.target.value)} /></div>
                        </div>
                    </div>
                </div>

                {(shipments.length > 0 || assets.length > 0) && (
                    <div className="border-t border-border pt-3">
                        <p className="mb-3 text-xs font-bold uppercase tracking-wider text-muted-foreground/70">Links</p>
                        <div className="grid grid-cols-2 gap-3">
                            {shipments.length > 0 && <div><label className="label">Shipment</label><Select className="w-full" value={form.data.crm_project_shipment_id} onChange={v => form.setData('crm_project_shipment_id', v)} placeholder="— Not assigned —" options={shipments.map(s => ({ value: String(s.id), label: shipmentOpt(s) }))} /></div>}
                            {assets.length > 0 && <div><label className="label">Linked asset</label><Select className="w-full" value={form.data.asset_id} onChange={v => form.setData('asset_id', v)} placeholder="— Not linked —" searchable searchPlaceholder="Search assets…" options={assets} /></div>}
                        </div>
                    </div>
                )}

                <div><label className="label">Notes</label><textarea className="input min-h-[48px]" value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} placeholder="optional" /></div>
            </form>
        </Modal>
    );
}

function ShipmentModal({ base, shipment, carriers, statuses, onClose }: { base: string; shipment: Shipment | null; carriers: Option[]; statuses: Option[]; onClose: () => void }) {
    const isEdit = !!shipment;
    const form = useForm({
        direction: shipment?.direction ?? 'inbound',
        carrier: shipment?.carrier ?? '',
        service: shipment?.service ?? '',
        tracking_number: shipment?.tracking_number ?? '',
        status: shipment?.status ?? 'preparing',
        shipped_date: shipment?.shipped_date ?? '',
        expected_arrival: shipment?.expected_arrival ?? '',
        arrived_date: shipment?.arrived_date ?? '',
        crate_number: shipment?.crate_number ?? '',
        package_count: shipment?.package_count != null ? String(shipment.package_count) : '',
        pallet_info: shipment?.pallet_info ?? '',
        weight: shipment?.weight ?? '',
        gross_weight: shipment?.gross_weight ?? '',
        net_weight: shipment?.net_weight ?? '',
        shipping_weight: shipment?.shipping_weight ?? '',
        dimensions: shipment?.dimensions ?? '',
        bill_of_lading: shipment?.bill_of_lading ?? '',
        packing_list: shipment?.packing_list ?? '',
        forklift_instructions: shipment?.forklift_instructions ?? '',
        lift_points: shipment?.lift_points ?? '',
        shock_indicator: shipment?.shock_indicator ?? '',
        tilt_indicator: shipment?.tilt_indicator ?? '',
        notes: shipment?.notes ?? '',
    });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform(d => ({
            ...d,
            carrier: d.carrier || null,
            package_count: d.package_count ? Number(d.package_count) : null,
            shipped_date: d.shipped_date || null,
            expected_arrival: d.expected_arrival || null,
            arrived_date: d.arrived_date || null,
            shock_indicator: d.shock_indicator || null,
            tilt_indicator: d.tilt_indicator || null,
        }));
        const opts = { preserveScroll: true, onSuccess: () => { form.reset(); onClose(); } };
        if (isEdit) form.put(`${base}/shipments/${shipment!.id}`, opts); else form.post(`${base}/shipments`, opts);
    };
    const indicatorOpts = [{ value: 'none', label: 'No indicator' }, { value: 'intact', label: 'Intact' }, { value: 'tripped', label: 'Tripped' }];
    return (
        <Modal open onClose={onClose} title={isEdit ? 'Edit Shipment' : 'Add Shipment'}
            description="Carrier, tracking, crate & weights, plus handling and shock/tilt indicators."
            footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button onClick={submit as unknown as () => void} disabled={form.processing}>{form.processing ? 'Saving…' : isEdit ? 'Save' : 'Add Shipment'}</Button></>}>
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-3 gap-3">
                    <div><label className="label">Direction</label><Select className="w-full" value={form.data.direction} onChange={v => form.setData('direction', v)} options={[{ value: 'inbound', label: 'Inbound' }, { value: 'outbound', label: 'Outbound' }, { value: 'internal', label: 'Internal' }]} /></div>
                    <div><label className="label">Carrier</label><Select className="w-full" value={form.data.carrier} onChange={v => form.setData('carrier', v)} placeholder="— Carrier —" options={carriers.map(c => ({ value: c.value, label: c.label }))} /></div>
                    <div><label className="label">Status</label><Select className="w-full" value={form.data.status} onChange={v => form.setData('status', v)} options={statuses.map(s => ({ value: s.value, label: s.label }))} /></div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div><label className="label">Tracking number</label><input className="input" value={form.data.tracking_number} onChange={e => form.setData('tracking_number', e.target.value)} placeholder="optional" /></div>
                    <div><label className="label">Service</label><input className="input" value={form.data.service} onChange={e => form.setData('service', e.target.value)} placeholder="e.g. Ground, Freight LTL" /></div>
                </div>

                <div className="border-t border-border pt-3">
                    <p className="mb-3 text-xs font-bold uppercase tracking-wider text-muted-foreground/70">Schedule</p>
                    <div className="grid grid-cols-3 gap-3">
                        <div><label className="label">Shipped</label><input type="date" className="input" value={form.data.shipped_date} onChange={e => form.setData('shipped_date', e.target.value)} /></div>
                        <div><label className="label">Expected arrival</label><input type="date" className="input" value={form.data.expected_arrival} onChange={e => form.setData('expected_arrival', e.target.value)} /></div>
                        <div><label className="label">Arrived</label><input type="date" className="input" value={form.data.arrived_date} onChange={e => form.setData('arrived_date', e.target.value)} /></div>
                    </div>
                </div>

                <div className="border-t border-border pt-3">
                    <p className="mb-3 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-muted-foreground/70"><Package className="h-3.5 w-3.5" /> Packaging & weights</p>
                    <div className="space-y-4">
                        <div className="grid grid-cols-3 gap-3">
                            <div><label className="label">Crate number</label><input className="input" value={form.data.crate_number} onChange={e => form.setData('crate_number', e.target.value)} placeholder="optional" /></div>
                            <div><label className="label">Package count</label><NumberInput className="input" value={form.data.package_count} onChange={e => form.setData('package_count', e.target.value)} placeholder="optional" /></div>
                            <div><label className="label">Pallet info</label><input className="input" value={form.data.pallet_info} onChange={e => form.setData('pallet_info', e.target.value)} placeholder="optional" /></div>
                            <div><label className="label">Weight</label><input className="input" value={form.data.weight} onChange={e => form.setData('weight', e.target.value)} placeholder="optional" /></div>
                            <div><label className="label">Gross weight</label><input className="input" value={form.data.gross_weight} onChange={e => form.setData('gross_weight', e.target.value)} placeholder="optional" /></div>
                            <div><label className="label">Net weight</label><input className="input" value={form.data.net_weight} onChange={e => form.setData('net_weight', e.target.value)} placeholder="optional" /></div>
                            <div><label className="label">Shipping weight</label><input className="input" value={form.data.shipping_weight} onChange={e => form.setData('shipping_weight', e.target.value)} placeholder="optional" /></div>
                            <div><label className="label">Dimensions</label><input className="input" value={form.data.dimensions} onChange={e => form.setData('dimensions', e.target.value)} placeholder="L×W×H" /></div>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div><label className="label">Bill of lading</label><input className="input" value={form.data.bill_of_lading} onChange={e => form.setData('bill_of_lading', e.target.value)} placeholder="BOL #" /></div>
                            <div><label className="label">Packing list</label><input className="input" value={form.data.packing_list} onChange={e => form.setData('packing_list', e.target.value)} placeholder="Reference / link" /></div>
                        </div>
                    </div>
                </div>

                <div className="border-t border-border pt-3">
                    <p className="mb-3 text-xs font-bold uppercase tracking-wider text-muted-foreground/70">Handling & indicators</p>
                    <div className="space-y-4">
                        <div className="grid grid-cols-2 gap-3">
                            <div><label className="label">Shock indicator</label><Select className="w-full" value={form.data.shock_indicator} onChange={v => form.setData('shock_indicator', v)} placeholder="— n/a —" options={indicatorOpts} /></div>
                            <div><label className="label">Tilt indicator</label><Select className="w-full" value={form.data.tilt_indicator} onChange={v => form.setData('tilt_indicator', v)} placeholder="— n/a —" options={indicatorOpts} /></div>
                        </div>
                        <div><label className="label">Forklift instructions</label><textarea className="input min-h-[48px]" value={form.data.forklift_instructions} onChange={e => form.setData('forklift_instructions', e.target.value)} placeholder="Fork length, pick points, capacity…" /></div>
                        <div><label className="label">Lift points</label><textarea className="input min-h-[48px]" value={form.data.lift_points} onChange={e => form.setData('lift_points', e.target.value)} placeholder="Sling / hook locations…" /></div>
                    </div>
                </div>

                <div><label className="label">Notes</label><textarea className="input min-h-[48px]" value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} placeholder="optional" /></div>
            </form>
        </Modal>
    );
}

/* ── Execution records & checklists ─────────────────────────────────────── */
function ExecutionTab({ executionRecords, executionTypes, executionStatuses, checklists, owners, sites, base, can }: Props & { base: string }) {
    const [recModal, setRecModal] = useState<ExecutionRecord | null | 'new'>(null);
    const [delRec, setDelRec] = useState<ExecutionRecord | null>(null);
    const [clModal, setClModal] = useState<Checklist | null | 'new'>(null);
    const [delCl, setDelCl] = useState<Checklist | null>(null);

    const siteName = (id: number | null) => sites.find(s => s.id === id)?.name ?? null;

    return (
        <div className="space-y-8">
            <section>
                <div className="mb-3 flex items-center justify-between">
                    <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70"><Wrench className="h-4 w-4" /> Records ({executionRecords.length})</h2>
                    {can.manage && <Button size="sm" icon={Plus} onClick={() => setRecModal('new')}>Add Record</Button>}
                </div>
                {executionRecords.length === 0 ? (
                    <div className="card-surface p-8 text-center text-sm text-muted-foreground">
                        No execution records yet — log installation, commissioning, training, warranty and inspection events here.
                    </div>
                ) : (
                    <div className="space-y-4">
                        {executionRecords.map(r => (
                            <ExecutionRecordCard key={r.id} record={r} siteName={siteName(r.crm_project_site_id)} can={can}
                                onEdit={() => setRecModal(r)} onDelete={() => setDelRec(r)} />
                        ))}
                    </div>
                )}
            </section>

            <section>
                <div className="mb-3 flex items-center justify-between">
                    <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70"><ListChecks className="h-4 w-4" /> Checklists ({checklists.length})</h2>
                    {can.manage && <Button size="sm" icon={Plus} onClick={() => setClModal('new')}>Add Checklist</Button>}
                </div>
                {checklists.length === 0 ? (
                    <div className="card-surface p-8 text-center text-sm text-muted-foreground">
                        No checklists yet — build pre-departure prep, required tools/spares, punch list or customer-request lists with tick-off.
                    </div>
                ) : (
                    <div className="space-y-4">
                        {checklists.map(c => (
                            <ChecklistCard key={c.id} checklist={c} base={base} can={can} onEdit={() => setClModal(c)} onDelete={() => setDelCl(c)} />
                        ))}
                    </div>
                )}
            </section>

            {recModal && <RecordModal base={base} record={recModal === 'new' ? null : recModal} types={executionTypes} statuses={executionStatuses} owners={owners} sites={sites} onClose={() => setRecModal(null)} />}
            {clModal && <ChecklistModal base={base} checklist={clModal === 'new' ? null : clModal} onClose={() => setClModal(null)} />}
            <ConfirmDialog open={!!delRec} onClose={() => setDelRec(null)} onConfirm={() => { if (delRec) router.delete(`${base}/execution-records/${delRec.id}`, { preserveScroll: true, onFinish: () => setDelRec(null) }); }} title="Remove record?" message={delRec ? <>Remove <span className="font-medium text-foreground">{delRec.title}</span>?</> : ''} />
            <ConfirmDialog open={!!delCl} onClose={() => setDelCl(null)} onConfirm={() => { if (delCl) router.delete(`${base}/checklists/${delCl.id}`, { preserveScroll: true, onFinish: () => setDelCl(null) }); }} title="Remove checklist?" message={delCl ? <>Remove <span className="font-medium text-foreground">{delCl.title}</span> and its items?</> : ''} />
        </div>
    );
}

function ExecutionRecordCard({ record: r, siteName, can, onEdit, onDelete }: { record: ExecutionRecord; siteName: string | null; can: Props['can']; onEdit: () => void; onDelete: () => void }) {
    return (
        <div className="card-surface p-5">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <Pill color={r.type_color} label={r.type_label} />
                        <h3 className="text-base font-semibold text-foreground">{r.title}</h3>
                        <Pill color={r.status_color} label={r.status_label} />
                        {r.customer_visible && <span className="rounded-full bg-secondary px-2 py-0.5 text-[11px] font-medium text-muted-foreground">Customer-visible</span>}
                    </div>
                    <p className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                        {r.scheduled_date && <span className="inline-flex items-center gap-1"><CalendarDays className="h-3.5 w-3.5" /> Scheduled {formatDate(r.scheduled_date)}</span>}
                        {r.completed_date && <span className="inline-flex items-center gap-1"><CheckSquare className="h-3.5 w-3.5" /> Completed {formatDate(r.completed_date)}</span>}
                        {r.performer && <span className="inline-flex items-center gap-1"><Users className="h-3.5 w-3.5" /> {r.performer}</span>}
                        {siteName && <span className="inline-flex items-center gap-1"><MapPin className="h-3.5 w-3.5" /> {siteName}</span>}
                    </p>
                </div>
                {can.manage && (
                    <div className="flex shrink-0 items-center gap-1">
                        <button onClick={onEdit} className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground"><Pencil className="h-3.5 w-3.5" /></button>
                        <button onClick={onDelete} className="rounded-md p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"><Trash2 className="h-3.5 w-3.5" /></button>
                    </div>
                )}
            </div>
            {(r.summary || r.outcome || r.notes) && (
                <div className="mt-3 space-y-3 border-t border-border pt-3">
                    {r.summary && <div><h4 className="mb-1 text-xs font-bold uppercase tracking-wider text-muted-foreground/70">Scope / instructions</h4><p className="whitespace-pre-line text-sm text-foreground">{r.summary}</p></div>}
                    {r.outcome && <div><h4 className="mb-1 text-xs font-bold uppercase tracking-wider text-muted-foreground/70">Outcome / lessons learned</h4><p className="whitespace-pre-line text-sm text-foreground">{r.outcome}</p></div>}
                    {r.notes && <p className="whitespace-pre-line text-sm text-muted-foreground">{r.notes}</p>}
                </div>
            )}
        </div>
    );
}

function ChecklistCard({ checklist: c, base, can, onEdit, onDelete }: { checklist: Checklist; base: string; can: Props['can']; onEdit: () => void; onDelete: () => void }) {
    const pct = c.total_count > 0 ? Math.round(c.done_count / c.total_count * 100) : 0;
    const toggle = (item: ChecklistItem) => can.manage && router.patch(`${base}/checklists/${c.id}/items/${item.id}`, { is_done: !item.is_done }, { preserveScroll: true });
    return (
        <div className="card-surface p-5">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <h3 className="text-base font-semibold text-foreground">{c.title}</h3>
                    {c.description && <p className="mt-0.5 text-xs text-muted-foreground">{c.description}</p>}
                </div>
                <div className="flex shrink-0 items-center gap-2">
                    <span className="text-xs font-medium text-muted-foreground">{c.done_count}/{c.total_count}</span>
                    {can.manage && (
                        <>
                            <button onClick={onEdit} className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground"><Pencil className="h-3.5 w-3.5" /></button>
                            <button onClick={onDelete} className="rounded-md p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"><Trash2 className="h-3.5 w-3.5" /></button>
                        </>
                    )}
                </div>
            </div>
            <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-secondary">
                <div className="h-full rounded-full bg-emerald-500 transition-all" style={{ width: `${pct}%` }} />
            </div>
            <ul className="mt-3 space-y-1.5">
                {c.items.map(item => (
                    <li key={item.id} className="group flex items-center gap-2.5">
                        <button onClick={() => toggle(item)} disabled={!can.manage} className={cn('shrink-0', can.manage ? 'cursor-pointer' : 'cursor-default')} title={item.is_done ? 'Mark not done' : 'Mark done'}>
                            {item.is_done ? <CheckSquare className="h-4 w-4 text-emerald-500" /> : <Square className="h-4 w-4 text-muted-foreground" />}
                        </button>
                        <span className={cn('flex-1 text-sm', item.is_done ? 'text-muted-foreground line-through' : 'text-foreground')}>{item.text}</span>
                        {item.is_done && item.done_by && <span className="hidden text-[11px] text-muted-foreground sm:inline">{item.done_by}{item.done_at ? ` · ${timeAgo(item.done_at)}` : ''}</span>}
                        {can.manage && <button onClick={() => router.delete(`${base}/checklists/${c.id}/items/${item.id}`, { preserveScroll: true })} className="rounded p-1 text-muted-foreground opacity-0 transition-opacity hover:bg-destructive/10 hover:text-destructive group-hover:opacity-100"><X className="h-3.5 w-3.5" /></button>}
                    </li>
                ))}
                {c.items.length === 0 && <li className="text-sm text-muted-foreground">No items yet.</li>}
            </ul>
            {can.manage && <AddItemRow base={base} checklistId={c.id} />}
        </div>
    );
}

function AddItemRow({ base, checklistId }: { base: string; checklistId: number }) {
    const form = useForm({ text: '' });
    const add = () => { if (!form.data.text.trim()) return; form.post(`${base}/checklists/${checklistId}/items`, { preserveScroll: true, onSuccess: () => form.reset() }); };
    return (
        <div className="mt-3 flex gap-2">
            <input className="input h-9 flex-1 text-sm" placeholder="Add an item…" value={form.data.text} onChange={e => form.setData('text', e.target.value)} onKeyDown={e => { if (e.key === 'Enter') { e.preventDefault(); add(); } }} />
            <Button size="sm" variant="secondary" onClick={add} disabled={form.processing || !form.data.text.trim()}>Add</Button>
        </div>
    );
}

function RecordModal({ base, record, types, statuses, owners, sites, onClose }: { base: string; record: ExecutionRecord | null; types: Option[]; statuses: Option[]; owners: Person[]; sites: ProjectSite[]; onClose: () => void }) {
    const isEdit = !!record;
    const form = useForm({
        type: record?.type ?? 'installation',
        title: record?.title ?? '',
        status: record?.status ?? 'scheduled',
        scheduled_date: record?.scheduled_date ?? '',
        completed_date: record?.completed_date ?? '',
        performed_by: record?.performed_by ? String(record.performed_by) : '',
        crm_project_site_id: record?.crm_project_site_id ? String(record.crm_project_site_id) : '',
        summary: record?.summary ?? '',
        outcome: record?.outcome ?? '',
        customer_visible: record?.customer_visible ?? false,
        notes: record?.notes ?? '',
    });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform(d => ({
            ...d,
            performed_by: d.performed_by || null,
            crm_project_site_id: d.crm_project_site_id || null,
            scheduled_date: d.scheduled_date || null,
            completed_date: d.completed_date || null,
        }));
        const opts = { preserveScroll: true, onSuccess: () => { form.reset(); onClose(); } };
        if (isEdit) form.put(`${base}/execution-records/${record!.id}`, opts); else form.post(`${base}/execution-records`, opts);
    };
    return (
        <Modal open onClose={onClose} title={isEdit ? 'Edit Record' : 'Add Record'}
            footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button onClick={submit as unknown as () => void} disabled={form.processing}>{form.processing ? 'Saving…' : isEdit ? 'Save' : 'Add Record'}</Button></>}>
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-2 gap-3">
                    <div><label className="label">Type</label><Select className="w-full" value={form.data.type} onChange={v => form.setData('type', v)} options={types.map(t => ({ value: t.value, label: t.label }))} /></div>
                    <div><label className="label">Status</label><Select className="w-full" value={form.data.status} onChange={v => form.setData('status', v)} options={statuses.map(s => ({ value: s.value, label: s.label }))} /></div>
                </div>
                <div><label className="label">Title *</label><input className="input" value={form.data.title} onChange={e => form.setData('title', e.target.value)} autoFocus placeholder="e.g. Factory acceptance test" />{form.errors.title && <p className="mt-1 text-xs text-destructive">{form.errors.title}</p>}</div>
                <div className="grid grid-cols-2 gap-3">
                    <div><label className="label">Scheduled date</label><input type="date" className="input" value={form.data.scheduled_date} onChange={e => form.setData('scheduled_date', e.target.value)} /></div>
                    <div><label className="label">Completed date</label><input type="date" className="input" value={form.data.completed_date} onChange={e => form.setData('completed_date', e.target.value)} /></div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div><label className="label">Performed by</label><Select className="w-full" value={form.data.performed_by} onChange={v => form.setData('performed_by', v)} placeholder="— Unassigned —" searchable searchPlaceholder="Search people…" options={owners.map(o => ({ value: String(o.id), label: o.name }))} /></div>
                    {sites.length > 0 && <div><label className="label">Site</label><Select className="w-full" value={form.data.crm_project_site_id} onChange={v => form.setData('crm_project_site_id', v)} placeholder="— Not site-specific —" options={sites.map(s => ({ value: String(s.id), label: s.name }))} /></div>}
                </div>
                <div><label className="label">Scope / instructions</label><textarea className="input min-h-[64px]" value={form.data.summary} onChange={e => form.setData('summary', e.target.value)} placeholder="What's planned, special instructions…" /></div>
                <div><label className="label">Outcome / lessons learned</label><textarea className="input min-h-[64px]" value={form.data.outcome} onChange={e => form.setData('outcome', e.target.value)} placeholder="Results, sign-off, lessons learned, completion notes…" /></div>
                <label className="flex items-center gap-2 text-sm text-foreground">
                    <input type="checkbox" className="h-4 w-4 rounded border-input text-primary focus:ring-primary/50" checked={form.data.customer_visible} onChange={e => form.setData('customer_visible', e.target.checked)} />
                    Customer-visible
                </label>
                <div><label className="label">Notes</label><textarea className="input min-h-[48px]" value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} placeholder="optional" /></div>
            </form>
        </Modal>
    );
}

function ChecklistModal({ base, checklist, onClose }: { base: string; checklist: Checklist | null; onClose: () => void }) {
    const isEdit = !!checklist;
    const form = useForm({ title: checklist?.title ?? '', description: checklist?.description ?? '' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => { form.reset(); onClose(); } };
        if (isEdit) form.put(`${base}/checklists/${checklist!.id}`, opts); else form.post(`${base}/checklists`, opts);
    };
    return (
        <Modal open onClose={onClose} title={isEdit ? 'Edit Checklist' : 'Add Checklist'}
            footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button onClick={submit as unknown as () => void} disabled={form.processing}>{form.processing ? 'Saving…' : isEdit ? 'Save' : 'Add Checklist'}</Button></>}>
            <form onSubmit={submit} className="space-y-4">
                <div><label className="label">Title *</label><input className="input" value={form.data.title} onChange={e => form.setData('title', e.target.value)} autoFocus placeholder="e.g. Pre-departure prep, Punch list" />{form.errors.title && <p className="mt-1 text-xs text-destructive">{form.errors.title}</p>}</div>
                <div><label className="label">Description</label><input className="input" value={form.data.description} onChange={e => form.setData('description', e.target.value)} placeholder="optional" /></div>
            </form>
        </Modal>
    );
}

/* ── Travel ─────────────────────────────────────────────────────────────── */
const TRAVEL_ICON: Record<string, LucideIcon> = {
    flight: Plane, lodging: Hotel, car_rental: Car, ground: Navigation, rail: TrainFront, per_diem: Wallet, parking: SquareParking, other: Receipt,
};
const TRAVEL_STATUS_COLOR: Record<string, string> = { planned: 'gray', booked: 'blue', completed: 'green', cancelled: 'red' };

const fmtDateTime = (iso: string | null): string => {
    if (!iso) return '';
    const d = formatDate(iso.slice(0, 10));
    const t = iso.slice(11, 16);
    return t && t !== '00:00' ? `${d} · ${t}` : d;
};
const fmtMoney = (cost: number | null, currency: string | null): string | null => {
    if (cost == null) return null;
    return currency && currency !== 'USD' ? `${currency} ${cost.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}` : formatCurrency(cost);
};

function TravelTab({ travel, travelTypes, owners, base, can }: Props & { base: string }) {
    const [modal, setModal] = useState<Travel | null | 'new'>(null);
    const [deleting, setDeleting] = useState<Travel | null>(null);

    const totals = travel.reduce<Record<string, number>>((acc, t) => {
        if (t.cost != null) { const c = t.currency || 'USD'; acc[c] = (acc[c] ?? 0) + t.cost; }
        return acc;
    }, {});

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70"><Plane className="h-4 w-4" /> Travel ({travel.length})</h2>
                <div className="flex items-center gap-2">
                    {Object.entries(totals).map(([cur, sum]) => (
                        <span key={cur} className="rounded-full bg-secondary px-2.5 py-1 text-xs font-medium text-foreground">{fmtMoney(sum, cur)}</span>
                    ))}
                    {can.manage && <Button size="sm" icon={Plus} onClick={() => setModal('new')}>Add Travel</Button>}
                </div>
            </div>
            <p className="text-xs text-muted-foreground">Nearest hospital, emergency numbers and site maps live on the <span className="font-medium text-foreground">Site &amp; Safety</span> tab.</p>

            {travel.length === 0 ? (
                <div className="card-surface p-8 text-center text-sm text-muted-foreground">
                    No travel booked yet — add flights, lodging, rental cars, ground transport and per-diem for the trip.
                </div>
            ) : (
                <div className="space-y-3">
                    {travel.map(t => <TravelCard key={t.id} travel={t} can={can} onEdit={() => setModal(t)} onDelete={() => setDeleting(t)} />)}
                </div>
            )}

            {modal && <TravelModal base={base} travel={modal === 'new' ? null : modal} types={travelTypes} owners={owners} onClose={() => setModal(null)} />}
            <ConfirmDialog open={!!deleting} onClose={() => setDeleting(null)} onConfirm={() => { if (deleting) router.delete(`${base}/travel/${deleting.id}`, { preserveScroll: true, onFinish: () => setDeleting(null) }); }} title="Remove travel?" message={deleting ? <>Remove <span className="font-medium text-foreground">{deleting.title}</span>?</> : ''} />
        </div>
    );
}

function TravelCard({ travel: t, can, onEdit, onDelete }: { travel: Travel; can: Props['can']; onEdit: () => void; onDelete: () => void }) {
    const Icon = TRAVEL_ICON[t.type] ?? Receipt;
    const money = fmtMoney(t.cost, t.currency);
    const schedule = [fmtDateTime(t.start_at), fmtDateTime(t.end_at)].filter(Boolean).join(' → ');
    const route = [t.from_location, t.to_location].filter(Boolean).join(' → ');
    return (
        <div className="card-surface p-4">
            <div className="flex items-start gap-3">
                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-secondary text-muted-foreground"><Icon className="h-4 w-4" /></span>
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <p className="truncate text-sm font-semibold text-foreground">{t.title}</p>
                        <Pill color={t.type_color} label={t.type_label} />
                        {t.status && <Pill color={TRAVEL_STATUS_COLOR[t.status] ?? 'gray'} label={t.status.charAt(0).toUpperCase() + t.status.slice(1)} />}
                    </div>
                    <p className="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-muted-foreground">
                        {t.traveler && <span className="inline-flex items-center gap-1"><Users className="h-3.5 w-3.5" />{t.traveler}</span>}
                        {schedule && <span className="inline-flex items-center gap-1"><CalendarDays className="h-3.5 w-3.5" />{schedule}</span>}
                        {route && <span className="inline-flex items-center gap-1"><Navigation className="h-3.5 w-3.5" />{route}</span>}
                    </p>
                    {(t.provider || t.confirmation_number) && (
                        <p className="mt-0.5 text-xs text-muted-foreground">{t.provider}{t.provider && t.confirmation_number ? ' · ' : ''}{t.confirmation_number && <span className="font-mono">{t.confirmation_number}</span>}</p>
                    )}
                    {t.notes && <p className="mt-1.5 whitespace-pre-line text-xs text-muted-foreground">{t.notes}</p>}
                    {t.booking_url && <a href={t.booking_url} target="_blank" rel="noopener noreferrer" className="mt-1 inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline">Booking <ExternalLink className="h-3 w-3" /></a>}
                </div>
                <div className="flex shrink-0 flex-col items-end gap-1">
                    {money && <span className="text-sm font-semibold text-foreground">{money}</span>}
                    {can.manage && (
                        <div className="flex items-center gap-1">
                            <button onClick={onEdit} className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground"><Pencil className="h-3.5 w-3.5" /></button>
                            <button onClick={onDelete} className="rounded-md p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"><Trash2 className="h-3.5 w-3.5" /></button>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

function TravelModal({ base, travel, types, owners, onClose }: { base: string; travel: Travel | null; types: Option[]; owners: Person[]; onClose: () => void }) {
    const isEdit = !!travel;
    const form = useForm({
        type: travel?.type ?? 'flight',
        title: travel?.title ?? '',
        status: travel?.status ?? '',
        traveler_id: travel?.traveler_id ? String(travel.traveler_id) : '',
        traveler_name: travel?.traveler_name ?? '',
        provider: travel?.provider ?? '',
        confirmation_number: travel?.confirmation_number ?? '',
        start_at: travel?.start_at ? travel.start_at.slice(0, 16) : '',
        end_at: travel?.end_at ? travel.end_at.slice(0, 16) : '',
        from_location: travel?.from_location ?? '',
        to_location: travel?.to_location ?? '',
        cost: travel?.cost != null ? String(travel.cost) : '',
        currency: travel?.currency ?? 'USD',
        booking_url: travel?.booking_url ?? '',
        notes: travel?.notes ?? '',
    });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform(d => ({
            ...d,
            status: d.status || null,
            traveler_id: d.traveler_id || null,
            start_at: d.start_at || null,
            end_at: d.end_at || null,
            cost: d.cost ? Number(d.cost) : null,
            currency: d.currency || null,
        }));
        const opts = { preserveScroll: true, onSuccess: () => { form.reset(); onClose(); } };
        if (isEdit) form.put(`${base}/travel/${travel!.id}`, opts); else form.post(`${base}/travel`, opts);
    };
    return (
        <Modal open onClose={onClose} title={isEdit ? 'Edit Travel' : 'Add Travel'}
            footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button onClick={submit as unknown as () => void} disabled={form.processing}>{form.processing ? 'Saving…' : isEdit ? 'Save' : 'Add Travel'}</Button></>}>
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-2 gap-3">
                    <div><label className="label">Type</label><Select className="w-full" value={form.data.type} onChange={v => form.setData('type', v)} options={types.map(t => ({ value: t.value, label: t.label }))} /></div>
                    <div><label className="label">Status</label><Select className="w-full" value={form.data.status} onChange={v => form.setData('status', v)} placeholder="— Status —" options={[{ value: 'planned', label: 'Planned' }, { value: 'booked', label: 'Booked' }, { value: 'completed', label: 'Completed' }, { value: 'cancelled', label: 'Cancelled' }]} /></div>
                </div>
                <div><label className="label">Title *</label><input className="input" value={form.data.title} onChange={e => form.setData('title', e.target.value)} autoFocus placeholder="e.g. United 1234 SFO→RNO, Hilton Reno" />{form.errors.title && <p className="mt-1 text-xs text-destructive">{form.errors.title}</p>}</div>
                <div className="grid grid-cols-2 gap-3">
                    <div><label className="label">Traveler</label><Select className="w-full" value={form.data.traveler_id} onChange={v => form.setData('traveler_id', v)} placeholder="— Team member —" searchable searchPlaceholder="Search people…" options={owners.map(o => ({ value: String(o.id), label: o.name }))} /></div>
                    <div><label className="label">or other traveler</label><input className="input" value={form.data.traveler_name} onChange={e => form.setData('traveler_name', e.target.value)} placeholder="Name (non-staff)" /></div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div><label className="label">Provider</label><input className="input" value={form.data.provider} onChange={e => form.setData('provider', e.target.value)} placeholder="Airline / hotel / rental co." /></div>
                    <div><label className="label">Confirmation #</label><input className="input" value={form.data.confirmation_number} onChange={e => form.setData('confirmation_number', e.target.value)} placeholder="optional" /></div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div><label className="label">Start</label><input type="datetime-local" className="input" value={form.data.start_at} onChange={e => form.setData('start_at', e.target.value)} /></div>
                    <div><label className="label">End</label><input type="datetime-local" className="input" value={form.data.end_at} onChange={e => form.setData('end_at', e.target.value)} /></div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div><label className="label">From</label><input className="input" value={form.data.from_location} onChange={e => form.setData('from_location', e.target.value)} placeholder="Origin / airport" /></div>
                    <div><label className="label">To</label><input className="input" value={form.data.to_location} onChange={e => form.setData('to_location', e.target.value)} placeholder="Destination / airport" /></div>
                </div>
                <div className="grid grid-cols-[1fr_5rem] gap-3">
                    <div><label className="label">Cost</label><NumberInput className="input" value={form.data.cost} onChange={e => form.setData('cost', e.target.value)} placeholder="0.00" /></div>
                    <div><label className="label">Currency</label><input className="input" value={form.data.currency} onChange={e => form.setData('currency', e.target.value.toUpperCase().slice(0, 3))} placeholder="USD" /></div>
                </div>
                <div><label className="label">Booking link</label><input className="input" value={form.data.booking_url} onChange={e => form.setData('booking_url', e.target.value)} placeholder="https://…" />{form.errors.booking_url && <p className="mt-1 text-xs text-destructive">{form.errors.booking_url}</p>}</div>
                <div><label className="label">Notes</label><textarea className="input min-h-[48px]" value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} placeholder="optional" /></div>
            </form>
        </Modal>
    );
}
