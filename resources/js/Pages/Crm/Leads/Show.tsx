import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { CrmLayout } from '@/Components/layout/CrmLayout';
import { Button } from '@/Components/ui/Button';
import { Pill } from '@/Components/ui/Pill';
import { Select } from '@/Components/ui/Select';
import { ConfirmDialog } from '@/Components/ui/Modal';
import { LeadFormModal, EditableLead } from '@/Components/crm/LeadFormModal';
import { ActivityTimeline, ActivityItem } from '@/Components/crm/ActivityTimeline';
import { FollowUpPanel, FollowUpRow } from '@/Components/crm/FollowUpPanel';
import { cn, getInitials, avatarGradient, formatCurrency, formatDate } from '@/Lib/utils';
import { ArrowLeft, Mail, Phone, Package, User, CalendarClock, Tag, Percent, DollarSign, Pencil, Trash2, CheckCheck } from 'lucide-react';

interface Lead {
    id: number;
    title: string;
    company: string | null;
    company_id: number | null;
    contact_name: string | null;
    product: string | null;
    email: string | null;
    phone: string | null;
    source: string | null;
    status: string;
    status_label: string;
    status_color: string;
    estimated_value: number;
    probability: number | null;
    expected_close_date: string | null;
    owner: string | null;
    owner_id: number | null;
    notes: string | null;
    created_at: string | null;
}

interface Props {
    lead: Lead;
    activities: ActivityItem[];
    followUps: FollowUpRow[];
    statuses: Array<{ value: string; label: string }>;
    sources: string[];
    owners: Array<{ id: number; name: string }>;
    currentUserId: number;
    priorities: string[];
    can: { manage: boolean };
}

export default function LeadShow({ lead, activities, followUps, statuses, sources, owners, currentUserId, priorities, can }: Props) {
    const [editOpen, setEditOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [processing, setProcessing] = useState(false);

    const setStatus = (status: string) => {
        if (status === lead.status) return;
        router.post(`/crm/leads/${lead.id}/status`, { status }, { preserveScroll: true });
    };
    const convert = () => router.post(`/crm/leads/${lead.id}/convert`, {}, { preserveScroll: true });
    const confirmDelete = () => {
        setProcessing(true);
        router.delete(`/crm/leads/${lead.id}`, { onFinish: () => setProcessing(false) });
    };

    const editable: EditableLead = {
        id: lead.id,
        company: lead.company,
        contact_name: lead.contact_name,
        phone: lead.phone,
        product: lead.product,
        owner_id: lead.owner_id,
        email: lead.email,
        source: lead.source,
        status: lead.status,
        estimated_value: lead.estimated_value,
        probability: lead.probability,
        expected_close_date: lead.expected_close_date,
        notes: lead.notes,
    };

    const facts: Array<{ icon: React.ComponentType<{ className?: string }>; label: string; value: string | null; href?: string }> = [
        { icon: User, label: 'Contact', value: lead.contact_name },
        { icon: Mail, label: 'Email', value: lead.email, href: lead.email ? `mailto:${lead.email}` : undefined },
        { icon: Phone, label: 'Phone', value: lead.phone, href: lead.phone ? `tel:${lead.phone}` : undefined },
        { icon: Package, label: 'Product', value: lead.product },
        { icon: DollarSign, label: 'Value', value: lead.estimated_value ? formatCurrency(lead.estimated_value) : null },
        { icon: Percent, label: 'Probability', value: lead.probability != null ? `${lead.probability}%` : null },
        { icon: CalendarClock, label: 'Expected close', value: lead.expected_close_date ? formatDate(lead.expected_close_date) : null },
        { icon: Tag, label: 'Source', value: lead.source },
        { icon: User, label: 'Owner', value: lead.owner },
    ];

    return (
        <CrmLayout>
            <Head title={`${lead.company ?? lead.title} · Lead`} />
            <div className="mx-auto max-w-6xl px-4 py-6 sm:px-6">
                <Link href="/crm/leads" className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" /> Pipeline
                </Link>

                <div className="card-surface mb-6 p-6">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div className="flex items-center gap-4">
                            <div className={cn('flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br text-lg font-bold text-white', avatarGradient(lead.company ?? lead.title))}>
                                {getInitials(lead.company ?? lead.title)}
                            </div>
                            <div>
                                <h1 className="text-2xl font-bold tracking-tight text-foreground">{lead.company ?? lead.title}</h1>
                                <div className="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                    <Pill color={lead.status_color} label={lead.status_label} />
                                    {lead.product && <span>{lead.product}</span>}
                                    {lead.created_at && <span>· added {formatDate(lead.created_at)}</span>}
                                </div>
                            </div>
                        </div>
                        {can.manage && (
                            <div className="flex flex-wrap items-center gap-2">
                                <div className="w-40">
                                    <Select value={lead.status} onChange={setStatus} options={statuses} />
                                </div>
                                {lead.status !== 'won' && lead.status !== 'lost' && (
                                    <Button variant="secondary" icon={CheckCheck} onClick={convert}>Convert</Button>
                                )}
                                <Button variant="secondary" icon={Pencil} onClick={() => setEditOpen(true)}>Edit</Button>
                                <Button variant="danger" icon={Trash2} onClick={() => setDeleting(true)}>Delete</Button>
                            </div>
                        )}
                    </div>

                    <div className="mt-5 grid grid-cols-1 gap-x-6 gap-y-3 border-t border-border pt-5 sm:grid-cols-2 lg:grid-cols-3">
                        {facts.filter(f => f.value).map((f, i) => {
                            const Icon = f.icon;
                            return (
                                <div key={i} className="flex items-center gap-2.5 text-sm">
                                    <Icon className="h-4 w-4 shrink-0 text-muted-foreground" />
                                    <span className="text-muted-foreground">{f.label}:</span>
                                    {f.href ? (
                                        <a href={f.href} className="truncate font-medium text-primary hover:underline">{f.value}</a>
                                    ) : (
                                        <span className="truncate font-medium text-foreground">{f.value}</span>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                    {lead.notes && <p className="mt-4 whitespace-pre-line border-t border-border pt-4 text-sm text-muted-foreground">{lead.notes}</p>}
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="lg:col-span-2">
                        <ActivityTimeline subject="lead" subjectId={lead.id} activities={activities} />
                    </div>
                    <div className="lg:col-span-1">
                        <FollowUpPanel
                            subject={{ type: 'lead', id: lead.id }}
                            followUps={followUps}
                            owners={owners}
                            currentUserId={currentUserId}
                            priorities={priorities}
                        />
                    </div>
                </div>
            </div>

            {editOpen && (
                <LeadFormModal
                    open
                    onClose={() => setEditOpen(false)}
                    lead={editable}
                    owners={owners}
                    currentUserId={currentUserId}
                    sources={sources}
                    statuses={statuses}
                />
            )}
            <ConfirmDialog
                open={deleting}
                onClose={() => setDeleting(false)}
                onConfirm={confirmDelete}
                processing={processing}
                title="Delete lead?"
                message={<>This removes <span className="font-medium text-foreground">{lead.company ?? lead.title}</span> from your pipeline.</>}
            />
        </CrmLayout>
    );
}
