import { Head, Link, usePage } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { formatCurrency, getDueDateLabel, getDueDateColor } from '@/Lib/utils';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { SharedProps } from '@/Types';
import {
    Target, FileText, Trophy, TrendingUp, DollarSign,
    Clock, AlertCircle, CheckCircle, ArrowUpRight, Plus, ArrowRight,
} from 'lucide-react';

interface DashboardMetrics {
    mySubmitted: number;
    myAwarded: number;
    myLost: number;
    myPending: number;
    mySubmittedValue: number;
    myAwardValue: number;
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
    companyTotalProposals: number;
    companyMonthlySubmissions: number;
    companyMonthlyValue: number;
}

interface Props {
    metrics: DashboardMetrics;
    canViewExecutiveDashboard: boolean;
}

const colorMap: Record<string, string> = {
    indigo: 'from-indigo-500 to-violet-500',
    green: 'from-emerald-500 to-teal-500',
    purple: 'from-fuchsia-500 to-purple-500',
    teal: 'from-cyan-500 to-sky-500',
    orange: 'from-amber-500 to-orange-500',
    red: 'from-rose-500 to-red-500',
};

function StatCard({ title, value, subtitle, icon: Icon, color = 'indigo', href }: {
    title: string;
    value: string | number;
    subtitle?: string;
    icon: React.ComponentType<{ className?: string }>;
    color?: string;
    href?: string;
}) {
    const card = (
        <div className="card-surface card-hover group h-full p-5">
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

export default function DashboardIndex({ metrics, canViewExecutiveDashboard }: Props) {
    const { auth } = usePage<SharedProps>().props;

    return (
        <AppLayout>
            <Head title="Dashboard" />
            <div className="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
                {/* Hero */}
                <div className="bg-sidebar relative overflow-hidden rounded-3xl p-6 sm:p-8">
                    <div className="animate-float absolute -right-10 -top-16 h-56 w-56 rounded-full bg-indigo-500/25 blur-3xl" />
                    <div className="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p className="text-sm font-medium text-indigo-300">Welcome back 👋</p>
                            <h1 className="mt-1 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                                {auth.user?.name?.split(' ')[0]}
                            </h1>
                            <p className="mt-1.5 text-sm text-slate-300">Here's what's happening with your proposals today.</p>
                        </div>
                        <div className="flex flex-wrap gap-2.5">
                            <Link
                                href="/proposals/create"
                                className="inline-flex items-center gap-2 rounded-xl bg-white/10 px-4 py-2.5 text-sm font-semibold text-white ring-1 ring-white/15 backdrop-blur transition-colors hover:bg-white/20"
                            >
                                <Plus className="h-4 w-4" /> New Proposal
                            </Link>
                            {canViewExecutiveDashboard && (
                                <Link
                                    href="/dashboard/executive"
                                    className="bg-brand-gradient inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-glow transition-opacity hover:opacity-95"
                                >
                                    <TrendingUp className="h-4 w-4" /> Executive View
                                </Link>
                            )}
                        </div>
                    </div>
                </div>

                {/* Stats */}
                <div className="stagger grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <StatCard title="My Submissions" value={metrics.mySubmitted} icon={FileText} color="indigo" href="/proposals" />
                    <StatCard title="My Awards" value={metrics.myAwarded} icon={Trophy} color="green" />
                    <StatCard title="Submitted Value" value={formatCurrency(metrics.mySubmittedValue)} icon={DollarSign} color="purple" />
                    <StatCard title="Commissions (YTD)" value={formatCurrency(metrics.myCommissions)} icon={TrendingUp} color="teal" href="/commissions" />
                    <StatCard title="Award Value" value={formatCurrency(metrics.myAwardValue)} icon={CheckCircle} color="green" />
                    <StatCard title="Active Proposals" value={metrics.myPending} icon={Target} color="orange" href="/proposals" />
                    <StatCard title="Open Tasks" value={metrics.myTasks} icon={Clock} color="indigo" />
                    <StatCard title="Follow-ups Due" value={metrics.myFollowUps} icon={AlertCircle} color={metrics.myFollowUps > 0 ? 'red' : 'green'} href="/follow-ups" />
                </div>

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
            </div>
        </AppLayout>
    );
}
