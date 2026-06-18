import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { InventoryLayout } from '@/Components/layout/InventoryLayout';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Pill } from '@/Components/ui/Pill';
import { ConfirmDialog } from '@/Components/ui/Modal';
import { ProductFormModal } from '@/Components/inventory/ProductFormModal';
import { StockOpModal, StockOp } from '@/Components/inventory/StockOpModal';
import { formatCurrency, cn } from '@/Lib/utils';
import {
    ArrowLeft, Package, Pencil, Trash2, PlusCircle, MinusCircle, SlidersHorizontal,
    ClipboardCheck, ArrowLeftRight, Barcode, Factory,
} from 'lucide-react';

interface Product {
    id: number; ulid: string; sku: string; name: string;
    type: string; type_label: string; type_color: string;
    category: string | null; description: string | null; unit_of_measure: string;
    barcode: string | null; manufacturer: string | null; mpn: string | null;
    unit_cost: number; unit_price: number; currency: string;
    reorder_point: number | null; reorder_quantity: number | null;
    lead_time_days: number | null; weight: number | null;
    is_serialized: boolean; track_inventory: boolean; is_active: boolean;
    total_on_hand: number; stock_value: number;
}
interface StockRow { warehouse_id: number; warehouse: string | null; code: string | null; on_hand: number; reserved: number; available: number; average_cost: number; value: number }
interface MovementRow { id: number; type: string; type_label: string; type_color: string; quantity: number; quantity_after: number; unit_cost: number | null; warehouse: string | null; note: string | null; by: string | null; occurred_at: string | null }
interface WarehouseOption { id: number; name: string; code?: string }

interface Props {
    product: Product;
    stocks: StockRow[];
    movements: MovementRow[];
    warehouses: WarehouseOption[];
    movement_types: { value: string; label: string }[];
    types: { value: string; label: string }[];
    can: { manage: boolean; adjust: boolean };
}

