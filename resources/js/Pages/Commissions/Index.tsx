import { Head, Link, router } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { Commission, PaginatedResponse } from '@/Types';
import { formatCurrency, formatDate } from '@/Lib/utils';
import { DollarSign, Check, X } from 'lucide-react';

interface Props {
    commissions: PaginatedResponse<Commission & {
        user: { id: number; name: string };
        proposal: { id: number; proposal_number: string; project_name: string } | null;
    }>;
    filters: Record<string, string>;
    can: { approve: boolean; viewAll: boolean };
    summary: { total: number; pending: number; approved: number };
}

export default function CommissionsIndex({ commissions, filters, can, summary }: Props) {
    const handleFilter = (key: string, value: string) => {
        router.get('/commissions', { ...filters, [key]: value || undefined }, { preserveState: true });
    };

    const handleApprove = (id: number) => {
        router.post(`/commissions/${id}/approve`);
    };

    return (
        <AppLayout>
            <Head title="Commissions" />
            <div className="p-6">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Commissions</h1>
                        <p className="text-gray-500 mt-1">{commissions.total} records</p>
                    </div>
                    {can.viewAll && (
                        <Link href="/commissions/rules" className="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">
                            Manage Rules
                        </Link>
                    )}
                </div>

                {/* Summary Cards */}
                <div className="grid grid-cols-3 gap-4 mb-6">
                    {[
                        { label: 'Total', value: formatCurrency(summary.total), icon: DollarSign, color: 'blue' },
                        { label: 'Pending Approval', value: formatCurrency(summary.pending), icon: DollarSign, color: 'amber' },
                        { label: 'Approved', value: formatCurrency(summary.approved), icon: Check, color: 'green' },
                    ].map(({ label, value, icon: Icon, color }) => (
                        <div key={label} className="bg-white rounded-xl border border-gray-200 p-5">
                            <p className="text-sm font-medium text-gray-500">{label}</p>
                            <p className="text-2xl font-bold text-gray-900 mt-1">{value}</p>
                        </div>
                    ))}
                </div>

                <div className="bg-white rounded-xl border border-gray-200 p-4 mb-4">
                    <div className="flex gap-3">
                        <select value={filters.status ?? ''} onChange={e => handleFilter('status', e.target.value)}
                            className="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="paid">Paid</option>
                            <option value="disputed">Disputed</option>
                        </select>
                        <input type="month" value={filters.period ?? ''} onChange={e => handleFilter('period', e.target.value)}
                            className="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                        {Object.keys(filters).length > 0 && (
                            <button onClick={() => router.get('/commissions')} className="flex items-center gap-1 text-sm text-red-600">
                                <X className="h-4 w-4" /> Clear
                            </button>
                        )}
                    </div>
                </div>

                <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-gray-200 bg-gray-50">
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Person</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Proposal</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Period</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Base</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Commission</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Status</th>
                                {can.approve && <th className="px-4 py-3"></th>}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {commissions.data.length === 0 ? (
                                <tr><td colSpan={7} className="text-center py-12 text-gray-500">
                                    <DollarSign className="h-12 w-12 mx-auto mb-3 text-gray-300" />
                                    <p>No commissions found</p>
                                </td></tr>
                            ) : commissions.data.map(c => (
                                <tr key={c.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 text-sm font-medium text-gray-900">{c.user.name}</td>
                                    <td className="px-4 py-3">
                                        {c.proposal ? (
                                            <Link href={`/proposals/${c.proposal.id}`} className="text-sm text-blue-600 hover:underline font-mono">
                                                {c.proposal.proposal_number}
                                            </Link>
                                        ) : '—'}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-gray-600">{c.period_month}</td>
                                    <td className="px-4 py-3 text-sm text-gray-700">{formatCurrency(c.base_amount)}</td>
                                    <td className="px-4 py-3 text-sm font-semibold text-green-700">{formatCurrency(c.commission_amount)}</td>
                                    <td className="px-4 py-3">
                                        <span className={`text-xs px-2 py-1 rounded-full font-medium ${
                                            c.status === 'approved' ? 'bg-green-100 text-green-700' :
                                            c.status === 'paid' ? 'bg-blue-100 text-blue-700' :
                                            c.status === 'disputed' ? 'bg-red-100 text-red-700' :
                                            'bg-amber-100 text-amber-700'
                                        }`}>{c.status}</span>
                                    </td>
                                    {can.approve && (
                                        <td className="px-4 py-3">
                                            {c.status === 'pending' && (
                                                <button onClick={() => handleApprove(c.id)}
                                                    className="text-xs text-green-600 border border-green-200 rounded px-2 py-1 hover:bg-green-50">
                                                    Approve
                                                </button>
                                            )}
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
