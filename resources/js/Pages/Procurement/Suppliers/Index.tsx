import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ProcurementLayout } from '@/Components/layout/ProcurementLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Pill } from '@/Components/ui/Pill';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Pagination } from '@/Components/ui/Pagination';
import { SearchInput } from '@/Components/ui/SearchInput';
import { Select } from '@/Components/ui/Select';
import { SupplierFormModal } from '@/Components/procurement/SupplierFormModal';
import { cn, getInitials, avatarGradient } from '@/Lib/utils';
import { PaginatedResponse } from '@/Types';
import { Factory, Plus, ExternalLink } from 'lucide-react';

interface SupplierRow {
    id: number; code: string; name: string; category: string | null;
    status_label: string; status_color: string;
    email: string | null; phone: string | null; payment_terms: string | null;
    purchase_orders_count: number;
}

interface Props {
    suppliers: PaginatedResponse<SupplierRow>;
    filters: Record<string, string>;
    statuses: { value: string; label: string }[];
    can: { manage: boolean };
}

export default function SuppliersIndex({ suppliers, filters, statuses, can }: Props) {
    const [formOpen, setFormOpen] = useState(false);

    const apply = (patch: Record<string, string | undefined>) => {
        router.get('/procurement/suppliers', { ...filters, ...patch }, { preserveState: true, preserveScroll: true, replace: true });
    };

    return (
        <ProcurementLayout>
            <Head title="Suppliers · Procurement" />
            <div className="p-4 sm:p-6">
                <PageHeader
                    icon={Factory}
                    title="Suppliers"
                    description={`${suppliers.total} ${suppliers.total === 1 ? 'supplier' : 'suppliers'}`}
                    actions={can.manage && <Button onClick={() => setFormOpen(true)} icon={Plus}>Add Supplier</Button>}
                />

                <Card className="mb-4 flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                    <SearchInput className="w-full sm:max-w-sm" initial={filters.search ?? ''} onSearch={v => apply({ search: v || undefined })} placeholder="Search name, code, category…" />
                    <Select value={filters.status ?? ''} onChange={v => apply({ status: v || undefined })} placeholder="All status" options={statuses} />
                </Card>

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b border-border bg-secondary/40">
                                <tr>
                                    <th className="th">Supplier</th>
                                    <th className="th hidden sm:table-cell">Category</th>
                                    <th className="th">Status</th>
                                    <th className="th hidden md:table-cell">Terms</th>
                                    <th className="th text-right">POs</th>
                                    <th className="th" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {suppliers.data.length === 0 ? (
                                    <tr><td colSpan={6}>
                                        <EmptyState icon={Factory} title="No suppliers found"
                                            description="Add a supplier to start raising purchase orders."
                                            action={can.manage && <Button onClick={() => setFormOpen(true)} icon={Plus}>Add Supplier</Button>} />
                                    </td></tr>
                                ) : suppliers.data.map(s => (
                                    <tr key={s.id} className="row-link">
                                        <td className="td">
                                            <div className="flex items-center gap-3">
                                                <div className={cn('flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br text-xs font-bold text-white', avatarGradient(s.name))}>
                                                    {getInitials(s.name)}
                                                </div>
                                                <Link href={`/procurement/suppliers/${s.id}`} className="block">
                                                    <span className="font-medium text-foreground hover:text-primary">{s.name}</span>
                                                    <span className="block font-mono text-xs text-muted-foreground">{s.code}</span>
                                                </Link>
                                            </div>
                                        </td>
                                        <td className="td hidden text-muted-foreground sm:table-cell">{s.category ?? '—'}</td>
                                        <td className="td"><Pill color={s.status_color} label={s.status_label} /></td>
                                        <td className="td hidden text-muted-foreground md:table-cell">{s.payment_terms ?? '—'}</td>
                                        <td className="td text-right text-muted-foreground">{s.purchase_orders_count}</td>
                                        <td className="td">
                                            <div className="flex justify-end">
                                                <Link href={`/procurement/suppliers/${s.id}`} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-secondary hover:text-primary" title="View">
                                                    <ExternalLink className="h-4 w-4" />
                                                </Link>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination from={suppliers.from} to={suppliers.to} total={suppliers.total} links={suppliers.links} />
                </Card>
            </div>

            {formOpen && <SupplierFormModal open onClose={() => setFormOpen(false)} statuses={statuses} />}
        </ProcurementLayout>
    );
}
