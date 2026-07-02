import { Select } from '@/Components/ui/Select';
import { Button } from '@/Components/ui/Button';
import { formatCurrency } from '@/Lib/utils';
import { Plus, Trash2 } from 'lucide-react';

export interface Line {
    inventory_product_id: string;
    description: string;
    sku: string;
    unit: string;
    quantity: string;
    unit_cost: string;
    tax_rate: string;
}

export const emptyLine: Line = {
    inventory_product_id: '', description: '', sku: '', unit: '', quantity: '1', unit_cost: '0', tax_rate: '0',
};

export interface ProductOpt { id: number; sku: string; name: string; unit_cost: number }

interface Props {
    items: Line[];
    onChange: (items: Line[]) => void;
    products: ProductOpt[];
    currency: string;
    errors?: Record<string, string>;
}

/** Shared editable line-item grid for purchase requests, quotations and bills. */
export function LineItemsEditor({ items, onChange, products, currency, errors = {} }: Props) {
    const setLine = (i: number, patch: Partial<Line>) => onChange(items.map((l, idx) => (idx === i ? { ...l, ...patch } : l)));
    const pickProduct = (i: number, productId: string) => {
        const p = products.find(pr => String(pr.id) === productId);
        setLine(i, p
            ? { inventory_product_id: productId, description: p.name, sku: p.sku, unit_cost: String(p.unit_cost) }
            : { inventory_product_id: '' });
    };
    const addLine = () => onChange([...items, { ...emptyLine }]);
    const removeLine = (i: number) => onChange(items.length > 1 ? items.filter((_, idx) => idx !== i) : items);
    const productOptions = [{ value: '', label: '— Custom line —' }, ...products.map(p => ({ value: String(p.id), label: `${p.sku} · ${p.name}` }))];
    const errFor = (i: number, f: string) => errors[`items.${i}.${f}`];

    return (
        <>
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead className="bg-secondary/40 text-left text-xs uppercase text-muted-foreground/70">
                        <tr>
                            <th className="px-3 py-2">Product / description</th>
                            <th className="px-3 py-2 w-20 text-right">Qty</th>
                            <th className="px-3 py-2 w-28 text-right">Unit cost</th>
                            <th className="px-3 py-2 w-16 text-right">Tax %</th>
                            <th className="px-3 py-2 w-28 text-right">Line total</th>
                            <th className="px-2 py-2 w-10" />
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-border">
                        {items.map((l, i) => {
                            const lineTotal = (parseFloat(l.quantity) || 0) * (parseFloat(l.unit_cost) || 0);
                            return (
                                <tr key={i} className="align-top">
                                    <td className="px-3 py-2">
                                        <Select className="w-full" value={l.inventory_product_id} placeholder="— Custom line —"
                                            searchable searchPlaceholder="Search products by name or SKU…"
                                            onChange={v => pickProduct(i, v)} options={productOptions} />
                                        <input className="input mt-1.5 h-9" placeholder="Description *" value={l.description} onChange={e => setLine(i, { description: e.target.value })} />
                                        {errFor(i, 'description') && <p className="mt-1 text-xs text-destructive">{errFor(i, 'description')}</p>}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        <input type="number" step="0.001" min="0" className="input h-9 text-right" value={l.quantity} onChange={e => setLine(i, { quantity: e.target.value })} />
                                        {errFor(i, 'quantity') && <p className="mt-1 text-xs text-destructive">{errFor(i, 'quantity')}</p>}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        <input type="number" step="0.0001" min="0" className="input h-9 text-right" value={l.unit_cost} onChange={e => setLine(i, { unit_cost: e.target.value })} />
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        <input type="number" step="0.01" min="0" max="100" className="input h-9 text-right" value={l.tax_rate} onChange={e => setLine(i, { tax_rate: e.target.value })} />
                                    </td>
                                    <td className="px-3 py-2 text-right font-medium text-foreground">{formatCurrency(lineTotal, currency)}</td>
                                    <td className="px-2 py-2 text-right">
                                        <button type="button" onClick={() => removeLine(i)} className="rounded-md p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"><Trash2 className="h-4 w-4" /></button>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
            <div className="px-3 py-3">
                <Button type="button" variant="ghost" size="sm" icon={Plus} onClick={addLine}>Add line</Button>
            </div>
            {typeof errors.items === 'string' && <p className="px-5 pb-3 text-xs text-destructive">{errors.items}</p>}
        </>
    );
}

/** Sum helper: subtotal + per-line tax across the grid. */
export function computeTotals(items: Line[]): { subtotal: number; tax: number } {
    let subtotal = 0;
    let tax = 0;
    for (const l of items) {
        const line = (parseFloat(l.quantity) || 0) * (parseFloat(l.unit_cost) || 0);
        subtotal += line;
        tax += line * (parseFloat(l.tax_rate) || 0) / 100;
    }
    return { subtotal, tax };
}
