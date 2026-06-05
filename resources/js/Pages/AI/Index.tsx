import { Head, Link, router, useForm } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { AiAnalysis, PaginatedResponse } from '@/Types';
import { Brain, Sparkles, ExternalLink, Clock, CheckCircle, AlertCircle } from 'lucide-react';
import { formatDateTime } from '@/Lib/utils';

interface Props {
    analyses: PaginatedResponse<AiAnalysis & {
        created_by_user: { id: number; name: string } | null;
    }>;
    filters: Record<string, string>;
    provider: string;
}

const STATUS_ICONS: Record<string, React.ComponentType<{ className?: string }>> = {
    pending: Clock,
    processing: Clock,
    completed: CheckCircle,
    failed: AlertCircle,
    cancelled: AlertCircle,
};

const STATUS_COLORS: Record<string, string> = {
    pending: 'text-amber-500',
    processing: 'text-blue-500',
    completed: 'text-green-500',
    failed: 'text-red-500',
    cancelled: 'text-gray-400',
};

export default function AiIndex({ analyses, filters, provider }: Props) {
    return (
        <AppLayout>
            <Head title="AI Assistant" />
            <div className="p-6">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 flex items-center gap-2">
                            <Brain className="h-6 w-6 text-purple-500" />
                            AI Assistant
                        </h1>
                        <p className="text-gray-500 mt-1">
                            Provider: <span className="font-medium capitalize">{provider}</span>
                            {provider === 'fake' && (
                                <span className="ml-2 text-xs text-amber-700 bg-amber-50 px-2 py-0.5 rounded-full">Demo mode</span>
                            )}
                        </p>
                    </div>
                </div>

                {/* Quick Actions */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    {[
                        { label: 'Go/No-Go Analysis', desc: 'AI-powered bid decision recommendation', type: 'go_no_go', icon: '🎯' },
                        { label: 'Win Probability', desc: 'Estimate win likelihood based on past data', type: 'win_probability', icon: '📊' },
                        { label: 'Proposal Summary', desc: 'Generate executive summary from RFP', type: 'proposal_summary', icon: '📝' },
                    ].map(action => (
                        <div key={action.type} className="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md transition-shadow">
                            <div className="text-2xl mb-2">{action.icon}</div>
                            <h3 className="text-sm font-semibold text-gray-900">{action.label}</h3>
                            <p className="text-xs text-gray-500 mt-1">{action.desc}</p>
                            <Link href={`/opportunities`} className="mt-3 block text-xs text-blue-600 hover:underline">
                                Select an opportunity →
                            </Link>
                        </div>
                    ))}
                </div>

                {/* Recent Analyses */}
                <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div className="px-4 py-3 border-b border-gray-100">
                        <h2 className="text-base font-semibold text-gray-900">Recent Analyses</h2>
                    </div>
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-gray-200 bg-gray-50">
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Type</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Subject</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Status</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">By</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Date</th>
                                <th className="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {analyses.data.length === 0 ? (
                                <tr><td colSpan={6} className="text-center py-12 text-gray-500">
                                    <Sparkles className="h-12 w-12 mx-auto mb-3 text-gray-300" />
                                    <p>No AI analyses yet. Select an opportunity to run an analysis.</p>
                                </td></tr>
                            ) : analyses.data.map(a => {
                                const statusKey = typeof a.status === 'string' ? a.status : (a.status as any)?.value ?? 'pending';
                                const StatusIcon = STATUS_ICONS[statusKey] ?? Clock;
                                return (
                                    <tr key={a.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3">
                                            <span className="text-xs bg-purple-100 text-purple-700 px-2 py-1 rounded-full capitalize">
                                                {(a.analysis_type ?? '').replace(/_/g, ' ')}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-700 max-w-xs truncate">
                                            {a.subject_type}: #{a.subject_id}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className={`flex items-center gap-1 ${STATUS_COLORS[statusKey] ?? 'text-gray-500'}`}>
                                                <StatusIcon className="h-4 w-4" />
                                                <span className="text-xs capitalize">{statusKey}</span>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-600">{a.created_by_user?.name ?? '—'}</td>
                                        <td className="px-4 py-3 text-sm text-gray-500">{formatDateTime(a.created_at)}</td>
                                        <td className="px-4 py-3">
                                            <Link href={`/ai/${a.id}`} className="text-gray-400 hover:text-gray-600">
                                                <ExternalLink className="h-4 w-4" />
                                            </Link>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
