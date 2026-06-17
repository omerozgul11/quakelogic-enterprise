import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { CrmLayout } from '@/Components/layout/CrmLayout';
import { Button } from '@/Components/ui/Button';
import { Select } from '@/Components/ui/Select';
import { ConfirmDialog } from '@/Components/ui/Modal';
import { LeadFormModal, EditableLead } from '@/Components/crm/LeadFormModal';
import { cn, formatCurrency, getInitials, avatarGradient } from '@/Lib/utils';
import { Target, Plus, Pencil, Trash2, CheckCheck } from 'lucide-react';

interface Lead extends EditableLead {
    id: number;
    title: string;
    company: string | null;
    owner: string | null;
    status: string;
    estimated_value: number;
    probability: number | null;
}

interface Column { key: string; label: string; color: string; leads: Lead[]; value: number }

interface Props {
    columns: Column[];
    total: number;
    companies: Array<{ id: number; name: string }>;
    sources: string[];
    statuses: Array<{ value: string; label: string }>;
    can: { manage: boolean };
}

const DOT: Record<string, string> = {
    gray: 'bg-gray-400', blue: 'bg-blue-500', indigo: 'bg-indigo-500', amber: 'bg-amber-500', green: 'bg-emerald-500', red: 'bg-red-500',
};

export default function LeadsIndex({ columns, total, companies, sources, statuses, can }: Props) {
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<Lead | null>(null);
    const [deleting, setDeleting] = useState<Lead | null>(null);
    const [processing, setProcessing] = useState(false);

    const pipelineValue = columns.filter(c => c.key !== 'lost' && c.key !== 'won').reduce((s, c) => s + c.value, 0);

    const move = (lead: Lead, status: string) => {
        if (status === lead.status) return;
        router.post(`/crm/leads/${lead.id}/status`, { status }, { preserveScroll: true });
    };
    const convert = (lead: Lead) => router.post(`/crm/leads/${lead.id}/convert`, {}, { preserveScroll: true });
    const confirmDelete = () => {
        if (!deleting) return;
        setProcessing(true);
        router.delete(`/crm/leads/${deleting.id}`, { preserveScroll: true, onFinish: () => { setProcessing(false); setDeleting(null); } });
    };

    return (
        <CrmLayout>
            <Head title="Leads · CRM" />
            <div className="px-4 py-6 sm:px-6">
                <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-4">
                        <div className="bg-brand-gradient shadow-glow flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl">
                            <Target className="h-[22px] w-[22px] text-white" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight text-foreground">Leads</h1>
                            <p className="mt-0.5 text-sm text-muted-foreground">{total} leads · {formatCurrency(pipelineValue)} open pipeline</p>
                        </div>
                    </div>
                    {can.manage && <Button onClick={() => { setEditing(null); setFormOpen(true); }} icon={Plus}>Add Lead</Button>}
                </div>

                <div className="flex gap-4 overflow-x-auto pb-4">
                    {columns.map(col => (
                        <div key={col.key} className="flex w-72 shrink-0 flex-col">
                            <div className="mb-2 flex items-center justify-between px-1">
                                <span className="flex items-center gap-2 text-sm font-semibold text-foreground">
                                    <span className={cn('h-2.5 w-2.5 rounded-full', DOT[col.color] ?? DOT.gray)} />
                                    {col.label}
                                    <span className="rounded-full bg-secondary px-1.5 text-xs font-medium text-muted-foreground">{col.leads.length}</span>
                                </span>
                                <span className="text-xs text-muted-foreground">{formatCurrency(col.value)}</span>
                            </div>

                            <div className="flex-1 space-y-2 rounded-xl bg-secondary/30 p-2">
                                {col.leads.length === 0 ? (
                                    <p className="px-2 py-6 text-center text-xs text-muted-foreground">No leads</p>
                                ) : col.leads.map(lead => (
                                    <div key={lead.id} className="group rounded-lg border border-border bg-card p-3 shadow-sm">
                                        <div className="flex items-start justify-between gap-2">
                                            <button onClick={() => { setEditing(lead); setFormOpen(true); }} className="min-w-0 flex-1 text-left">
                                                <p className="truncate text-sm font-semibold text-foreground hover:text-primary">{lead.title}</p>
                                                {lead.company && <p className="truncate text-xs text-muted-foreground">{lead.company}</p>}
                                            </button>
                                            {lead.owner && (
                                                <span className={cn('flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gradient-to-br text-[9px] font-bold text-white', avatarGradient(lead.owner))} title={lead.owner}>
                                                    {getInitials(lead.owner)}
                                                </span>
                                            )}
                                        </div>
                                        <div className="mt-2 flex items-center justify-between">
                                            <span className="text-sm font-semibold text-foreground">{formatCurrency(lead.estimated_value)}</span>
                                            {lead.probability != null && <span className="text-xs text-muted-foreground">{lead.probability}% win</span>}
                                        </div>

                                        {can.manage && (
                                            <div className="mt-2.5 flex items-center gap-1.5 border-t border-border pt-2.5">
                                                <Select
                                                    size="sm"
                                                    className="flex-1"
                                                    value={lead.status}
                                                    onChange={v => move(lead, v)}
                                                    options={statuses.map(s => ({ value: s.value, label: s.label }))}
                                                />
                                                {(lead.status === 'qualified' || lead.status === 'proposal') && (
                                                    <button onClick={() => convert(lead)} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-emerald-50 hover:text-emerald-600 dark:hover:bg-emerald-950/40" title="Convert to client">
                                                        <CheckCheck className="h-4 w-4" />
                                                    </button>
                                                )}
                                                <button onClick={() => { setEditing(lead); setFormOpen(true); }} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground" title="Edit"><Pencil className="h-4 w-4" /></button>
                                                <button onClick={() => setDeleting(lead)} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive" title="Delete"><Trash2 className="h-4 w-4" /></button>
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {formOpen && <LeadFormModal key={editing?.id ?? 'new'} open onClose={() => setFormOpen(false)} lead={editing} companies={companies} sources={sources} statuses={statuses} />}
            <ConfirmDialog
                open={!!deleting}
                onClose={() => setDeleting(null)}
                onConfirm={confirmDelete}
                processing={processing}
                title="Delete lead?"
                message={deleting ? <>This removes <span className="font-medium text-foreground">{deleting.title}</span> from your pipeline.</> : ''}
            />
        </CrmLayout>
    );
}
