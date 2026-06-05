import { Head } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { formatCurrency } from '@/Lib/utils';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Legend } from 'recharts';
import { BarChart2 } from 'lucide-react';

interface Props {
    proposalTrend: Array<{ year: number; month: number; total: number; awarded: number; proposal_value: number; award_value: number }>;
    commissionTrend: Array<{ period_month: string; total_commissions: number; count: number }>;
    topOpportunities: Array<{ id: number; title: string; agency_name: string | null; award_value: number }>;
}

const MONTH_NAMES = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

export default function ReportsIndex({ proposalTrend, commissionTrend, topOpportunities }: Props) {
    const chartData = proposalTrend.slice(0, 12).reverse().map(d => ({
        name: `${MONTH_NAMES[d.month - 1]} ${d.year}`,
        Submitted: d.total,
        Awarded: d.awarded,
        'Proposal Value': d.proposal_value,
    }));

    const commissionData = commissionTrend.slice(0, 6).reverse().map(d => ({
        name: d.period_month,
        Commission: Number(d.total_commissions),
    }));

    return (
        <AppLayout>
            <Head title="Reports" />
            <div className="p-6">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 flex items-center gap-2">
                            <BarChart2 className="h-6 w-6 text-blue-500" />
                            Reports & Analytics
                        </h1>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    {/* Proposal Activity */}
                    <div className="bg-white rounded-xl border border-gray-200 p-5">
                        <h2 className="text-base font-semibold text-gray-900 mb-4">Proposal Activity (Last 12 Months)</h2>
                        {chartData.length === 0 ? (
                            <p className="text-sm text-gray-500 text-center py-8">No data available.</p>
                        ) : (
                            <ResponsiveContainer width="100%" height={250}>
                                <BarChart data={chartData}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="#F3F4F6" />
                                    <XAxis dataKey="name" tick={{ fontSize: 10 }} />
                                    <YAxis tick={{ fontSize: 10 }} />
                                    <Tooltip />
                                    <Legend />
                                    <Bar dataKey="Submitted" fill="#3B82F6" radius={[4, 4, 0, 0]} />
                                    <Bar dataKey="Awarded" fill="#10B981" radius={[4, 4, 0, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        )}
                    </div>

                    {/* Commission Trend */}
                    <div className="bg-white rounded-xl border border-gray-200 p-5">
                        <h2 className="text-base font-semibold text-gray-900 mb-4">Commission Trend (Last 6 Months)</h2>
                        {commissionData.length === 0 ? (
                            <p className="text-sm text-gray-500 text-center py-8">No commission data available.</p>
                        ) : (
                            <ResponsiveContainer width="100%" height={250}>
                                <BarChart data={commissionData}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="#F3F4F6" />
                                    <XAxis dataKey="name" tick={{ fontSize: 10 }} />
                                    <YAxis tick={{ fontSize: 10 }} tickFormatter={v => `$${(v / 1000).toFixed(0)}K`} />
                                    <Tooltip formatter={(v: number) => formatCurrency(v)} />
                                    <Bar dataKey="Commission" fill="#8B5CF6" radius={[4, 4, 0, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        )}
                    </div>
                </div>

                {/* Top Awarded Opportunities */}
                <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div className="px-4 py-3 border-b border-gray-100">
                        <h2 className="text-base font-semibold text-gray-900">Top Awarded Contracts</h2>
                    </div>
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-gray-200 bg-gray-50">
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Opportunity</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Agency</th>
                                <th className="text-right text-xs font-medium text-gray-500 uppercase px-4 py-3">Award Value</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {topOpportunities.length === 0 ? (
                                <tr><td colSpan={3} className="text-center py-8 text-gray-500">No awarded contracts yet.</td></tr>
                            ) : topOpportunities.map((opp, i) => (
                                <tr key={opp.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-3">
                                            <span className="h-6 w-6 rounded-full bg-blue-100 text-blue-700 text-xs font-bold flex items-center justify-center">
                                                {i + 1}
                                            </span>
                                            <span className="text-sm text-gray-900 truncate max-w-sm">{opp.title}</span>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-sm text-gray-600">{opp.agency_name ?? '—'}</td>
                                    <td className="px-4 py-3 text-sm font-semibold text-green-700 text-right">{formatCurrency(opp.award_value)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
