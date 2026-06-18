import { Head, router } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { cn, formatCurrency } from '@/Lib/utils';
import { ExportMenu } from '@/Components/ui/ExportMenu';
import { DateRangePicker } from '@/Components/ui/DateRangePicker';
import { Users as UsersIcon, Trophy } from 'lucide-react';
import {
    ResponsiveContainer, BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend,
} from 'recharts';

interface Row {
    user: string;
    role: string | null;
    is_active: boolean;
    created: number;
    submitted: number;
    awarded: number;
    lost: number;
    active: number;
    win_rate: number | null;
    submitted_value: number;
    earnings: number;
    pipeline_value: number;
}

interface StatusRow {
    status: string;
    label: string;
    color: string;
    count: number;
    value: number;
}

interface Props {
    team: Row[];
    period: 'week' | 'month' | 'year' | 'all' | 'custom';
    from: string | null;
    to: string | null;
    periodLabel: string;
    statusBreakdown: StatusRow[];
    totals: {
        created: number; submitted: number; awarded: number;
        submitted_value: number; earnings: number; pipeline_value: number;
    };
}

const PERIODS: Array<{ value: Exclude<Props['period'], 'custom'>; label: string }> = [
    { value: 'week', label: 'This Week' },
    { value: 'month', label: 'This Month' },
    { value: 'year', label: 'This Year' },
    { value: 'all', label: 'All Time' },
];

const compactDollars = (v: number) =>
    new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', notation: 'compact', maximumFractionDigits: 1 }).format(v);

