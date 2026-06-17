import { Head } from '@inertiajs/react';
import { Plug, CheckCircle2, FlaskConical, Clock } from 'lucide-react';
import { ShipmentsLayout } from '@/Components/layout/ShipmentsLayout';
import { Pill } from '@/Components/ui/Pill';

interface CarrierRow {
    key: string;
    name: string;
    color: string;
    supported: boolean;
    status: 'live' | 'test' | 'available' | 'coming_soon';
    mailings: number;
}

const STATUS_META: Record<CarrierRow['status'], { label: string; color: string; icon: React.ComponentType<{ className?: string }> }> = {
    live: { label: 'Live', color: 'green', icon: CheckCircle2 },
    test: { label: 'Test mode', color: 'amber', icon: FlaskConical },
    available: { label: 'Available', color: 'blue', icon: CheckCircle2 },
    coming_soon: { label: 'Coming soon', color: 'gray', icon: Clock },
};

export default function Carriers({ carriers }: { carriers: CarrierRow[] }) {
    return (
        <ShipmentsLayout>
            <Head title="Carriers" />
            <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6">
                <div className="mb-6 flex items-center gap-3">
                    <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-gradient text-white"><Plug className="h-5 w-5" /></span>
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-foreground">Carriers</h1>
                        <p className="mt-0.5 text-sm text-muted-foreground">Shipping carriers Shipments can track.</p>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    {carriers.map(c => {
                        const meta = STATUS_META[c.status];
                        const Icon = meta.icon;
                        return (
                            <div key={c.key} className={`rounded-xl border border-border bg-card p-5 shadow-soft ${!c.supported ? 'opacity-75' : ''}`}>
                                <div className="flex items-start justify-between">
                                    <div className="flex items-center gap-3">
                                        <Pill color={c.color} label={c.name} />
                                        <Pill color={meta.color} label={meta.label} />
                                    </div>
                                    <Icon className="h-5 w-5 text-muted-foreground" />
                                </div>

                                <p className="mt-3 text-sm text-muted-foreground">
                                    {c.status === 'live' && 'Connected to the live carrier API. Tracking updates every 30 minutes.'}
                                    {c.status === 'test' && 'Using simulated tracking data. Add API credentials to go live (see below).'}
                                    {c.status === 'coming_soon' && 'Integration planned — drop in a tracking client to enable.'}
                                    {c.status === 'available' && 'Ready to track.'}
                                </p>

                                <div className="mt-4 flex items-center justify-between border-t border-border pt-3 text-sm">
                                    <span className="text-muted-foreground">Shipments</span>
                                    <span className="font-semibold text-foreground">{c.mailings}</span>
                                </div>

                                {c.status === 'test' && (
                                    <div className="mt-3 rounded-lg bg-secondary/60 p-3 text-xs text-muted-foreground">
                                        Set <code className="font-mono text-foreground">UPS_SYNC_ENABLED=true</code>,{' '}
                                        <code className="font-mono text-foreground">UPS_CLIENT_ID</code> and{' '}
                                        <code className="font-mono text-foreground">UPS_CLIENT_SECRET</code> in the app env, then restart — no redeploy of code needed.
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>
        </ShipmentsLayout>
    );
}
