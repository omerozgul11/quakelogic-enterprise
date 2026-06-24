import { Head, Link, router } from '@inertiajs/react';
import { InventoryLayout } from '@/Components/layout/InventoryLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { Pill } from '@/Components/ui/Pill';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Pagination } from '@/Components/ui/Pagination';
import { SearchInput } from '@/Components/ui/SearchInput';
import { Select } from '@/Components/ui/Select';
import { formatCurrency, cn } from '@/Lib/utils';
import { PaginatedResponse } from '@/Types';
import { ArrowLeftRight, Trash2 } from 'lucide-react';

interface MovementRow {
    id: number; type: string; type_label: string; type_color: string;
    quantity: number; quantity_after: number; unit_cost: number | null;
    product_id: number; product: string; warehouse: string | null;
    note: string | null; by: string | null; occurred_at: string | null;
}

interface Props {
    movements: PaginatedResponse<MovementRow>;
    filters: Record<string, string>;
    types: { value: string; label: string }[];
    warehouses: { id: number; name: string }[];
    can: { manage: boolean };
}

function when(iso: string | null): string {
    if (!iso) return '';
    return new Date(iso).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

export default function MovementsIndex({ movements, filters, types, warehouses, can }: Props) {
    const apply = (patch: Record<string, string | undefined>) => {
        router.get('/inventory/movements', { ...filters, ...patch }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const del = (m: MovementRow) => {
        if (confirm(`Delete this ${m.type_label.toLowerCase()} entry? This reverses its effect on stock.`)) {
            router.delete(`/inventory/movements/${m.id}`, { preserveScroll: true });
        }
    };

    return (
        <InventoryLayout>
            <Head title="Movements · Inventory" />
            <div className="p-4 sm:p-6">
                <PageHeader icon={ArrowLeftRight} title="Stock movements" description={`${movements.total} ledger entries`} />

                <Card className="mb-4 flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                    <SearchInput className="w-full sm:max-w-sm" initial={filters.search ?? ''} onSearch={v => apply({ search: v || undefined })} placeholder="Search product…" />
                    <div className="flex gap-2">
                        <Select value={filters.type ?? ''} onChange={v => apply({ type: v || undefined })} placeholder="All types" options={types} />
                        <Select value={filters.warehouse_id ?? ''} onChange={v => apply({ warehouse_id: v || undefined })} placeholder="All warehouses"
                            options={warehouses.map(w => ({ value: String(w.id), label: w.name }))} />
                    </div>
                </Card>

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b border-border bg-secondary/40">
                                <tr>
                                    <th className="th">When</th>
                                    <th className="th">Type</th>
                                    <th className="th">Product</th>
                                    <th className="th hidden md:table-cell">Warehouse</th>
                                    <th className="th text-right">Qty</th>
                                    <th className="th hidden text-right sm:table-cell">Balance</th>
                                    <th className="th hidden lg:table-cell">By</th>
                                    {can.manage && <th className="th" />}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {movements.data.length === 0 ? (
                                    <tr><td colSpan={8}><EmptyState icon={ArrowLeftRight} title="No movements" description="Stock activity will appear here." /></td></tr>
                                ) : movements.data.map(m => (
                                    <tr key={m.id}>
                                        <td className="td whitespace-nowrap text-muted-foreground">{when(m.occurred_at)}</td>
                                        <td className="td"><Pill color={m.type_color} label={m.type_label} /></td>
                                        <td className="td">
                                            <Link href={`/inventory/products/${m.product_id}`} className="font-medium text-foreground hover:text-primary">{m.product}</Link>
                                            {m.note && <span className="block max-w-[220px] truncate text-xs text-muted-foreground" title={m.note}>{m.note}</span>}
                                        </td>
                                        <td className="td hidden text-muted-foreground md:table-cell">{m.warehouse}</td>
                                        <td className={cn('td text-right font-semibold', m.quantity < 0 ? 'text-red-600' : 'text-emerald-600')}>{m.quantity > 0 ? '+' : ''}{m.quantity}</td>
                                        <td className="td hidden text-right text-foreground sm:table-cell">{m.quantity_after}</td>
                                        <td className="td hidden text-muted-foreground lg:table-cell">{m.by || '—'}</td>
                                        {can.manage && (
                                            <td className="td text-right">
                                                <button onClick={() => del(m)} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive" title="Delete entry"><Trash2 className="h-4 w-4" /></button>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination from={movements.from} to={movements.to} total={movements.total} links={movements.links} />
                </Card>
            </div>
        </InventoryLayout>
    );
}
