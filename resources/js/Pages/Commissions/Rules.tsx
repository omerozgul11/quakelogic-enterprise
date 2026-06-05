import { Head, Link, useForm } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { formatPercent, formatCurrency } from '@/Lib/utils';
import { ArrowLeft, Plus } from 'lucide-react';
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
                <div className="flex items-center gap-4 mb-6">
                    <Link href="/commissions" className="text-gray-400 hover:text-gray-600"><ArrowLeft className="h-5 w-5" /></Link>
                    <h1 className="text-xl font-bold text-gray-900">Commission Rules</h1>
                    <button onClick={() => setShowForm(!showForm)}
                        className="ml-auto flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
                        <Plus className="h-4 w-4" /> Add Rule
                    </button>
                </div>

                {showForm && (
                    <form onSubmit={handleSubmit} className="bg-white rounded-xl border border-gray-200 p-6 mb-6 space-y-4">
                        <h2 className="text-base font-semibold text-gray-900">New Commission Rule</h2>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Rule Name</label>
                                <input type="text" value={data.name} onChange={e => setData('name', e.target.value)}
                                    className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    required />
                                {errors.name && <p className="text-red-600 text-xs mt-1">{errors.name}</p>}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Type</label>
                                <select value={data.type} onChange={e => setData('type', e.target.value)}
                                    className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="percentage">Percentage</option>
                                    <option value="fixed">Fixed Amount</option>
                                    <option value="tiered">Tiered</option>
                                </select>
                            </div>
                        </div>
                        {data.type === 'percentage' && (
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Rate (%)</label>
                                <input type="number" value={data.rate} onChange={e => setData('rate', e.target.value)}
                                    min="0" max="100" step="0.1"
                                    className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                            </div>
                        )}
                        {data.type === 'fixed' && (
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Fixed Amount ($)</label>
                                <input type="number" value={data.fixed_amount} onChange={e => setData('fixed_amount', e.target.value)}
                                    min="0" step="0.01"
                                    className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                            </div>
                        )}
                        <div className="flex gap-2">
                            <button type="submit" disabled={processing}
                                className="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
                                Create Rule
                            </button>
                            <button type="button" onClick={() => setShowForm(false)}
                                className="px-4 py-2 text-sm border border-gray-200 rounded-lg hover:bg-gray-50">
                                Cancel
                            </button>
                        </div>
                    </form>
                )}

                <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-gray-200 bg-gray-50">
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Rule</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Type</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Rate / Amount</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {rules.map(rule => (
                                <tr key={rule.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3">
                                        <p className="text-sm font-medium text-gray-900">{rule.name}</p>
                                        {rule.is_default && <span className="text-xs text-blue-600">Default rule</span>}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded-full capitalize">{rule.type}</span>
                                    </td>
                                    <td className="px-4 py-3 text-sm text-gray-700">
                                        {rule.type === 'percentage' && rule.rate != null && formatPercent(rule.rate / 100)}
                                        {rule.type === 'fixed' && rule.fixed_amount != null && formatCurrency(rule.fixed_amount)}
                                        {rule.type === 'tiered' && rule.tier_config && (
                                            <span>{rule.tier_config.length} tiers</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`text-xs px-2 py-1 rounded-full ${rule.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                                            {rule.is_active ? 'Active' : 'Inactive'}
                                        </span>
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
