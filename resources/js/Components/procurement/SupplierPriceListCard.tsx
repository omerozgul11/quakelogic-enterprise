import { useRef, useState } from 'react';
import axios from 'axios';
import { router } from '@inertiajs/react';
import { Card } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Modal } from '@/Components/ui/Modal';
import { Select } from '@/Components/ui/Select';
import { cn } from '@/Lib/utils';
import { FileUp, Loader2, Sparkles, Link2, Plus, Check } from 'lucide-react';

interface Match {
    product_id: number;
    sku: string;
    name: string;
    current_cost: number;
    currency: string | null;
}
interface ParsedLine {
    supplier_sku: string | null;
    name: string | null;
    price: number | null;
    currency: string | null;
    match: Match | null;
    action: 'update' | 'create' | 'skip';
}

interface Props {
    supplierId: number;
    supplierCurrency: string | null;
}

const ACTIONS = [
    { value: 'update', label: 'Update cost' },
    { value: 'create', label: 'Create new' },
    { value: 'skip', label: 'Skip' },
];

/**
 * Drop a supplier's price list / product sheet → the server parses it (sheet
 * reader for xlsx/csv, AI for pdf/images) and matches each line to an existing
 * inventory product. The user reviews every line here before anything is
 * written; applying updates matched products' cost and links them to the
 * supplier.
 */
