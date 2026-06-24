import { Head, router } from '@inertiajs/react';
import { CrmLayout } from '@/Components/layout/CrmLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { StatCard } from '@/Components/ui/StatCard';
import { cn, formatCurrency } from '@/Lib/utils';
import { BarChart3, Trophy, DollarSign, Timer, Target } from 'lucide-react';

interface FunnelStage { key: string; label: string; color: string; count: number; value: number }
interface Summary { total: number; won: number; lost: number; open: number; win_rate: number; won_value: number; open_value: number; avg_deal: number }
interface SourceRow { source: string; total: number; won: number; open: number; value: number; win_rate: number }
interface OwnerRow { owner: string; total: number; won: number; open: number; value: number; win_rate: number }
interface ForecastMonth { key: string; label: string; weighted: number; count: number }
interface Forecast { weighted_total: number; unweighted_total: number; by_month: ForecastMonth[] }
interface Velocity { avg_days: number | null; sample: number }

interface Props {
    period: number;
    funnel: FunnelStage[];
    summary: Summary;
    bySource: SourceRow[];
    byOwner: OwnerRow[];
    forecast: Forecast;
    velocity: Velocity;
}

const DOT: Record<string, string> = {
    gray: 'bg-gray-400', blue: 'bg-blue-500', indigo: 'bg-indigo-500', amber: 'bg-amber-500', green: 'bg-emerald-500', red: 'bg-rose-500',
};

const PERIODS = [{ v: 30, l: '30 days' }, { v: 90, l: '90 days' }, { v: 365, l: '1 year' }, { v: 0, l: 'All time' }];

