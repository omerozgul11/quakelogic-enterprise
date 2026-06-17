import { Head, Link } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/Card';
import { formatCurrency } from '@/Lib/utils';
import { Gauge, Target, Users, AlertTriangle, Clock, Inbox, FileText, ArrowRightLeft, UserX, CalendarClock, MessageSquare } from 'lucide-react';

interface Counts { discovered_today: number; active: number; assigned: number; unassigned: number; overdue: number; inactive: number; submitted: number; won: number; lost: number; abandoned: number }
interface Metrics { win_rate: number | null; assignment_rate: number | null; avg_response_hours: number | null; avg_submission_days: number | null; proposal_velocity_per_week: number | null }
interface WorkUser { id: number; name: string; workload: number }
interface AtRiskItem { id: number; title: string; owner: string | null; stage: string | null; days_until_deadline: number | null; days_since_activity: number | null; health: number; category: string }
interface Reassign { id: number; title: string; current_owner: string | null; days_since_activity: number | null; escalation_level: number; suggested_owner: string | null; suggested_score: number | null }
interface OppRow { id: number; title: string; agency: string | null; owner: string | null; stage: string | null; days_until_deadline: number | null; value: number | null; currency: string | null }
interface FuRow { id: number; subject: string; assigned_to: string | null; scheduled_date?: string | null; sent_at?: string | null }
interface PrRow { id: number; title: string; number: string; status: string | null; owner: string | null }

interface Summary {
    counts: Counts;
    metrics: Metrics;
    workload: { highest: WorkUser[]; lowest: WorkUser[] };
    at_risk: { warning: number; critical: number; flagged_total: number; items: AtRiskItem[] };
    reassignments: Reassign[];
    attention: {
        missed_follow_ups: FuRow[];
        inactive_opportunities: OppRow[];
        unassigned_opportunities: OppRow[];
        upcoming_deadlines: OppRow[];
        pending_customer_responses: FuRow[];
        pending_proposal_reviews: PrRow[];
    };
}

const HEALTH_TONE: Record<string, string> = {
    healthy: 'bg-emerald-500/10 text-emerald-600',
    warning: 'bg-amber-500/10 text-amber-600',
    critical: 'bg-rose-500/10 text-rose-600',
};

function Kpi({ title, value, subtitle, icon: Icon, tone = 'text-blue-500' }: { title: string; value: string | number; subtitle?: string; icon: React.ComponentType<{ className?: string }>; tone?: string }) {
    return (
        <div className="rounded-xl border border-border bg-card p-5">
            <div className="flex items-start justify-between">
                <div>
                    <p className="text-sm font-medium text-muted-foreground">{title}</p>
                    <p className="mt-1 text-2xl font-bold text-foreground">{value}</p>
                    {subtitle && <p className="mt-1 text-xs text-muted-foreground">{subtitle}</p>}
                </div>
                <Icon className={`h-6 w-6 ${tone}`} />
            </div>
        </div>
    );
}

function deadlineLabel(d: number | null) {
    if (d === null) return '—';
    if (d < 0) return `${Math.abs(d)}d overdue`;
    if (d === 0) return 'due today';
    return `${d}d left`;
}

