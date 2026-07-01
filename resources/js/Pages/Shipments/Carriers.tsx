import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Plug, CheckCircle2, FlaskConical, Clock, Plus, Truck, Trash2, Pencil, Check, X, ExternalLink, ArrowDownToLine, ArrowUpFromLine, RotateCcw, Zap, Copy } from 'lucide-react';
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
    import_number: string;
    export_number: string;
    login_url: string;
    login_url_override: string;
    default_login_url: string | null;
}

interface Draft {
    name: string;
    login_url: string;
    import_number: string;
    export_number: string;
}

const STATUS_META: Record<CarrierRow['status'], { label: string; color: string; icon: React.ComponentType<{ className?: string }> }> = {
    live: { label: 'Live', color: 'green', icon: CheckCircle2 },
    test: { label: 'Test mode', color: 'amber', icon: FlaskConical },
    available: { label: 'Available', color: 'blue', icon: CheckCircle2 },
    coming_soon: { label: 'Coming soon', color: 'gray', icon: Clock },
    custom: { label: 'Manual tracking', color: 'blue', icon: Truck },
};

interface HiddenCarrier { key: string; name: string; color: string }

interface DhlSubscription {
    ulid: string;
    type: 'shipment' | 'account';
    tracking_number: string | null;
    account_number: string | null;
    status: string;
    created_at: string | null;
}

interface DhlPanel {
    apiConfigured: boolean;
    pushConfigured: boolean;
    webhookUrl: string | null;
    subscriptions: DhlSubscription[];
}

const SUB_STATUS: Record<string, { label: string; color: string }> = {
    pending: { label: 'Pending validation', color: 'amber' },
    validating: { label: 'Validating', color: 'amber' },
    ready: { label: 'Live', color: 'green' },
    failed: { label: 'Failed', color: 'red' },
    removed: { label: 'Removed', color: 'gray' },
};

