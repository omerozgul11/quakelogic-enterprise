import { useRef, useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, UploadCloud, FileText, Image as ImageIcon, Table, File as FileIcon, X, Sparkles } from 'lucide-react';
import { ShipmentsLayout } from '@/Components/layout/ShipmentsLayout';
import { Select } from '@/Components/ui/Select';
import { CarrierField } from '@/Components/ui/CarrierField';

interface Props {
    carrierOptions: { value: string; label: string }[];
    referenceTypeOptions: { value: string; label: string }[];
}

const ACCEPT = '.csv,.pdf,.png,.jpg,.jpeg,.webp,.gif,.txt,.md,.doc,.docx,application/pdf,image/*,text/csv,text/plain';
const MAX_FILES = 20;

function fileKind(name: string) {
    const ext = name.split('.').pop()?.toLowerCase() ?? '';
    if (ext === 'csv') return { Icon: Table, tone: 'text-emerald-500' };
    if (['png', 'jpg', 'jpeg', 'webp', 'gif'].includes(ext)) return { Icon: ImageIcon, tone: 'text-violet-500' };
    if (ext === 'pdf') return { Icon: FileText, tone: 'text-rose-500' };
    return { Icon: FileIcon, tone: 'text-muted-foreground' };
}

function humanSize(bytes: number) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

