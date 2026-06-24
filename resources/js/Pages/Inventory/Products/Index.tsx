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
import { ProductImportModal } from '@/Components/inventory/ProductImportModal';
import { Modal } from '@/Components/ui/Modal';
import { Checkbox } from '@/Components/ui/Checkbox';
import { Package, Plus, ExternalLink, Upload, ArrowUp, ArrowDown, ChevronsUpDown, Tags } from 'lucide-react';

interface ProductRow {
    id: number;
    sku: string;
    name: string;
    image_url: string | null;
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
    currencies: { value: string; label: string; symbol?: string }[];
    can: { manage: boolean; adjust: boolean };
}

export default function ProductsIndex({ products, filters, types, currencies, can }: Props) {
    const [formOpen, setFormOpen] = useState(false);
    const [importOpen, setImportOpen] = useState(false);
    const [catOpen, setCatOpen] = useState(false);
    const [catAll, setCatAll] = useState(false);
    const [catBusy, setCatBusy] = useState(false);

    const runAutoCategorize = () => {
        setCatBusy(true);
        router.post('/inventory/products/autocategorize', { mode: catAll ? 'all' : 'empty' }, {
            preserveScroll: true,
            onFinish: () => { setCatBusy(false); setCatOpen(false); },
        });
    };

    const apply = (patch: Record<string, string | undefined>) => {
        router.get('/inventory/products', { ...filters, ...patch }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const sort = filters.sort ?? 'name';
    const dir = filters.dir === 'desc' ? 'desc' : 'asc';
    const toggleSort = (key: string) =>
        apply({ sort: key, dir: sort === key && dir === 'asc' ? 'desc' : 'asc' });

    const SortHeader = ({ field, label, align = 'left' }: { field: string; label: string; align?: 'left' | 'right' }) => (
        <button
            type="button"
            onClick={() => toggleSort(field)}
            className={`group inline-flex items-center gap-1 hover:text-foreground ${align === 'right' ? 'flex-row-reverse' : ''}`}
        >
            {label}
            {sort === field
                ? (dir === 'asc' ? <ArrowUp className="h-3.5 w-3.5 text-primary" /> : <ArrowDown className="h-3.5 w-3.5 text-primary" />)
                : <ChevronsUpDown className="h-3.5 w-3.5 text-muted-foreground/40 group-hover:text-muted-foreground" />}
        </button>
    );

    return (
        <InventoryLayout>
            <Head title="Products · Inventory" />
            <div className="p-4 sm:p-6">
                <PageHeader
                    icon={Package}
                    title="Products"
                    description={`${products.total} ${products.total === 1 ? 'product' : 'products'}`}
                    actions={can.manage && (
                        <div className="flex items-center gap-2">
                            <Button variant="secondary" icon={Tags} onClick={() => setCatOpen(true)}>Auto-categorize</Button>
                            <Button variant="secondary" icon={Upload} onClick={() => setImportOpen(true)}>Import</Button>
                            <Button icon={Plus} onClick={() => setFormOpen(true)}>Add Product</Button>
                        </div>
                    )}
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
                                    <th className="th"><SortHeader field="name" label="Product" /></th>
                                    <th className="th hidden sm:table-cell"><SortHeader field="type" label="Type" /></th>
                                    <th className="th text-right">On hand</th>
                                    <th className="th hidden text-right md:table-cell">Cost</th>
                                    <th className="th text-right"><SortHeader field="price" label="Price" align="right" /></th>
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
                                            <Link href={`/inventory/products/${p.id}`} className="flex items-center gap-3">
                                                <span className="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-md border border-border bg-secondary">
                                                    {p.image_url
                                                        ? <img src={p.image_url} alt="" className="h-full w-full object-cover" />
                                                        : <Package className="h-4 w-4 text-muted-foreground" />}
                                                </span>
                                                <span className="min-w-0">
                                                    <span className="block truncate font-medium text-foreground hover:text-primary">{p.name}</span>
                                                    <span className="mt-0.5 flex items-center gap-2">
                                                        <span className="font-mono text-xs text-muted-foreground">{p.sku}</span>
                                                        {!p.is_active && <span className="chip">Inactive</span>}
                                                    </span>
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

            {formOpen && <ProductFormModal open onClose={() => setFormOpen(false)} types={types} currencies={currencies} />}
            {importOpen && <ProductImportModal open onClose={() => setImportOpen(false)} />}

            <Modal
                open={catOpen}
                onClose={() => !catBusy && setCatOpen(false)}
                title="Auto-categorize products"
                size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setCatOpen(false)} disabled={catBusy}>Cancel</Button>
                        <Button icon={Tags} onClick={runAutoCategorize} disabled={catBusy}>
                            {catBusy ? 'Categorizing…' : 'Categorize'}
                        </Button>
                    </>
                }
            >
                <p className="text-sm text-muted-foreground">
                    Assigns a category to each product by reading its name — sensors, cables,
                    enclosures, power, antennas, data acquisition, software, services, shake
                    tables and more.
                </p>
                <label className="mt-4 flex cursor-pointer items-start gap-3">
                    <Checkbox checked={catAll} onChange={setCatAll} ariaLabel="Re-categorize all" />
                    <span className="text-sm">
                        <span className="font-medium text-foreground">Re-categorize everything</span>
                        <span className="block text-muted-foreground">
                            Off: only fill products that have no category yet. On: re-derive every product's category from its name.
                        </span>
                    </span>
                </label>
            </Modal>
        </InventoryLayout>
    );
}
