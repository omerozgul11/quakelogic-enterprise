import { Head, Link, usePage } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { formatCurrency, formatDate, getDueDateLabel, getDueDateColor, getStatusColor, statusLabel } from '@/Lib/utils';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { SharedProps } from '@/Types';
import {
    Target, FileText, Trophy, TrendingUp, DollarSign,
    Clock, AlertCircle, CheckCircle, ArrowUpRight, ExternalLink
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

function StatCard({ title, value, subtitle, icon: Icon, color = 'blue', href }: {
    title: string;
    value: string | number;
    subtitle?: string;
    icon: React.ComponentType<{ className?: string }>;
    color?: string;
    href?: string;
}) {
    const colorMap: Record<string, string> = {
        blue: 'bg-blue-50 text-blue-600',
        green: 'bg-green-50 text-green-600',
        purple: 'bg-purple-50 text-purple-600',
        orange: 'bg-orange-50 text-orange-600',
        red: 'bg-red-50 text-red-600',
        teal: 'bg-teal-50 text-teal-600',
    };

    const card = (
        <div className="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md transition-shadow">
            <div className="flex items-center justify-between mb-3">
                <p className="text-sm font-medium text-gray-500">{title}</p>
                <div className={`p-2 rounded-lg ${colorMap[color] ?? colorMap.blue}`}>
                    <Icon className="h-5 w-5" />
                </div>
            </div>
            <p className="text-2xl font-bold text-gray-900">{value}</p>
            {subtitle && <p className="text-xs text-gray-500 mt-1">{subtitle}</p>}
            {href && (
                <div className="mt-3 flex items-center gap-1 text-xs text-blue-600 hover:underline">
                    <span>View all</span>
                    <ArrowUpRight className="h-3 w-3" />
                </div>
            )}
        </div>
    );

    if (href) return <Link href={href}>{card}</Link>;
    return card;
}

export default function DashboardIndex({ metrics, canViewExecutiveDashboard }: Props) {
    const { auth } = usePage<SharedProps>().props;

    return (
        <AppLayout>
            <Head title="Dashboard" />
            <div className="p-6">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">
                            Welcome back, {auth.user?.name?.split(' ')[0]}
                        </h1>
                        <p className="text-gray-500 mt-1">Here's what's happening with your proposals today.</p>
                    </div>
                    {canViewExecutiveDashboard && (
                        <Link
                            href="/dashboard/executive"
                            className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium"
                        >
                            <TrendingUp className="h-4 w-4" />
                            Executive Dashboard
                        </Link>
                    )}
                </div>

                {/* Personal Stats */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <StatCard
                        title="My Submissions"
                        value={metrics.mySubmitted}
                        icon={FileText}
                        color="blue"
                        href="/proposals"
                    />
                    <StatCard
                        title="My Awards"
                        value={metrics.myAwarded}
                        icon={Trophy}
                        color="green"
                    />
                    <StatCard
                        title="Total Submitted Value"
                        value={formatCurrency(metrics.mySubmittedValue)}
                        icon={DollarSign}
                        color="purple"
                    />
                    <StatCard
                        title="Commissions (YTD)"
                        value={formatCurrency(metrics.myCommissions)}
                        icon={TrendingUp}
                        color="teal"
                        href="/commissions"
                    />
                </div>

                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <StatCard
                        title="Award Value"
                        value={formatCurrency(metrics.myAwardValue)}
                        icon={CheckCircle}
                        color="green"
                    />
                    <StatCard
                        title="Active Proposals"
                        value={metrics.myPending}
                        icon={Target}
                        color="orange"
                        href="/proposals"
                    />
                    <StatCard
                        title="Open Tasks"
                        value={metrics.myTasks}
                        icon={Clock}
                        color="orange"
                    />
                    <StatCard
                        title="Follow-ups Due"
                        value={metrics.myFollowUps}
                        icon={AlertCircle}
                        color={metrics.myFollowUps > 0 ? 'red' : 'green'}
                        href="/follow-ups"
                    />
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Upcoming Deadlines */}
                    <div className="bg-white rounded-xl border border-gray-200 p-5">
                        <div className="flex items-center justify-between mb-4">
                            <h2 className="text-lg font-semibold text-gray-900">Upcoming Deadlines</h2>
                            <Link href="/proposals" className="text-sm text-blue-600 hover:underline">View all</Link>
                        </div>
                        {metrics.myUpcomingDeadlines.length === 0 ? (
                            <p className="text-sm text-gray-500 py-8 text-center">No upcoming deadlines in the next 30 days.</p>
                        ) : (
                            <div className="space-y-3">
                                {metrics.myUpcomingDeadlines.map(proposal => (
                                    <Link
                                        key={proposal.id}
                                        href={`/proposals/${proposal.id}`}
                                        className="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors"
                                    >
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-medium text-gray-900 truncate">{proposal.project_name}</p>
                                            <p className="text-xs text-gray-500">{proposal.proposal_number}</p>
                                        </div>
                                        <div className="flex items-center gap-2 ml-3">
                                            <StatusBadge status={proposal.status} />
                                            <span className={`text-xs whitespace-nowrap ${getDueDateColor(proposal.due_date)}`}>
                                                {getDueDateLabel(proposal.due_date)}
                                            </span>
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Company Overview */}
                    <div className="bg-white rounded-xl border border-gray-200 p-5">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">Company This Month</h2>
                        <div className="space-y-4">
                            <div className="flex items-center justify-between py-3 border-b border-gray-100">
                                <span className="text-sm text-gray-600">Total Proposals (YTD)</span>
                                <span className="text-sm font-semibold text-gray-900">{metrics.companyTotalProposals}</span>
                            </div>
                            <div className="flex items-center justify-between py-3 border-b border-gray-100">
                                <span className="text-sm text-gray-600">Submissions This Month</span>
                                <span className="text-sm font-semibold text-gray-900">{metrics.companyMonthlySubmissions}</span>
                            </div>
                            <div className="flex items-center justify-between py-3 border-b border-gray-100">
                                <span className="text-sm text-gray-600">Value Submitted This Month</span>
                                <span className="text-sm font-semibold text-gray-900">{formatCurrency(metrics.companyMonthlyValue)}</span>
                            </div>
                            <div className="pt-2">
                                <div className="flex gap-3">
                                    <Link href="/opportunities" className="flex-1 text-center py-2 bg-blue-50 text-blue-700 rounded-lg text-sm font-medium hover:bg-blue-100 transition-colors">
                                        View Opportunities
                                    </Link>
                                    <Link href="/proposals/create" className="flex-1 text-center py-2 bg-green-50 text-green-700 rounded-lg text-sm font-medium hover:bg-green-100 transition-colors">
                                        New Proposal
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
