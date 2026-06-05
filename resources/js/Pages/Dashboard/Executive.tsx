import { Head, Link } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { formatCurrency, formatPercent } from '@/Lib/utils';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import {
    BarChart, Bar, LineChart, Line, XAxis, YAxis, CartesianGrid,
    Tooltip, ResponsiveContainer, PieChart, Pie, Cell, Legend
} from 'recharts';
import { TrendingUp, TrendingDown, Target, Trophy, DollarSign, AlertCircle, Clock } from 'lucide-react';

interface ExecutiveMetrics {
    totalProposals: number;
    awarded: number;
    lost: number;
    winRate: number;
    lossRate: number;
    pipelineValue: number;
    awardValue: number;
    submittedThisMonth: number;
    submittedThisMonthValue: number;
    submittedThisYear: number;
    submittedThisYearValue: number;
    activeOpportunities: number;
    newOpportunitiesThisMonth: number;
    overdueTasks: number;
    overdueFollowUps: number;
    upcomingDeadlines: Array<{ id: number; proposal_number: string; project_name: string; due_date: string; status: string }>;
    proposalsByStatus: Record<string, number>;
    monthlyTrend: Array<{ month: string; submitted: number; awarded: number }>;
    topUsers: Array<{ user: string; total_proposals: number; total_value: number; won: number }>;
    sourceAnalysis: Record<string, number>;
}

const COLORS = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#14B8A6', '#F97316'];

function KpiCard({ title, value, subtitle, icon: Icon, trend }: {
    title: string; value: string; subtitle?: string;
    icon: React.ComponentType<{ className?: string }>;
    trend?: { value: number; positive: boolean };
}) {
    return (
        <div className="bg-white rounded-xl border border-gray-200 p-5">
            <div className="flex items-start justify-between">
                <div>
                    <p className="text-sm font-medium text-gray-500">{title}</p>
                    <p className="text-2xl font-bold text-gray-900 mt-1">{value}</p>
                    {subtitle && <p className="text-xs text-gray-400 mt-1">{subtitle}</p>}
                </div>
                <Icon className="h-6 w-6 text-blue-500" />
            </div>
            {trend && (
                <div className={`flex items-center gap-1 mt-3 text-xs font-medium ${trend.positive ? 'text-green-600' : 'text-red-600'}`}>
                    {trend.positive ? <TrendingUp className="h-3 w-3" /> : <TrendingDown className="h-3 w-3" />}
                    {formatPercent(trend.value)} vs last period
                </div>
            )}
        </div>
    );
}

