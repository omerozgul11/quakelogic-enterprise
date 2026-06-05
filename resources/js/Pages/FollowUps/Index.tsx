import { Head, Link, router, useForm } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { formatDate } from '@/Lib/utils';
import { Bell, Plus, X, Check } from 'lucide-react';
import { useState } from 'react';

interface FollowUp {
    id: number;
    type: string;
    subject: string;
    status: string;
    scheduled_date: string;
    sent_at: string | null;
    assigned_to_user: { id: number; name: string } | null;
    proposal: { id: number; proposal_number: string } | null;
    contact: { id: number; first_name: string; last_name: string } | null;
}

interface Props {
    followUps: {
        data: FollowUp[];
        total: number;
        current_page: number;
        last_page: number;
    };
    filters: Record<string, string>;
}

const STATUS_STYLES: Record<string, string> = {
    scheduled: 'bg-blue-100 text-blue-700',
    sent: 'bg-green-100 text-green-700',
    overdue: 'bg-red-100 text-red-700',
    responded: 'bg-purple-100 text-purple-700',
    cancelled: 'bg-gray-100 text-gray-500',
};

export default function FollowUpsIndex({ followUps, filters }: Props) {
    const handleFilter = (value: string) => {
        router.get('/follow-ups', value ? { status: value } : {}, { preserveState: true });
    };

    const handleMarkSent = (id: number) => {
        router.patch(`/follow-ups/${id}`, { status: 'sent' });
    };

    return (
        <AppLayout>
            <Head title="Follow-Ups" />
            <div className="p-6">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Follow-Ups</h1>
                        <p className="text-gray-500 mt-1">{followUps.total} total</p>
                    </div>
                </div>

                <div className="bg-white rounded-xl border border-gray-200 p-4 mb-4">
                    <div className="flex gap-3">
                        <select value={filters.status ?? ''} onChange={e => handleFilter(e.target.value)}
                            className="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All Statuses</option>
                            {Object.keys(STATUS_STYLES).map(s => (
                                <option key={s} value={s}>{s.charAt(0).toUpperCase() + s.slice(1)}</option>
                            ))}
                        </select>
                        {filters.status && (
                            <button onClick={() => router.get('/follow-ups')} className="flex items-center gap-1 text-sm text-red-600">
                                <X className="h-4 w-4" /> Clear
                            </button>
                        )}
                    </div>
                </div>

                <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-gray-200 bg-gray-50">
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Subject</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Type</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Status</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Scheduled</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Assigned To</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Linked</th>
                                <th className="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {followUps.data.length === 0 ? (
                                <tr><td colSpan={7} className="text-center py-12 text-gray-500">
                                    <Bell className="h-12 w-12 mx-auto mb-3 text-gray-300" />
                                    <p>No follow-ups found.</p>
                                </td></tr>
                            ) : followUps.data.map(f => (
                                <tr key={f.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 text-sm font-medium text-gray-900 max-w-xs truncate">{f.subject}</td>
                                    <td className="px-4 py-3">
                                        <span className="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded-full capitalize">{f.type}</span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`text-xs px-2 py-1 rounded-full font-medium ${STATUS_STYLES[f.status] ?? 'bg-gray-100 text-gray-600'}`}>
                                            {f.status}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-sm text-gray-600">{formatDate(f.scheduled_date)}</td>
                                    <td className="px-4 py-3 text-sm text-gray-600">{f.assigned_to_user?.name ?? '—'}</td>
                                    <td className="px-4 py-3">
                                        {f.proposal && (
                                            <Link href={`/proposals/${f.proposal.id}`} className="text-xs text-blue-600 hover:underline font-mono">
                                                {f.proposal.proposal_number}
                                            </Link>
                                        )}
                                        {f.contact && (
                                            <span className="text-xs text-gray-600">{f.contact.first_name} {f.contact.last_name}</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        {f.status === 'scheduled' && (
                                            <button onClick={() => handleMarkSent(f.id)}
                                                className="text-xs text-green-600 border border-green-200 rounded px-2 py-1 hover:bg-green-50 flex items-center gap-1">
                                                <Check className="h-3 w-3" /> Sent
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
