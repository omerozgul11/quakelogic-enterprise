import { Head, Link } from '@inertiajs/react';
import { ServiceDeskLayout } from '@/Components/layout/ServiceDeskLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { StatCard } from '@/Components/ui/StatCard';
import { Card } from '@/Components/ui/Card';
import { Pill } from '@/Components/ui/Pill';
import { EmptyState } from '@/Components/ui/EmptyState';
import { LifeBuoy, Inbox, AlertTriangle, UserX, CheckCheck } from 'lucide-react';

interface Stats { open: number; overdue: number; unassigned: number; resolved_week: number }
interface TicketRow { id: number; number: string; subject: string; status_label: string; status_color: string; priority_label: string; priority_color: string; overdue: boolean; due_at: string | null }

interface Props {
    stats: Stats;
    my_queue: TicketRow[];
    unassigned_queue: TicketRow[];
}

function dueLabel(iso: string | null, overdue: boolean): string {
    if (!iso) return '';
    const d = new Date(iso);
    const txt = d.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    return (overdue ? 'Overdue · ' : 'Due ') + txt;
}

export default function ServiceDeskDashboard({ stats, my_queue, unassigned_queue }: Props) {
    return (
        <ServiceDeskLayout>
            <Head title="Service Desk" />
            <div className="p-4 sm:p-6">
                <PageHeader icon={LifeBuoy} title="Service Desk" description="Support, service & RMA tickets" />

                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <StatCard title="Open tickets" value={stats.open} icon={Inbox} tone="indigo" href="/tickets/queue" />
                    <StatCard title="Overdue" value={stats.overdue} icon={AlertTriangle} tone={stats.overdue > 0 ? 'rose' : 'emerald'} href="/tickets/queue?status=open" />
                    <StatCard title="Unassigned" value={stats.unassigned} icon={UserX} tone={stats.unassigned > 0 ? 'amber' : 'sky'} href="/tickets/queue?assignee=unassigned" />
                    <StatCard title="Resolved (7d)" value={stats.resolved_week} icon={CheckCheck} tone="teal" />
                </div>

                <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <Card className="p-5">
                        <h2 className="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70"><Inbox className="h-4 w-4" /> My queue</h2>
                        {my_queue.length === 0 ? (
                            <p className="py-6 text-center text-sm text-muted-foreground">Nothing assigned to you.</p>
                        ) : (
                            <div className="space-y-1.5">{my_queue.map(t => <TicketLine key={t.id} t={t} />)}</div>
                        )}
                    </Card>
                    <Card className="p-5">
                        <h2 className="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70"><UserX className="h-4 w-4" /> Unassigned</h2>
                        {unassigned_queue.length === 0 ? (
                            <EmptyState icon={LifeBuoy} title="Inbox zero" description="No unassigned tickets in the queue." />
                        ) : (
                            <div className="space-y-1.5">{unassigned_queue.map(t => <TicketLine key={t.id} t={t} />)}</div>
                        )}
                    </Card>
                </div>
            </div>
        </ServiceDeskLayout>
    );
}

function TicketLine({ t }: { t: TicketRow }) {
    return (
        <Link href={`/tickets/queue/${t.id}`} className="flex items-center gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-secondary">
            <Pill color={t.priority_color} label={t.priority_label} />
            <span className="min-w-0 flex-1">
                <span className="block truncate text-sm font-medium text-foreground">{t.subject}</span>
                <span className="block truncate font-mono text-xs text-muted-foreground">{t.number}</span>
            </span>
            {t.due_at && <span className={t.overdue ? 'text-xs font-semibold text-red-600' : 'text-xs text-muted-foreground'}>{dueLabel(t.due_at, t.overdue)}</span>}
        </Link>
    );
}
