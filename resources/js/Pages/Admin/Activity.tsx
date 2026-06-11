import { Head, router } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { cn, formatCurrency } from '@/Lib/utils';
import { DateRangePicker } from '@/Components/ui/DateRangePicker';
import { Activity as ActivityIcon, Users } from 'lucide-react';
import {
    ResponsiveContainer, BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend,
} from 'recharts';

interface Row {
    user: string;
    proposals: number;
    submitted: number;
    followups: number;
    value_created: number;
    value_submitted: number;
    value_awarded: number;
    total: number;
}

interface Props {
    team: Row[];
    period: 'day' | 'week' | 'month' | 'year' | 'custom';
    from: string | null;
    to: string | null;
    totals: {
        proposals: number; submitted: number; followups: number;
        value_created: number; value_submitted: number; value_awarded: number;
    };
}

const PERIODS: Array<{ value: Exclude<Props['period'], 'custom'>; label: string }> = [
    { value: 'day', label: 'Today' },
    { value: 'week', label: 'This Week' },
    { value: 'month', label: 'This Month' },
    { value: 'year', label: 'This Year' },
];

const COUNT_SERIES = [
    { key: 'proposals', label: 'Proposals created', color: '#6366f1' },
    { key: 'submitted', label: 'Proposals submitted', color: '#10b981' },
    { key: 'followups', label: 'Follow-ups', color: '#06b6d4' },
] as const;

const VALUE_SERIES = [
    { key: 'value_created', label: 'Value created', color: '#6366f1' },
    { key: 'value_submitted', label: 'Value submitted', color: '#10b981' },
    { key: 'value_awarded', label: 'Value awarded', color: '#f59e0b' },
] as const;

const compactDollars = (v: number) =>
    new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', notation: 'compact', maximumFractionDigits: 1 }).format(v);

