import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { ArrowLeft, Download, RefreshCw, Save, Plus, X, ScrollText, FileText, ImageIcon } from 'lucide-react';

interface Spec { [key: string]: string; label: string; value: string }
interface Sections { [key: string]: string | string[] | Spec[] | null; tagline: string | null; overview: string; key_features: string[]; specifications: Spec[]; applications: string[] }
interface Datasheet {
    id: number; ulid: string; product_name: string; model_number: string | null; tagline: string | null;
    status: string; input_notes: string | null; sections: Sections;
    media: Array<{ name: string; kind: string; mime: string | null }>; generated_at: string | null;
}
interface Props { datasheet: Datasheet; aiProvider?: string; aiAvailable?: boolean }

const input = 'w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary';

export default function DatasheetShow({ datasheet }: Props) {
    const s = datasheet.sections;
    const [productName, setProductName] = useState(datasheet.product_name);
    const [modelNumber, setModelNumber] = useState(datasheet.model_number ?? '');
    const [tagline, setTagline] = useState(s.tagline ?? datasheet.tagline ?? '');
    const [overview, setOverview] = useState(s.overview ?? '');
    const [features, setFeatures] = useState<string[]>(s.key_features ?? []);
    const [apps, setApps] = useState<string[]>(s.applications ?? []);
    const [specs, setSpecs] = useState<Spec[]>(s.specifications ?? []);
    const [saving, setSaving] = useState(false);
    const [regenerating, setRegenerating] = useState(false);

    const save = () => {
        setSaving(true);
        router.post(`/ai/datasheets/${datasheet.id}/edit`, {
            _method: 'put',
            product_name: productName,
            model_number: modelNumber,
            tagline,
            sections: { tagline, overview, key_features: features.filter(Boolean), applications: apps.filter(Boolean), specifications: specs.filter(x => x.label.trim()) },
        }, { preserveScroll: true, onFinish: () => setSaving(false) });
    };

    const regenerate = () => {
        setRegenerating(true);
        router.post(`/ai/datasheets/${datasheet.id}/regenerate`, {}, { onFinish: () => setRegenerating(false) });
    };

    const listField = (items: string[], set: (v: string[]) => void, placeholder: string) => (
        <div className="space-y-2">
            {items.map((item, i) => (
                <div key={i} className="flex gap-2">
                    <input className={input} value={item} placeholder={placeholder} onChange={e => set(items.map((x, j) => j === i ? e.target.value : x))} />
                    <button type="button" onClick={() => set(items.filter((_, j) => j !== i))} className="rounded p-2 text-muted-foreground hover:text-red-600"><X className="h-4 w-4" /></button>
                </div>
            ))}
            <button type="button" onClick={() => set([...items, ''])} className="flex items-center gap-1 text-xs font-medium text-primary hover:underline"><Plus className="h-3.5 w-3.5" /> Add</button>
        </div>
    );

    return (
        <AppLayout>
            <Head title={`Datasheet — ${datasheet.product_name}`} />
            <div className="p-4 sm:p-6">
            <div className="mb-2"><Link href="/ai/datasheets" className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"><ArrowLeft className="h-4 w-4" /> All datasheets</Link></div>
            <PageHeader
                title={datasheet.product_name}
                description={datasheet.model_number ?? 'Product datasheet'}
                icon={ScrollText}
                actions={
                    <div className="flex flex-wrap items-center gap-2">
                        <Button variant="secondary" icon={RefreshCw} onClick={regenerate} disabled={regenerating} className={regenerating ? '[&_svg]:animate-spin' : ''}>{regenerating ? 'Regenerating…' : 'Regenerate'}</Button>
                        <a href={`/ai/datasheets/${datasheet.id}/download`} className="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90"><Download className="h-4 w-4" /> Download PDF</a>
                    </div>
                }
            />

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-5 lg:col-span-2">
                    <Card className="p-5">
                        <div className="grid grid-cols-2 gap-3">
                            <div><label className="mb-1 block text-xs font-medium text-muted-foreground">Product name</label><input className={input} value={productName} onChange={e => setProductName(e.target.value)} /></div>
                            <div><label className="mb-1 block text-xs font-medium text-muted-foreground">Model / part no.</label><input className={input} value={modelNumber} onChange={e => setModelNumber(e.target.value)} /></div>
                        </div>
                        <div className="mt-3"><label className="mb-1 block text-xs font-medium text-muted-foreground">Tagline</label><input className={input} value={tagline} onChange={e => setTagline(e.target.value)} /></div>
                    </Card>

                    <Card className="p-5">
                        <h2 className="mb-2 font-semibold text-foreground">Overview</h2>
                        <textarea className={`${input} min-h-[140px]`} value={overview} onChange={e => setOverview(e.target.value)} />
                    </Card>

                    <Card className="p-5">
                        <h2 className="mb-3 font-semibold text-foreground">Technical Specifications</h2>
                        <div className="space-y-2">
                            {specs.map((sp, i) => (
                                <div key={i} className="flex gap-2">
                                    <input className={`${input} w-2/5`} value={sp.label} placeholder="Spec" onChange={e => setSpecs(specs.map((x, j) => j === i ? { ...x, label: e.target.value } : x))} />
                                    <input className={input} value={sp.value} placeholder="Value" onChange={e => setSpecs(specs.map((x, j) => j === i ? { ...x, value: e.target.value } : x))} />
                                    <button type="button" onClick={() => setSpecs(specs.filter((_, j) => j !== i))} className="rounded p-2 text-muted-foreground hover:text-red-600"><X className="h-4 w-4" /></button>
                                </div>
                            ))}
                            <button type="button" onClick={() => setSpecs([...specs, { label: '', value: '' }])} className="flex items-center gap-1 text-xs font-medium text-primary hover:underline"><Plus className="h-3.5 w-3.5" /> Add spec</button>
                        </div>
                    </Card>

                    <Card className="p-5">
                        <h2 className="mb-3 font-semibold text-foreground">Key Features</h2>
                        {listField(features, setFeatures, 'Feature / benefit')}
                    </Card>

                    <Card className="p-5">
                        <h2 className="mb-3 font-semibold text-foreground">Applications</h2>
                        {listField(apps, setApps, 'Use / industry')}
                    </Card>

                    <Button icon={Save} onClick={save} disabled={saving}>{saving ? 'Saving…' : 'Save changes'}</Button>
                </div>

                <div className="space-y-5">
                    <Card className="p-5">
                        <h2 className="mb-3 text-sm font-semibold text-foreground">Source material</h2>
                        {datasheet.media.length === 0 ? (
                            <p className="text-xs text-muted-foreground">No files — generated from notes only.</p>
                        ) : (
                            <ul className="space-y-2">
                                {datasheet.media.map((m, i) => (
                                    <li key={i} className="flex items-center gap-2 text-xs text-muted-foreground">
                                        {m.kind === 'image' ? <ImageIcon className="h-4 w-4 shrink-0" /> : <FileText className="h-4 w-4 shrink-0" />}
                                        <span className="truncate">{m.name}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                        {datasheet.input_notes && (
                            <div className="mt-4">
                                <h3 className="mb-1 text-xs font-semibold text-foreground">Your notes</h3>
                                <p className="whitespace-pre-wrap text-xs text-muted-foreground">{datasheet.input_notes}</p>
                            </div>
                        )}
                    </Card>
                </div>
            </div>
            </div>
        </AppLayout>
    );
}
