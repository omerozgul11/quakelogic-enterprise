import { Head, useForm } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/Card';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { EmptyState } from '@/Components/ui/EmptyState';
import { formatPercent, formatCurrency } from '@/Lib/utils';
import { ArrowLeft, Plus, Percent } from 'lucide-react';
import { useState } from 'react';

interface CommissionRule {
    id: number;
    name: string;
    type: string;
    rate: number | null;
    fixed_amount: number | null;
    tier_config: Array<{ min: number; max: number | null; rate: number }> | null;
    is_default: boolean;
    is_active: boolean;
}

interface Props {
    rules: CommissionRule[];
}

export default function CommissionRules({ rules }: Props) {
    const [showForm, setShowForm] = useState(false);
    const { data, setData, post, errors, processing, reset } = useForm({
        name: '',
        type: 'percentage',
        rate: '',
        fixed_amount: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/commissions/rules', { onSuccess: () => { setShowForm(false); reset(); } });
    };

    return (
        <AppLayout>
            <Head title="Commission Rules" />
            <div className="p-6 max-w-4xl mx-auto">
                <PageHeader
                    icon={Percent}
                    title="Commission Rules"
                    description="Configure how commissions are calculated"
                    actions={
                        <>
                            <Button variant="secondary" icon={ArrowLeft} href="/commissions">
                                Back
                            </Button>
                            <Button icon={Plus} onClick={() => setShowForm(!showForm)}>
                                Add Rule
                            </Button>
                        </>
                    }
                />

                {showForm && (
                    <form onSubmit={handleSubmit}>
                        <Card className="animate-scale-in mb-6">
                            <CardHeader>
                                <CardTitle>New Commission Rule</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="label">Rule Name</label>
                                        <input type="text" value={data.name} onChange={e => setData('name', e.target.value)}
                                            className="input" required />
                                        {errors.name && <p className="mt-1 text-xs text-destructive">{errors.name}</p>}
                                    </div>
                                    <div>
                                        <label className="label">Type</label>
                                        <select value={data.type} onChange={e => setData('type', e.target.value)} className="select">
                                            <option value="percentage">Percentage</option>
                                            <option value="fixed">Fixed Amount</option>
                                            <option value="tiered">Tiered</option>
                                        </select>
                                    </div>
                                </div>
                                {data.type === 'percentage' && (
                                    <div>
                                        <label className="label">Rate (%)</label>
                                        <input type="number" value={data.rate} onChange={e => setData('rate', e.target.value)}
                                            min="0" max="100" step="0.1" className="input" />
                                    </div>
                                )}
                                {data.type === 'fixed' && (
                                    <div>
                                        <label className="label">Fixed Amount ($)</label>
                                        <input type="number" value={data.fixed_amount} onChange={e => setData('fixed_amount', e.target.value)}
                                            min="0" step="0.01" className="input" />
                                    </div>
                                )}
                                <div className="flex gap-2">
                                    <Button type="submit" disabled={processing}>
                                        Create Rule
                                    </Button>
                                    <Button type="button" variant="secondary" onClick={() => setShowForm(false)}>
                                        Cancel
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </form>
                )}

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b border-border bg-secondary/40">
                                <tr>
                                    <th className="th">Rule</th>
                                    <th className="th">Type</th>
                                    <th className="th">Rate / Amount</th>
                                    <th className="th">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {rules.length === 0 ? (
                                    <tr>
                                        <td colSpan={4}>
                                            <EmptyState
                                                icon={Percent}
                                                title="No commission rules yet"
                                                description="Create a rule to define how commissions are calculated."
                                                action={<Button icon={Plus} onClick={() => setShowForm(true)}>Add Rule</Button>}
                                            />
                                        </td>
                                    </tr>
                                ) : rules.map(rule => (
                                    <tr key={rule.id} className="row-link">
                                        <td className="td">
                                            <p className="font-medium text-foreground">{rule.name}</p>
                                            {rule.is_default && <span className="text-xs text-primary">Default rule</span>}
                                        </td>
                                        <td className="td">
                                            <span className="chip capitalize">{rule.type}</span>
                                        </td>
                                        <td className="td text-muted-foreground">
                                            {rule.type === 'percentage' && rule.rate != null && formatPercent(rule.rate / 100)}
                                            {rule.type === 'fixed' && rule.fixed_amount != null && formatCurrency(rule.fixed_amount)}
                                            {rule.type === 'tiered' && rule.tier_config && (
                                                <span>{rule.tier_config.length} tiers</span>
                                            )}
                                        </td>
                                        <td className="td">
                                            <StatusBadge status={rule.is_active ? 'active' : 'inactive'} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>
        </AppLayout>
    );
}