export default function ExecutiveDashboard({ metrics }: { metrics: ExecutiveMetrics }) {
    const statusChartData = Object.entries(metrics.proposalsByStatus).map(([status, count]) => ({
        name: status.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()),
        value: count,
    }));

    const sourceData = Object.entries(metrics.sourceAnalysis).map(([source, count]) => ({
        name: source.replace(/_/g, ' ').toUpperCase(),
        count,
    }));

    return (
        <AppLayout>
            <Head title="Executive Dashboard" />
            <div className="p-6">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Executive Dashboard</h1>
                        <p className="text-gray-500 mt-1">Company-wide performance overview</p>
                    </div>
                    <Link href="/" className="text-sm text-blue-600 hover:underline">← My Dashboard</Link>
                </div>

                {/* KPI Row 1 */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <KpiCard title="Win Rate" value={formatPercent(metrics.winRate)} subtitle={`${metrics.awarded} won / ${metrics.awarded + metrics.lost} closed`} icon={Trophy} />
                    <KpiCard title="Pipeline Value" value={formatCurrency(metrics.pipelineValue)} subtitle="Active proposals" icon={Target} />
                    <KpiCard title="Award Value (All Time)" value={formatCurrency(metrics.awardValue)} subtitle="Total contract value awarded" icon={DollarSign} />
                    <KpiCard title="Total Proposals" value={String(metrics.totalProposals)} subtitle="All time" icon={Target} />
                </div>

                {/* KPI Row 2 */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <KpiCard title="Submitted This Month" value={String(metrics.submittedThisMonth)} subtitle={formatCurrency(metrics.submittedThisMonthValue)} icon={Target} />
                    <KpiCard title="Submitted This Year" value={String(metrics.submittedThisYear)} subtitle={formatCurrency(metrics.submittedThisYearValue)} icon={TrendingUp} />
                    <KpiCard title="Active Opportunities" value={String(metrics.activeOpportunities)} subtitle={`+${metrics.newOpportunitiesThisMonth} this month`} icon={Target} />
                    <KpiCard title="Overdue Items" value={String(metrics.overdueTasks + metrics.overdueFollowUps)} subtitle={`${metrics.overdueTasks} tasks, ${metrics.overdueFollowUps} follow-ups`} icon={AlertCircle} />
                </div>

                {/* Charts Row */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    {/* Monthly Trend */}
                    <div className="bg-white rounded-xl border border-gray-200 p-5">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">Proposal Activity (12 Months)</h2>
                        <ResponsiveContainer width="100%" height={250}>
                            <BarChart data={metrics.monthlyTrend}>
                                <CartesianGrid strokeDasharray="3 3" stroke="#F3F4F6" />
                                <XAxis dataKey="month" tick={{ fontSize: 11 }} />
                                <YAxis tick={{ fontSize: 11 }} />
                                <Tooltip />
                                <Legend />
                                <Bar dataKey="submitted" fill="#3B82F6" name="Submitted" radius={[4, 4, 0, 0]} />
                                <Bar dataKey="awarded" fill="#10B981" name="Awarded" radius={[4, 4, 0, 0]} />
                            </BarChart>
                        </ResponsiveContainer>
                    </div>

                    {/* Proposals by Status */}
                    <div className="bg-white rounded-xl border border-gray-200 p-5">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">Proposals by Status</h2>
                        <ResponsiveContainer width="100%" height={250}>
                            <PieChart>
                                <Pie data={statusChartData} cx="50%" cy="50%" outerRadius={80} dataKey="value" label={({ name, value }) => `${name}: ${value}`} labelLine={false}>
                                    {statusChartData.map((_, i) => <Cell key={i} fill={COLORS[i % COLORS.length]} />)}
                                </Pie>
                                <Tooltip />
                            </PieChart>
                        </ResponsiveContainer>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Top Users */}
                    <div className="bg-white rounded-xl border border-gray-200 p-5">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">Top BD Performance</h2>
                        <div className="space-y-3">
                            {metrics.topUsers.length === 0 ? (
                                <p className="text-sm text-gray-500 text-center py-6">No data yet</p>
                            ) : metrics.topUsers.map((u, i) => (
                                <div key={i} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div className="flex items-center gap-3">
                                        <span className="h-7 w-7 rounded-full bg-blue-100 text-blue-700 text-xs font-bold flex items-center justify-center">
                                            {i + 1}
                                        </span>
                                        <div>
                                            <p className="text-sm font-medium text-gray-900">{u.user}</p>
                                            <p className="text-xs text-gray-500">{u.total_proposals} proposals · {u.won} won</p>
                                        </div>
                                    </div>
                                    <span className="text-sm font-semibold text-gray-900">{formatCurrency(u.total_value)}</span>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Source Analysis */}
                    <div className="bg-white rounded-xl border border-gray-200 p-5">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">Opportunity Sources</h2>
                        <div className="space-y-3">
                            {sourceData.map((s, i) => (
                                <div key={i} className="flex items-center gap-3">
                                    <div className="h-3 w-3 rounded-full" style={{ backgroundColor: COLORS[i % COLORS.length] }} />
                                    <span className="text-sm text-gray-700 flex-1">{s.name}</span>
                                    <span className="text-sm font-semibold text-gray-900">{s.count}</span>
                                    <div className="w-24 bg-gray-100 rounded-full h-2">
                                        <div
                                            className="h-2 rounded-full"
                                            style={{
                                                backgroundColor: COLORS[i % COLORS.length],
                                                width: `${(s.count / Math.max(...sourceData.map(x => x.count))) * 100}%`
                                            }}
                                        />
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