function when(iso: string | null): string {
    if (!iso) return '';
    return new Date(iso).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

export default function ProductShow({ product, stocks, movements, warehouses, types, can }: Props) {
    const [editOpen, setEditOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [op, setOp] = useState<StockOp | null>(null);

    const confirmDelete = () => {
        setProcessing(true);
        router.delete(`/inventory/products/${product.id}`, { onFinish: () => setProcessing(false) });
    };

    const isLow = product.reorder_point !== null && product.total_on_hand <= product.reorder_point;
    const noWarehouses = warehouses.length === 0;

    const opButtons: { op: StockOp; label: string; icon: React.ComponentType<{ className?: string }>; variant?: 'secondary' }[] = [
        { op: 'receive', label: 'Receive', icon: PlusCircle },
        { op: 'issue', label: 'Issue', icon: MinusCircle },
        { op: 'adjust', label: 'Adjust', icon: SlidersHorizontal },
        { op: 'count', label: 'Count', icon: ClipboardCheck },
        { op: 'transfer', label: 'Transfer', icon: ArrowLeftRight },
    ];

    const details: Array<{ label: string; value: React.ReactNode }> = [
        { label: 'Category', value: product.category || '—' },
        { label: 'Unit of measure', value: product.unit_of_measure },
        { label: 'Manufacturer', value: product.manufacturer || '—' },
        { label: 'MPN', value: product.mpn || '—' },
        { label: 'Barcode', value: product.barcode ? <span className="inline-flex items-center gap-1 font-mono"><Barcode className="h-3.5 w-3.5" />{product.barcode}</span> : '—' },
        { label: 'Lead time', value: product.lead_time_days != null ? `${product.lead_time_days} days` : '—' },
        { label: 'Reorder point', value: product.reorder_point != null ? `${product.reorder_point} ${product.unit_of_measure}` : '—' },
        { label: 'Reorder qty', value: product.reorder_quantity != null ? `${product.reorder_quantity} ${product.unit_of_measure}` : '—' },
        { label: 'Weight', value: product.weight != null ? `${product.weight}` : '—' },
        { label: 'Serialized', value: product.is_serialized ? 'Yes' : 'No' },
    ];

    return (
        <InventoryLayout>
            <Head title={`${product.sku} · Inventory`} />
            <div className="mx-auto max-w-6xl px-4 py-6 sm:px-6">
                <Link href="/inventory/products" className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" /> Products
                </Link>

                <div className="card-surface mb-6 p-6">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div className="flex items-center gap-4">
                            <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-500 text-white">
                                <Package className="h-7 w-7" />
                            </div>
                            <div>
                                <h1 className="text-2xl font-bold tracking-tight text-foreground">{product.name}</h1>
                                <div className="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                    <span className="font-mono text-xs">{product.sku}</span>
                                    <Pill color={product.type_color} label={product.type_label} />
                                    {!product.is_active && <span className="chip">Inactive</span>}
                                    {isLow && <Pill color="amber" label="Low stock" />}
                                </div>
                            </div>
                        </div>
                        {can.manage && (
                            <div className="flex items-center gap-2">
                                <Button variant="secondary" icon={Pencil} onClick={() => setEditOpen(true)}>Edit</Button>
                                <Button variant="danger" icon={Trash2} onClick={() => setDeleting(true)}>Delete</Button>
                            </div>
                        )}
                    </div>

                    <div className="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <Metric label="On hand" value={`${product.total_on_hand} ${product.unit_of_measure}`} highlight={isLow} />
                        <Metric label="Stock value" value={formatCurrency(product.stock_value, product.currency)} />
                        <Metric label="Avg / unit cost" value={formatCurrency(product.unit_cost, product.currency)} />
                        <Metric label="Sell price" value={formatCurrency(product.unit_price, product.currency)} />
                    </div>

                    {can.adjust && (
                        <div className="mt-5 flex flex-wrap gap-2 border-t border-border pt-4">
                            {opButtons.map(b => (
                                <Button key={b.op} variant="secondary" size="sm" icon={b.icon}
                                    onClick={() => setOp(b.op)} disabled={noWarehouses}>
                                    {b.label}
                                </Button>
                            ))}
                            {noWarehouses && <span className="self-center text-xs text-muted-foreground">Add a warehouse first to move stock.</span>}
                        </div>
                    )}
                    {product.description && <p className="mt-4 whitespace-pre-line border-t border-border pt-4 text-sm text-muted-foreground">{product.description}</p>}
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <Card className="p-5">
                        <h2 className="mb-3 text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Stock by warehouse</h2>
                        {stocks.length === 0 ? (
                            <p className="py-4 text-sm text-muted-foreground">No stock recorded yet.</p>
                        ) : (
                            <table className="w-full text-sm">
                                <thead><tr className="text-left text-xs uppercase text-muted-foreground/70">
                                    <th className="pb-2">Warehouse</th><th className="pb-2 text-right">On hand</th><th className="pb-2 text-right">Avg cost</th><th className="pb-2 text-right">Value</th>
                                </tr></thead>
                                <tbody className="divide-y divide-border">
                                    {stocks.map(s => (
                                        <tr key={s.warehouse_id}>
                                            <td className="py-2">
                                                <Link href={`/inventory/warehouses/${s.warehouse_id}`} className="font-medium text-foreground hover:text-primary">{s.warehouse}</Link>
                                            </td>
                                            <td className="py-2 text-right">{s.on_hand} {product.unit_of_measure}</td>
                                            <td className="py-2 text-right text-muted-foreground">{formatCurrency(s.average_cost, product.currency)}</td>
                                            <td className="py-2 text-right font-medium">{formatCurrency(s.value, product.currency)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </Card>

                    <Card className="p-5">
                        <h2 className="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70"><Factory className="h-4 w-4" /> Details</h2>
                        <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                            {details.map(d => (
                                <div key={d.label}>
                                    <dt className="text-xs text-muted-foreground">{d.label}</dt>
                                    <dd className="text-foreground">{d.value}</dd>
                                </div>
                            ))}
                        </dl>
                    </Card>

                    <Card className="p-5 lg:col-span-2">
                        <h2 className="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70"><ArrowLeftRight className="h-4 w-4" /> Movement history</h2>
                        {movements.length === 0 ? (
                            <p className="py-4 text-sm text-muted-foreground">No movements yet.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead><tr className="text-left text-xs uppercase text-muted-foreground/70">
                                        <th className="pb-2">When</th><th className="pb-2">Type</th><th className="pb-2">Warehouse</th>
                                        <th className="pb-2 text-right">Qty</th><th className="pb-2 text-right">Balance</th><th className="pb-2">Note</th><th className="pb-2">By</th>
                                    </tr></thead>
                                    <tbody className="divide-y divide-border">
                                        {movements.map(m => (
                                            <tr key={m.id}>
                                                <td className="py-2 whitespace-nowrap text-muted-foreground">{when(m.occurred_at)}</td>
                                                <td className="py-2"><Pill color={m.type_color} label={m.type_label} /></td>
                                                <td className="py-2 text-muted-foreground">{m.warehouse}</td>
                                                <td className={cn('py-2 text-right font-semibold', m.quantity < 0 ? 'text-red-600' : 'text-emerald-600')}>{m.quantity > 0 ? '+' : ''}{m.quantity}</td>
                                                <td className="py-2 text-right text-foreground">{m.quantity_after}</td>
                                                <td className="py-2 max-w-[180px] truncate text-muted-foreground" title={m.note ?? ''}>{m.note || '—'}</td>
                                                <td className="py-2 text-muted-foreground">{m.by || '—'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Card>
                </div>
            </div>

            {editOpen && <ProductFormModal open onClose={() => setEditOpen(false)} product={product} types={types} />}
            {op && (
                <StockOpModal
                    key={op}
                    open
                    op={op}
                    onClose={() => setOp(null)}
                    product={{ id: product.id, sku: product.sku, name: product.name, unit_of_measure: product.unit_of_measure }}
                    warehouses={warehouses}
                    defaultWarehouseId={stocks[0]?.warehouse_id ?? warehouses[0]?.id ?? null}
                />
            )}
            <ConfirmDialog
                open={deleting}
                onClose={() => setDeleting(false)}
                onConfirm={confirmDelete}
                processing={processing}
                title="Delete product?"
                message={<>This soft-deletes <span className="font-medium text-foreground">{product.name}</span>. Stock history is retained.</>}
            />
        </InventoryLayout>
    );
}

function Metric({ label, value, highlight }: { label: string; value: string; highlight?: boolean }) {
    return (
        <div>
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className={cn('mt-0.5 text-lg font-bold tracking-tight', highlight ? 'text-amber-600' : 'text-foreground')}>{value}</p>
        </div>
    );
}
