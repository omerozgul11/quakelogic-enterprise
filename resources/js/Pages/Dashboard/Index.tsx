import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { cn, formatCurrency, getDueDateLabel, getDueDateColor, formatDate } from '@/Lib/utils';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { QuakeBotScene } from '@/Components/ui/QuakeBotScene';
import { SharedProps } from '@/Types';
import {
    Target, FileText, Trophy, TrendingUp, DollarSign,
    Clock, AlertCircle, CheckCircle, ArrowUpRight, Plus, ArrowRight, FileSearch, CalendarClock, Banknote,
} from 'lucide-react';

interface ExchangeRates {
    date: string;
    source: 'realtime' | 'live' | 'reference';
    fetched_at?: string;
    rates: Array<{ code: string; name: string; symbol: string; usd_per_unit: number }>;
}

interface DashboardMetrics {
    mySubmitted: number;
    myAwarded: number;
    myLost: number;
    myPending: number;
    mySubmittedValue: number;
    myAwardValue: number;
    myPipelineValue: number;
    myWeightedPipelineValue: number;
    companyPipelineValue: number;
    companyWeightedPipelineValue: number;
    myCommissions: number;
    myTasks: number;
    myFollowUps: number;
    myUpcomingDeadlines: Array<{
        id: number;
        proposal_number: string;
        project_name: string;
        due_date: string;
        status: string;
    }>;
    recentActivity: Array<{
        type: 'proposal' | 'document';
        title: string;
        sub: string | null;
        value: number;
        url: string;
        at: string | null;
    }>;
    companyTotalProposals: number;
    companyMonthlySubmissions: number;
    companyMonthlyValue: number;
    companySubmittedValue: number;
    companySubmittedCount: number;
    companyAwardValue: number;
    isAdmin: boolean;
    orgSubmissions: Record<'last7' | 'last30' | 'last60' | 'total', { count: number; value: number }> | null;
    upcomingSubmissions: { this_week: number; in15: number } | null;
}

const SUBMITTED_STATUSES = 'submitted,award_pending,awarded,completed,lost';
const OPEN_STATUSES = 'in_progress,submitted,award_pending';
const ymd = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

