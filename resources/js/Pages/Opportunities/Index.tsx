import { Head, Link, router, useForm } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { formatCurrency, formatDate, getDueDateLabel, getDueDateColor } from '@/Lib/utils';
import { PaginatedResponse, Opportunity } from '@/Types';
import { Plus, Upload, Search, Filter, X, ExternalLink } from 'lucide-react';
import { useState } from 'react';

interface Props {
    opportunities: PaginatedResponse<Opportunity>;
    filters: Record<string, string>;
    statuses: Array<{ value: string; label: string; color: string }>;
    sources: Array<{ value: string; label: string }>;
    can: { create: boolean; import: boolean };
}

export default function OpportunitiesIndex({ opportunities, filters, statuses, sources, can }: Props) {
    const [showImportModal, setShowImportModal] = useState(false);
    const { data, setData, post, processing } = useForm({ naics_codes: [] as string[], keywords: '' });

    const handleFilter = (key: string, value: string) => {
        router.get('/opportunities', { ...filters, [key]: value || undefined }, { preserveState: true });
    };

    const clearFilter = (key: string) => {
        const newFilters = { ...filters };
        delete newFilters[key];
        router.get('/opportunities', newFilters, { preserveState: true });
    };

    const handleImport = (e: React.FormEvent) => {
        e.preventDefault();
        post('/opportunities/import/sam-gov', { onSuccess: () => setShowImportModal(false) });
    };

    return (
        <AppLayout>
            <Head title="Opportunities" />
            <div className="p-6">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Opportunities</h1>
                        <p className="text-gray-500 mt-1">{opportunities.total} total opportunities</p>
                    </div>
                    <div className="flex gap-3">
                        {can.import && (
                            <button
                                onClick={() => setShowImportModal(true)}
                                className="flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm font-medium"
                            >
                                <Upload className="h-4 w-4" />
                                Import from SAM.gov
                            </button>
                        )}
                        {can.create && (
                            <Link
                                href="/opportunities/create"
                                className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium"
                            >
                                <Plus className="h-4 w-4" />
                                Add Opportunity
                            </Link>
                        )}
                    </div>
                </div>

                {/* Filters */}
                <div className="bg-white rounded-xl border border-gray-200 p-4 mb-4">
                    <div className="flex flex-wrap gap-3">
                        <div className="relative">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                            <input
                                type="text"
                                placeholder="Search title, number, agency..."
                                defaultValue={filters.search ?? ''}
                                onKeyDown={e => e.key === 'Enter' && handleFilter('search', (e.target as HTMLInputElement).value)}
                                className="pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 w-72"
                            />
                        </div>
                        <select
                            value={filters.status ?? ''}
                            onChange={e => handleFilter('status', e.target.value)}
                            className="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">All Statuses</option>
                            {statuses.map(s => <option key={s.value} value={s.value}>{s.label}</option>)}
                        </select>
                        <select
                            value={filters.source ?? ''}
                            onChange={e => handleFilter('source', e.target.value)}
                            className="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">All Sources</option>
                            {sources.map(s => <option key={s.value} value={s.value}>{s.label}</option>)}
                        </select>
                        {Object.keys(filters).length > 0 && (
                            <button onClick={() => router.get('/opportunities')} className="flex items-center gap-1 text-sm text-red-600 hover:text-red-700">
                                <X className="h-4 w-4" /> Clear
                            </button>
                        )}
                    </div>
                </div>

                {/* Table */}
                <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="border-b border-gray-200 bg-gray-50">
                                    <th className="text-left text-xs font-medium text-gray-500 uppercase tracking-wider px-4 py-3">Title</th>
                                    <th className="text-left text-xs font-medium text-gray-500 uppercase tracking-wider px-4 py-3">Agency</th>
                                    <th className="text-left text-xs font-medium text-gray-500 uppercase tracking-wider px-4 py-3">Status</th>
                                    <th className="text-left text-xs font-medium text-gray-500 uppercase tracking-wider px-4 py-3">Value</th>
                                    <th className="text-left text-xs font-medium text-gray-500 uppercase tracking-wider px-4 py-3">Due Date</th>
                                    <th className="text-left text-xs font-medium text-gray-500 uppercase tracking-wider px-4 py-3">Source</th>
                                    <th className="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {opportunities.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={7} className="text-center py-12 text-gray-500">
                                            <Target className="h-12 w-12 mx-auto mb-3 text-gray-300" />
                                            <p className="font-medium">No opportunities found</p>
                                            <p className="text-sm mt-1">Try adjusting your filters or import from SAM.gov</p>
                                        </td>
                                    </tr>
                                ) : opportunities.data.map(opp => (
                                    <tr key={opp.id} className="hover:bg-gray-50 transition-colors">
                                        <td className="px-4 py-3">
                                            <Link href={`/opportunities/${opp.id}`} className="text-sm font-medium text-blue-600 hover:underline line-clamp-2">
                                                {opp.title}
                                            </Link>
                                            {opp.solicitation_number && (
                                                <p className="text-xs text-gray-500 mt-0.5">{opp.solicitation_number}</p>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-700">{opp.agency_name ?? '—'}</td>
                                        <td className="px-4 py-3">
                                            <StatusBadge status={opp.status} />
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-700">{formatCurrency(opp.estimated_value)}</td>
                                        <td className="px-4 py-3">
                                            <span className={`text-sm ${getDueDateColor(opp.due_date)}`}>
                                                {opp.due_date ? getDueDateLabel(opp.due_date) : '—'}
                                            </span>
                                            {opp.due_date && <p className="text-xs text-gray-400">{formatDate(opp.due_date)}</p>}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded-full">
                                                {opp.source?.replace(/_/g, ' ').toUpperCase()}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <Link href={`/opportunities/${opp.id}`} className="text-gray-400 hover:text-gray-600">
                                                <ExternalLink className="h-4 w-4" />
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {opportunities.last_page > 1 && (
                        <div className="border-t border-gray-200 px-4 py-3 flex items-center justify-between">
                            <p className="text-sm text-gray-500">
                                Showing {opportunities.from}–{opportunities.to} of {opportunities.total}
                            </p>
                            <div className="flex gap-2">
                                {opportunities.links.map((link, i) => (
                                    <button
                                        key={i}
                                        onClick={() => link.url && router.get(link.url)}
                                        disabled={!link.url}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                        className={`px-3 py-1 text-sm rounded ${link.active ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100 disabled:opacity-50'}`}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Import Modal */}
            {showImportModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center">
                    <div className="fixed inset-0 bg-black/50" onClick={() => setShowImportModal(false)} />
                    <div className="relative bg-white rounded-xl shadow-xl p-6 w-full max-w-md mx-4">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">Import from SAM.gov</h2>
                        <form onSubmit={handleImport} className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Keywords (optional)</label>
                                <input
                                    type="text"
                                    value={data.keywords}
                                    onChange={e => setData('keywords', e.target.value)}
                                    placeholder="e.g., cybersecurity, cloud, AI"
                                    className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>
                            <p className="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-3">
                                Note: No SAM.gov API key configured. This will use the demo fake data client to demonstrate the import functionality.
                            </p>
                            <div className="flex justify-end gap-3">
                                <button type="button" onClick={() => setShowImportModal(false)} className="px-4 py-2 text-sm border border-gray-200 rounded-lg hover:bg-gray-50">
                                    Cancel
                                </button>
                                <button type="submit" disabled={processing} className="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
                                    {processing ? 'Importing...' : 'Start Import'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
