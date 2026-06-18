import { Head, Link, router } from '@inertiajs/react';
import { ProcurementLayout } from '@/Components/layout/ProcurementLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Pill } from '@/Components/ui/Pill';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Pagination } from '@/Components/ui/Pagination';
import { SearchInput } from '@/Components/ui/SearchInput';
import { Select } from '@/Components/ui/Select';
import { formatCurrency } from '@/Lib/utils';
import { PaginatedResponse } from '@/Types';
import { ShoppingCart, Plus, ExternalLink } from 'lucide-react';

interface PoRow {
    id: number; number: string; supplier: string | null;
    status: string; status_label: string; status_color: string;
    total: number; currency: string; order_date: string | null; expected_date: string | null;
}

interface Props {
    orders: PaginatedResponse<PoRow>;
    filters: Record<string, string>;
    statuses: { value: string; label: string }[];
    can: { manage: boolean };
}

export default function PurchaseOrdersIndex({ orders, filters, statuses, can }: Props) {
    const apply = (patch: Record<string, string | undefined>) => {
        router.get('/procurement/purchase-orders', { ...filters, ...patch }, { preserveState: true, preserveScroll: true, replace: true });
    };

    return (
        <ProcurementLayout>
            <Head title="Purchase Orders · Procurement" />
            <div className="p-4 sm:p-6">
                <PageHeader
                    icon={ShoppingCart}
                    title="Purchase Orders"
                    description={`${orders.total} ${orders.total === 1 ? 'order' : 'orders'}`}
                    actions={can.manage && <Button href="/procurement/purchase-orders/create" icon={Plus}>New PO</Button>}
                />

                <Card className="mb-4 flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                    <SearchInput className="w-full sm:max-w-sm" initial={filters.search ?? ''} onSearch={v => apply({ search: v || undefined })} placeholder="Search PO # or supplier…" />
                    <Select value={filters.status ?? ''} onChange={v => apply({ status: v || undefined })} placeholder="All status" options={statuses} />
                </Card>

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b border-border bg-secondary/40">
                                <tr>
                                    <th className="th">PO #</th>
                                    <th className="th">Supplier</th>
                                    <th className="th">Status</th>
                                    <th className="th hidden md:table-cell">Order date</th>
                                    <th className="th text-right">Total</th>
                                    <th className="th" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {orders.data.length === 0 ? (
                                    <tr><td colSpan={6}>
                                        <EmptyState icon={ShoppingCart} title="No purchase orders"
                                            description="Raise a purchase order against a supplier to begin."
                                            action={can.manage && <Button href="/procurement/purchase-orders/create" icon={Plus}>New PO</Button>} />
                                    </td></tr>
                                ) : orders.data.map(po => (
                                    <tr key={po.id} className="row-link">
                                        <td className="td">
                                            <Link href={`/procurement/purchase-orders/${po.id}`} className="font-mono font-medium text-foreground hover:text-primary">{po.number}</Link>
                                        </td>
                                        <td className="td text-foreground">{po.supplier ?? '—'}</td>
                                        <td className="td"><Pill color={po.status_color} label={po.status_label} /></td>
                                        <td className="td hidden text-muted-foreground md:table-cell">{po.order_date ?? '—'}</td>
                                        <td className="td text-right font-medium text-foreground">{formatCurrency(po.total, po.currency)}</td>
                                        <td className="td">
                                            <div className="flex justify-end">
                                                <Link href={`/procurement/purchase-orders/${po.id}`} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-secondary hover:text-primary" title="View">
                                                    <ExternalLink className="h-4 w-4" />
                                                </Link>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination from={orders.from} to={orders.to} total={orders.total} links={orders.links} />
                </Card>
            </div>
        </ProcurementLayout>
    );
}
