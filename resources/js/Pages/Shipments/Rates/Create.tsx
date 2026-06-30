import { useRef, useState } from 'react';
import { Head, Link, useForm, router } from '@inertiajs/react';
import axios from 'axios';
import { ArrowLeft, BadgeDollarSign, Mail, Paperclip, Upload, Download, Trash2, RefreshCw, FileText, Calculator, AlertTriangle } from 'lucide-react';
import { ShipmentsLayout } from '@/Components/layout/ShipmentsLayout';
import { Select } from '@/Components/ui/Select';
import { Combobox } from '@/Components/ui/Combobox';
import { CarrierField } from '@/Components/ui/CarrierField';
import { Checkbox } from '@/Components/ui/Checkbox';

interface Option { value: string; label: string }

interface DiscountTier { pct: number; label: string }
interface RateCard {
    available: boolean;
    product?: string;
    currency?: string;
    origin_country?: string;
    as_of?: string | null;
    discount_tiers?: DiscountTier[];
}

interface Estimate {
    zone: string;
    band: string;
    actual_weight_lb: number;
    volumetric_weight_lb: number;
    billable_weight_lb: number;
    published_amount: number;
    discount_pct: number;
    discount_amount: number;
    premium_key: string | null;
    premium_amount: number;
    net_amount: number;
    currency: string;
    service_level: string;
    transit_days: number | null;
    per_lb_rate: number | null;
    warnings: string[];
}

interface QuoteForm {
    ulid?: string;
    reference: string;
    contact_email: string;
    carrier: string;
    service_line: string;
    status: string;
    proposal_mailing_id: string;
    origin_city: string; origin_state: string; origin_postal: string; origin_country: string;
    dest_city: string; dest_state: string; dest_postal: string; dest_country: string;
    ready_date: string;
    service_level: string;
    weight: string; weight_unit: string; length: string; width: string; height: string; dim_unit: string;
    freight_class: string; pallet_count: string; piece_count: string; accessorials: string[];
    amount: string; currency: string; transit_days: string; estimated_delivery: string;
    quote_reference: string; expires_at: string; notes: string;
}

interface AttachedDoc { name: string | null; size: string | null; uploaded_at: string | null }

interface Props {
    quote: (QuoteForm & { document: AttachedDoc | null }) | null;
    carrierOptions: Option[];
    serviceLineOptions: Option[];
    statusOptions: Option[];
    accessorialOptions: Option[];
    linkableShipments: { id: number; label: string }[];
    rateCard: RateCard | null;
}

const CURRENCIES = ['USD', 'EUR', 'GBP', 'CAD', 'MXN', 'AUD'].map(c => ({ value: c, label: c }));

// Re-seed the form whenever a new rate sheet is attached so AI-extracted values show.
export default function RatesCreate(props: Props) {
    const key = props.quote?.document?.uploaded_at ?? props.quote?.ulid ?? 'new';
    return <RateForm key={key} {...props} />;
}

