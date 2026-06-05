import { Head, Link, router } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { formatDate } from '@/Lib/utils';
import { CapturePlan } from '@/Types';
import { PaginatedResponse } from '@/Types';
import { Target, ExternalLink, Search, X } from 'lucide-react';

const STAGES = ['discovery', 'qualification', 'pursuit', 'proposal_development', 'submission', 'evaluation', 'award', 'execution'];

interface Props {
    capturePlans: PaginatedResponse<CapturePlan & {
        opportunity: { id: number; title: string; agency_name: string | null; due_date: string | null };
        owner: { id: number; name: string } | null;
    }>;
    filters: Record<string, string>;
}

export default function CaptureIndex({ capturePlans, filters }: Props) {
    const handleFilter = (key: string, value: string) => {
        router.get('/capture', { ...filters, [key]: value || undefined }, { preserveState: true });
    };

    return (
        <AppLayout>
            <Head title="Capture Management" />
            <div className="p-6">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Capture Management</h1>
                        <p className="text-gray-500 mt-1">{capturePlans.total} active capture plans</p>
                    </div>
                </div>

                <div className="bg-white rounded-xl border border-gray-200 p-4 mb-4">
                    <div className="flex flex-wrap gap-3">
                        <div className="relative">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                            <input type="text" placeholder="Search opportunities..."
                                defaultValue={filters.search ?? ''}
                                onKeyDown={e => e.key === 'Enter' && handleFilter('search', (e.target as HTMLInputElement).value)}
                                className="pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 w-64" />
                        </div>
                        <select value={filters.stage ?? ''} onChange={e => handleFilter('stage', e.target.value)}
                            className="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All Stages</option>
                            {STAGES.map(s => (
                                <option key={s} value={s}>{s.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</option>
                            ))}
                        </select>
                        {Object.keys(filters).length > 0 && (
                            <button onClick={() => router.get('/capture')} className="flex items-center gap-1 text-sm text-red-600">
                                <X className="h-4 w-4" /> Clear
                            </button>
                        )}
                    </div>
                </div>

                {/* Stage pipeline view */}
                <div className="grid grid-cols-4 lg:grid-cols-8 gap-2 mb-6 overflow-x-auto">
                    {STAGES.map(stage => {
                        const count = capturePlans.data.filter(p => {
                            const s = typeof p.stage === 'string' ? p.stage : (p.stage as any)?.value ?? '';
                            return s === stage;
                        }).length;
                        return (
                            <button key={stage} onClick={() => handleFilter('stage', filters.stage === stage ? '' : stage)}
                                className={`text-center p-3 rounded-lg border text-xs font-medium transition-colors ${
                                    filters.stage === stage
                                        ? 'bg-blue-600 text-white border-blue-600'
                                        : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50'
                                }`}>
                                <p className="text-lg font-bold">{count}</p>
                                <p className="capitalize">{stage.replace(/_/g, ' ')}</p>
                            </button>
                        );
                    })}
                </div>

                <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-gray-200 bg-gray-50">
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Opportunity</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Stage</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Win Prob.</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Owner</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Due Date</th>
                                <th className="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {capturePlans.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="text-center py-12 text-gray-500">
                                        <Target className="h-12 w-12 mx-auto mb-3 text-gray-300" />
                                        <p className="font-medium">No capture plans found</p>
                                    </td>
                                </tr>
                            ) : capturePlans.data.map(plan => {
                                const stage = typeof plan.stage === 'string' ? plan.stage : (plan.stage as any)?.value ?? 'discovery';
                                return (
                                    <tr key={plan.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3">
                                            <Link href={`/capture/${plan.id}`} className="text-sm font-medium text-blue-600 hover:underline line-clamp-1">
                                                {plan.opportunity?.title ?? 'Unknown'}
                                            </Link>
                                            <p className="text-xs text-gray-500">{plan.opportunity?.agency_name}</p>
                                        </td>
                                        <td className="px-4 py-3"><StatusBadge status={stage} /></td>
                                        <td className="px-4 py-3 text-sm text-gray-700">
                                            {plan.win_probability ? `${plan.win_probability}%` : '—'}
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-700">{plan.owner?.name ?? '—'}</td>
                                        <td className="px-4 py-3 text-sm text-gray-500">
                                            {plan.opportunity?.due_date ? formatDate(plan.opportunity.due_date) : '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Link href={`/capture/${plan.id}`} className="text-gray-400 hover:text-gray-600">
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
