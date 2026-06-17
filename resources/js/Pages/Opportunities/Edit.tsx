import { Head, useForm } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card, CardContent } from '@/Components/ui/Card';
import { Select } from '@/Components/ui/Select';
import { NumberInput } from '@/Components/ui/NumberInput';
import { ArrowLeft, Target } from 'lucide-react';

interface OpportunityData {
    id: number;
    title: string;
    solicitation_number: string | null;
    status: string;
    agency_name: string | null;
    assigned_to: number | null;
    estimated_value: number | string | null;
    due_date: string | null;
    naics_code: string | null;
    description: string | null;
    notes: string | null;
}

interface Props {
    opportunity: OpportunityData;
    agencies: Array<{ id: number; name: string }>;
    users: Array<{ id: number; name: string }>;
    statuses: Array<{ value: string; label: string }>;
}

export default function OpportunityEdit({ opportunity, users, statuses }: Props) {
    const { data, setData, put, errors, processing } = useForm({
        title: opportunity.title ?? '',
        solicitation_number: opportunity.solicitation_number ?? '',
        status: opportunity.status ?? 'new',
        agency_name: opportunity.agency_name ?? '',
        assigned_to: opportunity.assigned_to ? String(opportunity.assigned_to) : '',
        estimated_value: opportunity.estimated_value != null ? String(opportunity.estimated_value) : '',
        due_date: opportunity.due_date ? String(opportunity.due_date).slice(0, 10) : '',
        naics_code: opportunity.naics_code ?? '',
        description: opportunity.description ?? '',
        notes: opportunity.notes ?? '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/opportunities/${opportunity.id}`);
    };

    return (
        <AppLayout>
            <Head title={`Edit — ${opportunity.title}`} />
            <div className="p-6 max-w-3xl mx-auto">
                <PageHeader
                    icon={Target}
                    title="Edit Opportunity"
                    description="Update this opportunity's details"
                    actions={
                        <Button variant="secondary" icon={ArrowLeft} href={`/opportunities/${opportunity.id}`}>
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
                                    <label className="label">Status</label>
                                    <Select
                                        value={data.status}
                                        onChange={v => setData('status', v)}
                                        options={statuses.map(s => ({ value: s.value, label: s.label }))}
                                        className="w-full"
                                    />
                                    {errors.status && <p className="mt-1 text-xs text-destructive">{errors.status}</p>}
                                </div>
                                <div>
                                    <label className="label">Assigned To</label>
                                    <Select
                                        value={data.assigned_to}
                                        onChange={v => setData('assigned_to', v)}
                                        placeholder="— Unassigned —"
                                        options={[{ value: '', label: '— Unassigned —' }, ...users.map(u => ({ value: String(u.id), label: u.name }))]}
                                        className="w-full"
                                    />
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
                                    <NumberInput value={data.estimated_value} onChange={e => setData('estimated_value', e.target.value)}
                                        className="input" placeholder="0.00" />
                                </div>
                                <div>
                                    <label className="label">Due Date</label>
                                    <input type="date" value={data.due_date} onChange={e => setData('due_date', e.target.value)}
                                        className="input" />
                                </div>
                            </div>

                            <div>
                                <label className="label">NAICS Code</label>
                                <input type="text" value={data.naics_code} onChange={e => setData('naics_code', e.target.value)}
                                    className="input" placeholder="541512" maxLength={20} />
                            </div>

                            <div>
                                <label className="label">Description</label>
                                <textarea value={data.description} onChange={e => setData('description', e.target.value)} rows={5}
                                    className="input" placeholder="Opportunity description..." />
                            </div>

                            <div>
                                <label className="label">Notes</label>
                                <textarea value={data.notes} onChange={e => setData('notes', e.target.value)} rows={3}
                                    className="input" placeholder="Internal notes..." />
                            </div>

                            <div className="flex justify-end gap-3 pt-2">
                                <Button variant="secondary" href={`/opportunities/${opportunity.id}`}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Saving...' : 'Save Changes'}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </AppLayout>
    );
}
