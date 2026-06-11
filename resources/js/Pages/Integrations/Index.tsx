import { Head, router, useForm } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Select } from '@/Components/ui/Select';
import { formatDate } from '@/Lib/utils';
import { Zap, Plus, Trash2, Info, RefreshCw, CheckCircle2, X } from 'lucide-react';
import { useState } from 'react';

interface Integration {
    id: number;
    name: string;
    type: string;
    status: string;
    last_synced_at: string | null;
    created_at: string;
}

interface SamGov {
    connected: boolean;
    sync_enabled: boolean;
    last_import: string | null;
    last_stats: { imported: number; updated: number } | null;
}

interface Props {
    integrations: Integration[];
    samGov: SamGov;
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

export default function IntegrationsIndex({ integrations, samGov }: Props) {
    const [showForm, setShowForm] = useState(false);
    const [syncing, setSyncing] = useState(false);
    const form = useForm<{ name: string; type: string; credentials: Record<string, string> }>({
        name: '',
        type: 'sam_gov',
        credentials: { api_key: '' },
    });

    const handleDelete = (id: number) => {
        if (confirm('Remove this integration?')) {
            router.delete(`/integrations/${id}`);
        }
    };

    const syncSam = () => {
        setSyncing(true);
        router.post('/integrations/sync/sam_gov', {}, { preserveScroll: true, onFinish: () => setSyncing(false) });
    };

    const submitIntegration = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/integrations', { onSuccess: () => { form.reset(); setShowForm(false); } });
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

                {/* SAM.gov status */}
                {samGov.connected ? (
                    <Card className="mb-6 flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-start gap-3">
                            <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-sky-500 to-blue-500 text-white shadow-sm">
                                <Zap className="h-5 w-5" />
                            </div>
                            <div>
                                <p className="flex items-center gap-2 font-semibold text-foreground">
                                    SAM.gov
                                    <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs font-medium text-emerald-600">
                                        <CheckCircle2 className="h-3 w-3" /> Connected
                                    </span>
                                </p>
                                <p className="mt-0.5 text-sm text-muted-foreground">
                                    Live federal opportunity feed.{' '}
                                    {samGov.last_import
                                        ? `Last sync ${formatDate(samGov.last_import)}${samGov.last_stats ? ` · ${samGov.last_stats.imported} new, ${samGov.last_stats.updated} updated` : ''}.`
                                        : 'Not synced yet — run a sync to pull recent opportunities.'}
                                </p>
                            </div>
                        </div>
                        <Button onClick={syncSam} disabled={syncing} icon={RefreshCw}>
                            {syncing ? 'Syncing…' : 'Sync now'}
                        </Button>
                    </Card>
                ) : (
                    <Card className="mb-6 flex items-start gap-3 p-4">
                        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-amber-500 to-orange-500 text-white shadow-sm">
                            <Info className="h-4 w-4" />
                        </div>
                        <p className="text-sm text-muted-foreground">
                            <strong className="text-foreground">Demo Mode:</strong> SAM.gov isn't connected, so a fake client generates demo data.
                            Set a SAM.gov API key to enable the live feed.
                        </p>
                    </Card>
                )}

                {/* Add integration form */}
                {showForm && (
                    <form onSubmit={submitIntegration}>
                        <Card className="mb-6 ring-1 ring-primary/30">
                            <CardHeader>
                                <CardTitle>Add an integration</CardTitle>
                                <button type="button" onClick={() => setShowForm(false)} className="text-muted-foreground transition-colors hover:text-foreground">
                                    <X className="h-4 w-4" />
                                </button>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <label className="label">Name</label>
                                        <input type="text" value={form.data.name} onChange={e => form.setData('name', e.target.value)} className="input" placeholder="e.g. SAM.gov (Production)" required />
                                        {form.errors.name && <p className="mt-1 text-xs text-destructive">{form.errors.name}</p>}
                                    </div>
                                    <div>
                                        <label className="label">Type</label>
                                        <Select
                                            value={form.data.type}
                                            onChange={v => form.setData('type', v)}
                                            options={Object.entries(TYPE_LABELS).map(([v, l]) => ({ value: v, label: l }))}
                                            className="w-full"
                                        />
                                    </div>
                                </div>
                                <div>
                                    <label className="label">API Key / Credential</label>
                                    <input type="text" value={form.data.credentials.api_key}
                                        onChange={e => form.setData('credentials', { ...form.data.credentials, api_key: e.target.value })}
                                        className="input font-mono" placeholder="Paste the API key or token" required />
                                    {form.errors.credentials && <p className="mt-1 text-xs text-destructive">{form.errors.credentials}</p>}
                                    <p className="mt-1 text-xs text-muted-foreground">Stored encrypted. For SAM.gov the live key is already configured server-side.</p>
                                </div>
                                <div className="flex gap-2">
                                    <Button type="submit" disabled={form.processing}>{form.processing ? 'Saving…' : 'Save integration'}</Button>
                                    <Button type="button" variant="secondary" onClick={() => setShowForm(false)}>Cancel</Button>
                                </div>
                            </CardContent>
                        </Card>
                    </form>
                )}

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
