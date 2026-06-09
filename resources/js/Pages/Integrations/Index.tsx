import { Head, router, useForm } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { formatDate } from '@/Lib/utils';
import { Zap, Plus, Trash2, Info } from 'lucide-react';
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

const STATUS_DOTS: Record<string, string> = {
    active: 'bg-emerald-500',
    inactive: 'bg-muted-foreground',
    error: 'bg-destructive',
};

const STATUS_TEXT: Record<string, string> = {
    active: 'text-emerald-600',
    inactive: 'text-muted-foreground',
    error: 'text-destructive',
};

const TYPE_LABELS: Record<string, string> = {
    sam_gov: 'SAM.gov',
    bidprime: 'BidPrime',
    govwin: 'GovWin IQ',
    email_smtp: 'Email (SMTP)',
    email_gmail: 'Gmail',
    email_microsoft: 'Microsoft 365',
};

const TYPE_TILES: Record<string, string> = {
    sam_gov: 'from-sky-500 to-blue-500',
    bidprime: 'from-violet-500 to-fuchsia-500',
    govwin: 'from-indigo-500 to-violet-500',
    email_smtp: 'from-amber-500 to-orange-500',
    email_gmail: 'from-rose-500 to-red-500',
    email_microsoft: 'from-teal-500 to-cyan-500',
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
            <div className="p-6">
                <PageHeader
                    icon={Zap}
                    title="Integrations"
                    description="Connect external bid sources and email providers"
                    actions={
                        <Button icon={Plus} onClick={() => setShowForm(!showForm)}>
                            Add Integration
                        </Button>
                    }
                />

                {/* Notice */}
                <Card className="mb-6 flex items-start gap-3 p-4">
                    <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-amber-500 to-orange-500 text-white shadow-sm">
                        <Info className="h-4 w-4" />
                    </div>
                    <p className="text-sm text-muted-foreground">
                        <strong className="text-foreground">Demo Mode:</strong> No real API credentials are required to boot. The fake SAM.gov and BidPrime clients
                        generate demo data automatically. Add integration credentials here to enable live syncing.
                    </p>
                </Card>

                {/* Integration Cards */}
                {integrations.length === 0 ? (
                    <Card>
                        <EmptyState
                            icon={Zap}
                            title="No integrations configured"
                            description="Add an integration to enable live data syncing."
                            action={<Button icon={Plus} onClick={() => setShowForm(true)}>Add Integration</Button>}
                        />
                    </Card>
                ) : (
                    <div className="stagger grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {integrations.map(integration => (
                            <Card key={integration.id} hover className="flex flex-col gap-4 p-5">
                                <div className="flex items-start justify-between">
                                    <div className={`flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br text-white shadow-sm ${TYPE_TILES[integration.type] ?? 'from-indigo-500 to-violet-500'}`}>
                                        <Zap className="h-5 w-5" />
                                    </div>
                                    <button onClick={() => handleDelete(integration.id)} className="text-muted-foreground transition-colors hover:text-destructive">
                                        <Trash2 className="h-4 w-4" />
                                    </button>
                                </div>
                                <div>
                                    <p className="font-semibold text-foreground">{integration.name}</p>
                                    <p className="mt-0.5 text-sm text-muted-foreground">
                                        {TYPE_LABELS[integration.type] ?? integration.type}
                                        {integration.last_synced_at && ` · Last sync: ${formatDate(integration.last_synced_at)}`}
                                    </p>
                                </div>
                                <div className="mt-auto flex items-center gap-2 border-t border-border pt-3">
                                    <span className={`h-2 w-2 rounded-full ${STATUS_DOTS[integration.status] ?? 'bg-muted-foreground'}`} />
                                    <span className={`text-xs font-medium capitalize ${STATUS_TEXT[integration.status] ?? 'text-muted-foreground'}`}>
                                        {integration.status}
                                    </span>
                                </div>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