/** Admin: total submissions org-wide with a 7 / 30 / 60 / All interval picker. */
function AdminSubmissionsCard({ data }: { data: NonNullable<DashboardMetrics['orgSubmissions']> }) {
    const [iv, setIv] = useState<'last7' | 'last30' | 'last60' | 'total'>('total');
    const cur = data[iv];
    const days = iv === 'last7' ? 7 : iv === 'last30' ? 30 : iv === 'last60' ? 60 : null;
    const today = new Date();
    const href = days === null
        ? `/proposals/board?status=${SUBMITTED_STATUSES}`
        : `/proposals/board?date_field=submission_date&from=${ymd(new Date(today.getTime() - days * 86400000))}&to=${ymd(today)}&status=${SUBMITTED_STATUSES}`;
    const tabs: Array<[typeof iv, string]> = [['last7', '7d'], ['last30', '30d'], ['last60', '60d'], ['total', 'All']];
    const rangeNote = days === null ? 'all time' : `last ${days} days`;
    return (
        <div className="card-surface card-hover group h-full p-5">
            {/* Whole body links to the board filtered to the selected interval's submissions */}
            <Link href={href} className="block" title={`View submissions (${rangeNote}) on the board`}>
                <div className="mb-4 flex items-start justify-between">
                    <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-500 to-sky-500 text-white shadow-soft">
                        <FileText className="h-5 w-5" />
                    </div>
                    <ArrowUpRight className="h-4 w-4 text-muted-foreground transition-all group-hover:-translate-y-0.5 group-hover:translate-x-0.5 group-hover:text-primary" />
                </div>
                <p className="text-2xl font-bold tracking-tight text-foreground">{cur.count}</p>
                <p className="mt-0.5 text-sm font-medium text-muted-foreground">Total Submissions</p>
                <p className="mt-1 text-xs text-muted-foreground/80">{formatCurrency(cur.value)} · {rangeNote}</p>
            </Link>
            {/* Interval picker — buttons don't navigate; they re-target the link above */}
            <div className="mt-2.5 inline-flex rounded-lg bg-secondary p-0.5 text-[11px] font-medium">
                {tabs.map(([k, label]) => (
                    <button key={k} type="button" onClick={() => setIv(k)} className={cn('rounded-md px-2 py-1 transition-colors', iv === k ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground')}>
                        {label}
                    </button>
                ))}
            </div>
        </div>
    );
}

interface Props {
    metrics: DashboardMetrics;
    canViewExecutiveDashboard: boolean;
    exchangeRates: ExchangeRates;
    eurUsdThreshold: number;
}

/** USD exchange rates (EUR→USD etc.). Near-real-time market quotes refreshed
 *  every minute, with the ECB daily feed / static rates as fallbacks.
 *  The EUR tile turns green when EUR/USD is under the user's threshold. */
function ExchangeRatesCard({ data, threshold }: { data: ExchangeRates; threshold: number }) {
    const fmt = (v: number) => (v >= 0.1 ? v.toFixed(2) : v.toFixed(4));
    const asOf = data.fetched_at
        ? new Date(data.fetched_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
        : null;
    const isLiveMarket = data.source === 'realtime';
    const stamp = isLiveMarket
        ? (asOf ? `Live · ${asOf}` : 'Live')
        : data.source === 'live' ? `ECB · ${data.date}` : `Reference · ${data.date}`;
    const stampTitle = isLiveMarket
        ? 'Near-real-time market rate (refreshed every minute)'
        : data.source === 'live' ? 'European Central Bank daily reference rate' : 'Static reference rate — live feed unavailable';
    return (
        <div className="card-surface p-5">
            <div className="mb-3 flex items-center justify-between gap-2">
                <div className="flex items-center gap-2">
                    <Banknote className="h-4 w-4 text-muted-foreground" />
                    <h2 className="font-semibold text-foreground">Exchange Rates</h2>
                    <span className="rounded-full bg-secondary px-2 py-0.5 text-xs font-medium text-muted-foreground">vs USD</span>
                </div>
                <span className="flex items-center gap-1.5 text-xs text-muted-foreground" title={stampTitle}>
                    {isLiveMarket && <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500" />}
                    {stamp}
                </span>
            </div>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                {data.rates.map(r => {
                    const isEur = r.code === 'EUR';
                    const under = isEur && r.usd_per_unit < threshold;
                    const tileClass = isEur
                        ? (under ? 'border-emerald-500/50 bg-emerald-500/10' : 'border-amber-500/50 bg-amber-500/10')
                        : 'border-border';
                    const valueClass = isEur ? (under ? 'text-emerald-600' : 'text-amber-600') : 'text-foreground';
                    return (
                        <div key={r.code} className={`rounded-xl border p-3 ${tileClass}`} title={`1 ${r.name} = $${fmt(r.usd_per_unit)} USD`}>
                            <div className="flex items-center justify-between text-xs">
                                <span className="font-semibold text-foreground">{r.code}</span>
                                <span className="text-muted-foreground">{r.symbol}</span>
                            </div>
                            <p className={`mt-1 text-lg font-bold ${valueClass}`}>${fmt(r.usd_per_unit)}</p>
                            <p className="text-[11px] text-muted-foreground">{isEur ? `target < $${threshold.toFixed(2)}` : `per 1 ${r.code}`}</p>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

const colorMap: Record<string, string> = {
    indigo: 'from-indigo-500 to-violet-500',
    green: 'from-emerald-500 to-teal-500',
    purple: 'from-fuchsia-500 to-purple-500',
    teal: 'from-cyan-500 to-sky-500',
    orange: 'from-amber-500 to-orange-500',
    red: 'from-rose-500 to-red-500',
};

function StatCard({ title, value, subtitle, icon: Icon, color = 'indigo', href, hint }: {
    title: string;
    value: string | number;
    subtitle?: string;
    icon: React.ComponentType<{ className?: string }>;
    color?: string;
    href?: string;
    hint?: string;
}) {
    const card = (
        <div className="card-surface card-hover group h-full p-5" title={hint}>
            <div className="mb-4 flex items-start justify-between">
                <div className={`flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br ${colorMap[color] ?? colorMap.indigo} text-white shadow-soft`}>
                    <Icon className="h-5 w-5" />
                </div>
                {href && (
                    <ArrowUpRight className="h-4 w-4 text-muted-foreground transition-all group-hover:-translate-y-0.5 group-hover:translate-x-0.5 group-hover:text-primary" />
                )}
            </div>
            <p className="text-2xl font-bold tracking-tight text-foreground">{value}</p>
            <p className="mt-0.5 text-sm font-medium text-muted-foreground">{title}</p>
            {subtitle && <p className="mt-1 text-xs text-muted-foreground/80">{subtitle}</p>}
        </div>
    );
    return href ? <Link href={href} className="block h-full">{card}</Link> : card;
}

export default function DashboardIndex({ metrics, canViewExecutiveDashboard, exchangeRates, eurUsdThreshold }: Props) {
    const { auth } = usePage<SharedProps>().props;

    return (
        <AppLayout>
            <Head title="Dashboard" />
            <div className="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
                {/* Hero */}
                <div className="bg-sidebar relative overflow-hidden rounded-3xl p-6 sm:p-8">
                    <div className="animate-float absolute -right-10 -top-16 h-56 w-56 rounded-full bg-white/15 blur-3xl" />
                    <div className="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p className="text-sm font-semibold text-white/85">Welcome back 👋</p>
                            <h1 className="mt-1 text-2xl font-bold tracking-tight text-white drop-shadow-sm sm:text-3xl">
                                {auth.user?.name?.split(' ')[0]}
                            </h1>
                            <p className="mt-1.5 text-sm text-white/80">Here's what's happening with your proposals today.</p>
                        </div>
                        <div className="flex items-center gap-4 sm:gap-5">
                            {/* QuakeBot at work — click to chat */}
                            <Link
                                href="/ai"
                                title="Ask QuakeBot — your AI assistant"
                                className="group hidden shrink-0 md:block"
                            >
                                <div className="rounded-2xl bg-white/10 px-3 py-2.5 ring-1 ring-white/15 backdrop-blur transition-colors group-hover:bg-white/[0.18]">
                                    <QuakeBotScene />
                                </div>
                            </Link>
                            <div className="flex flex-wrap gap-2.5">
                                <Link
                                    href="/proposals/create"
                                    className="inline-flex items-center gap-2 rounded-xl bg-white/10 px-4 py-2.5 text-sm font-semibold text-white ring-1 ring-inset ring-white/15 backdrop-blur transition-all hover:bg-white/20"
                                >
                                    <Plus className="h-4 w-4" /> New Proposal
                                </Link>
                                {canViewExecutiveDashboard && (
                                    <Link
                                        href="/dashboard/executive"
                                        className="inline-flex items-center gap-2 rounded-xl bg-white/10 px-4 py-2.5 text-sm font-semibold text-white ring-1 ring-inset ring-white/15 backdrop-blur transition-all hover:bg-white/20"
                                    >
                                        <TrendingUp className="h-4 w-4" /> Executive View
                                    </Link>
                                )}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Stats — every card links to the list its number is drawn from */}
                <div className="stagger grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {metrics.isAdmin && metrics.orgSubmissions ? (
                        <>
                            <AdminSubmissionsCard data={metrics.orgSubmissions} />
                            <StatCard title="Upcoming Submissions" value={metrics.upcomingSubmissions?.this_week ?? 0} icon={CalendarClock} color="red"
                                subtitle={`Due this week · ${metrics.upcomingSubmissions?.in15 ?? 0} within 15 days`}
                                href={`/proposals/board?date_field=due_date&from=${ymd(new Date())}&to=${ymd(new Date(Date.now() + 15 * 86400000))}&status=${OPEN_STATUSES}`}
                                hint="Open proposals whose deadline is approaching (this week, and within 15 days). Opens the board filtered to those." />
                        </>
                    ) : (
                        <>
                            <StatCard title="My Submissions" value={metrics.mySubmitted} icon={FileText} color="indigo" subtitle="Proposals you've submitted"
                                href="/proposals/board" hint="Your proposals that have been submitted (have a submission date). Opens the Applications board." />
                            <StatCard title="My Awards" value={metrics.myAwarded} icon={Trophy} color="green" subtitle="Bids you've won"
                                href="/proposals/board?status=awarded,completed" hint="Proposals you've won — awarded or completed. Opens the Applications board filtered to those." />
                        </>
                    )}
                    <StatCard title="Pipeline Value" value={formatCurrency(metrics.isAdmin ? metrics.companyPipelineValue : metrics.myPipelineValue)} icon={TrendingUp} color="orange" subtitle={metrics.isAdmin ? 'Open bids · org-wide' : 'Projected value, open bids'}
                        href="/proposals/board?status=in_progress,submitted,award_pending" hint={metrics.isAdmin ? "Projected value (USD) of all open proposals company-wide. Opens the Applications board filtered to open work." : "Projected value (USD) of your open proposals. Opens the Applications board filtered to open work."} />
                    <StatCard title="Weighted Pipeline" value={formatCurrency(metrics.isAdmin ? metrics.companyWeightedPipelineValue : metrics.myWeightedPipelineValue)} icon={TrendingUp} color="teal" subtitle={metrics.isAdmin ? 'Value × win probability · org-wide' : 'Value × win probability'}
                        href="/proposals/board?status=in_progress,submitted,award_pending" hint="Forecast: each open proposal's value weighted by its win probability (per-stage default until you set one on the proposal)." />
                    <StatCard title="My Submitted Value" value={formatCurrency(metrics.mySubmittedValue)} icon={DollarSign} color="purple" subtitle="Total value you've submitted"
                        href="/proposals/board" hint="Total value (USD) of everything you've submitted. Opens the Applications board." />
                    <StatCard title="Total Submitted Value" value={formatCurrency(metrics.companySubmittedValue)} icon={DollarSign} color="teal" subtitle={`${metrics.companySubmittedCount} submitted · org-wide`}
                        href="/proposals/board?status=submitted,award_pending,awarded,completed,lost" hint="Org-wide value (USD) of every proposal past the submit stage. Opens the Applications board filtered to those." />
                    <StatCard title="My Earnings (YTD)" value={formatCurrency(metrics.myAwardValue)} icon={CheckCircle} color="green" subtitle="Your awards this year"
                        href="/proposals/board?status=awarded,completed" hint="Value (USD) of contracts awarded to you this year. Opens the Applications board filtered to won work." />
                    <StatCard title="Company Earnings (YTD)" value={formatCurrency(metrics.companyAwardValue)} icon={Trophy} color="green" subtitle="All wins this year, org-wide"
                        href="/proposals/board?status=awarded,completed" hint="Value (USD) of all contracts the company has won this year. Opens the Applications board filtered to won work." />
                    <StatCard title="Active Proposals" value={metrics.myPending} icon={Target} color="orange" subtitle="Still in progress"
                        href="/proposals/board?status=in_progress" hint="In-progress proposals you can see. Opens the Applications board filtered to In Progress — the count matches what's shown there." />
                    <StatCard title="Open Tasks" value={metrics.myTasks} icon={Clock} color="indigo" subtitle="Assigned to you, not done"
                        href="/calendar" hint="Tasks assigned to you that aren't completed yet. Opens your calendar." />
                    <StatCard title="Follow-ups Due" value={metrics.myFollowUps} icon={AlertCircle} color={metrics.myFollowUps > 0 ? 'red' : 'green'} subtitle="Scheduled or overdue"
                        href="/follow-ups" hint="Follow-ups assigned to you that are scheduled or overdue. Opens your inbox." />
                </div>

                {/* Daily exchange rates (EUR→USD and other majors) */}
                <ExchangeRatesCard data={exchangeRates} threshold={eurUsdThreshold} />

                {/* Panels */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-5">
                    {/* Deadlines */}
                    <div className="card-surface lg:col-span-3">
                        <div className="flex items-center justify-between border-b border-border px-5 py-4">
                            <h2 className="font-semibold text-foreground">Upcoming Deadlines</h2>
                            <Link href="/proposals" className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline">
                                View all <ArrowRight className="h-3.5 w-3.5" />
                            </Link>
                        </div>
                        {metrics.myUpcomingDeadlines.length === 0 ? (
                            <div className="flex flex-col items-center justify-center px-5 py-14 text-center">
                                <div className="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-secondary">
                                    <CheckCircle className="h-6 w-6 text-emerald-500" />
                                </div>
                                <p className="text-sm text-muted-foreground">You're all caught up — no deadlines in the next 30 days.</p>
                            </div>
                        ) : (
                            <div className="divide-y divide-border">
                                {metrics.myUpcomingDeadlines.map(proposal => (
                                    <Link
                                        key={proposal.id}
                                        href={`/proposals/${proposal.id}`}
                                        className="flex items-center justify-between gap-3 px-5 py-3.5 transition-colors hover:bg-secondary/50"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium text-foreground">{proposal.project_name}</p>
                                            <p className="text-xs text-muted-foreground">{proposal.proposal_number}</p>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <StatusBadge status={proposal.status} />
                                            <span className={`whitespace-nowrap text-xs font-medium ${getDueDateColor(proposal.due_date)}`}>
                                                {getDueDateLabel(proposal.due_date)}
                                            </span>
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Company */}
                    <div className="card-surface lg:col-span-2">
                        <div className="border-b border-border px-5 py-4">
                            <h2 className="font-semibold text-foreground">Company This Month</h2>
                        </div>
                        <div className="space-y-1 p-5">
                            {[
                                { label: 'Total Proposals (YTD)', value: metrics.companyTotalProposals },
                                { label: 'Submissions This Month', value: metrics.companyMonthlySubmissions },
                                { label: 'Value Submitted', value: formatCurrency(metrics.companyMonthlyValue) },
                            ].map(row => (
                                <div key={row.label} className="flex items-center justify-between rounded-xl px-3 py-3 transition-colors hover:bg-secondary/50">
                                    <span className="text-sm text-muted-foreground">{row.label}</span>
                                    <span className="text-sm font-semibold text-foreground">{row.value}</span>
                                </div>
                            ))}
                            <div className="grid grid-cols-2 gap-3 pt-3">
                                <Link href="/opportunities" className="rounded-xl bg-accent px-3 py-2.5 text-center text-sm font-medium text-accent-foreground transition-colors hover:bg-accent/70">
                                    Opportunities
                                </Link>
                                <Link href="/proposals/create" className="bg-brand-gradient rounded-xl px-3 py-2.5 text-center text-sm font-medium text-white transition-opacity hover:opacity-95">
                                    New Proposal
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Recently Added */}
                <div className="card-surface">
                    <div className="flex items-center justify-between border-b border-border px-5 py-4">
                        <h2 className="font-semibold text-foreground">Recently Added to the Portal</h2>
                        <Link href="/documents" className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline">
                            Documents <ArrowRight className="h-3.5 w-3.5" />
                        </Link>
                    </div>
                    {metrics.recentActivity.length === 0 ? (
                        <p className="px-5 py-10 text-center text-sm text-muted-foreground">Nothing added yet. Upload a proposal to get started.</p>
                    ) : (
                        <div className="divide-y divide-border">
                            {metrics.recentActivity.map((a, i) => (
                                <Link key={i} href={a.url} className="flex items-center gap-3 px-5 py-3 transition-colors hover:bg-secondary/50">
                                    <span className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-xl ${a.type === 'proposal' ? 'bg-primary/10 text-primary' : 'bg-secondary text-muted-foreground'}`}>
                                        {a.type === 'proposal' ? <FileText className="h-4 w-4" /> : <FileSearch className="h-4 w-4" />}
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium text-foreground">{a.title}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {a.type === 'proposal' ? 'Proposal' : 'Document'}{a.sub ? ` · ${a.sub}` : ''}{a.at ? ` · ${formatDate(a.at)}` : ''}
                                        </p>
                                    </div>
                                    {a.value > 0 && <span className="whitespace-nowrap text-sm font-semibold text-foreground">{formatCurrency(a.value)}</span>}
                                </Link>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
