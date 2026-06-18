import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { InventoryLayout } from '@/Components/layout/InventoryLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Pill } from '@/Components/ui/Pill';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Pagination } from '@/Components/ui/Pagination';
import { SearchInput } from '@/Components/ui/SearchInput';
import { Select } from '@/Components/ui/Select';
import { ProductFormModal } from '@/Components/inventory/ProductFormModal';
import { formatCurrency } from '@/Lib/utils';
import { PaginatedResponse } from '@/Types';
import { Package, Plus, ExternalLink } from 'lucide-react';

interface ProductRow {
    id: number;
    sku: string;
    name: string;
    type_label: string;
    type_color: string;
    category: string | null;
    unit_of_measure: string;
    unit_price: number;
    unit_cost: number;
    currency: string;
    on_hand: number;
    reorder_point: number | null;
    is_low: boolean;
    is_active: boolean;
}

interface Props {
    products: PaginatedResponse<ProductRow>;
    filters: Record<string, string>;
    types: { value: string; label: string }[];
    can: { manage: boolean; adjust: boolean };
}

export default function ProductsIndex({ products, filters, types, can }: Props) {
    const [formOpen, setFormOpen] = useState(false);

    const apply = (patch: Record<string, string | undefined>) => {
        router.get('/inventory/products', { ...filters, ...patch }, { preserveState: true, preserveScroll: true, replace: true });
    };

    return (
        <InventoryLayout>
            <Head title="Products · Inventory" />
            <div className="p-4 sm:p-6">
                <PageHeader
                    icon={Package}
                    title="Products"
                    description={`${products.total} ${products.total === 1 ? 'product' : 'products'}`}
                    actions={can.manage && <Button onClick={() => setFormOpen(true)} icon={Plus}>Add Product</Button>}
                />

                <Card className="mb-4 flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                    <SearchInput className="w-full sm:max-w-sm" initial={filters.search ?? ''} onSearch={v => apply({ search: v || undefined })} placeholder="Search SKU, name, barcode…" />
                    <div className="flex gap-2">
                        <Select value={filters.type ?? ''} onChange={v => apply({ type: v || undefined })} placeholder="All types" options={types} />
                        <Select value={filters.status ?? ''} onChange={v => apply({ status: v || undefined })} placeholder="All status" options={[
                            { value: 'active', label: 'Active' },
                            { value: 'inactive', label: 'Inactive' },
                            { value: 'low', label: 'Low stock' },
                        ]} />
                    </div>
                </Card>

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b border-border bg-secondary/40">
                                <tr>
                                    <th className="th">Product</th>
                                    <th className="th hidden sm:table-cell">Type</th>
                                    <th className="th text-right">On hand</th>
                                    <th className="th hidden text-right md:table-cell">Cost</th>
                                    <th className="th text-right">Price</th>
                                    <th className="th" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {products.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6}>
                                            <EmptyState
                                                icon={Package}
                                                title="No products found"
                                                description="Add a product to start tracking stock, cost and movements."
                                                action={can.manage && <Button onClick={() => setFormOpen(true)} icon={Plus}>Add Product</Button>}
                                            />
                                        </td>
                                    </tr>
                                ) : products.data.map(p => (
                                    <tr key={p.id} className="row-link">
                                        <td className="td">
                                            <Link href={`/inventory/products/${p.id}`} className="block">
                                                <span className="font-medium text-foreground hover:text-primary">{p.name}</span>
                                                <span className="mt-0.5 flex items-center gap-2">
                                                    <span className="font-mono text-xs text-muted-foreground">{p.sku}</span>
                                                    {!p.is_active && <span className="chip">Inactive</span>}
                                                </span>
                                            </Link>
                                        </td>
                                        <td className="td hidden sm:table-cell"><Pill color={p.type_color} label={p.type_label} /></td>
                                        <td className="td text-right">
                                            <span className={p.is_low ? 'font-semibold text-amber-600' : 'text-foreground'}>
                                                {p.on_hand}
                                            </span>
                                            <span className="ml-1 text-xs text-muted-foreground">{p.unit_of_measure}</span>
                                            {p.is_low && <Pill className="ml-2" color="amber" label="Low" />}
                                        </td>
                                        <td className="td hidden text-right text-muted-foreground md:table-cell">{formatCurrency(p.unit_cost, p.currency)}</td>
                                        <td className="td text-right font-medium text-foreground">{formatCurrency(p.unit_price, p.currency)}</td>
                                        <td className="td">
                                            <div className="flex justify-end">
                                                <Link href={`/inventory/products/${p.id}`} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-secondary hover:text-primary" title="View">
                                                    <ExternalLink className="h-4 w-4" />
                                                </Link>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination from={products.from} to={products.to} total={products.total} links={products.links} />
                </Card>
            </div>

            {formOpen && <ProductFormModal open onClose={() => setFormOpen(false)} types={types} />}
        </InventoryLayout>
    );
}
