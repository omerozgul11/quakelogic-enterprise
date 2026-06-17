import { Head, Link } from '@inertiajs/react';
import { Building2, Users, Target, FolderKanban, ReceiptText, AlertTriangle, ArrowRight } from 'lucide-react';
import { CrmLayout } from '@/Components/layout/CrmLayout';
import { StatCard } from '@/Components/ui/StatCard';
import { Pill } from '@/Components/ui/Pill';
import { formatCurrency, formatDate } from '@/Lib/utils';

interface Stats {
    clients: number;
    contacts: number;
    open_leads: number;
    pipeline_value: number;
    active_projects: number;
    outstanding_amount: number;
    overdue_invoices: number;
}

interface PipelineStage { key: string; label: string; color: string; count: number; value: number }
interface RecentLead { id: number; title: string; company: string | null; value: number; status_label: string; status_color: string }
interface RecentInvoice { id: number; number: string; kind: string; company: string | null; total: number; currency: string; status_label: string; status_color: string }
interface ProjectDue { id: number; name: string; due_date: string | null; progress: number; status_label: string; status_color: string }

interface Props {
    stats: Stats;
    pipeline: PipelineStage[];
    recentLeads: RecentLead[];
    recentInvoices: RecentInvoice[];
    projectsDue: ProjectDue[];
}

export default function CrmDashboard({ stats, pipeline, recentLeads, recentInvoices, projectsDue }: Props) {
    const pipelineMax = Math.max(1, ...pipeline.map(p => p.count));

    return (
        <CrmLayout>
            <Head title="CRM" />
            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-bold tracking-tight text-foreground">CRM Dashboard</h1>
                    <p className="mt-1 text-sm text-muted-foreground">Clients, pipeline, projects and billing at a glance.</p>
                </div>

                <div className="stagger grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard title="Clients" value={stats.clients} icon={Building2} tone="indigo" href="/crm/clients" />
                    <StatCard title="Open leads" value={stats.open_leads} subtitle={formatCurrency(stats.pipeline_value) + ' in pipeline'} icon={Target} tone="violet" href="/crm/leads" />
                    <StatCard title="Active projects" value={stats.active_projects} icon={FolderKanban} tone="teal" href="/crm/projects" />
                    <StatCard title="Outstanding" value={formatCurrency(stats.outstanding_amount)} subtitle={stats.overdue_invoices ? `${stats.overdue_invoices} overdue` : 'All current'} icon={ReceiptText} tone={stats.overdue_invoices ? 'rose' : 'emerald'} href="/crm/invoices" />
                </div>

                <div className="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-5">
                    {/* Pipeline funnel */}
                    <div className="card-surface p-5 lg:col-span-3">
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Sales pipeline</h2>
                            <Link href="/crm/leads" className="text-sm font-medium text-primary hover:underline">Open board</Link>
                        </div>
                        <div className="space-y-3">
                            {pipeline.map(stage => (
                                <div key={stage.key} className="flex items-center gap-3">
                                    <span className="w-24 shrink-0 text-sm font-medium text-foreground">{stage.label}</span>
                                    <div className="h-7 flex-1 overflow-hidden rounded-lg bg-secondary/60">
                                        <div
                                            className="flex h-full items-center justify-end rounded-lg bg-brand-gradient px-2 text-[11px] font-bold text-white transition-all"
                                            style={{ width: `${Math.max(8, (stage.count / pipelineMax) * 100)}%` }}
                                        >
                                            {stage.count}
                                        </div>
                                    </div>
                                    <span className="w-24 shrink-0 text-right text-xs text-muted-foreground">{formatCurrency(stage.value)}</span>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Projects due */}
                    <div className="card-surface p-5 lg:col-span-2">
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Projects due</h2>
                            <Link href="/crm/projects" className="text-sm font-medium text-primary hover:underline">All</Link>
                        </div>
                        {projectsDue.length === 0 ? (
                            <p className="py-6 text-center text-sm text-muted-foreground">No upcoming deadlines.</p>
                        ) : (
                            <div className="space-y-2">
                                {projectsDue.map(p => (
                                    <Link key={p.id} href={`/crm/projects/${p.id}`} className="flex items-center gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-secondary">
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate text-sm font-medium text-foreground">{p.name}</span>
                                            <span className="block text-xs text-muted-foreground">{formatDate(p.due_date)} · {p.progress}%</span>
                                        </span>
                                        <Pill color={p.status_color} label={p.status_label} />
                                    </Link>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                <div className="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Recent leads */}
                    <div>
                        <div className="mb-3 flex items-center justify-between">
                            <h2 className="text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Recent leads</h2>
                            <Link href="/crm/leads" className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline">View all <ArrowRight className="h-3 w-3" /></Link>
                        </div>
                        {recentLeads.length === 0 ? (
                            <div className="card-surface px-5 py-8 text-center text-sm text-muted-foreground">No leads yet.</div>
                        ) : (
                            <div className="card-surface overflow-hidden">
                                {recentLeads.map(l => (
                                    <div key={l.id} className="flex items-center gap-3 border-b border-border px-4 py-3 last:border-0">
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate text-sm font-medium text-foreground">{l.title}</span>
                                            <span className="block truncate text-xs text-muted-foreground">{l.company ?? '—'}</span>
                                        </span>
                                        <span className="text-xs font-medium text-muted-foreground">{formatCurrency(l.value)}</span>
                                        <Pill color={l.status_color} label={l.status_label} />
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Recent invoices */}
                    <div>
                        <div className="mb-3 flex items-center justify-between">
                            <h2 className="text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Recent billing</h2>
                            <Link href="/crm/invoices" className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline">View all <ArrowRight className="h-3 w-3" /></Link>
                        </div>
                        {recentInvoices.length === 0 ? (
                            <div className="card-surface px-5 py-8 text-center text-sm text-muted-foreground">No invoices or estimates yet.</div>
                        ) : (
                            <div className="card-surface overflow-hidden">
                                {recentInvoices.map(i => (
                                    <Link key={i.id} href={`/crm/invoices/${i.id}`} className="flex items-center gap-3 border-b border-border px-4 py-3 transition-colors last:border-0 hover:bg-secondary">
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate font-mono text-sm font-medium text-foreground">{i.number}</span>
                                            <span className="block truncate text-xs text-muted-foreground">{i.company ?? '—'}{i.kind === 'estimate' ? ' · Estimate' : ''}</span>
                                        </span>
                                        <span className="text-xs font-medium text-muted-foreground">{formatCurrency(i.total, i.currency)}</span>
                                        <Pill color={i.status_color} label={i.status_label} />
                                    </Link>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                {(stats.overdue_invoices > 0) && (
                    <div className="card-surface mt-8 flex items-center gap-3 border-amber-200 px-5 py-4 text-sm dark:border-amber-900">
                        <AlertTriangle className="h-5 w-5 text-amber-500" />
                        <span className="text-foreground">{stats.overdue_invoices} invoice{stats.overdue_invoices === 1 ? '' : 's'} overdue — </span>
                        <Link href="/crm/invoices?status=overdue" className="font-medium text-primary hover:underline">review now</Link>
                    </div>
                )}
            </div>
        </CrmLayout>
    );
}