export default function Carriers({ carriers, hiddenCarriers, dhl }: { carriers: CarrierRow[]; hiddenCarriers: HiddenCarrier[]; dhl?: DhlPanel }) {
    const [name, setName] = useState('');
    const [saving, setSaving] = useState(false);
    const [editKey, setEditKey] = useState<string | null>(null);
    const [draft, setDraft] = useState<Draft>({ name: '', login_url: '', import_number: '', export_number: '' });
    const [tracking, setTracking] = useState('');
    const [subscribing, setSubscribing] = useState(false);
    const [copied, setCopied] = useState(false);

    const dhlReady = !!dhl?.apiConfigured && !!dhl?.pushConfigured;

    const subscribe = (e: React.FormEvent) => {
        e.preventDefault();
        const value = tracking.trim();
        if (!value || subscribing) return;
        setSubscribing(true);
        router.post('/shipments/carriers/dhl/subscribe', { tracking_number: value, type: 'shipment' }, {
            preserveScroll: true,
            onSuccess: () => setTracking(''),
            onFinish: () => setSubscribing(false),
        });
    };

    const unsubscribe = (ulid: string) => {
        if (!confirm('Remove this DHL push subscription? DHL will stop sending live updates for it.')) return;
        router.post('/shipments/carriers/dhl/unsubscribe', { ulid }, { preserveScroll: true });
    };

    const copyWebhook = () => {
        if (!dhl?.webhookUrl) return;
        navigator.clipboard?.writeText(dhl.webhookUrl).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        });
    };

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

    const startEdit = (c: CarrierRow) => {
        setEditKey(c.key);
        setDraft({ name: c.name, login_url: c.login_url_override, import_number: c.import_number, export_number: c.export_number });
    };
    const cancelEdit = () => { setEditKey(null); };

    const saveProfile = (e: React.FormEvent, c: CarrierRow) => {
        e.preventDefault();
        const payload: Record<string, string> = {
            key: c.key,
            login_url: draft.login_url.trim(),
            import_number: draft.import_number.trim(),
            export_number: draft.export_number.trim(),
        };
        if (c.status === 'custom') payload.new_name = draft.name.trim();
        router.post('/shipments/carriers/profile', payload, { preserveScroll: true, onSuccess: cancelEdit });
    };

    const remove = (c: CarrierRow) => {
        const msg = c.status === 'custom'
            ? `Remove ${c.name}? Shipments using it must be reassigned first.`
            : `Remove ${c.name} from this organization? It’s hidden from the carriers list and shipment forms — you can restore it anytime.`;
        if (!confirm(msg)) return;
        router.post('/shipments/carriers/remove', { key: c.key }, { preserveScroll: true });
    };

    const restore = (key: string) => router.post('/shipments/carriers/restore', { key }, { preserveScroll: true });

    return (
        <ShipmentsLayout>
            <Head title="Carriers" />
            <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6">
                <div className="mb-6 flex items-center gap-3">
                    <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-gradient text-white"><Plug className="h-5 w-5" /></span>
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-foreground">Carriers</h1>
                        <p className="mt-0.5 text-sm text-muted-foreground">Shipping carriers Shipments can track — plus your account numbers and login links.</p>
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
                        const editing = editKey === c.key;
                        return (
                            <div key={c.key} className={`rounded-xl border border-border bg-card p-5 shadow-soft ${!c.supported && c.status !== 'custom' ? 'opacity-90' : ''}`}>
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
                                    {c.status === 'custom' && 'Custom carrier — tracked manually. Set the status by hand and upload the bill of lading or labels.'}
                                </p>

                                {editing ? (
                                    <form onSubmit={e => saveProfile(e, c)} className="mt-4 space-y-3 border-t border-border pt-4">
                                        {c.status === 'custom' && (
                                            <div>
                                                <label className="label">Name</label>
                                                <input value={draft.name} onChange={e => setDraft({ ...draft, name: e.target.value })} className="input h-9" maxLength={50} autoFocus />
                                            </div>
                                        )}
                                        <div>
                                            <label className="label">Login URL</label>
                                            <input
                                                value={draft.login_url}
                                                onChange={e => setDraft({ ...draft, login_url: e.target.value })}
                                                className="input h-9"
                                                placeholder={c.default_login_url ?? 'https://…'}
                                                maxLength={255}
                                                type="url"
                                            />
                                            {c.default_login_url && draft.login_url.trim() === '' && (
                                                <p className="mt-1 text-xs text-muted-foreground">Using the default: {c.default_login_url}</p>
                                            )}
                                        </div>
                                        <div className="grid grid-cols-2 gap-2">
                                            <div>
                                                <label className="label">Import #</label>
                                                <input value={draft.import_number} onChange={e => setDraft({ ...draft, import_number: e.target.value })} className="input h-9 font-mono" maxLength={50} />
                                            </div>
                                            <div>
                                                <label className="label">Export #</label>
                                                <input value={draft.export_number} onChange={e => setDraft({ ...draft, export_number: e.target.value })} className="input h-9 font-mono" maxLength={50} />
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2 pt-1">
                                            <button type="submit" className="inline-flex items-center gap-1.5 rounded-full bg-brand-gradient px-4 py-1.5 text-sm font-semibold text-white transition hover:-translate-y-0.5">
                                                <Check className="h-4 w-4" /> Save
                                            </button>
                                            <button type="button" onClick={cancelEdit} className="inline-flex items-center gap-1.5 rounded-full border border-border px-4 py-1.5 text-sm font-medium text-muted-foreground transition hover:bg-secondary">
                                                <X className="h-4 w-4" /> Cancel
                                            </button>
                                        </div>
                                    </form>
                                ) : (
                                    <>
                                        {(c.import_number || c.export_number || c.login_url) && (
                                            <div className="mt-4 space-y-2 border-t border-border pt-3 text-sm">
                                                {c.login_url && (
                                                    <a href={c.login_url} target="_blank" rel="noopener noreferrer"
                                                        className="inline-flex items-center gap-1.5 font-medium text-primary hover:underline">
                                                        <ExternalLink className="h-3.5 w-3.5" /> Log in to {c.name}
                                                    </a>
                                                )}
                                                {c.import_number && (
                                                    <div className="flex items-center gap-2 text-muted-foreground">
                                                        <ArrowDownToLine className="h-3.5 w-3.5" /> Import #
                                                        <span className="ml-auto font-mono text-foreground">{c.import_number}</span>
                                                    </div>
                                                )}
                                                {c.export_number && (
                                                    <div className="flex items-center gap-2 text-muted-foreground">
                                                        <ArrowUpFromLine className="h-3.5 w-3.5" /> Export #
                                                        <span className="ml-auto font-mono text-foreground">{c.export_number}</span>
                                                    </div>
                                                )}
                                            </div>
                                        )}

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

                                        <div className="mt-3 flex items-center gap-4">
                                            <button
                                                onClick={() => startEdit(c)}
                                                className="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground transition hover:text-primary"
                                            >
                                                <Pencil className="h-3.5 w-3.5" /> Edit details
                                            </button>
                                            <button
                                                onClick={() => remove(c)}
                                                disabled={!c.removable}
                                                className="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground transition hover:text-destructive disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:text-muted-foreground"
                                            >
                                                <Trash2 className="h-3.5 w-3.5" /> Remove
                                            </button>
                                            {!c.removable && (
                                                <span className="text-xs text-muted-foreground/70">In use — reassign to remove</span>
                                            )}
                                        </div>
                                    </>
                                )}
                            </div>
                        );
                    })}
                </div>

                {dhl && (
                    <div className="mt-8 rounded-xl border border-border bg-card p-5 shadow-soft">
                        <div className="flex items-start justify-between gap-3">
                            <div className="flex items-center gap-3">
                                <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-red-500/10 text-red-600"><Zap className="h-5 w-5" /></span>
                                <div>
                                    <h2 className="text-base font-semibold text-foreground">DHL live tracking (push)</h2>
                                    <p className="text-xs text-muted-foreground">DHL pushes shipment status to this app in real time — no polling, updates arrive within seconds.</p>
                                </div>
                            </div>
                            <div className="flex flex-col items-end gap-1">
                                <Pill color={dhl.apiConfigured ? 'green' : 'gray'} label={dhl.apiConfigured ? 'API key set' : 'API key missing'} />
                                <Pill color={dhl.pushConfigured ? 'green' : 'gray'} label={dhl.pushConfigured ? 'Webhook ready' : 'Webhook token missing'} />
                            </div>
                        </div>

                        {!dhlReady && (
                            <div className="mt-4 rounded-lg bg-secondary/60 p-3 text-xs text-muted-foreground">
                                To go live, set <code className="font-mono text-foreground">DHL_API_KEY</code> and{' '}
                                <code className="font-mono text-foreground">DHL_PUSH_WEBHOOK_TOKEN</code> (any random string) in the app env, then restart — no code redeploy needed. Then register the webhook URL below in the DHL developer portal.
                            </div>
                        )}

                        {dhl.webhookUrl && (
                            <div className="mt-4">
                                <label className="label">Webhook URL — register this in the DHL developer portal</label>
                                <div className="flex items-center gap-2">
                                    <code className="flex-1 truncate rounded-lg border border-border bg-secondary/40 px-3 py-2 font-mono text-xs text-foreground">{dhl.webhookUrl}</code>
                                    <button type="button" onClick={copyWebhook} className="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-border px-3 py-2 text-xs font-medium text-foreground transition hover:bg-secondary">
                                        {copied ? <Check className="h-3.5 w-3.5" /> : <Copy className="h-3.5 w-3.5" />} {copied ? 'Copied' : 'Copy'}
                                    </button>
                                </div>
                            </div>
                        )}

                        <form onSubmit={subscribe} className="mt-4 flex flex-col gap-2 sm:flex-row sm:items-end">
                            <div className="flex-1">
                                <label htmlFor="dhl-tracking" className="label">Subscribe a DHL tracking number</label>
                                <input
                                    id="dhl-tracking"
                                    value={tracking}
                                    onChange={e => setTracking(e.target.value)}
                                    className="input h-9 font-mono"
                                    placeholder="e.g. 00340434292135100186"
                                    maxLength={100}
                                    disabled={!dhlReady}
                                />
                                <p className="mt-1.5 text-xs text-muted-foreground">DHL confirms the webhook, then pushes every update for this shipment. Account-wide subscriptions need DHL business approval.</p>
                            </div>
                            <button
                                type="submit"
                                disabled={subscribing || !tracking.trim() || !dhlReady}
                                className="bg-brand-gradient shadow-glow inline-flex items-center justify-center gap-2 rounded-full px-5 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 disabled:opacity-60"
                            >
                                <Plus className="h-4 w-4" /> {subscribing ? 'Subscribing…' : 'Subscribe'}
                            </button>
                        </form>

                        {dhl.subscriptions.length > 0 && (
                            <div className="mt-5 border-t border-border pt-4">
                                <h3 className="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground/70">Push subscriptions</h3>
                                <div className="divide-y divide-border">
                                    {dhl.subscriptions.map(s => {
                                        const meta = SUB_STATUS[s.status] ?? { label: s.status, color: 'gray' };
                                        return (
                                            <div key={s.ulid} className="flex items-center gap-3 py-2.5">
                                                <span className="min-w-0 flex-1">
                                                    <span className="block truncate font-mono text-sm text-foreground">{s.tracking_number || s.account_number || '—'}</span>
                                                    <span className="text-xs text-muted-foreground">{s.type === 'account' ? 'Account' : 'Shipment'}{s.created_at ? ` · ${s.created_at}` : ''}</span>
                                                </span>
                                                <Pill color={meta.color} label={meta.label} />
                                                <button onClick={() => unsubscribe(s.ulid)} className="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground transition hover:text-destructive">
                                                    <Trash2 className="h-3.5 w-3.5" /> Remove
                                                </button>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {hiddenCarriers.length > 0 && (
                    <div className="mt-8 border-t border-border pt-6">
                        <h2 className="mb-1 text-sm font-semibold text-foreground">Removed carriers</h2>
                        <p className="mb-3 text-xs text-muted-foreground">Hidden from the list and shipment forms. Restore one to bring it back.</p>
                        <div className="flex flex-wrap gap-2">
                            {hiddenCarriers.map(h => (
                                <div key={h.key} className="inline-flex items-center gap-3 rounded-full border border-border bg-card px-3 py-1.5 shadow-soft">
                                    <Pill color={h.color} label={h.name} />
                                    <button onClick={() => restore(h.key)} className="inline-flex items-center gap-1 text-xs font-medium text-primary transition hover:underline">
                                        <RotateCcw className="h-3.5 w-3.5" /> Restore
                                    </button>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </ShipmentsLayout>
    );
}
