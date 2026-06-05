import { Head, Link, useForm } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { Opportunity } from '@/Types';
import { ArrowLeft } from 'lucide-react';

interface Props {
    opportunities: Array<{ id: number; title: string; solicitation_number: string | null }>;
}

export default function ProposalCreate({ opportunities }: Props) {
    const { data, setData, post, errors, processing } = useForm({
        opportunity_id: '',
        project_name: '',
        solicitation_number: '',
        agency_name: '',
        proposal_value: '',
        due_date: '',
        primary_contact_name: '',
        primary_contact_email: '',
        win_probability: '',
        notes: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/proposals');
    };

    const handleOpportunityChange = (id: string) => {
        setData('opportunity_id', id);
        if (id) {
            const opp = opportunities.find(o => String(o.id) === id);
            if (opp) {
                setData(prev => ({
                    ...prev,
                    opportunity_id: id,
                    project_name: opp.title,
                    solicitation_number: opp.solicitation_number ?? '',
                }));
            }
        }
    };

    return (
        <AppLayout>
            <Head title="New Proposal" />
            <div className="p-6 max-w-3xl mx-auto">
                <div className="flex items-center gap-3 mb-6">
                    <Link href="/proposals" className="text-gray-400 hover:text-gray-600">
                        <ArrowLeft className="h-5 w-5" />
                    </Link>
                    <h1 className="text-xl font-bold text-gray-900">Create Proposal</h1>
                </div>

                <form onSubmit={handleSubmit} className="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Linked Opportunity</label>
                        <select value={data.opportunity_id} onChange={e => handleOpportunityChange(e.target.value)}
                            className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">None (standalone proposal)</option>
                            {opportunities.map(o => (
                                <option key={o.id} value={o.id}>
                                    {o.solicitation_number ? `[${o.solicitation_number}] ` : ''}{o.title}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Project Name *</label>
                        <input type="text" value={data.project_name} onChange={e => setData('project_name', e.target.value)}
                            className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required placeholder="Full project name as it will appear in the proposal" />
                        {errors.project_name && <p className="text-red-600 text-xs mt-1">{errors.project_name}</p>}
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Solicitation #</label>
                            <input type="text" value={data.solicitation_number} onChange={e => setData('solicitation_number', e.target.value)}
                                className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Agency</label>
                            <input type="text" value={data.agency_name} onChange={e => setData('agency_name', e.target.value)}
                                className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Proposal Value ($)</label>
                            <input type="number" value={data.proposal_value} onChange={e => setData('proposal_value', e.target.value)}
                                className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                min="0" step="0.01" />
                            {errors.proposal_value && <p className="text-red-600 text-xs mt-1">{errors.proposal_value}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
                            <input type="date" value={data.due_date} onChange={e => setData('due_date', e.target.value)}
                                className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Primary Contact Name</label>
                            <input type="text" value={data.primary_contact_name} onChange={e => setData('primary_contact_name', e.target.value)}
                                className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Win Probability (%)</label>
                            <input type="number" value={data.win_probability} onChange={e => setData('win_probability', e.target.value)}
                                className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                min="0" max="100" step="1" />
                        </div>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea value={data.notes} onChange={e => setData('notes', e.target.value)} rows={4}
                            className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Any additional notes or context..." />
                    </div>

                    <div className="flex justify-end gap-3 pt-2">
                        <Link href="/proposals" className="px-4 py-2 text-sm border border-gray-200 rounded-lg hover:bg-gray-50">
                            Cancel
                        </Link>
                        <button type="submit" disabled={processing}
                            className="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
                            {processing ? 'Creating...' : 'Create Proposal'}
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