function RateForm({ quote, carrierOptions, serviceLineOptions, statusOptions, accessorialOptions, linkableShipments, rateCard }: Props) {
    const editing = !!quote;
    const fileInput = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);

    const { data, setData, post, put, processing, errors, transform } = useForm<QuoteForm>({
        reference: quote?.reference ?? '',
        contact_email: quote?.contact_email ?? '',
        carrier: quote?.carrier ?? 'dhl',
        service_line: quote?.service_line ?? '',
        status: quote?.status ?? 'draft',
        proposal_mailing_id: quote?.proposal_mailing_id ?? '',
        origin_city: quote?.origin_city ?? '', origin_state: quote?.origin_state ?? '', origin_postal: quote?.origin_postal ?? '', origin_country: quote?.origin_country ?? 'US',
        dest_city: quote?.dest_city ?? '', dest_state: quote?.dest_state ?? '', dest_postal: quote?.dest_postal ?? '', dest_country: quote?.dest_country ?? 'US',
        ready_date: quote?.ready_date ?? '',
        service_level: quote?.service_level ?? '',
        weight: quote?.weight ?? '', weight_unit: quote?.weight_unit ?? 'lb', length: quote?.length ?? '', width: quote?.width ?? '', height: quote?.height ?? '', dim_unit: quote?.dim_unit ?? 'in',
        freight_class: quote?.freight_class ?? '', pallet_count: quote?.pallet_count ?? '', piece_count: quote?.piece_count ?? '', accessorials: quote?.accessorials ?? [],
        amount: quote?.amount ?? '', currency: quote?.currency ?? 'USD', transit_days: quote?.transit_days ?? '', estimated_delivery: quote?.estimated_delivery ?? '',
        quote_reference: quote?.quote_reference ?? '', expires_at: quote?.expires_at ?? '', notes: quote?.notes ?? '',
    });

    const showParcel = data.service_line !== 'freight';
    const showFreight = data.service_line !== 'express';

    // Instant estimate from the DHL contract rate card (US-outbound express).
    const tiers = rateCard?.discount_tiers ?? [];
    const canEstimate = !!rateCard?.available && data.carrier === 'dhl' && showParcel;
    const [contentType, setContentType] = useState<'package' | 'document'>('package');
    const [discountPct, setDiscountPct] = useState<string>(tiers[0] ? String(tiers[0].pct) : '0');
    const [premium, setPremium] = useState<string>('');
    const [estimate, setEstimate] = useState<Estimate | null>(null);
    const [estimating, setEstimating] = useState(false);
    const [estimateError, setEstimateError] = useState<string | null>(null);

    const runEstimate = async () => {
        setEstimating(true);
        setEstimateError(null);
        try {
            const { data: res } = await axios.post('/shipments/rates/estimate', {
                origin_country: data.origin_country || 'US',
                dest_country: data.dest_country,
                weight: data.weight || null,
                weight_unit: data.weight_unit,
                length: data.length || null, width: data.width || null, height: data.height || null,
                dim_unit: data.dim_unit,
                content_type: contentType,
                discount_pct: discountPct === '' ? null : Number(discountPct),
                premium: premium || null,
            });
            const e: Estimate = res.estimate;
            setEstimate(e);
            // Pre-fill the Quote fields with the computed figures (still editable).
            setData('amount', e.net_amount.toFixed(2));
            setData('currency', e.currency);
            setData('service_level', e.service_level);
            if (e.transit_days != null) setData('transit_days', String(e.transit_days));
        } catch (err) {
            setEstimate(null);
            const msg = axios.isAxiosError(err) ? (err.response?.data as { message?: string })?.message : null;
            setEstimateError(msg ?? 'Could not estimate this shipment — check the destination and weight.');
        } finally {
            setEstimating(false);
        }
    };

    const money = (n: number) => n.toLocaleString(undefined, { style: 'currency', currency: estimate?.currency ?? 'USD' });

    const toggleAcc = (val: string) => {
        setData('accessorials', data.accessorials.includes(val) ? data.accessorials.filter(a => a !== val) : [...data.accessorials, val]);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        transform(d => {
            const out: Record<string, unknown> = { ...d };
            for (const k of Object.keys(out)) if (out[k] === '') out[k] = null;
            return out;
        });
        if (editing) put(`/shipments/rates/${quote!.ulid}`);
        else post('/shipments/rates');
    };

    // Prefilled email draft to the carrier asking for a spot rate.
    const mailtoHref = () => {
        const lane = [
            [data.origin_city, data.origin_postal].filter(Boolean).join(' '),
            [data.dest_city, data.dest_postal].filter(Boolean).join(' '),
        ].filter(Boolean).join(' → ');
        const carrierLabel = carrierOptions.find(o => o.value === data.carrier)?.label ?? data.carrier;
        const serviceLabel = serviceLineOptions.find(o => o.value === data.service_line)?.label;
        const accLabels = data.accessorials.map(a => accessorialOptions.find(o => o.value === a)?.label ?? a);
        const subject = `Spot rate request — ${data.reference || lane || carrierLabel}`;
        const body = [
            'Hello,', '', 'Please provide a spot rate quote for the following shipment:', '',
            `Carrier: ${carrierLabel}`,
            serviceLabel ? `Service: ${serviceLabel}` : '',
            lane ? `Lane: ${lane}` : '',
            data.ready_date ? `Ready date: ${data.ready_date}` : '',
            data.weight ? `Weight: ${data.weight} ${data.weight_unit}` : '',
            (data.length || data.width || data.height) ? `Dimensions: ${data.length || '?'} × ${data.width || '?'} × ${data.height || '?'} ${data.dim_unit}` : '',
            data.pallet_count ? `Pallets: ${data.pallet_count}` : '',
            data.piece_count ? `Pieces: ${data.piece_count}` : '',
            data.freight_class ? `Freight class: ${data.freight_class}` : '',
            accLabels.length ? `Accessorials: ${accLabels.join(', ')}` : '',
            '', 'Thank you.',
        ].filter(Boolean).join('\n');
        return `mailto:${encodeURIComponent(data.contact_email || '')}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
    };

    const emailRequest = () => {
        if (editing) router.post(`/shipments/rates/${quote!.ulid}/request`, {}, { preserveScroll: true });
    };

    const onPickFile = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file || !editing) return;
        setUploading(true);
        router.post(`/shipments/rates/${quote!.ulid}/document`, { file }, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => { setUploading(false); if (fileInput.current) fileInput.current.value = ''; },
        });
    };

    const field = (name: keyof QuoteForm, label: string, props: React.InputHTMLAttributes<HTMLInputElement> = {}) => (
        <div>
            <label className="label">{label}</label>
            <input {...props} value={data[name] as string} onChange={e => setData(name, e.target.value)} className="input" />
            {errors[name] && <p className="mt-1.5 text-sm text-destructive">{errors[name]}</p>}
        </div>
    );

    return (
        <ShipmentsLayout>
            <Head title={editing ? 'Edit rate request' : 'New rate request'} />
            <div className="mx-auto max-w-3xl px-4 py-8 sm:px-6">
                <Link href="/shipments/rates" className="mb-4 inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" /> Back to rate quotes
                </Link>

                <div className="mb-6 flex items-center gap-3">
                    <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-gradient text-white"><BadgeDollarSign className="h-5 w-5" /></span>
                    <h1 className="text-2xl font-bold tracking-tight text-foreground">{editing ? 'Edit rate request' : 'New rate request'}</h1>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    {/* Basics */}
                    <section className="card-surface space-y-5 p-6">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label className="label">Carrier</label>
                                <CarrierField value={data.carrier} onChange={v => setData('carrier', v)} options={carrierOptions} className="w-full" />
                                {errors.carrier && <p className="mt-1.5 text-sm text-destructive">{errors.carrier}</p>}
                            </div>
                            <div>
                                <label className="label">Service line</label>
                                <Select value={data.service_line} onChange={v => setData('service_line', v)} options={serviceLineOptions} placeholder="General / not sure" className="w-full" />
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            {field('reference', 'Reference', { placeholder: 'e.g. RFP 24-118 — bid set to DC' })}
                            {field('contact_email', 'Carrier contact email', { type: 'email', placeholder: 'rep@dhl.com' })}
                        </div>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label className="label">Link to shipment <span className="text-muted-foreground">(optional)</span></label>
                                <Combobox
                                    value={data.proposal_mailing_id}
                                    onChange={v => setData('proposal_mailing_id', v)}
                                    options={linkableShipments.map(s => ({ value: String(s.id), label: s.label }))}
                                    placeholder="Find a shipment…"
                                    className="w-full"
                                />
                            </div>
                            {field('ready_date', 'Ready / pickup date', { type: 'date' })}
                        </div>
                    </section>

                    {/* Lane */}
                    <section className="card-surface space-y-4 p-6">
                        <h2 className="text-sm font-semibold text-foreground">Lane</h2>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div className="space-y-3 rounded-lg border border-border/60 p-3">
                                <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Origin</p>
                                {field('origin_city', 'City', { placeholder: 'Sacramento' })}
                                <div className="grid grid-cols-3 gap-2">
                                    {field('origin_state', 'State', { placeholder: 'CA' })}
                                    {field('origin_postal', 'Postal', { placeholder: '95814' })}
                                    {field('origin_country', 'Country', { placeholder: 'US', maxLength: 2 })}
                                </div>
                            </div>
                            <div className="space-y-3 rounded-lg border border-border/60 p-3">
                                <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Destination</p>
                                {field('dest_city', 'City', { placeholder: 'Washington' })}
                                <div className="grid grid-cols-3 gap-2">
                                    {field('dest_state', 'State', { placeholder: 'DC' })}
                                    {field('dest_postal', 'Postal', { placeholder: '20001' })}
                                    {field('dest_country', 'Country', { placeholder: 'US', maxLength: 2 })}
                                </div>
                            </div>
                        </div>
                    </section>

                    {/* Package (parcel) */}
                    {showParcel && (
                        <section className="card-surface space-y-4 p-6">
                            <h2 className="text-sm font-semibold text-foreground">Package <span className="font-normal text-muted-foreground">(parcel / express)</span></h2>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="label">Weight</label>
                                    <div className="flex gap-2">
                                        <input value={data.weight} onChange={e => setData('weight', e.target.value)} className="input" type="number" min="0" step="0.01" placeholder="5" />
                                        <Select value={data.weight_unit} onChange={v => setData('weight_unit', v)} options={[{ value: 'lb', label: 'lb' }, { value: 'kg', label: 'kg' }]} className="w-24" />
                                    </div>
                                    {errors.weight && <p className="mt-1.5 text-sm text-destructive">{errors.weight}</p>}
                                </div>
                                <div>
                                    <label className="label">Dimensions (L × W × H)</label>
                                    <div className="flex items-center gap-2">
                                        <input value={data.length} onChange={e => setData('length', e.target.value)} className="input" type="number" min="0" step="0.1" placeholder="L" />
                                        <input value={data.width} onChange={e => setData('width', e.target.value)} className="input" type="number" min="0" step="0.1" placeholder="W" />
                                        <input value={data.height} onChange={e => setData('height', e.target.value)} className="input" type="number" min="0" step="0.1" placeholder="H" />
                                        <Select value={data.dim_unit} onChange={v => setData('dim_unit', v)} options={[{ value: 'in', label: 'in' }, { value: 'cm', label: 'cm' }]} className="w-24" />
                                    </div>
                                </div>
                            </div>

                            {/* Instant estimate from the DHL contract rate card */}
                            {canEstimate && (
                                <div className="rounded-xl border border-primary/30 bg-primary/[0.03] p-4">
                                    <div className="mb-3 flex flex-wrap items-center gap-2">
                                        <Calculator className="h-4 w-4 text-primary" />
                                        <h3 className="text-sm font-semibold text-foreground">Instant estimate</h3>
                                        <span className="text-xs text-muted-foreground">
                                            from the DHL contract rate card{rateCard?.as_of ? ` · as of ${rateCard.as_of}` : ''}
                                        </span>
                                    </div>

                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                        <div>
                                            <label className="label">Contents</label>
                                            <Select value={contentType} onChange={v => setContentType(v as 'package' | 'document')}
                                                options={[{ value: 'package', label: 'Package' }, { value: 'document', label: 'Document / envelope' }]} className="w-full" />
                                        </div>
                                        <div>
                                            <label className="label">Your DHL discount</label>
                                            <Select value={discountPct} onChange={setDiscountPct}
                                                options={[{ value: '0', label: 'None (published rate)' }, ...tiers.map(t => ({ value: String(t.pct), label: t.label }))]} className="w-full" />
                                        </div>
                                        <div>
                                            <label className="label">Premium</label>
                                            <Select value={premium} onChange={setPremium}
                                                options={[{ value: '', label: 'None' }, { value: '9', label: '9:00 (+$25.20)' }, { value: '12', label: '12:00 (+$6.30)' }]} className="w-full" />
                                        </div>
                                    </div>

                                    <div className="mt-3 flex flex-wrap items-center gap-3">
                                        <button type="button" onClick={runEstimate} disabled={estimating || !data.dest_country}
                                            className="bg-brand-gradient shadow-glow inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 disabled:opacity-60">
                                            <Calculator className="h-4 w-4" /> {estimating ? 'Estimating…' : 'Estimate from rate card'}
                                        </button>
                                        <span className="text-xs text-muted-foreground">US-outbound · destination zone × weight · fills Price below</span>
                                    </div>

                                    {estimateError && (
                                        <p className="mt-3 flex items-start gap-1.5 text-sm text-destructive">
                                            <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" /> {estimateError}
                                        </p>
                                    )}

                                    {estimate && (
                                        <div className="mt-4 rounded-lg border border-border/60 bg-card p-4 text-sm">
                                            <div className="flex flex-wrap items-baseline justify-between gap-2">
                                                <span className="font-semibold text-foreground">{estimate.service_level} · Zone {estimate.zone}</span>
                                                <span className="text-lg font-bold text-foreground">{money(estimate.net_amount)}</span>
                                            </div>
                                            <dl className="mt-2 space-y-1 text-muted-foreground">
                                                <div className="flex justify-between gap-3">
                                                    <dt>Published rate · {estimate.billable_weight_lb} lb{estimate.band === 'multiplier' && estimate.per_lb_rate ? ` @ $${estimate.per_lb_rate}/lb` : ''}</dt>
                                                    <dd>{money(estimate.published_amount)}</dd>
                                                </div>
                                                {estimate.discount_amount > 0 && (
                                                    <div className="flex justify-between gap-3">
                                                        <dt>Contract discount {Math.round(estimate.discount_pct * 100)}%</dt>
                                                        <dd className="text-emerald-600">−{money(estimate.discount_amount)}</dd>
                                                    </div>
                                                )}
                                                {estimate.premium_amount > 0 && (
                                                    <div className="flex justify-between gap-3">
                                                        <dt>Premium {estimate.premium_key}:00</dt>
                                                        <dd>+{money(estimate.premium_amount)}</dd>
                                                    </div>
                                                )}
                                                <div className="flex justify-between gap-3 border-t border-border/60 pt-1 font-semibold text-foreground">
                                                    <dt>Net estimate</dt>
                                                    <dd>{money(estimate.net_amount)} {estimate.currency}</dd>
                                                </div>
                                            </dl>
                                            {estimate.transit_days != null && (
                                                <p className="mt-2 text-xs text-muted-foreground">Typical transit ~{estimate.transit_days} business days (estimate, not contractual).</p>
                                            )}
                                            {estimate.warnings.map((w, i) => (
                                                <p key={i} className="mt-2 flex items-start gap-1.5 text-xs text-amber-600">
                                                    <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0" /> {w}
                                                </p>
                                            ))}
                                            <p className="mt-2 text-xs text-muted-foreground">Transport charge only — excludes surcharges, duties &amp; taxes. Filled into Price below; adjust as needed.</p>
                                        </div>
                                    )}
                                </div>
                            )}
                        </section>
                    )}

                    {/* Freight */}
                    {showFreight && (
                        <section className="card-surface space-y-4 p-6">
                            <h2 className="text-sm font-semibold text-foreground">Freight <span className="font-normal text-muted-foreground">(LTL)</span></h2>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                {field('freight_class', 'Freight class', { placeholder: '70' })}
                                {field('pallet_count', 'Pallets', { type: 'number', min: '0', placeholder: '2' })}
                                {field('piece_count', 'Pieces', { type: 'number', min: '0', placeholder: '4' })}
                            </div>
                            <div>
                                <label className="label">Accessorials</label>
                                <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    {accessorialOptions.map(a => (
                                        <label key={a.value} className="flex cursor-pointer items-center gap-2 text-sm text-foreground">
                                            <Checkbox checked={data.accessorials.includes(a.value)} onChange={() => toggleAcc(a.value)} ariaLabel={a.label} />
                                            {a.label}
                                        </label>
                                    ))}
                                </div>
                            </div>
                        </section>
                    )}

                    {/* Request email + rate sheet — only once the quote is saved */}
                    {editing && (
                        <section className="card-surface space-y-4 p-6">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h2 className="text-sm font-semibold text-foreground">Request &amp; rate sheet</h2>
                                    <p className="text-xs text-muted-foreground">Spot rates are quoted by email. Send the request, then attach the PDF the carrier replies with — we’ll read the price off it.</p>
                                </div>
                                <a href={mailtoHref()} onClick={emailRequest}
                                    className="inline-flex items-center gap-1.5 rounded-full border border-primary/40 px-4 py-2 text-sm font-semibold text-primary transition hover:bg-primary/5">
                                    <Mail className="h-4 w-4" /> Compose request email
                                </a>
                            </div>

                            <div className="rounded-lg border border-dashed border-border p-4">
                                {quote?.document ? (
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <div className="flex items-center gap-2 text-sm text-foreground">
                                            <FileText className="h-4 w-4 text-muted-foreground" />
                                            <span className="font-medium">{quote.document.name}</span>
                                            {quote.document.size && <span className="text-xs text-muted-foreground">({quote.document.size})</span>}
                                        </div>
                                        <div className="flex items-center gap-1">
                                            <a href={`/shipments/rates/${quote.ulid}/document/download`} className="rounded-lg p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground" title="Download">
                                                <Download className="h-4 w-4" />
                                            </a>
                                            <button type="button" onClick={() => router.post(`/shipments/rates/${quote.ulid}/extract`, {}, { preserveScroll: true })}
                                                className="rounded-lg p-1.5 text-muted-foreground hover:bg-secondary hover:text-primary" title="Re-read with AI">
                                                <RefreshCw className="h-4 w-4" />
                                            </button>
                                            <button type="button" onClick={() => confirm('Remove the attached rate sheet?') && router.delete(`/shipments/rates/${quote.ulid}/document`, { preserveScroll: true })}
                                                className="rounded-lg p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive" title="Remove">
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <span className="inline-flex items-center gap-2 text-sm text-muted-foreground"><Paperclip className="h-4 w-4" /> No rate sheet attached yet</span>
                                        <button type="button" onClick={() => fileInput.current?.click()} disabled={uploading}
                                            className="inline-flex items-center gap-1.5 rounded-full bg-secondary px-4 py-2 text-sm font-medium text-foreground hover:bg-secondary/70 disabled:opacity-60">
                                            <Upload className="h-4 w-4" /> {uploading ? 'Uploading…' : 'Attach PDF & auto-read'}
                                        </button>
                                    </div>
                                )}
                                <input ref={fileInput} type="file" accept="application/pdf,image/jpeg,image/png" className="hidden" onChange={onPickFile} />
                            </div>
                        </section>
                    )}

                    {/* Quote result */}
                    <section className="card-surface space-y-4 p-6">
                        <h2 className="text-sm font-semibold text-foreground">Quote</h2>
                        {field('service_level', 'Service level', { placeholder: 'e.g. DHL Express Worldwide' })}
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <label className="label">Price</label>
                                <div className="flex gap-2">
                                    <input value={data.amount} onChange={e => setData('amount', e.target.value)} className="input" type="number" min="0" step="0.01" placeholder="0.00" />
                                    <Select value={data.currency} onChange={v => setData('currency', v)} options={CURRENCIES} className="w-24" />
                                </div>
                                {errors.amount && <p className="mt-1.5 text-sm text-destructive">{errors.amount}</p>}
                            </div>
                            {field('transit_days', 'Transit (days)', { type: 'number', min: '0', placeholder: '3' })}
                            {field('estimated_delivery', 'Estimated delivery', { type: 'date' })}
                        </div>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            {field('quote_reference', 'Carrier quote #', { placeholder: 'optional' })}
                            {field('expires_at', 'Quote valid until', { type: 'date' })}
                            <div>
                                <label className="label">Status</label>
                                <Select value={data.status} onChange={v => setData('status', v)} options={statusOptions} className="w-full" />
                            </div>
                        </div>
                        <div>
                            <label className="label">Notes</label>
                            <textarea value={data.notes} onChange={e => setData('notes', e.target.value)} className="input min-h-[72px]" placeholder="Anything worth remembering about this quote…" />
                        </div>
                    </section>

                    <div className="flex flex-wrap items-center justify-end gap-3">
                        <Link href="/shipments/rates" className="rounded-full px-4 py-2 text-sm font-medium text-muted-foreground hover:text-foreground">Cancel</Link>
                        <button type="submit" disabled={processing} className="bg-brand-gradient shadow-glow rounded-full px-5 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 disabled:opacity-60">
                            {processing ? 'Saving…' : editing ? 'Save changes' : 'Save rate request'}
                        </button>
                    </div>
                </form>
            </div>
        </ShipmentsLayout>
    );
}