export default function AdminActivity({ team, period, from, to, totals }: Props) {
    const setPeriod = (p: string) => router.get('/admin/activity', { period: p }, { preserveState: true, preserveScroll: true });
    const applyRange = (f: string, t: string) => router.get('/admin/activity', { from: f, to: t }, { preserveState: true, preserveScroll: true });
    const chartData = team.filter(r => r.total > 0);
    const valueData = team.filter(r => r.value_created + r.value_submitted + r.value_awarded > 0);
    const grandTotal = team.reduce((s, r) => s + r.total, 0);

    const chips = [
        { label: 'Proposals created', color: '#6366f1', value: totals.proposals },
        { label: 'Proposals submitted', color: '#10b981', value: totals.submitted },
        { label: 'Follow-ups', color: '#06b6d4', value: totals.followups },
        { label: 'Value created', color: '#6366f1', value: formatCurrency(totals.value_created) },
        { label: 'Value submitted', color: '#10b981', value: formatCurrency(totals.value_submitted) },
        { label: 'Value awarded', color: '#f59e0b', value: formatCurrency(totals.value_awarded) },
    ];

    return (
        <AppLayout>
            <Head title="Activity Log" />
            <div className="p-6">
                <PageHeader
                    icon={ActivityIcon}
                    title="Activity Log"
                    description="Who did what across the team — pick a time period."
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
                                onClear={() => setPeriod('month')}
                            />
                        </>
                    }
                />

                {/* Summary chips */}
                <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    {chips.map(c => (
                        <div key={c.label} className="rounded-2xl border border-border bg-card p-4">
                            <div className="flex items-center gap-2">
                                <span className="h-2.5 w-2.5 rounded-full" style={{ background: c.color }} />
                                <p className="text-xs font-medium text-muted-foreground">{c.label}</p>
                            </div>
                            <p className="mt-1 text-xl font-bold text-foreground">{c.value}</p>
                        </div>
                    ))}
                </div>

                {grandTotal === 0 ? (
                    <Card><CardContent className="py-4">
                        <EmptyState icon={Users} title="No activity in this period" description="Try a wider time range, or check back after the team logs some work." />
                    </CardContent></Card>
                ) : (
                    <>
                        <div className="mb-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
                            <Card>
                                <CardHeader><CardTitle>Activity by user</CardTitle></CardHeader>
                                <CardContent>
                                    <div className="h-80 w-full">
                                        <ResponsiveContainer width="100%" height="100%">
                                            <BarChart data={chartData} margin={{ top: 8, right: 8, left: 0, bottom: 8 }}>
                                                <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" vertical={false} />
                                                <XAxis dataKey="user" tick={{ fontSize: 12, fill: 'hsl(var(--muted-foreground))' }} />
                                                <YAxis allowDecimals={false} tick={{ fontSize: 12, fill: 'hsl(var(--muted-foreground))' }} />
                                                <Tooltip
                                                    contentStyle={{ background: 'hsl(var(--card))', border: '1px solid hsl(var(--border))', borderRadius: 12, fontSize: 13 }}
                                                    cursor={{ fill: 'hsl(var(--secondary))', opacity: 0.4 }}
                                                />
                                                <Legend wrapperStyle={{ fontSize: 12 }} />
                                                {COUNT_SERIES.map(s => (
                                                    <Bar key={s.key} dataKey={s.key} name={s.label} stackId="a" fill={s.color} radius={[0, 0, 0, 0]} maxBarSize={56} />
                                                ))}
                                            </BarChart>
                                        </ResponsiveContainer>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader><CardTitle>Dollar value by user</CardTitle></CardHeader>
                                <CardContent>
                                    <div className="h-80 w-full">
                                        {valueData.length === 0 ? (
                                            <div className="flex h-full items-center justify-center text-sm text-muted-foreground">
                                                No proposal value recorded in this period.
                                            </div>
                                        ) : (
                                            <ResponsiveContainer width="100%" height="100%">
                                                <BarChart data={valueData} margin={{ top: 8, right: 8, left: 8, bottom: 8 }}>
                                                    <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" vertical={false} />
                                                    <XAxis dataKey="user" tick={{ fontSize: 12, fill: 'hsl(var(--muted-foreground))' }} />
                                                    <YAxis tickFormatter={compactDollars} tick={{ fontSize: 12, fill: 'hsl(var(--muted-foreground))' }} width={70} />
                                                    <Tooltip
                                                        formatter={(v: number) => formatCurrency(v)}
                                                        contentStyle={{ background: 'hsl(var(--card))', border: '1px solid hsl(var(--border))', borderRadius: 12, fontSize: 13 }}
                                                        cursor={{ fill: 'hsl(var(--secondary))', opacity: 0.4 }}
                                                    />
                                                    <Legend wrapperStyle={{ fontSize: 12 }} />
                                                    {VALUE_SERIES.map(s => (
                                                        <Bar key={s.key} dataKey={s.key} name={s.label} fill={s.color} radius={[3, 3, 0, 0]} maxBarSize={28} />
                                                    ))}
                                                </BarChart>
                                            </ResponsiveContainer>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <Card className="overflow-hidden">
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead className="border-b border-border bg-secondary/40">
                                        <tr>
                                            <th className="th">User</th>
                                            <th className="th text-right">Proposals</th>
                                            <th className="th text-right">Submitted</th>
                                            <th className="th text-right">Follow-ups</th>
                                            <th className="th text-right">$ Created</th>
                                            <th className="th text-right">$ Submitted</th>
                                            <th className="th text-right">$ Awarded</th>
                                            <th className="th text-right">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {team.map(r => (
                                            <tr key={r.user} className={cn('row-link', r.total === 0 && 'opacity-50')}>
                                                <td className="td font-medium text-foreground">{r.user}</td>
                                                <td className="td text-right text-muted-foreground">{r.proposals}</td>
                                                <td className="td text-right text-muted-foreground">{r.submitted}</td>
                                                <td className="td text-right text-muted-foreground">{r.followups}</td>
                                                <td className="td text-right text-muted-foreground">{formatCurrency(r.value_created)}</td>
                                                <td className="td text-right text-muted-foreground">{formatCurrency(r.value_submitted)}</td>
                                                <td className="td text-right text-muted-foreground">{formatCurrency(r.value_awarded)}</td>
                                                <td className="td text-right font-semibold text-foreground">{r.total}</td>
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
