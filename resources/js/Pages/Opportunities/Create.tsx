import { Head, Link, useForm } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { ArrowLeft } from 'lucide-react';

const SOURCES = ['manual', 'sam_gov', 'bidprime', 'govwin', 'merx', 'world_bank', 'referral', 'repeat_business'];
const STATUSES = ['new', 'identified', 'monitoring', 'qualified', 'no_bid', 'pursuing', 'proposal_in_progress', 'submitted'];

export default function OpportunityCreate() {
    const { data, setData, post, errors, processing } = useForm({
        title: '',
        source: 'manual',
        status: 'new',
        solicitation_number: '',
        agency_name: '',
        naics_code: '',
        estimated_value: '',
        due_date: '',
        description: '',
        set_aside_type: '',
        place_of_performance: '',
        source_url: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/opportunities');
    };

    return (
        <AppLayout>
            <Head title="New Opportunity" />
            <div className="p-6 max-w-3xl mx-auto">
                <div className="flex items-center gap-3 mb-6">
                    <Link href="/opportunities" className="text-gray-400 hover:text-gray-600">
                        <ArrowLeft className="h-5 w-5" />
                    </Link>
                    <h1 className="text-xl font-bold text-gray-900">Add Opportunity</h1>
                </div>

                <form onSubmit={handleSubmit} className="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                        <input type="text" value={data.title} onChange={e => setData('title', e.target.value)}
                            className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Opportunity title" required />
                        {errors.title && <p className="text-red-600 text-xs mt-1">{errors.title}</p>}
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Source</label>
                            <select value={data.source} onChange={e => setData('source', e.target.value)}
                                className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                {SOURCES.map(s => <option key={s} value={s}>{s.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select value={data.status} onChange={e => setData('status', e.target.value)}
                                className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                {STATUSES.map(s => <option key={s} value={s}>{s.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</option>)}
                            </select>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Solicitation #</label>
                            <input type="text" value={data.solicitation_number} onChange={e => setData('solicitation_number', e.target.value)}
                                className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="e.g. FA8522-24-R-0001" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Agency</label>
                            <input type="text" value={data.agency_name} onChange={e => setData('agency_name', e.target.value)}
                                className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Department of Defense" />
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Estimated Value ($)</label>
                            <input type="number" value={data.estimated_value} onChange={e => setData('estimated_value', e.target.value)}
                                className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="0.00" min="0" step="0.01" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
                            <input type="date" value={data.due_date} onChange={e => setData('due_date', e.target.value)}
                                className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">NAICS Code</label>
                            <input type="text" value={data.naics_code} onChange={e => setData('naics_code', e.target.value)}
                                className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="541512" maxLength={6} />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Set-Aside Type</label>
                            <input type="text" value={data.set_aside_type} onChange={e => setData('set_aside_type', e.target.value)}
                                className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Small Business" />
                        </div>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea value={data.description} onChange={e => setData('description', e.target.value)} rows={5}
                            className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Opportunity description..." />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Source URL</label>
                        <input type="url" value={data.source_url} onChange={e => setData('source_url', e.target.value)}
                            className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="https://sam.gov/opp/..." />
                    </div>

                    <div className="flex justify-end gap-3 pt-2">
                        <Link href="/opportunities" className="px-4 py-2 text-sm border border-gray-200 rounded-lg hover:bg-gray-50">
                            Cancel
                        </Link>
                        <button type="submit" disabled={processing}
                            className="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
                            {processing ? 'Creating...' : 'Create Opportunity'}
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
