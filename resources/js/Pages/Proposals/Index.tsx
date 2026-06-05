import { Head, Link, router } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { formatCurrency, formatDate, getDueDateLabel, getDueDateColor } from '@/Lib/utils';
import { PaginatedResponse, ProposalSubmission } from '@/Types';
import { Plus, Search, X, FileText, ExternalLink } from 'lucide-react';

interface Props {
    proposals: PaginatedResponse<ProposalSubmission>;
    filters: Record<string, string>;
    statuses: Array<{ value: string; label: string; color: string }>;
    can: { create: boolean };
}

export default function ProposalsIndex({ proposals, filters, statuses, can }: Props) {
    const handleFilter = (key: string, value: string) => {
        router.get('/proposals', { ...filters, [key]: value || undefined }, { preserveState: true });
    };

    return (
        <AppLayout>
            <Head title="Proposals" />
            <div className="p-6">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Proposals</h1>
                        <p className="text-gray-500 mt-1">{proposals.total} total proposals</p>
                    </div>
                    {can.create && (
                        <Link href="/proposals/create" className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">
                            <Plus className="h-4 w-4" />
                            New Proposal
                        </Link>
                    )}
                </div>

                <div className="bg-white rounded-xl border border-gray-200 p-4 mb-4">
                    <div className="flex flex-wrap gap-3">
                        <div className="relative">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                            <input
                                type="text"
                                placeholder="Search proposals..."
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
                        {Object.keys(filters).length > 0 && (
                            <button onClick={() => router.get('/proposals')} className="flex items-center gap-1 text-sm text-red-600">
                                <X className="h-4 w-4" /> Clear
                            </button>
                        )}
                    </div>
                </div>

                <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-gray-200 bg-gray-50">
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Proposal #</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Project</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Agency</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Status</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Value</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Due Date</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Owner</th>
                                <th className="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {proposals.data.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="text-center py-12 text-gray-500">
                                        <FileText className="h-12 w-12 mx-auto mb-3 text-gray-300" />
                                        <p className="font-medium">No proposals found</p>
                                        <p className="text-sm mt-1">Create your first proposal to get started</p>
                                    </td>
                                </tr>
                            ) : proposals.data.map(proposal => (
                                <tr key={proposal.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3">
                                        <Link href={`/proposals/${proposal.id}`} className="text-sm font-mono text-blue-600 hover:underline">
                                            {proposal.proposal_number}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3">
                                        <p className="text-sm font-medium text-gray-900 line-clamp-2">{proposal.project_name}</p>
                                        {proposal.solicitation_number && (
                                            <p className="text-xs text-gray-500">{proposal.solicitation_number}</p>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-gray-700">{proposal.agency?.name ?? '—'}</td>
                                    <td className="px-4 py-3">
                                        <StatusBadge status={typeof proposal.status === 'string' ? proposal.status : (proposal.status as any)?.value ?? 'draft'} />
                                    </td>
                                    <td className="px-4 py-3 text-sm text-gray-700">
                                        {proposal.award_value ? (
                                            <span className="text-green-700 font-medium">{formatCurrency(proposal.award_value)}</span>
                                        ) : formatCurrency(proposal.proposal_value)}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`text-sm ${getDueDateColor(proposal.due_date)}`}>
                                            {proposal.due_date ? getDueDateLabel(proposal.due_date) : '—'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-sm text-gray-700">{proposal.owner?.name ?? '—'}</td>
                                    <td className="px-4 py-3">
                                        <Link href={`/proposals/${proposal.id}`} className="text-gray-400 hover:text-gray-600">
                                            <ExternalLink className="h-4 w-4" />
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>

                    {proposals.last_page > 1 && (
                        <div className="border-t border-gray-200 px-4 py-3 flex items-center justify-between">
                            <p className="text-sm text-gray-500">Showing {proposals.from}–{proposals.to} of {proposals.total}</p>
                            <div className="flex gap-2">
                                {proposals.links.map((link, i) => (
                                    <button key={i} onClick={() => link.url && router.get(link.url)} disabled={!link.url}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                        className={`px-3 py-1 text-sm rounded ${link.active ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100 disabled:opacity-50'}`}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
