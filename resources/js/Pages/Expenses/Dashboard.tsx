import { Head, Link } from '@inertiajs/react';
import { ExpenseLayout } from '@/Components/layout/ExpenseLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { StatCard } from '@/Components/ui/StatCard';
import { EmptyState } from '@/Components/ui/EmptyState';
import { formatCurrency, formatRelativeDate, cn } from '@/Lib/utils';
import { Receipt, CalendarDays, CalendarRange, Wallet, Clock, BadgeDollarSign, AlertTriangle } from 'lucide-react';

interface CategorySpend { id: number; name: string; color: string | null; spent: number; budget: number | null; over_budget: boolean; pct: number | null }
interface TrendPoint { month: string; label: string; total: number }
interface VendorSpend { vendor: string; total: number }
interface PendingItem { id: number; number: string; vendor: string | null; amount: number; currency: string; owner: string | null; category: string | null; submitted_at: string | null }

interface Props {
    stats: { spend_month: number; spend_quarter: number; spend_year: number; pending_approval: number; awaiting_reimbursement: number };
    byCategory: CategorySpend[];
    trend: TrendPoint[];
    topVendors: VendorSpend[];
    pending: PendingItem[];
}

export default function ExpensesDashboard({ stats, byCategory, trend, topVendors, pending }: Props) {
    const maxTrend = Math.max(1, ...trend.map(t => t.total));

    return (
        <ExpenseLayout>
            <Head title="Dashboard · Expenses" />
            <div className="p-4 sm:p-6">
                <PageHeader icon={Receipt} title="Expenses" description="Spend, budgets and approvals at a glance" />

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard title="Spent this month" value={formatCurrency(stats.spend_month)} icon={CalendarDays} tone="indigo" href="/expenses/list" />
                    <StatCard title="Spent this quarter" value={formatCurrency(stats.spend_quarter)} icon={CalendarRange} tone="violet" />
                    <StatCard title="Spent this year" value={formatCurrency(stats.spend_year)} icon={Wallet} tone="teal" />
                    <StatCard title="Pending approval" value={stats.pending_approval} subtitle={`${stats.awaiting_reimbursement} awaiting reimbursement`} icon={Clock} tone="amber" href="/expenses/list?status=submitted" />
                </div>

                <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Spend trend */}
                    <Card className="p-5 lg:col-span-2">
                        <h2 className="text-sm font-semibold text-foreground">Spend — last 12 months</h2>
                        <div className="mt-5 flex h-44 items-end gap-1.5">
                            {trend.map(t => (
                                <div key={t.label} className="group flex flex-1 flex-col items-center justify-end gap-1.5" title={`${t.label}: ${formatCurrency(t.total)}`}>
                                    <div
                                        className="w-full rounded-t bg-gradient-to-t from-indigo-500 to-violet-400 transition-all group-hover:from-indigo-600"
                                        style={{ height: `${Math.max(2, (t.total / maxTrend) * 100)}%` }}
                                    />
                                    <span className="text-[10px] text-muted-foreground">{t.month}</span>
                                </div>
                            ))}
                        </div>
                    </Card>

                    {/* Top vendors */}
                    <Card className="p-5">
                        <h2 className="text-sm font-semibold text-foreground">Top vendors (YTD)</h2>
                        {topVendors.length === 0 ? (
                            <p className="mt-6 text-sm text-muted-foreground">No vendor spend yet.</p>
                        ) : (
                            <ul className="mt-4 space-y-3">
                                {topVendors.map(v => (
                                    <li key={v.vendor} className="flex items-center justify-between gap-3 text-sm">
                                        <span className="truncate text-foreground">{v.vendor}</span>
                                        <span className="font-semibold text-foreground">{formatCurrency(v.total)}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                </div>

                <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Budgets */}
                    <Card className="p-5">
                        <div className="flex items-center justify-between">
                            <h2 className="text-sm font-semibold text-foreground">Category budgets — this month</h2>
                            <Link href="/expenses/categories" className="text-xs font-semibold text-primary hover:underline">Manage</Link>
                        </div>
                        {byCategory.length === 0 ? (
                            <p className="mt-6 text-sm text-muted-foreground">No category spend this month.</p>
                        ) : (
                            <ul className="mt-4 space-y-4">
                                {byCategory.map(c => (
                                    <li key={c.id}>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="flex items-center gap-2 font-medium text-foreground">
                                                {c.name}
                                                {c.over_budget && <AlertTriangle className="h-3.5 w-3.5 text-red-500" />}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {formatCurrency(c.spent)}{c.budget != null && <span className="text-muted-foreground/70"> / {formatCurrency(c.budget)}</span>}
                                            </span>
                                        </div>
                                        {c.budget != null && (
                                            <div className="mt-1.5 h-2 overflow-hidden rounded-full bg-secondary">
                                                <div className={cn('h-full rounded-full', c.over_budget ? 'bg-red-500' : 'bg-emerald-500')} style={{ width: `${c.pct ?? 0}%` }} />
                                            </div>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>

                    {/* Pending approval queue */}
                    <Card className="p-5">
                        <div className="flex items-center justify-between">
                            <h2 className="text-sm font-semibold text-foreground">Awaiting your approval</h2>
                            <Link href="/expenses/list?status=submitted" className="text-xs font-semibold text-primary hover:underline">View all</Link>
                        </div>
                        {pending.length === 0 ? (
                            <EmptyState icon={BadgeDollarSign} title="Nothing pending" description="Submitted expenses will appear here for approval." className="py-8" />
                        ) : (
                            <ul className="mt-4 divide-y divide-border">
                                {pending.map(p => (
                                    <li key={p.id} className="flex items-center justify-between gap-3 py-2.5">
                                        <Link href={`/expenses/list/${p.id}`} className="min-w-0">
                                            <span className="block truncate text-sm font-medium text-foreground hover:text-primary">{p.vendor ?? p.number}</span>
                                            <span className="block truncate text-xs text-muted-foreground">{p.owner} · {p.category ?? 'Uncategorized'} · {formatRelativeDate(p.submitted_at)}</span>
                                        </Link>
                                        <span className="shrink-0 text-sm font-semibold text-foreground">{formatCurrency(p.amount, p.currency)}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                </div>
            </div>
        </ExpenseLayout>
    );
}
