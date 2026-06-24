import { Head, router } from '@inertiajs/react';
import { ExpenseLayout } from '@/Components/layout/ExpenseLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Pill } from '@/Components/ui/Pill';
import { formatDateTime } from '@/Lib/utils';
import { Plug, RefreshCw, Link2, Unplug, CheckCircle2, AlertTriangle, ArrowUpFromLine, ArrowDownToLine } from 'lucide-react';

interface Connection {
    realm_id: string;
    environment: string;
    is_demo: boolean;
    push_enabled: boolean;
    connected_by: string | null;
    last_synced_at: string | null;
    last_sync_status: string | null;
    last_sync_message: string | null;
}

interface Props {
    live: boolean;
    realtime: { push: boolean; webhook_url: string; webhook_ready: boolean };
    connection: Connection | null;
    imported_count: number;
    can: { manage: boolean };
}

export default function QuickBooksIndex({ live, realtime, connection, imported_count, can }: Props) {
    const sync = () => router.post('/expenses/quickbooks/sync', {}, { preserveScroll: true });
    const togglePush = () => router.post('/expenses/quickbooks/push-toggle', {}, { preserveScroll: true });
    const disconnect = () => { if (confirm('Disconnect QuickBooks? Imported expenses are kept.')) router.delete('/expenses/quickbooks', { preserveScroll: true }); };

    return (
        <ExpenseLayout>
            <Head title="QuickBooks · Expenses" />
            <div className="p-4 sm:p-6">
                <PageHeader icon={Plug} title="QuickBooks" description="Sync expenses with Intuit QuickBooks Online" />

                {!live && (
                    <div className="mb-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300">
                        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                        <span><span className="font-semibold">Demo mode.</span> No Intuit credentials are configured, so connecting and syncing use realistic sample data — nothing leaves or enters a real QuickBooks company. Add your Intuit app credentials (<code>QUICKBOOKS_CLIENT_ID/SECRET</code>, <code>QUICKBOOKS_SYNC_ENABLED=true</code>) to go live.</span>
                    </div>
                )}

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <Card className="p-5 lg:col-span-2">
                        {connection ? (
                            <>
                                <div className="flex items-start justify-between">
                                    <div className="flex items-center gap-3">
                                        <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 dark:bg-emerald-900/40">
                                            <CheckCircle2 className="h-6 w-6" />
                                        </span>
                                        <div>
                                            <h2 className="flex items-center gap-2 font-semibold text-foreground">
                                                Connected
                                                {connection.is_demo && <Pill color="amber" label="Demo" />}
                                                <Pill color="slate" label={connection.environment} />
                                            </h2>
                                            <p className="text-sm text-muted-foreground">Company {connection.realm_id}{connection.connected_by ? ` · by ${connection.connected_by}` : ''}</p>
                                        </div>
                                    </div>
                                    {can.manage && <Button variant="ghost" icon={Unplug} onClick={disconnect}>Disconnect</Button>}
                                </div>

                                <div className="mt-5 flex flex-wrap items-center gap-3 border-t border-border pt-4">
                                    {can.manage && <Button icon={RefreshCw} onClick={sync}>Sync now</Button>}
                                    <div className="text-sm text-muted-foreground">
                                        {connection.last_synced_at ? (
                                            <span className="flex items-center gap-1.5">
                                                {connection.last_sync_status === 'error'
                                                    ? <AlertTriangle className="h-4 w-4 text-red-500" />
                                                    : <CheckCircle2 className="h-4 w-4 text-emerald-500" />}
                                                Last synced {formatDateTime(connection.last_synced_at)}
                                            </span>
                                        ) : 'Not synced yet'}
                                    </div>
                                </div>
                                {connection.last_sync_message && (
                                    <p className="mt-2 text-xs text-muted-foreground">{connection.last_sync_message}</p>
                                )}
                            </>
                        ) : (
                            <div className="flex flex-col items-start gap-4">
                                <div className="flex items-center gap-3">
                                    <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-secondary text-muted-foreground">
                                        <Plug className="h-6 w-6" />
                                    </span>
                                    <div>
                                        <h2 className="font-semibold text-foreground">Not connected</h2>
                                        <p className="text-sm text-muted-foreground">Connect a QuickBooks company to import its expenses automatically.</p>
                                    </div>
                                </div>
                                {can.manage && (
                                    // Full-page navigation (OAuth redirect / demo connect), not an Inertia visit.
                                    <a href="/expenses/quickbooks/connect">
                                        <Button icon={Link2}>{live ? 'Connect QuickBooks' : 'Connect (demo)'}</Button>
                                    </a>
                                )}
                            </div>
                        )}
                    </Card>

                    <div className="space-y-6">
                        <Card className="p-5">
                            <p className="text-sm font-medium text-muted-foreground">Imported from QuickBooks</p>
                            <p className="mt-2 text-3xl font-bold text-foreground">{imported_count}</p>
                            <p className="mt-1 text-xs text-muted-foreground">expenses tagged as QuickBooks</p>
                        </Card>

                        {connection && (
                            <Card className="p-5">
                                <div className="flex items-start gap-3">
                                    <ArrowUpFromLine className="mt-0.5 h-5 w-5 text-muted-foreground" />
                                    <div className="min-w-0 flex-1">
                                        <h3 className="text-sm font-semibold text-foreground">Push to QuickBooks</h3>
                                        <p className="mt-0.5 text-xs text-muted-foreground">When on, an approved expense is sent to QuickBooks the moment it's approved or edited.</p>
                                        <div className="mt-3 flex items-center gap-2">
                                            <Pill color={connection.push_enabled ? 'green' : 'gray'} label={connection.push_enabled ? 'Real-time' : 'Disabled'} />
                                            {can.manage && (
                                                <Button variant="secondary" size="sm" onClick={togglePush}>
                                                    {connection.push_enabled ? 'Turn off' : 'Turn on'}
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </Card>
                        )}
                    </div>
                </div>

                {/* Real-time status */}
                <Card className="mt-6 p-5">
                    <h2 className="text-sm font-semibold text-foreground">Real-time sync</h2>
                    <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="flex items-start gap-3">
                            <span className="mt-0.5 flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600 dark:bg-emerald-900/40"><ArrowUpFromLine className="h-4 w-4" /></span>
                            <div>
                                <p className="text-sm font-medium text-foreground">App → QuickBooks <Pill color="green" label="Live" /></p>
                                <p className="mt-0.5 text-xs text-muted-foreground">Approving or editing an expense pushes it to QuickBooks within seconds (queued).</p>
                            </div>
                        </div>
                        <div className="flex items-start gap-3">
                            <span className={`mt-0.5 flex h-8 w-8 items-center justify-center rounded-lg ${realtime.webhook_ready ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/40' : 'bg-amber-100 text-amber-600 dark:bg-amber-900/40'}`}><ArrowDownToLine className="h-4 w-4" /></span>
                            <div className="min-w-0">
                                <p className="text-sm font-medium text-foreground">QuickBooks → App <Pill color={realtime.webhook_ready ? 'green' : 'amber'} label={realtime.webhook_ready ? 'Live' : 'Needs webhook'} /></p>
                                <p className="mt-0.5 text-xs text-muted-foreground">
                                    {realtime.webhook_ready
                                        ? 'Changes in QuickBooks reflect here instantly via webhook (15-min poll backstop).'
                                        : 'To go instant, add this webhook URL in your Intuit app and set QUICKBOOKS_WEBHOOK_TOKEN. Until then a 15-min poll keeps things current.'}
                                </p>
                                <code className="mt-1.5 block break-all rounded bg-secondary px-2 py-1 text-[11px] text-muted-foreground">{realtime.webhook_url}</code>
                            </div>
                        </div>
                    </div>
                </Card>
            </div>
        </ExpenseLayout>
    );
}