export default function ReportsUsers({ team, period, from, to, periodLabel, statusBreakdown, totals }: Props) {
    const setPeriod = (p: string) => router.get('/reports/users', { period: p }, { preserveState: true, preserveScroll: true });
    const applyRange = (f: string, t: string) => router.get('/reports/users', { from: f, to: t }, { preserveState: true, preserveScroll: true });
    const exportQuery = period === 'custom' ? `from=${from}&to=${to}` : `period=${period}`;
    const chartData = team.filter(r => r.earnings + r.submitted_value + r.pipeline_value > 0);
    const hasData = team.some(r => r.created + r.submitted + r.awarded + r.lost > 0);

    const kpis = [
        { label: 'Proposals Created', value: totals.created },
        { label: 'Proposals Submitted', value: totals.submitted },
        { label: 'Contracts Won', value: totals.awarded },
        { label: 'Submitted Value', value: formatCurrency(totals.submitted_value) },
        { label: 'Earnings', value: formatCurrency(totals.earnings) },
        { label: 'Open Pipeline', value: formatCurrency(totals.pipeline_value) },
    ];

    return (
        <AppLayout>
            <Head title="Team Performance" />
            <div className="p-6">
                <PageHeader
                    icon={UsersIcon}
                    title="Team Performance"
                    description={`Per-user output, proposal statuses, and earnings — ${periodLabel}.`}
                    actions={
                        <>
                            <div className="inline-flex rounded-xl border border-border bg-card p-1">
                                {PERIODS.map(p => (
                                    <button
                                        key={p.value}
                                        onClick={() => setPeriod(p.value)}
                                        className={cn(
                                            'rounded-lg px-3 py-1.5 text-sm font-medium transition',
                                            period === p.value ? 'bg-brand-gradient text-white shadow-sm' : 'text-muted-foreground hover:text-foreground',
                                        )}
                                    >
                                        {p.label}
                                    </button>
                                ))}
                            </div>
                            <DateRangePicker
                                from={from}
                                to={to}
                                active={period === 'custom'}
                                onApply={applyRange}
                                onClear={() => setPeriod('year')}
                            />
                            <ExportMenu urlTemplate={`/reports/users/download/{format}?${exportQuery}`} />
                        </>
                    }
                />

                {/* KPI chips */}
                <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    {kpis.map(k => (
                        <div key={k.label} className="rounded-2xl border border-border bg-card p-4">
                            <p className="text-xs font-medium text-muted-foreground">{k.label}</p>
                            <p className="mt-1 text-xl font-bold text-foreground">{k.value}</p>
                        </div>
                    ))}
                </div>

                {!hasData ? (
                    <Card><CardContent className="py-4">
                        <EmptyState icon={UsersIcon} title="No activity in this period" description="Try a wider time range." />
                    </CardContent></Card>
                ) : (
                    <>
                        <div className="mb-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
                            {/* Dollar performance per user */}
                            <Card>
                                <CardHeader><CardTitle>Dollars by user</CardTitle></CardHeader>
                                <CardContent>
                                    <div className="h-80 w-full">
                                        {chartData.length === 0 ? (
                                            <div className="flex h-full items-center justify-center text-sm text-muted-foreground">No dollar activity in this period.</div>
                                        ) : (
                                            <ResponsiveContainer width="100%" height="100%">
                                                <BarChart data={chartData} margin={{ top: 8, right: 8, left: 8, bottom: 8 }}>
                                                    <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" vertical={false} />
                                                    <XAxis dataKey="user" tick={{ fontSize: 12, fill: 'hsl(var(--muted-foreground))' }} />
                                                    <YAxis tickFormatter={compactDollars} tick={{ fontSize: 12, fill: 'hsl(var(--muted-foreground))' }} width={70} />
                                                    <Tooltip
                                                        formatter={(v: number) => formatCurrency(v)}
                                                        contentStyle={{ background: 'hsl(var(--card))', border: '1px solid hsl(var(--border))', borderRadius: 12, fontSize: 13 }}
                                                        cursor={{ fill: 'hsl(var(--secondary))', opacity: 0.4 }}
                                                    />
                                                    <Legend wrapperStyle={{ fontSize: 12 }} />
                                                    <Bar dataKey="submitted_value" name="Submitted value" fill="#6366f1" radius={[3, 3, 0, 0]} maxBarSize={28} />
                                                    <Bar dataKey="earnings" name="Earnings" fill="#10b981" radius={[3, 3, 0, 0]} maxBarSize={28} />
                                                    <Bar dataKey="pipeline_value" name="Open pipeline" fill="#f59e0b" radius={[3, 3, 0, 0]} maxBarSize={28} />
                                                </BarChart>
                                            </ResponsiveContainer>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Proposal statuses for the selected period */}
                            <Card>
                                <CardHeader><CardTitle>Proposals by status <span className="font-normal text-muted-foreground">· {periodLabel}</span></CardTitle></CardHeader>
                                <CardContent>
                                    <div className="space-y-2.5">
                                        {statusBreakdown.map(s => {
                                            const max = Math.max(...statusBreakdown.map(x => x.count), 1);
                                            return (
                                                <div key={s.status} className="flex items-center gap-3">
                                                    <div className="w-44 shrink-0"><StatusBadge status={s.status} /></div>
                                                    <div className="h-2.5 flex-1 overflow-hidden rounded-full bg-secondary">
                                                        <div className="h-full rounded-full bg-primary/70" style={{ width: `${(s.count / max) * 100}%` }} />
                                                    </div>
                                                    <span className="w-8 shrink-0 text-right text-sm font-semibold text-foreground">{s.count}</span>
                                                    <span className="w-24 shrink-0 text-right text-xs text-muted-foreground">{s.value > 0 ? formatCurrency(s.value) : '—'}</span>
                                                </div>
                                            );
                                        })}
                                        {statusBreakdown.length === 0 && (
                                            <p className="py-8 text-center text-sm text-muted-foreground">No proposals yet.</p>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Per-user table */}
                        <Card className="overflow-hidden">
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead className="border-b border-border bg-secondary/40">
                                        <tr>
                                            <th className="th">User</th>
                                            <th className="th text-right">Created</th>
                                            <th className="th text-right">Submitted</th>
                                            <th className="th text-right" title="Open proposals this person is still working on">Active</th>
                                            <th className="th text-right">Won</th>
                                            <th className="th text-right">Lost</th>
                                            <th className="th text-right">Win Rate</th>
                                            <th className="th text-right">Submitted $</th>
                                            <th className="th text-right">Earnings</th>
                                            <th className="th text-right">Open Pipeline $</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {team.map((r, i) => (
                                            <tr key={r.user} className={cn('row-link', !r.is_active && 'opacity-50')}>
                                                <td className="td">
                                                    <div className="flex items-center gap-2">
                                                        {i === 0 && r.earnings > 0 && <Trophy className="h-4 w-4 text-amber-500" />}
                                                        <div>
                                                            <p className="font-medium text-foreground">{r.user}{!r.is_active && ' (inactive)'}</p>
                                                            {r.role && <p className="text-xs text-muted-foreground">{r.role}</p>}
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="td text-right text-muted-foreground">{r.created}</td>
                                                <td className="td text-right text-muted-foreground">{r.submitted}</td>
                                                <td className="td text-right font-medium text-foreground">{r.active}</td>
                                                <td className="td text-right font-medium text-emerald-600 dark:text-emerald-400">{r.awarded}</td>
                                                <td className="td text-right text-muted-foreground">{r.lost}</td>
                                                <td className="td text-right text-muted-foreground">{r.win_rate != null ? `${r.win_rate}%` : '—'}</td>
                                                <td className="td text-right text-muted-foreground">{formatCurrency(r.submitted_value)}</td>
                                                <td className="td text-right font-semibold text-foreground">{formatCurrency(r.earnings)}</td>
                                                <td className="td text-right text-muted-foreground">{formatCurrency(r.pipeline_value)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </Card>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
