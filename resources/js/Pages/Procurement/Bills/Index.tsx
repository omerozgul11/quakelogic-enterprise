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
import { Receipt, Plus, ExternalLink, RefreshCw } from 'lucide-react';

interface BillRow {
    id: number; number: string; vendor_invoice_number: string | null; supplier: string | null;
    payment_status: string; payment_status_label: string; payment_status_color: string;
    total: number; amount_paid: number; currency: string; bill_date: string | null; due_date: string | null; recurring: boolean;
}
interface Props {
    bills: PaginatedResponse<BillRow>;
    filters: Record<string, string>;
    statuses: { value: string; label: string }[];
    can: { manage: boolean; approve: boolean };
}

export default function BillsIndex({ bills, filters, statuses, can }: Props) {
    const apply = (patch: Record<string, string | undefined>) => {
        router.get('/procurement/bills', { ...filters, ...patch }, { preserveState: true, preserveScroll: true, replace: true });
    };
    return (
        <ProcurementLayout>
            <Head title="Bills · Procurement" />
            <div className="p-4 sm:p-6">
                <PageHeader icon={Receipt} title="Bills"
                    description={`${bills.total} ${bills.total === 1 ? 'bill' : 'bills'}`}
                    actions={can.manage && <Button href="/procurement/bills/create" icon={Plus}>New bill</Button>} />

                <Card className="mb-4 flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                    <SearchInput className="w-full sm:max-w-sm" initial={filters.search ?? ''} onSearch={v => apply({ search: v || undefined })} placeholder="Search bill #, invoice # or vendor…" />
                    <Select value={filters.payment_status ?? ''} onChange={v => apply({ payment_status: v || undefined })} placeholder="All statuses" options={statuses} />
                </Card>

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b border-border bg-secondary/40">
                                <tr>
                                    <th className="th">Bill #</th><th className="th">Vendor</th><th className="th">Payment</th>
                                    <th className="th hidden md:table-cell">Due</th><th className="th text-right">Paid / Total</th><th className="th" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {bills.data.length === 0 ? (
                                    <tr><td colSpan={6}>
                                        <EmptyState icon={Receipt} title="No bills"
                                            description="Raise a bill from a purchase order, or add one directly, then record payments against it."
                                            action={can.manage && <Button href="/procurement/bills/create" icon={Plus}>New bill</Button>} />
                                    </td></tr>
                                ) : bills.data.map(b => (
                                    <tr key={b.id} className="row-link">
                                        <td className="td">
                                            <Link href={`/procurement/bills/${b.id}`} className="font-mono font-medium text-foreground hover:text-primary">{b.number}</Link>
                                            {b.recurring && <RefreshCw className="ml-1.5 inline h-3 w-3 text-muted-foreground" />}
                                            {b.vendor_invoice_number && <div className="text-xs text-muted-foreground">inv {b.vendor_invoice_number}</div>}
                                        </td>
                                        <td className="td text-foreground">{b.supplier ?? '—'}</td>
                                        <td className="td"><Pill color={b.payment_status_color} label={b.payment_status_label} /></td>
                                        <td className="td hidden text-muted-foreground md:table-cell">{b.due_date ?? '—'}</td>
                                        <td className="td text-right text-foreground"><span className="text-muted-foreground">{formatCurrency(b.amount_paid, b.currency)}</span> / <span className="font-medium">{formatCurrency(b.total, b.currency)}</span></td>
                                        <td className="td"><div className="flex justify-end"><Link href={`/procurement/bills/${b.id}`} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-secondary hover:text-primary" title="View"><ExternalLink className="h-4 w-4" /></Link></div></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination from={bills.from} to={bills.to} total={bills.total} links={bills.links} />
                </Card>
            </div>
        </ProcurementLayout>
    );
}
