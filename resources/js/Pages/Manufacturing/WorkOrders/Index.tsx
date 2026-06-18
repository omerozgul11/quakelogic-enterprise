import { Head, Link, router } from '@inertiajs/react';
import { ManufacturingLayout } from '@/Components/layout/ManufacturingLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Pill } from '@/Components/ui/Pill';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Pagination } from '@/Components/ui/Pagination';
import { SearchInput } from '@/Components/ui/SearchInput';
import { Select } from '@/Components/ui/Select';
import { PaginatedResponse } from '@/Types';
import { Factory, Plus, ExternalLink } from 'lucide-react';

interface WoRow {
    id: number; number: string; product: string;
    status: string; status_label: string; status_color: string;
    quantity_planned: number; quantity_produced: number; scheduled_date: string | null;
}

interface Props {
    orders: PaginatedResponse<WoRow>;
    filters: Record<string, string>;
    statuses: { value: string; label: string }[];
    can: { manage: boolean };
}

export default function WorkOrdersIndex({ orders, filters, statuses, can }: Props) {
    const apply = (patch: Record<string, string | undefined>) => router.get('/manufacturing/work-orders', { ...filters, ...patch }, { preserveState: true, preserveScroll: true, replace: true });

    return (
        <ManufacturingLayout>
            <Head title="Work Orders · Manufacturing" />
            <div className="p-4 sm:p-6">
                <PageHeader
                    icon={Factory}
                    title="Work Orders"
                    description={`${orders.total} ${orders.total === 1 ? 'order' : 'orders'}`}
                    actions={can.manage && <Button href="/manufacturing/work-orders/create" icon={Plus}>New Work Order</Button>}
                />

                <Card className="mb-4 flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                    <SearchInput className="w-full sm:max-w-sm" initial={filters.search ?? ''} onSearch={v => apply({ search: v || undefined })} placeholder="Search WO # or product…" />
                    <Select value={filters.status ?? ''} onChange={v => apply({ status: v || undefined })} placeholder="All status" options={statuses} />
                </Card>

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b border-border bg-secondary/40">
                                <tr>
                                    <th className="th">WO #</th>
                                    <th className="th">Product</th>
                                    <th className="th">Status</th>
                                    <th className="th text-right">Produced / planned</th>
                                    <th className="th hidden md:table-cell">Scheduled</th>
                                    <th className="th" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {orders.data.length === 0 ? (
                                    <tr><td colSpan={6}>
                                        <EmptyState icon={Factory} title="No work orders"
                                            description="Create a work order to build finished goods from a BOM."
                                            action={can.manage && <Button href="/manufacturing/work-orders/create" icon={Plus}>New Work Order</Button>} />
                                    </td></tr>
                                ) : orders.data.map(w => (
                                    <tr key={w.id} className="row-link">
                                        <td className="td"><Link href={`/manufacturing/work-orders/${w.id}`} className="font-mono font-medium text-foreground hover:text-primary">{w.number}</Link></td>
                                        <td className="td text-foreground">{w.product}</td>
                                        <td className="td"><Pill color={w.status_color} label={w.status_label} /></td>
                                        <td className="td text-right text-foreground">{w.quantity_produced} / {w.quantity_planned}</td>
                                        <td className="td hidden text-muted-foreground md:table-cell">{w.scheduled_date ?? '—'}</td>
                                        <td className="td"><div className="flex justify-end"><Link href={`/manufacturing/work-orders/${w.id}`} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-secondary hover:text-primary" title="View"><ExternalLink className="h-4 w-4" /></Link></div></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination from={orders.from} to={orders.to} total={orders.total} links={orders.links} />
                </Card>
            </div>
        </ManufacturingLayout>
    );
}
