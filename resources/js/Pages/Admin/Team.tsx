import { Head } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/Card';
import { StatCard } from '@/Components/ui/StatCard';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { EmptyState } from '@/Components/ui/EmptyState';
import { formatCurrency, formatDate, getInitials, avatarGradient, cn } from '@/Lib/utils';
import { BarChart3, Users, FileText, Trophy, DollarSign } from 'lucide-react';

interface TeamMember {
    id: number;
    name: string;
    email: string;
    title: string | null;
    role: string | null;
    is_active: boolean;
    proposals_total: number;
    proposals_active: number;
    proposals_submitted: number;
    proposals_won: number;
    pipeline_value: number;
    won_value: number;
    opportunities: number;
    open_follow_ups: number;
}

interface RecentProposal {
    id: number;
    proposal_number: string;
    project_name: string;
    status: string;
    owner: string | null;
    value: number;
    updated_at: string | null;
}

interface StatusCount {
    value: string;
    label: string;
    color: string;
    count: number;
}

interface Props {
    team: TeamMember[];
    recent: RecentProposal[];
    statusBreakdown: StatusCount[];
    totals: { employees: number; proposals: number; pipeline_value: number; won: number };
}

export default function AdminTeam({ team, recent, statusBreakdown, totals }: Props) {
    return (
        <AppLayout>
            <Head title="Team Activity" />
            <div className="p-6">
                <PageHeader
                    icon={BarChart3}
                    title="Team Activity"
                    description="What every employee is working on and what they've delivered."
                />

                <div className="stagger mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard title="Employees" value={totals.employees} icon={Users} tone="indigo" />
                    <StatCard title="Total Proposals" value={totals.proposals} icon={FileText} tone="sky" />
                    <StatCard title="Open Pipeline" value={formatCurrency(totals.pipeline_value)} icon={DollarSign} tone="amber" />
                    <StatCard title="Won" value={totals.won} icon={Trophy} tone="emerald" />
                </div>

                {/* Total proposals broken out into each status category */}
                <Card className="mb-6">
                    <CardHeader><CardTitle>Proposals by Status</CardTitle></CardHeader>
                    <CardContent>
                        <div className="flex flex-wrap gap-2.5">
                            {statusBreakdown.map(s => (
                                <div key={s.value} className={cn('flex items-center gap-2 rounded-xl border px-3 py-2', s.count > 0 ? 'border-border' : 'border-dashed border-border opacity-60')}>
                                    <StatusBadge status={s.value} />
                                    <span className="text-lg font-bold tabular-nums text-foreground">{s.count}</span>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                <Card className="mb-6 overflow-hidden">
                    <CardHeader><CardTitle>By Employee</CardTitle></CardHeader>
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b border-border bg-secondary/40">
                                <tr>
                                    <th className="th">Employee</th>
                                    <th className="th text-center">Active</th>
                                    <th className="th text-center">Submitted</th>
                                    <th className="th text-center">Won</th>
                                    <th className="th text-right">Pipeline</th>
                                    <th className="th text-center">Opps</th>
                                    <th className="th text-center">Follow-ups</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {team.map(m => (
                                    <tr key={m.id} className="transition-colors hover:bg-secondary/40">
                                        <td className="td">
                                            <div className="flex items-center gap-3">
                                                <span className={cn('flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br text-[11px] font-bold text-white', avatarGradient(m.name))}>
                                                    {getInitials(m.name)}
                                                </span>
                                                <div className="min-w-0">
                                                    <p className="truncate font-medium text-foreground">{m.name}{!m.is_active && <span className="ml-1.5 text-xs text-destructive">(disabled)</span>}</p>
                                                    <p className="truncate text-xs text-muted-foreground">{m.title || m.role || m.email}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="td text-center text-foreground">{m.proposals_active}</td>
                                        <td className="td text-center text-foreground">{m.proposals_submitted}</td>
                                        <td className="td text-center">
                                            <span className={cn('font-semibold', m.proposals_won > 0 ? 'text-emerald-600' : 'text-muted-foreground')}>{m.proposals_won}</span>
                                        </td>
                                        <td className="td text-right font-medium text-foreground">{formatCurrency(m.pipeline_value)}</td>
                                        <td className="td text-center text-muted-foreground">{m.opportunities}</td>
                                        <td className="td text-center">
                                            <span className={cn(m.open_follow_ups > 0 ? 'font-semibold text-amber-600' : 'text-muted-foreground')}>{m.open_follow_ups}</span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {team.length === 0 && <EmptyState icon={Users} title="No team members" description="Add users from the Admin panel." />}
                </Card>

                <Card>
                    <CardHeader><CardTitle>Recent Proposal Activity</CardTitle></CardHeader>
                    <CardContent>
                        {recent.length === 0 ? (
                            <EmptyState icon={FileText} title="No proposals yet" description="Proposal activity will appear here as the team works." />
                        ) : (
                            <div className="space-y-1">
                                {recent.map(p => (
                                    <a key={p.id} href={`/proposals/${p.id}`} className="flex items-center gap-3 rounded-lg px-2 py-2.5 transition-colors hover:bg-secondary/50">
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium text-foreground">{p.project_name}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {p.proposal_number} · {p.owner ?? 'Unassigned'}{p.updated_at ? ` · ${formatDate(p.updated_at)}` : ''}
                                            </p>
                                        </div>
                                        {p.value > 0 && <span className="hidden text-sm font-medium text-foreground sm:inline">{formatCurrency(p.value)}</span>}
                                        <StatusBadge status={p.status} />
                                    </a>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
