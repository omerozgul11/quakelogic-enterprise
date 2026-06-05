import { Head, router, useForm } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { formatDate } from '@/Lib/utils';
import { Zap, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface Integration {
    id: number;
    name: string;
    type: string;
    status: string;
    last_synced_at: string | null;
    created_at: string;
}

interface Props {
    integrations: Integration[];
}

const STATUS_STYLES: Record<string, string> = {
    active: 'bg-green-100 text-green-700',
    inactive: 'bg-gray-100 text-gray-500',
    error: 'bg-red-100 text-red-700',
};

const TYPE_LABELS: Record<string, string> = {
    sam_gov: 'SAM.gov',
    bidprime: 'BidPrime',
    govwin: 'GovWin IQ',
    email_smtp: 'Email (SMTP)',
    email_gmail: 'Gmail',
    email_microsoft: 'Microsoft 365',
};

export default function IntegrationsIndex({ integrations }: Props) {
    const [showForm, setShowForm] = useState(false);
    const { data, setData, post, errors, processing, reset } = useForm({
        name: '',
        type: 'sam_gov',
        credentials: {} as Record<string, string>,
    });

    const handleDelete = (id: number) => {
        if (confirm('Remove this integration?')) {
            router.delete(`/integrations/${id}`);
        }
    };

    return (
        <AppLayout>
            <Head title="Integrations" />
            <div className="p-6 max-w-4xl mx-auto">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 flex items-center gap-2">
                            <Zap className="h-6 w-6 text-amber-500" />
                            Integrations
                        </h1>
                        <p className="text-gray-500 mt-1">Connect external bid sources and email providers</p>
                    </div>
                    <button onClick={() => setShowForm(!showForm)}
                        className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
                        <Plus className="h-4 w-4" /> Add Integration
                    </button>
                </div>

                {/* Notice */}
                <div className="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6">
                    <p className="text-sm text-amber-800">
                        <strong>Demo Mode:</strong> No real API credentials are required to boot. The fake SAM.gov and BidPrime clients
                        generate demo data automatically. Add integration credentials here to enable live syncing.
                    </p>
                </div>

                {/* Integration Cards */}
                <div className="grid grid-cols-1 gap-4">
                    {integrations.length === 0 ? (
                        <div className="bg-white rounded-xl border border-gray-200 p-8 text-center text-gray-500">
                            <Zap className="h-12 w-12 mx-auto mb-3 text-gray-300" />
                            <p className="font-medium">No integrations configured</p>
                            <p className="text-sm mt-1">Add an integration to enable live data syncing</p>
                        </div>
                    ) : integrations.map(integration => (
                        <div key={integration.id} className="bg-white rounded-xl border border-gray-200 p-5 flex items-center justify-between">
                            <div>
                                <div className="flex items-center gap-3">
                                    <p className="font-semibold text-gray-900">{integration.name}</p>
                                    <span className={`text-xs px-2 py-1 rounded-full ${STATUS_STYLES[integration.status] ?? 'bg-gray-100 text-gray-600'}`}>
                                        {integration.status}
                                    </span>
                                </div>
                                <p className="text-sm text-gray-500 mt-1">
                                    {TYPE_LABELS[integration.type] ?? integration.type}
                                    {integration.last_synced_at && ` · Last sync: ${formatDate(integration.last_synced_at)}`}
                                </p>
                            </div>
                            <button onClick={() => handleDelete(integration.id)} className="text-gray-400 hover:text-red-600">
                                <Trash2 className="h-4 w-4" />
                            </button>
                        </div>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
