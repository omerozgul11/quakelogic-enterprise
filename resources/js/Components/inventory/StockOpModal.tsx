import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Modal } from '@/Components/ui/Modal';
import { Button } from '@/Components/ui/Button';
import { Select } from '@/Components/ui/Select';

export type StockOp = 'receive' | 'issue' | 'adjust' | 'count' | 'transfer';

interface WarehouseOption { id: number; name: string; code?: string }

interface Props {
    open: boolean;
    onClose: () => void;
    op: StockOp;
    product: { id: number; sku: string; name: string; unit_of_measure: string };
    warehouses: WarehouseOption[];
    defaultWarehouseId?: number | null;
}

const META: Record<StockOp, { title: string; verb: string }> = {
    receive: { title: 'Receive Stock', verb: 'Receive' },
    issue: { title: 'Issue Stock', verb: 'Issue' },
    adjust: { title: 'Adjust Stock', verb: 'Adjust' },
    count: { title: 'Cycle Count', verb: 'Record Count' },
    transfer: { title: 'Transfer Stock', verb: 'Transfer' },
};

export function StockOpModal({ open, onClose, op, product, warehouses, defaultWarehouseId }: Props) {
    const whOptions = warehouses.map(w => ({ value: String(w.id), label: w.code ? `${w.name} (${w.code})` : w.name }));
    const firstWh = defaultWarehouseId ? String(defaultWarehouseId) : (whOptions[0]?.value ?? '');

    const form = useForm<Record<string, string>>({
        warehouse_id: op === 'transfer' ? '' : firstWh,
        from_warehouse_id: firstWh,
        to_warehouse_id: '',
        quantity: '',
        delta: '',
        counted_quantity: '',
        unit_cost: '',
        note: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        // Send only the fields this op's endpoint expects (issue/count/transfer
        // reject stray cost/quantity keys).
        const keep: Record<StockOp, string[]> = {
            receive: ['warehouse_id', 'quantity', 'unit_cost', 'note'],
            issue: ['warehouse_id', 'quantity', 'note'],
            adjust: ['warehouse_id', 'delta', 'unit_cost', 'note'],
            count: ['warehouse_id', 'counted_quantity', 'note'],
            transfer: ['from_warehouse_id', 'to_warehouse_id', 'quantity', 'note'],
        };
        form.transform(data => Object.fromEntries(keep[op].map(k => [k, (data as Record<string, string>)[k]])));
        form.post(`/inventory/products/${product.id}/${op}`, {
            preserveScroll: true,
            onSuccess: () => { form.reset(); onClose(); },
        });
    };

    const err = (k: string) => (form.errors as Record<string, string>)[k];
    const uom = product.unit_of_measure || 'units';

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={META[op].title}
            description={`${product.sku} · ${product.name}`}
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>Cancel</Button>
                    <Button onClick={submit as unknown as () => void} disabled={form.processing}>
                        {form.processing ? 'Working…' : META[op].verb}
                    </Button>
                </>
            }
        >
            <form onSubmit={submit} className="space-y-4">
                {op === 'transfer' ? (
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="label">From *</label>
                            <Select className="w-full" value={form.data.from_warehouse_id} onChange={v => form.setData('from_warehouse_id', v)} options={whOptions} placeholder="Select…" />
                            {err('from_warehouse_id') && <p className="mt-1 text-xs text-destructive">{err('from_warehouse_id')}</p>}
                        </div>
                        <div>
                            <label className="label">To *</label>
                            <Select className="w-full" value={form.data.to_warehouse_id} onChange={v => form.setData('to_warehouse_id', v)} options={whOptions} placeholder="Select…" />
                            {err('to_warehouse_id') && <p className="mt-1 text-xs text-destructive">{err('to_warehouse_id')}</p>}
                        </div>
                    </div>
                ) : (
                    <div>
                        <label className="label">Warehouse *</label>
                        <Select className="w-full" value={form.data.warehouse_id} onChange={v => form.setData('warehouse_id', v)} options={whOptions} placeholder="Select…" />
                        {err('warehouse_id') && <p className="mt-1 text-xs text-destructive">{err('warehouse_id')}</p>}
                    </div>
                )}

                {(op === 'receive' || op === 'issue' || op === 'transfer') && (
                    <div>
                        <label className="label">Quantity ({uom}) *</label>
                        <input type="number" step="0.001" min="0" className="input" value={form.data.quantity} onChange={e => form.setData('quantity', e.target.value)} autoFocus />
                        {err('quantity') && <p className="mt-1 text-xs text-destructive">{err('quantity')}</p>}
                    </div>
                )}

                {op === 'adjust' && (
                    <div>
                        <label className="label">Change (+ adds, − removes) *</label>
                        <input type="number" step="0.001" className="input" value={form.data.delta} onChange={e => form.setData('delta', e.target.value)} placeholder="-3 or 5" autoFocus />
                        {err('delta') && <p className="mt-1 text-xs text-destructive">{err('delta')}</p>}
                    </div>
                )}

                {op === 'count' && (
                    <div>
                        <label className="label">Counted on-hand ({uom}) *</label>
                        <input type="number" step="0.001" min="0" className="input" value={form.data.counted_quantity} onChange={e => form.setData('counted_quantity', e.target.value)} autoFocus />
                        {err('counted_quantity') && <p className="mt-1 text-xs text-destructive">{err('counted_quantity')}</p>}
                    </div>
                )}

                {(op === 'receive' || op === 'adjust') && (
                    <div>
                        <label className="label">Unit cost {op === 'adjust' ? '(optional)' : ''}</label>
                        <input type="number" step="0.0001" min="0" className="input" value={form.data.unit_cost} onChange={e => form.setData('unit_cost', e.target.value)} placeholder="0.00" />
                        {err('unit_cost') && <p className="mt-1 text-xs text-destructive">{err('unit_cost')}</p>}
                    </div>
                )}

                <div>
                    <label className="label">Note</label>
                    <input className="input" value={form.data.note} onChange={e => form.setData('note', e.target.value)} placeholder="Reference, reason…" />
                </div>
            </form>
        </Modal>
    );
}