export function SupplierPriceListCard({ supplierId, supplierCurrency }: Props) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [lines, setLines] = useState<ParsedLine[] | null>(null);
    const [applying, setApplying] = useState(false);
    const [dragOver, setDragOver] = useState(false);

    const handleFile = async (file: File) => {
        setError(null);
        setBusy(true);
        try {
            const fd = new FormData();
            fd.append('file', file);
            const { data } = await axios.post<{ status: string; lines: ParsedLine[] }>(
                `/procurement/suppliers/${supplierId}/price-list`, fd,
            );
            if (data.status === 'unavailable') {
                setError('AI reading is unavailable right now — upload an Excel/CSV price list instead, or try again later.');
            } else if (data.status === 'empty' || !data.lines?.length) {
                setError('No products could be read from that file. Make sure it lists product names/SKUs and prices.');
            } else {
                setLines(data.lines);
            }
        } catch (e) {
            const msg = axios.isAxiosError(e) ? (e.response?.data?.message as string | undefined) : undefined;
            setError(msg ?? 'Could not read that file. Please try a different format.');
        } finally {
            setBusy(false);
            if (inputRef.current) inputRef.current.value = '';
        }
    };

    const onDrop = (e: React.DragEvent<HTMLButtonElement>) => {
        e.preventDefault();
        setDragOver(false);
        const f = e.dataTransfer.files?.[0];
        if (f) handleFile(f);
    };

    const setLine = (i: number, patch: Partial<ParsedLine>) =>
        setLines(ls => (ls ? ls.map((l, idx) => (idx === i ? { ...l, ...patch } : l)) : ls));

    const apply = () => {
        if (!lines) return;
        setApplying(true);
        router.post(`/procurement/suppliers/${supplierId}/price-list/apply`, {
            lines: lines.map(l => ({
                action: l.action,
                product_id: l.match?.product_id ?? null,
                supplier_sku: l.supplier_sku,
                name: l.name,
                price: l.price,
                currency: l.currency,
            })),
        }, {
            preserveScroll: true,
            onFinish: () => { setApplying(false); setLines(null); },
        });
    };

    const counts = lines && {
        update: lines.filter(l => l.action === 'update').length,
        create: lines.filter(l => l.action === 'create').length,
        skip: lines.filter(l => l.action === 'skip').length,
    };

    return (
        <Card className="p-5">
            <h2 className="mb-1 flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70">
                <Sparkles className="h-4 w-4" /> Price list / product sheet
            </h2>
            <p className="mb-3 text-xs text-muted-foreground">
                Drop an Excel, CSV, PDF or image. We read the products &amp; prices and update your inventory cost — you review every line before anything is saved.
            </p>

            <button
                type="button"
                onClick={() => inputRef.current?.click()}
                onDragOver={e => { e.preventDefault(); setDragOver(true); }}
                onDragLeave={() => setDragOver(false)}
                onDrop={onDrop}
                disabled={busy}
                className={cn(
                    'flex w-full flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-4 py-8 text-center transition-colors',
                    dragOver ? 'border-primary bg-primary/5' : 'border-border hover:border-muted-foreground/40',
                    busy && 'opacity-60',
                )}
            >
                {busy ? (
                    <><Loader2 className="h-6 w-6 animate-spin text-primary" /><span className="text-sm text-muted-foreground">Reading the file…</span></>
                ) : (
                    <>
                        <FileUp className="h-6 w-6 text-muted-foreground" />
                        <span className="text-sm font-medium text-foreground">Drop a price list or click to upload</span>
                        <span className="text-xs text-muted-foreground">xlsx · csv · pdf · image</span>
                    </>
                )}
            </button>
            <input ref={inputRef} type="file" className="hidden" accept=".xlsx,.xls,.csv,.pdf,image/*" onChange={e => { const f = e.target.files?.[0]; if (f) handleFile(f); }} />
            {error && <p className="mt-3 text-sm text-destructive">{error}</p>}

            <Modal
                open={!!lines}
                onClose={() => { if (!applying) setLines(null); }}
                title="Review price list"
                size="xl"
                description={counts ? `${lines!.length} lines · ${counts.update} update · ${counts.create} new · ${counts.skip} skipped` : undefined}
                footer={
                    <div className="flex justify-end gap-2">
                        <Button variant="ghost" onClick={() => setLines(null)} disabled={applying}>Cancel</Button>
                        <Button variant="primary" onClick={apply} disabled={applying} icon={applying ? Loader2 : Check}>
                            {applying ? 'Applying…' : 'Apply to inventory'}
                        </Button>
                    </div>
                }
            >
                <div className="max-h-[60vh] overflow-auto">
                    <table className="w-full text-sm">
                        <thead className="sticky top-0 z-10 bg-card text-left text-xs uppercase text-muted-foreground/70">
                            <tr>
                                <th className="px-2 py-2">Supplier SKU</th>
                                <th className="px-2 py-2">Product</th>
                                <th className="px-2 py-2 text-right">Price (cost)</th>
                                <th className="px-2 py-2">Matched inventory item</th>
                                <th className="px-2 py-2 w-32">Action</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {lines?.map((l, i) => (
                                <tr key={i} className={cn(l.action === 'skip' && 'opacity-40')}>
                                    <td className="px-2 py-2 align-top font-mono text-xs text-muted-foreground">{l.supplier_sku ?? '—'}</td>
                                    <td className="px-2 py-2 align-top text-foreground">{l.name ?? '—'}</td>
                                    <td className="px-2 py-2 align-top text-right">
                                        <input
                                            type="number"
                                            step="0.0001"
                                            value={l.price ?? ''}
                                            onChange={e => setLine(i, { price: e.target.value === '' ? null : parseFloat(e.target.value) })}
                                            className="w-24 rounded-md border border-input bg-card px-2 py-1 text-right text-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/50"
                                        />
                                        <span className="ml-1 text-xs text-muted-foreground">{l.currency ?? supplierCurrency ?? ''}</span>
                                    </td>
                                    <td className="px-2 py-2 align-top text-xs">
                                        {l.match ? (
                                            <span className="inline-flex items-center gap-1 text-foreground">
                                                <Link2 className="h-3.5 w-3.5 shrink-0 text-emerald-500" />
                                                <span>{l.match.name} <span className="text-muted-foreground">({l.match.sku}) · cost {l.match.current_cost}</span></span>
                                            </span>
                                        ) : (
                                            <span className="inline-flex items-center gap-1 text-amber-600"><Plus className="h-3.5 w-3.5" /> New product</span>
                                        )}
                                    </td>
                                    <td className="px-2 py-2 align-top">
                                        <Select
                                            size="sm"
                                            value={l.action}
                                            options={l.match ? ACTIONS : ACTIONS.filter(a => a.value !== 'update')}
                                            onChange={v => setLine(i, { action: v as ParsedLine['action'] })}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Modal>
        </Card>
    );
}
