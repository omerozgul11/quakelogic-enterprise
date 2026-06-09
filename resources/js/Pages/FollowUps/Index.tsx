import { Head, Link, router } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { EmptyState } from '@/Components/ui/EmptyState';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { formatDate } from '@/Lib/utils';
import { Bell, X, Check } from 'lucide-react';

interface FollowUp {
    id: number;
    type: string;
    subject: string;
    status: string;
    scheduled_date: string;
    sent_at: string | null;
    assigned_to_user: { id: number; name: string } | null;
    proposal: { id: number; proposal_number: string } | null;
    contact: { id: number; first_name: string; last_name: string } | null;
}

interface Props {
    followUps: {
        data: FollowUp[];
        total: number;
        current_page: number;
        last_page: number;
    };
    filters: Record<string, string>;
}

const STATUSES = ['scheduled', 'sent', 'overdue', 'responded', 'cancelled'];

export default function FollowUpsIndex({ followUps, filters }: Props) {
    const handleFilter = (value: string) => {
        router.get('/follow-ups', value ? { status: value } : {}, { preserveState: true });
    };

    const handleMarkSent = (id: number) => {
        router.patch(`/follow-ups/${id}`, { status: 'sent' });
    };

    return (
        <AppLayout>
            <Head title="Follow-Ups" />
            <div className="p-6">
                <PageHeader
                    icon={Bell}
                    title="Follow-Ups"
                    description={`${followUps.total} ${followUps.total === 1 ? 'follow-up' : 'follow-ups'} scheduled`}
                />

                <Card className="mb-4 p-4">
                    <div className="flex flex-wrap items-center gap-3">
                        <select value={filters.status ?? ''} onChange={e => handleFilter(e.target.value)} className="select">
                            <option value="">All Statuses</option>
                            {STATUSES.map(s => (
                                <option key={s} value={s}>{s.charAt(0).toUpperCase() + s.slice(1)}</option>
                            ))}
                        </select>
                        {filters.status && (
                            <button onClick={() => router.get('/follow-ups')} className="inline-flex items-center gap-1 text-sm font-medium text-destructive hover:underline">
                                <X className="h-4 w-4" /> Clear
                            </button>
                        )}
                    </div>
                </Card>

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b border-border bg-secondary/40">
                                <tr>
                                    <th className="th">Subject</th>
                                    <th className="th">Type</th>
                                    <th className="th">Status</th>
                                    <th className="th">Scheduled</th>
                                    <th className="th">Assigned To</th>
                                    <th className="th">Linked</th>
                                    <th className="th" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {followUps.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={7}>
                                            <EmptyState
                                                icon={Bell}
                                                title="No follow-ups found"
                                                description="Schedule a follow-up from a proposal or contact to keep your pipeline moving."
                                            />
                                        </td>
                                    </tr>
                                ) : followUps.data.map(f => (
                                    <tr key={f.id} className="row-link">
                                        <td className="td max-w-xs">
                                            <span className="font-medium text-foreground line-clamp-1">{f.subject}</span>
                                        </td>
                                        <td className="td">
                                            <span className="chip capitalize">{f.type}</span>
                                        </td>
                                        <td className="td"><StatusBadge status={f.status} /></td>
                                        <td className="td text-muted-foreground">{formatDate(f.scheduled_date)}</td>
                                        <td className="td text-muted-foreground">{f.assigned_to_user?.name ?? '—'}</td>
                                        <td className="td">
                                            {f.proposal && (
                                                <Link href={`/proposals/${f.proposal.id}`} className="font-mono text-xs text-primary hover:underline">
                                                    {f.proposal.proposal_number}
                                                </Link>
                                            )}
                                            {f.contact && (
                                                <span className="text-xs text-muted-foreground">{f.contact.first_name} {f.contact.last_name}</span>
                                            )}
                                        </td>
                                        <td className="td">
                                            {f.status === 'scheduled' && (
                                                <Button variant="success" size="sm" icon={Check} onClick={() => handleMarkSent(f.id)}>
                                                    Sent
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>
        </AppLayout>
    );
}
