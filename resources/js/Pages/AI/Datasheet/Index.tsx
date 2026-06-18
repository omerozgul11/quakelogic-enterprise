import { Head, Link, useForm, router } from '@inertiajs/react';
import { useRef } from 'react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { EmptyState } from '@/Components/ui/EmptyState';
import { ScrollText, UploadCloud, Sparkles, Loader2, FileText, ImageIcon, Trash2, ShieldAlert } from 'lucide-react';

interface Row {
    id: number;
    product_name: string;
    model_number: string | null;
    status: string;
    creator: string | null;
    spec_count: number;
    image_count: number;
    generated_at: string | null;
    updated_at: string | null;
}

interface Props {
    datasheets: Row[];
    aiProvider?: string;
    aiAvailable?: boolean;
}

const input = 'w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary';

export default function DatasheetIndex({ datasheets, aiProvider, aiAvailable }: Props) {
    const specRef = useRef<HTMLInputElement>(null);
    const imageRef = useRef<HTMLInputElement>(null);

    const form = useForm<{ product_name: string; model_number: string; tagline: string; input_notes: string; specs: File[]; images: File[] }>({
        product_name: '', model_number: '', tagline: '', input_notes: '', specs: [], images: [],
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/ai/datasheets', { forceFormData: true });
    };

    return (
        <AppLayout>
            <Head title="Datasheet Writer" />
            <div className="p-4 sm:p-6">
            <PageHeader
                title="Datasheet Writer"
                description="Dump spec sheets, technical notes and product photos — get a fully written, QuakeLogic-branded datasheet."
                icon={ScrollText}
            />

            {!aiAvailable && (
                <div className="mb-5 flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-300">
                    <ShieldAlert className="h-4 w-4 shrink-0" />
                    <span>AI provider <strong>{aiProvider ?? 'fake'}</strong> isn’t fully configured — datasheets will use a basic template until a live key (e.g. <code>AI_PROVIDER=gemini</code>) is set.</span>
                </div>
            )}

            <div className="grid gap-6 lg:grid-cols-5">
                {/* New datasheet */}
                <Card className="p-5 lg:col-span-2">
                    <h2 className="mb-4 flex items-center gap-2 font-semibold text-foreground"><Sparkles className="h-4 w-4 text-primary" /> New Datasheet</h2>
                    <form onSubmit={submit} className="space-y-3">
                        <div>
                            <label className="mb-1 block text-xs font-medium text-muted-foreground">Product name *</label>
                            <input className={input} value={form.data.product_name} onChange={e => form.setData('product_name', e.target.value)} placeholder="QL-RouteMaster 1530 ATC CNC Router" />
                            {form.errors.product_name && <p className="mt-1 text-xs text-red-600">{form.errors.product_name}</p>}
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="mb-1 block text-xs font-medium text-muted-foreground">Model / part no.</label>
                                <input className={input} value={form.data.model_number} onChange={e => form.setData('model_number', e.target.value)} placeholder="QL-RM1530" />
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-medium text-muted-foreground">Tagline (optional)</label>
                                <input className={input} value={form.data.tagline} onChange={e => form.setData('tagline', e.target.value)} placeholder="leave blank to auto-write" />
                            </div>
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-muted-foreground">Technical details / notes</label>
                            <textarea className={`${input} min-h-[120px]`} value={form.data.input_notes} onChange={e => form.setData('input_notes', e.target.value)} placeholder="Paste specs, dimensions, features, applications — anything you have." />
                            {form.errors.input_notes && <p className="mt-1 text-xs text-red-600">{form.errors.input_notes}</p>}
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <button type="button" onClick={() => specRef.current?.click()} className="flex flex-col items-center justify-center gap-1 rounded-lg border border-dashed border-border px-3 py-4 text-xs text-muted-foreground hover:border-primary hover:text-primary">
                                <FileText className="h-5 w-5" />
                                {form.data.specs.length ? `${form.data.specs.length} spec file(s)` : 'Spec sheets (PDF/DOCX)'}
                            </button>
                            <button type="button" onClick={() => imageRef.current?.click()} className="flex flex-col items-center justify-center gap-1 rounded-lg border border-dashed border-border px-3 py-4 text-xs text-muted-foreground hover:border-primary hover:text-primary">
                                <ImageIcon className="h-5 w-5" />
                                {form.data.images.length ? `${form.data.images.length} image(s)` : 'Product photos'}
                            </button>
                        </div>
                        <input ref={specRef} type="file" multiple accept=".pdf,.doc,.docx,.txt,.csv,.xlsx" className="hidden" onChange={e => form.setData('specs', Array.from(e.target.files ?? []))} />
                        <input ref={imageRef} type="file" multiple accept=".jpg,.jpeg,.png,.webp" className="hidden" onChange={e => form.setData('images', Array.from(e.target.files ?? []))} />

                        <Button type="submit" disabled={form.processing} icon={form.processing ? Loader2 : UploadCloud} className={form.processing ? '[&_svg]:animate-spin' : ''}>
                            {form.processing ? 'Generating…' : 'Generate Datasheet'}
                        </Button>
                        <p className="text-[11px] text-muted-foreground">The AI reads your photos + spec sheets and writes the full datasheet. You can edit it afterward and export a branded PDF.</p>
                    </form>
                </Card>

                {/* Existing datasheets */}
                <div className="lg:col-span-3">
                    {datasheets.length === 0 ? (
                        <Card className="p-5"><EmptyState icon={ScrollText} title="No datasheets yet" description="Create your first one with the form on the left." /></Card>
                    ) : (
                        <div className="grid gap-3 sm:grid-cols-2">
                            {datasheets.map(d => (
                                <Card key={d.id} className="group relative p-4">
                                    <Link href={`/ai/datasheets/${d.id}`} className="block">
                                        <div className="mb-2 flex items-start justify-between gap-2">
                                            <p className="font-medium leading-tight text-foreground">{d.product_name}</p>
                                            <span className={`shrink-0 rounded-full px-2 py-0.5 text-[10px] font-medium ${d.status === 'generated' ? 'bg-emerald-100 text-emerald-700' : 'bg-secondary text-muted-foreground'}`}>{d.status}</span>
                                        </div>
                                        {d.model_number && <p className="text-xs text-muted-foreground">{d.model_number}</p>}
                                        <p className="mt-2 text-[11px] text-muted-foreground">{d.spec_count} spec · {d.image_count} image{d.image_count === 1 ? '' : 's'} · {d.creator ?? '—'}</p>
                                    </Link>
                                    <button
                                        onClick={() => { if (confirm(`Delete datasheet “${d.product_name}”?`)) router.delete(`/ai/datasheets/${d.id}`); }}
                                        className="absolute right-2 top-2 rounded p-1 text-muted-foreground opacity-0 transition-opacity hover:text-red-600 group-hover:opacity-100"
                                        title="Delete"
                                    ><Trash2 className="h-4 w-4" /></button>
                                </Card>
                            ))}
                        </div>
                    )}
                </div>
            </div>
            </div>
        </AppLayout>
    );
}