export default function ShipmentsImport({ carrierOptions, referenceTypeOptions }: Props) {
    const { data, setData, post, processing, errors } = useForm<{
        files: File[];
        pasted_text: string;
        carrier: string;
        reference_type: string;
        scope: string;
        recipient_name: string;
        deadline: string;
    }>({
        files: [],
        pasted_text: '',
        carrier: 'ups',
        reference_type: 'orderNbr',
        scope: 'domestic',
        recipient_name: '',
        deadline: '',
    });

    const [dragging, setDragging] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);

    const addFiles = (incoming: FileList | null) => {
        if (!incoming) return;
        const merged = [...data.files];
        for (const f of Array.from(incoming)) {
            if (merged.length >= MAX_FILES) break;
            if (!merged.some(e => e.name === f.name && e.size === f.size)) merged.push(f);
        }
        setData('files', merged);
    };

    const removeFile = (i: number) => setData('files', data.files.filter((_, idx) => idx !== i));

    const pastedCount = data.pasted_text.split(/[\s,]+/).map(s => s.trim()).filter(Boolean).length;
    const canSubmit = data.files.length > 0 || pastedCount > 0;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/shipments/mailings/import', { forceFormData: true });
    };

    return (
        <ShipmentsLayout>
            <Head title="Import shipments" />
            <div className="mx-auto max-w-2xl px-4 py-8 sm:px-6">
                <Link href="/shipments/mailings" className="mb-4 inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" /> Back to shipments
                </Link>

                <div className="mb-6 flex items-center gap-3">
                    <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-gradient text-white"><UploadCloud className="h-5 w-5" /></span>
                    <h1 className="text-2xl font-bold tracking-tight text-foreground">Import shipments</h1>
                </div>

                <p className="mb-5 text-sm text-muted-foreground">
                    Dump shipping documents — label PDFs, photos of labels, packing slips, or a CSV — and
                    we'll read the tracking info and create the shipments for you. You can also paste
                    tracking numbers directly. Status loads automatically once each shipment is created.
                </p>

                <form onSubmit={submit} className="card-surface space-y-5 p-6">
                    {/* Dropzone */}
                    <div>
                        <label className="label">Documents</label>
                        <div
                            onClick={() => inputRef.current?.click()}
                            onDragOver={e => { e.preventDefault(); setDragging(true); }}
                            onDragLeave={() => setDragging(false)}
                            onDrop={e => { e.preventDefault(); setDragging(false); addFiles(e.dataTransfer.files); }}
                            className={`flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed px-6 py-8 text-center transition-colors ${
                                dragging ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/50 hover:bg-secondary/40'
                            }`}
                        >
                            <UploadCloud className="mb-2 h-7 w-7 text-muted-foreground" />
                            <p className="text-sm font-medium text-foreground">Drop files here or click to browse</p>
                            <p className="mt-1 text-xs text-muted-foreground">PDF, images (PNG/JPG), CSV, TXT, Word — up to {MAX_FILES} files, 50 MB each</p>
                            <input
                                ref={inputRef}
                                type="file"
                                multiple
                                accept={ACCEPT}
                                className="hidden"
                                onChange={e => { addFiles(e.target.files); e.target.value = ''; }}
                            />
                        </div>
                        {errors.files && <p className="mt-1.5 text-sm text-destructive">{errors.files}</p>}
                        {(errors as Record<string, string>)['files.0'] && (
                            <p className="mt-1.5 text-sm text-destructive">One or more files were rejected (unsupported type or too large).</p>
                        )}

                        {data.files.length > 0 && (
                            <ul className="mt-3 space-y-1.5">
                                {data.files.map((f, i) => {
                                    const { Icon, tone } = fileKind(f.name);
                                    return (
                                        <li key={`${f.name}-${i}`} className="flex items-center gap-2.5 rounded-lg border border-border bg-background px-3 py-2 text-sm">
                                            <Icon className={`h-4 w-4 shrink-0 ${tone}`} />
                                            <span className="min-w-0 flex-1 truncate text-foreground">{f.name}</span>
                                            <span className="shrink-0 text-xs text-muted-foreground">{humanSize(f.size)}</span>
                                            <button type="button" onClick={() => removeFile(i)} className="shrink-0 text-muted-foreground hover:text-destructive">
                                                <X className="h-4 w-4" />
                                            </button>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}

                        <p className="mt-2 flex items-start gap-1.5 text-xs text-muted-foreground">
                            <Sparkles className="mt-0.5 h-3.5 w-3.5 shrink-0 text-primary" />
                            PDFs and label photos are read automatically. CSV columns: <span className="font-mono">tracking_number, recipient, deadline, category</span> (extra columns ignored).
                        </p>
                    </div>

                    {/* Paste */}
                    <div>
                        <label htmlFor="paste" className="label">
                            …or paste tracking numbers {pastedCount > 0 && <span className="text-muted-foreground">({pastedCount})</span>}
                        </label>
                        <textarea
                            id="paste"
                            value={data.pasted_text}
                            onChange={e => setData('pasted_text', e.target.value)}
                            className="input min-h-[96px] font-mono text-sm"
                            placeholder={'1Z999AA10123456784\n1Z999AA10123456785'}
                        />
                        {errors.pasted_text && <p className="mt-1.5 text-sm text-destructive">{errors.pasted_text}</p>}
                    </div>

                    {/* Defaults */}
                    <div className="rounded-xl border border-border bg-secondary/30 p-4">
                        <p className="mb-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Defaults (used when a document doesn't specify)</p>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label className="label">Carrier</label>
                                <CarrierField value={data.carrier} onChange={v => setData('carrier', v)} options={carrierOptions} className="w-full" />
                                {errors.carrier && <p className="mt-1.5 text-sm text-destructive">{errors.carrier}</p>}
                            </div>
                            <div>
                                <label className="label">Category</label>
                                <Select value={data.scope} onChange={v => setData('scope', v)} options={[{ value: 'domestic', label: 'Domestic' }, { value: 'international', label: 'International' }]} className="w-full" />
                            </div>
                            {data.carrier === 'jbhunt' && (
                                <div>
                                    <label className="label">Reference type</label>
                                    <Select value={data.reference_type} onChange={v => setData('reference_type', v)} options={referenceTypeOptions} className="w-full" />
                                    {errors.reference_type && <p className="mt-1.5 text-sm text-destructive">{errors.reference_type}</p>}
                                </div>
                            )}
                            <div>
                                <label htmlFor="recipient" className="label">Recipient <span className="text-muted-foreground">(optional)</span></label>
                                <input id="recipient" value={data.recipient_name} onChange={e => setData('recipient_name', e.target.value)} className="input" placeholder="Agency / office" />
                            </div>
                            <div>
                                <label htmlFor="deadline" className="label">Deadline <span className="text-muted-foreground">(optional)</span></label>
                                <input id="deadline" type="date" value={data.deadline} onChange={e => setData('deadline', e.target.value)} className="input" />
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center justify-end gap-3 pt-2">
                        <Link href="/shipments/mailings" className="rounded-full px-4 py-2 text-sm font-medium text-muted-foreground hover:text-foreground">Cancel</Link>
                        <button type="submit" disabled={processing || !canSubmit} className="bg-brand-gradient shadow-glow rounded-full px-5 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 disabled:opacity-60">
                            {processing ? 'Reading…' : 'Import shipments'}
                        </button>
                    </div>
                </form>
            </div>
        </ShipmentsLayout>
    );
}