function OppList({ title, icon: Icon, rows, empty }: { title: string; icon: React.ComponentType<{ className?: string }>; rows: OppRow[]; empty: string }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-sm">
                    <Icon className="h-4 w-4 text-muted-foreground" /> {title}
                    <span className="ml-auto rounded-full bg-secondary px-2 py-0.5 text-xs font-semibold">{rows.length}</span>
                </CardTitle>
            </CardHeader>
            <CardContent>
                {rows.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{empty}</p>
                ) : (
                    <div className="space-y-2">
                        {rows.map(o => (
                            <Link key={o.id} href={`/opportunities/${o.id}`} className="flex items-center justify-between gap-3 rounded-lg border border-border p-2.5 text-sm transition-colors hover:bg-secondary/50">
                                <span className="min-w-0 flex-1 truncate text-foreground" title={o.title}>{o.title}</span>
                                <span className="shrink-0 text-xs text-muted-foreground">{o.owner ?? 'Unassigned'} · {deadlineLabel(o.days_until_deadline)}</span>
                            </Link>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

export default function Oversight({ summary }: { summary: Summary }) {
    const c = summary.counts;
    const m = summary.metrics;
    const pct = (v: number | null) => (v === null ? '—' : `${v}%`);

    return (
        <AppLayout>
            <Head title="Opportunity Command Center" />
            <div className="p-6">
                <PageHeader icon={Gauge} title="Opportunity Command Center" description="Every opportunity, who owns it, what's at risk — at a glance." />

                {/* Top-line counts */}
                <div className="mb-6 grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
                    <Kpi title="Found Today" value={c.discovered_today} icon={Target} tone="text-blue-500" />
                    <Kpi title="Active" value={c.active} icon={Target} tone="text-indigo-500" />
                    <Kpi title="Assigned" value={c.assigned} subtitle={`${c.unassigned} unassigned`} icon={Users} tone="text-purple-500" />
                    <Kpi title="Unassigned" value={c.unassigned} icon={UserX} tone={c.unassigned > 0 ? 'text-amber-500' : 'text-emerald-500'} />
                    <Kpi title="Overdue" value={c.overdue} icon={AlertTriangle} tone={c.overdue > 0 ? 'text-rose-500' : 'text-emerald-500'} />
                    <Kpi title="Inactive 7d+" value={c.inactive} icon={Clock} tone={c.inactive > 0 ? 'text-amber-500' : 'text-emerald-500'} />
                </div>

                {/* Metrics */}
                <div className="mb-6 grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
                    <Kpi title="Win Rate" value={pct(m.win_rate)} icon={Gauge} tone="text-emerald-500" />
                    <Kpi title="Assignment Rate" value={pct(m.assignment_rate)} icon={Gauge} tone="text-blue-500" />
                    <Kpi title="Avg Response" value={m.avg_response_hours === null ? '—' : `${m.avg_response_hours}h`} subtitle="assigned → accepted" icon={Clock} tone="text-indigo-500" />
                    <Kpi title="Avg to Submit" value={m.avg_submission_days === null ? '—' : `${m.avg_submission_days}d`} icon={CalendarClock} tone="text-purple-500" />
                    <Kpi title="Velocity" value={m.proposal_velocity_per_week ?? 0} subtitle="proposals / week" icon={FileText} tone="text-blue-500" />
                    <Kpi title="Won / Lost" value={`${c.won} / ${c.lost}`} icon={Target} tone="text-emerald-500" />
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Workload */}
                    <Card>
                        <CardHeader><CardTitle className="flex items-center gap-2 text-sm"><Users className="h-4 w-4 text-muted-foreground" /> Workload</CardTitle></CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <p className="mb-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Highest load</p>
                                    <div className="space-y-1.5">
                                        {summary.workload.highest.map(u => (
                                            <div key={u.id} className="flex items-center justify-between text-sm">
                                                <span className="text-foreground">{u.name}</span>
                                                <span className="font-semibold text-muted-foreground">{u.workload}</span>
                                            </div>
                                        ))}
                                        {summary.workload.highest.length === 0 && <p className="text-sm text-muted-foreground">No active owners.</p>}
                                    </div>
                                </div>
                                <div>
                                    <p className="mb-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Most available</p>
                                    <div className="space-y-1.5">
                                        {summary.workload.lowest.map(u => (
                                            <div key={u.id} className="flex items-center justify-between text-sm">
                                                <span className="text-foreground">{u.name}</span>
                                                <span className="font-semibold text-muted-foreground">{u.workload}</span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Recommended reassignments */}
                    <Card>
                        <CardHeader><CardTitle className="flex items-center gap-2 text-sm"><ArrowRightLeft className="h-4 w-4 text-muted-foreground" /> Recommended Reassignments</CardTitle></CardHeader>
                        <CardContent>
                            {summary.reassignments.length === 0 ? (
                                <p className="text-sm text-muted-foreground">Nothing needs reassigning — everyone's acting on their opportunities.</p>
                            ) : (
                                <div className="space-y-2">
                                    {summary.reassignments.map(r => (
                                        <Link key={r.id} href={`/opportunities/${r.id}`} className="block rounded-lg border border-border p-2.5 text-sm transition-colors hover:bg-secondary/50">
                                            <p className="truncate font-medium text-foreground" title={r.title}>{r.title}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {r.current_owner ?? 'Unassigned'} · inactive {r.days_since_activity ?? '—'}d → <span className="font-semibold text-foreground">{r.suggested_owner}</span>{r.suggested_score !== null ? ` (${Math.round(r.suggested_score)}%)` : ''}
                                            </p>
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* At risk */}
                <Card className="mt-6">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-sm">
                            <AlertTriangle className="h-4 w-4 text-muted-foreground" /> At-Risk Opportunities
                            <span className="ml-2 rounded-full bg-rose-500/10 px-2 py-0.5 text-xs font-semibold text-rose-600">{summary.at_risk.critical} critical</span>
                            <span className="rounded-full bg-amber-500/10 px-2 py-0.5 text-xs font-semibold text-amber-600">{summary.at_risk.warning} warning</span>
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {summary.at_risk.items.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No at-risk opportunities. 🎯</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                        <tr><th className="py-2">Opportunity</th><th className="py-2">Owner</th><th className="py-2">Stage</th><th className="py-2">Deadline</th><th className="py-2">Idle</th><th className="py-2">Health</th></tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {summary.at_risk.items.map(o => (
                                            <tr key={o.id}>
                                                <td className="py-2"><Link href={`/opportunities/${o.id}`} className="font-medium text-foreground hover:text-primary">{o.title}</Link></td>
                                                <td className="py-2 text-muted-foreground">{o.owner ?? '—'}</td>
                                                <td className="py-2 text-muted-foreground">{o.stage ?? '—'}</td>
                                                <td className="py-2 text-muted-foreground">{deadlineLabel(o.days_until_deadline)}</td>
                                                <td className="py-2 text-muted-foreground">{o.days_since_activity ?? '—'}d</td>
                                                <td className="py-2"><span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${HEALTH_TONE[o.category]}`}>{o.health}</span></td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Requires attention today */}
                <h2 className="mb-3 mt-8 text-lg font-bold text-foreground">Requires attention today</h2>
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <OppList title="Unassigned opportunities" icon={UserX} rows={summary.attention.unassigned_opportunities} empty="Everything has an owner." />
                    <OppList title="Inactive opportunities" icon={Clock} rows={summary.attention.inactive_opportunities} empty="No stalled work." />
                    <OppList title="Upcoming deadlines (7 days)" icon={CalendarClock} rows={summary.attention.upcoming_deadlines} empty="Nothing due this week." />

                    <Card>
                        <CardHeader><CardTitle className="flex items-center gap-2 text-sm"><Inbox className="h-4 w-4 text-muted-foreground" /> Missed follow-ups <span className="ml-auto rounded-full bg-secondary px-2 py-0.5 text-xs font-semibold">{summary.attention.missed_follow_ups.length}</span></CardTitle></CardHeader>
                        <CardContent>
                            {summary.attention.missed_follow_ups.length === 0 ? <p className="text-sm text-muted-foreground">All follow-ups are on track.</p> : (
                                <div className="space-y-2">
                                    {summary.attention.missed_follow_ups.map(f => (
                                        <div key={f.id} className="rounded-lg border border-border p-2.5 text-sm">
                                            <p className="truncate text-foreground">{f.subject}</p>
                                            <p className="text-xs text-muted-foreground">{f.assigned_to ?? '—'} · was due {f.scheduled_date ?? '—'}</p>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle className="flex items-center gap-2 text-sm"><MessageSquare className="h-4 w-4 text-muted-foreground" /> Pending customer responses <span className="ml-auto rounded-full bg-secondary px-2 py-0.5 text-xs font-semibold">{summary.attention.pending_customer_responses.length}</span></CardTitle></CardHeader>
                        <CardContent>
                            {summary.attention.pending_customer_responses.length === 0 ? <p className="text-sm text-muted-foreground">No outstanding client replies.</p> : (
                                <div className="space-y-2">
                                    {summary.attention.pending_customer_responses.map(f => (
                                        <div key={f.id} className="rounded-lg border border-border p-2.5 text-sm">
                                            <p className="truncate text-foreground">{f.subject}</p>
                                            <p className="text-xs text-muted-foreground">{f.assigned_to ?? '—'} · sent {f.sent_at ?? '—'}</p>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle className="flex items-center gap-2 text-sm"><FileText className="h-4 w-4 text-muted-foreground" /> Proposals awaiting decision <span className="ml-auto rounded-full bg-secondary px-2 py-0.5 text-xs font-semibold">{summary.attention.pending_proposal_reviews.length}</span></CardTitle></CardHeader>
                        <CardContent>
                            {summary.attention.pending_proposal_reviews.length === 0 ? <p className="text-sm text-muted-foreground">No proposals pending review.</p> : (
                                <div className="space-y-2">
                                    {summary.attention.pending_proposal_reviews.map(p => (
                                        <Link key={p.id} href={`/proposals/${p.id}`} className="block rounded-lg border border-border p-2.5 text-sm transition-colors hover:bg-secondary/50">
                                            <p className="truncate text-foreground">{p.title} <span className="text-xs text-muted-foreground">{p.number}</span></p>
                                            <p className="text-xs text-muted-foreground">{p.owner ?? '—'} · {p.status}</p>
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
