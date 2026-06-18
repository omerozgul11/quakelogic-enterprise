import { Head, Link, router } from '@inertiajs/react';
import { ServiceDeskLayout } from '@/Components/layout/ServiceDeskLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Pill } from '@/Components/ui/Pill';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Pagination } from '@/Components/ui/Pagination';
import { SearchInput } from '@/Components/ui/SearchInput';
import { Select } from '@/Components/ui/Select';
import { PaginatedResponse } from '@/Types';
import { LifeBuoy, Plus, ExternalLink } from 'lucide-react';

interface TicketRow {
    id: number; number: string; subject: string; type_label: string;
    status: string; status_label: string; status_color: string;
    priority_label: string; priority_color: string;
    assignee: string | null; company: string | null; overdue: boolean; due_at: string | null;
}

interface Props {
    tickets: PaginatedResponse<TicketRow>;
    filters: Record<string, string>;
    types: { value: string; label: string }[];
    statuses: { value: string; label: string }[];
    priorities: { value: string; label: string }[];
    can: { manage: boolean };
}

function dueLabel(iso: string | null, overdue: boolean): string {
    if (!iso) return '—';
    const txt = new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
    return overdue ? `Overdue (${txt})` : txt;
}

export default function TicketsIndex({ tickets, filters, types, statuses, priorities, can }: Props) {
    const apply = (patch: Record<string, string | undefined>) => router.get('/tickets/queue', { ...filters, ...patch }, { preserveState: true, preserveScroll: true, replace: true });

    return (
        <ServiceDeskLayout>
            <Head title="Tickets · Service Desk" />
            <div className="p-4 sm:p-6">
                <PageHeader
                    icon={LifeBuoy}
                    title="Tickets"
                    description={`${tickets.total} ${tickets.total === 1 ? 'ticket' : 'tickets'}`}
                    actions={can.manage && <Button href="/tickets/queue/create" icon={Plus}>New Ticket</Button>}
                />

                <Card className="mb-4 flex flex-col gap-3 p-4 lg:flex-row lg:items-center">
                    <SearchInput className="w-full lg:max-w-xs" initial={filters.search ?? ''} onSearch={v => apply({ search: v || undefined })} placeholder="Search # or subject…" />
                    <div className="flex flex-wrap gap-2">
                        <Select value={filters.status ?? ''} onChange={v => apply({ status: v || undefined })} placeholder="All status" options={statuses} />
                        <Select value={filters.priority ?? ''} onChange={v => apply({ priority: v || undefined })} placeholder="Any priority" options={priorities} />
                        <Select value={filters.type ?? ''} onChange={v => apply({ type: v || undefined })} placeholder="All types" options={types} />
                        <Select value={filters.assignee ?? ''} onChange={v => apply({ assignee: v || undefined })} placeholder="Anyone" options={[{ value: 'me', label: 'Assigned to me' }, { value: 'unassigned', label: 'Unassigned' }]} />
                    </div>
                </Card>

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b border-border bg-secondary/40">
                                <tr>
                                    <th className="th">Ticket</th>
                                    <th className="th">Priority</th>
                                    <th className="th">Status</th>
                                    <th className="th hidden md:table-cell">Assignee</th>
                                    <th className="th hidden sm:table-cell">Due</th>
                                    <th className="th" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {tickets.data.length === 0 ? (
                                    <tr><td colSpan={6}>
                                        <EmptyState icon={LifeBuoy} title="No tickets"
                                            description="Open a support, service or RMA ticket to get started."
                                            action={can.manage && <Button href="/tickets/queue/create" icon={Plus}>New Ticket</Button>} />
                                    </td></tr>
                                ) : tickets.data.map(t => (
                                    <tr key={t.id} className="row-link">
                                        <td className="td">
                                            <Link href={`/tickets/queue/${t.id}`} className="block">
                                                <span className="font-medium text-foreground hover:text-primary">{t.subject}</span>
                                                <span className="mt-0.5 flex items-center gap-2">
                                                    <span className="font-mono text-xs text-muted-foreground">{t.number}</span>
                                                    <span className="chip">{t.type_label}</span>
                                                </span>
                                            </Link>
                                        </td>
                                        <td className="td"><Pill color={t.priority_color} label={t.priority_label} /></td>
                                        <td className="td"><Pill color={t.status_color} label={t.status_label} /></td>
                                        <td className="td hidden text-muted-foreground md:table-cell">{t.assignee ?? '—'}</td>
                                        <td className="td hidden sm:table-cell"><span className={t.overdue ? 'font-semibold text-red-600' : 'text-muted-foreground'}>{dueLabel(t.due_at, t.overdue)}</span></td>
                                        <td className="td"><div className="flex justify-end"><Link href={`/tickets/queue/${t.id}`} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-secondary hover:text-primary" title="View"><ExternalLink className="h-4 w-4" /></Link></div></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination from={tickets.from} to={tickets.to} total={tickets.total} links={tickets.links} />
                </Card>
            </div>
        </ServiceDeskLayout>
    );
}
