import { Head, useForm } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card, CardContent } from '@/Components/ui/Card';
import { ArrowLeft, Target } from 'lucide-react';

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
                <PageHeader
                    icon={Target}
                    title="Add Opportunity"
                    description="Create a new opportunity in your pipeline"
                    actions={
                        <Button variant="secondary" icon={ArrowLeft} href="/opportunities">
                            Back
                        </Button>
                    }
                />

                <form onSubmit={handleSubmit}>
                    <Card className="animate-rise">
                        <CardContent className="space-y-5 pt-5">
                            <div>
                                <label className="label">Title *</label>
                                <input type="text" value={data.title} onChange={e => setData('title', e.target.value)}
                                    className="input" placeholder="Opportunity title" required />
                                {errors.title && <p className="mt-1 text-xs text-destructive">{errors.title}</p>}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="label">Source</label>
                                    <select value={data.source} onChange={e => setData('source', e.target.value)} className="select">
                                        {SOURCES.map(s => <option key={s} value={s}>{s.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="label">Status</label>
                                    <select value={data.status} onChange={e => setData('status', e.target.value)} className="select">
                                        {STATUSES.map(s => <option key={s} value={s}>{s.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</option>)}
                                    </select>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="label">Solicitation #</label>
                                    <input type="text" value={data.solicitation_number} onChange={e => setData('solicitation_number', e.target.value)}
                                        className="input" placeholder="e.g. FA8522-24-R-0001" />
                                </div>
                                <div>
                                    <label className="label">Agency</label>
                                    <input type="text" value={data.agency_name} onChange={e => setData('agency_name', e.target.value)}
                                        className="input" placeholder="Department of Defense" />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="label">Estimated Value ($)</label>
                                    <input type="number" value={data.estimated_value} onChange={e => setData('estimated_value', e.target.value)}
                                        className="input" placeholder="0.00" min="0" step="0.01" />
                                </div>
                                <div>
                                    <label className="label">Due Date</label>
                                    <input type="date" value={data.due_date} onChange={e => setData('due_date', e.target.value)}
                                        className="input" />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="label">NAICS Code</label>
                                    <input type="text" value={data.naics_code} onChange={e => setData('naics_code', e.target.value)}
                                        className="input" placeholder="541512" maxLength={6} />
                                </div>
                                <div>
                                    <label className="label">Set-Aside Type</label>
                                    <input type="text" value={data.set_aside_type} onChange={e => setData('set_aside_type', e.target.value)}
                                        className="input" placeholder="Small Business" />
                                </div>
                            </div>

                            <div>
                                <label className="label">Description</label>
                                <textarea value={data.description} onChange={e => setData('description', e.target.value)} rows={5}
                                    className="input" placeholder="Opportunity description..." />
                            </div>

                            <div>
                                <label className="label">Source URL</label>
                                <input type="url" value={data.source_url} onChange={e => setData('source_url', e.target.value)}
                                    className="input" placeholder="https://sam.gov/opp/..." />
                            </div>

                            <div className="flex justify-end gap-3 pt-2">
                                <Button variant="secondary" href="/opportunities">
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Creating...' : 'Create Opportunity'}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </AppLayout>
    );
}
