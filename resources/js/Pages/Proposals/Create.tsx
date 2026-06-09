import { Head, useForm } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card, CardContent } from '@/Components/ui/Card';
import { Opportunity } from '@/Types';
import { ArrowLeft, FileText } from 'lucide-react';

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
                <PageHeader
                    icon={FileText}
                    title="Create Proposal"
                    description="Start a new proposal submission."
                    actions={
                        <Button href="/proposals" variant="secondary" icon={ArrowLeft}>
                            Back
                        </Button>
                    }
                />

                <Card>
                    <CardContent className="pt-5">
                        <form onSubmit={handleSubmit} className="space-y-5">
                            <div>
                                <label className="label">Linked Opportunity</label>
                                <select value={data.opportunity_id} onChange={e => handleOpportunityChange(e.target.value)} className="select w-full">
                                    <option value="">None (standalone proposal)</option>
                                    {opportunities.map(o => (
                                        <option key={o.id} value={o.id}>
                                            {o.solicitation_number ? `[${o.solicitation_number}] ` : ''}{o.title}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="label">Project Name *</label>
                                <input type="text" value={data.project_name} onChange={e => setData('project_name', e.target.value)}
                                    className="input" required placeholder="Full project name as it will appear in the proposal" />
                                {errors.project_name && <p className="mt-1 text-xs text-destructive">{errors.project_name}</p>}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="label">Solicitation #</label>
                                    <input type="text" value={data.solicitation_number} onChange={e => setData('solicitation_number', e.target.value)}
                                        className="input" />
                                </div>
                                <div>
                                    <label className="label">Agency</label>
                                    <input type="text" value={data.agency_name} onChange={e => setData('agency_name', e.target.value)}
                                        className="input" />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="label">Proposal Value ($)</label>
                                    <input type="number" value={data.proposal_value} onChange={e => setData('proposal_value', e.target.value)}
                                        className="input" min="0" step="0.01" />
                                    {errors.proposal_value && <p className="mt-1 text-xs text-destructive">{errors.proposal_value}</p>}
                                </div>
                                <div>
                                    <label className="label">Due Date</label>
                                    <input type="date" value={data.due_date} onChange={e => setData('due_date', e.target.value)}
                                        className="input" />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="label">Primary Contact Name</label>
                                    <input type="text" value={data.primary_contact_name} onChange={e => setData('primary_contact_name', e.target.value)}
                                        className="input" />
                                </div>
                                <div>
                                    <label className="label">Win Probability (%)</label>
                                    <input type="number" value={data.win_probability} onChange={e => setData('win_probability', e.target.value)}
                                        className="input" min="0" max="100" step="1" />
                                </div>
                            </div>

                            <div>
                                <label className="label">Notes</label>
                                <textarea value={data.notes} onChange={e => setData('notes', e.target.value)} rows={4}
                                    className="input" placeholder="Any additional notes or context..." />
                            </div>

                            <div className="flex justify-end gap-3 pt-2">
                                <Button href="/proposals" variant="secondary">
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Creating…' : 'Create Proposal'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