export default function ReportsIndex({ period, funnel, summary, bySource, byOwner, forecast, velocity }: Props) {
    const funnelMax = Math.max(1, ...funnel.map(s => s.count));
    const monthMax = Math.max(1, ...forecast.by_month.map(m => m.weighted));

    const setPeriod = (v: number) =>
        router.get('/crm/reports', { period: v }, { preserveState: true, preserveScroll: true, replace: true });

    return (
        <CrmLayout>
            <Head title="Reports · CRM" />
            <div className="mx-auto max-w-6xl px-4 py-6 sm:px-6">
                <PageHeader
                    icon={BarChart3}
                    title="Sales reports"
                    description="Funnel, conversion, forecast and velocity across your pipeline."
                    actions={
                        <div className="flex rounded-lg border border-border p-0.5">
                            {PERIODS.map(p => (
                                <button key={p.v} onClick={() => setPeriod(p.v)}
                                    className={cn('rounded-md px-3 py-1 text-sm font-medium transition-colors', period === p.v ? 'bg-secondary text-foreground' : 'text-muted-foreground hover:text-foreground')}>
                                    {p.l}
                                </button>
                            ))}
                        </div>
                    }
                />

                <div className="stagger grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard title="Win rate" value={`${summary.win_rate}%`} subtitle={`${summary.won} won · ${summary.lost} lost`} icon={Trophy} tone="emerald" />
                    <StatCard title="Won value" value={formatCurrency(summary.won_value)} subtitle={`Avg deal ${formatCurrency(summary.avg_deal)}`} icon={DollarSign} tone="indigo" />
                    <StatCard title="Open pipeline" value={formatCurrency(summary.open_value)} subtitle={`${summary.open} open leads`} icon={Target} tone="violet" />
                    <StatCard title="Avg days to win" value={velocity.avg_days ?? '—'} subtitle={velocity.sample ? `from ${velocity.sample} won` : 'No wins yet'} icon={Timer} tone="amber" />
                </div>

                <div className="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Funnel */}
                    <section className="card-surface p-5">
                        <h2 className="mb-4 text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Pipeline funnel</h2>
                        <div className="space-y-3">
                            {funnel.map(s => (
                                <div key={s.key} className="flex items-center gap-3">
                                    <span className="flex w-20 shrink-0 items-center gap-1.5 text-sm font-medium text-foreground">
                                        <span className={cn('h-2 w-2 rounded-full', DOT[s.color] ?? DOT.gray)} />{s.label}
                                    </span>
                                    <div className="h-6 flex-1 overflow-hidden rounded-lg bg-secondary/60">
                                        <div className="flex h-full items-center justify-end rounded-lg bg-brand-gradient px-2 text-[11px] font-bold text-white" style={{ width: `${Math.max(6, (s.count / funnelMax) * 100)}%` }}>
                                            {s.count}
                                        </div>
                                    </div>
                                    <span className="w-20 shrink-0 text-right text-xs text-muted-foreground">{formatCurrency(s.value)}</span>
                                </div>
                            ))}
                        </div>
                    </section>

                    {/* Forecast */}
                    <section className="card-surface p-5">
                        <div className="mb-1 flex items-baseline justify-between">
                            <h2 className="text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Weighted forecast</h2>
                            <span className="text-lg font-bold text-foreground">{formatCurrency(forecast.weighted_total)}</span>
                        </div>
                        <p className="mb-4 text-xs text-muted-foreground">Open value × win probability{forecast.unweighted_total ? ` · ${formatCurrency(forecast.unweighted_total)} unweighted` : ''}</p>
                        <div className="flex h-40 items-end gap-2">
                            {forecast.by_month.map(m => (
                                <div key={m.key} className="flex flex-1 flex-col items-center gap-1">
                                    <div className="flex w-full flex-1 items-end">
                                        <div className="w-full rounded-t-md bg-brand-gradient transition-all" style={{ height: `${Math.max(2, (m.weighted / monthMax) * 100)}%` }} title={formatCurrency(m.weighted)} />
                                    </div>
                                    <span className="text-[10px] text-muted-foreground">{m.label.split(' ')[0]}</span>
                                </div>
                            ))}
                        </div>
                    </section>

                    {/* By source */}
                    <BreakdownTable title="By source" rows={bySource.map(r => ({ label: r.source, total: r.total, won: r.won, open: r.open, value: r.value, win_rate: r.win_rate }))} />
                    {/* By owner */}
                    <BreakdownTable title="By owner" rows={byOwner.map(r => ({ label: r.owner, total: r.total, won: r.won, open: r.open, value: r.value, win_rate: r.win_rate }))} />
                </div>
            </div>
        </CrmLayout>
    );
}

interface BreakdownRow { label: string; total: number; won: number; open: number; value: number; win_rate: number }

function BreakdownTable({ title, rows }: { title: string; rows: BreakdownRow[] }) {
    return (
        <section className="card-surface p-5">
            <h2 className="mb-3 text-sm font-bold uppercase tracking-wider text-muted-foreground/70">{title}</h2>
            {rows.length === 0 ? (
                <p className="py-4 text-center text-sm text-muted-foreground">No data.</p>
            ) : (
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b border-border text-left text-xs uppercase tracking-wider text-muted-foreground/70">
                            <th className="py-2 font-semibold">{title.replace('By ', '')}</th>
                            <th className="py-2 text-right font-semibold">Leads</th>
                            <th className="py-2 text-right font-semibold">Won</th>
                            <th className="py-2 text-right font-semibold">Win %</th>
                            <th className="py-2 text-right font-semibold">Value</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-border/60">
                        {rows.map((r, i) => (
                            <tr key={i}>
                                <td className="py-2 font-medium text-foreground">{r.label}</td>
                                <td className="py-2 text-right text-muted-foreground">{r.total}</td>
                                <td className="py-2 text-right text-muted-foreground">{r.won}</td>
                                <td className="py-2 text-right">
                                    <span className={cn('font-medium', r.win_rate >= 50 ? 'text-emerald-600 dark:text-emerald-400' : 'text-foreground')}>{r.win_rate}%</span>
                                </td>
                                <td className="py-2 text-right font-medium text-foreground">{formatCurrency(r.value)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </section>
    );
}
