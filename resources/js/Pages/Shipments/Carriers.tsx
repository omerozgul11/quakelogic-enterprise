import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Plug, CheckCircle2, FlaskConical, Clock, Plus, Truck, Trash2, Pencil, Check, X } from 'lucide-react';
import { ShipmentsLayout } from '@/Components/layout/ShipmentsLayout';
import { Pill } from '@/Components/ui/Pill';

interface CarrierRow {
    key: string;
    name: string;
    color: string;
    supported: boolean;
    removable: boolean;
    status: 'live' | 'test' | 'available' | 'coming_soon' | 'custom';
    mailings: number;
}

const STATUS_META: Record<CarrierRow['status'], { label: string; color: string; icon: React.ComponentType<{ className?: string }> }> = {
    live: { label: 'Live', color: 'green', icon: CheckCircle2 },
    test: { label: 'Test mode', color: 'amber', icon: FlaskConical },
    available: { label: 'Available', color: 'blue', icon: CheckCircle2 },
    coming_soon: { label: 'Coming soon', color: 'gray', icon: Clock },
    custom: { label: 'Manual tracking', color: 'blue', icon: Truck },
};

export default function Carriers({ carriers }: { carriers: CarrierRow[] }) {
    const [name, setName] = useState('');
    const [saving, setSaving] = useState(false);
    const [editKey, setEditKey] = useState<string | null>(null);
    const [draft, setDraft] = useState('');

    const add = (e: React.FormEvent) => {
        e.preventDefault();
        const value = name.trim();
        if (!value || saving) return;
        setSaving(true);
        router.post('/shipments/carriers', { name: value }, {
            preserveScroll: true,
            onSuccess: () => setName(''),
            onFinish: () => setSaving(false),
        });
    };

    const startEdit = (c: CarrierRow) => { setEditKey(c.key); setDraft(c.name); };
    const cancelEdit = () => { setEditKey(null); setDraft(''); };

    const saveRename = (e: React.FormEvent, current: string) => {
        e.preventDefault();
        const next = draft.trim();
        if (!next || next === current) { cancelEdit(); return; }
        router.post('/shipments/carriers/update', { name: current, new_name: next }, {
            preserveScroll: true,
            onSuccess: cancelEdit,
        });
    };

    const remove = (carrierName: string) => {
        if (!confirm(`Remove ${carrierName}? Shipments using it must be reassigned first.`)) return;
        router.post('/shipments/carriers/remove', { name: carrierName }, { preserveScroll: true });
    };

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

                <form onSubmit={add} className="card-surface mb-6 flex flex-col gap-3 p-5 sm:flex-row sm:items-end">
                    <div className="flex-1">
                        <label htmlFor="carrier-name" className="label">Add a carrier</label>
                        <input
                            id="carrier-name"
                            value={name}
                            onChange={e => setName(e.target.value)}
                            className="input"
                            placeholder="Carrier name (e.g. J.B. Hunt)"
                            maxLength={50}
                        />
                        <p className="mt-1.5 text-xs text-muted-foreground">Custom carriers (e.g. freight) are tracked manually — set status and upload documents by hand.</p>
                    </div>
                    <button
                        type="submit"
                        disabled={saving || !name.trim()}
                        className="bg-brand-gradient shadow-glow inline-flex items-center justify-center gap-2 rounded-full px-5 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 disabled:opacity-60"
                    >
                        <Plus className="h-4 w-4" /> {saving ? 'Adding…' : 'Add carrier'}
                    </button>
                </form>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    {carriers.map(c => {
                        const meta = STATUS_META[c.status];
                        const Icon = meta.icon;
                        return (
                            <div key={c.key} className={`rounded-xl border border-border bg-card p-5 shadow-soft ${!c.supported && c.status !== 'custom' ? 'opacity-75' : ''}`}>
                                <div className="flex items-start justify-between">
                                    {editKey === c.key ? (
                                        <form onSubmit={e => saveRename(e, c.name)} className="flex flex-1 items-center gap-2">
                                            <input
                                                value={draft}
                                                onChange={e => setDraft(e.target.value)}
                                                className="input h-9 flex-1"
                                                maxLength={50}
                                                autoFocus
                                            />
                                            <button type="submit" title="Save" className="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-gradient text-white transition hover:-translate-y-0.5">
                                                <Check className="h-4 w-4" />
                                            </button>
                                            <button type="button" onClick={cancelEdit} title="Cancel" className="flex h-9 w-9 items-center justify-center rounded-lg border border-border text-muted-foreground transition hover:bg-secondary">
                                                <X className="h-4 w-4" />
                                            </button>
                                        </form>
                                    ) : (
                                        <>
                                            <div className="flex items-center gap-3">
                                                <Pill color={c.color} label={c.name} />
                                                <Pill color={meta.color} label={meta.label} />
                                            </div>
                                            <Icon className="h-5 w-5 text-muted-foreground" />
                                        </>
                                    )}
                                </div>

                                <p className="mt-3 text-sm text-muted-foreground">
                                    {c.status === 'live' && 'Connected to the live carrier API. Tracking updates every 30 minutes.'}
                                    {c.status === 'test' && 'Using simulated tracking data. Add API credentials to go live (see below).'}
                                    {c.status === 'coming_soon' && 'Integration planned — drop in a tracking client to enable.'}
                                    {c.status === 'available' && 'Ready to track.'}
                                    {c.status === 'custom' && 'Custom carrier — tracked manually. Set the status by hand and upload the bill of lading or labels.'}
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

                                {c.status === 'custom' && editKey !== c.key && (
                                    <div className="mt-3 flex items-center gap-4">
                                        <button
                                            onClick={() => startEdit(c)}
                                            className="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground transition hover:text-primary"
                                        >
                                            <Pencil className="h-3.5 w-3.5" /> Rename
                                        </button>
                                        <button
                                            onClick={() => remove(c.name)}
                                            className="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground transition hover:text-destructive"
                                        >
                                            <Trash2 className="h-3.5 w-3.5" /> Remove
                                        </button>
                                        {!c.removable && (
                                            <span className="text-xs text-muted-foreground/70">In use — reassign to remove</span>
                                        )}
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
