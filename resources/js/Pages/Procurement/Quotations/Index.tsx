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
import { FileText, Plus, ExternalLink } from 'lucide-react';

interface QRow {
    id: number; number: string; supplier: string | null;
    status: string; status_label: string; status_color: string;
    total: number; currency: string; quote_date: string | null;
}
interface Props {
    quotations: PaginatedResponse<QRow>;
    filters: Record<string, string>;
    statuses: { value: string; label: string }[];
    can: { manage: boolean };
}

export default function QuotationsIndex({ quotations, filters, statuses, can }: Props) {
    const apply = (patch: Record<string, string | undefined>) => {
        router.get('/procurement/quotations', { ...filters, ...patch }, { preserveState: true, preserveScroll: true, replace: true });
    };
    return (
        <ProcurementLayout>
            <Head title="Quotations · Procurement" />
            <div className="p-4 sm:p-6">
                <PageHeader icon={FileText} title="Quotations"
                    description={`${quotations.total} ${quotations.total === 1 ? 'quotation' : 'quotations'}`}
                    actions={can.manage && <Button href="/procurement/quotations/create" icon={Plus}>New quotation</Button>} />

                <Card className="mb-4 flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                    <SearchInput className="w-full sm:max-w-sm" initial={filters.search ?? ''} onSearch={v => apply({ search: v || undefined })} placeholder="Search RFQ # or vendor…" />
                    <Select value={filters.status ?? ''} onChange={v => apply({ status: v || undefined })} placeholder="All status" options={statuses} />
                </Card>

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b border-border bg-secondary/40">
                                <tr>
                                    <th className="th">RFQ #</th><th className="th">Vendor</th><th className="th">Status</th>
                                    <th className="th hidden md:table-cell">Quote date</th><th className="th text-right">Total</th><th className="th" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {quotations.data.length === 0 ? (
                                    <tr><td colSpan={6}>
                                        <EmptyState icon={FileText} title="No quotations"
                                            description="Request quotes from vendors — accept one to raise a purchase order."
                                            action={can.manage && <Button href="/procurement/quotations/create" icon={Plus}>New quotation</Button>} />
                                    </td></tr>
                                ) : quotations.data.map(q => (
                                    <tr key={q.id} className="row-link">
                                        <td className="td"><Link href={`/procurement/quotations/${q.id}`} className="font-mono font-medium text-foreground hover:text-primary">{q.number}</Link></td>
                                        <td className="td text-foreground">{q.supplier ?? '—'}</td>
                                        <td className="td"><Pill color={q.status_color} label={q.status_label} /></td>
                                        <td className="td hidden text-muted-foreground md:table-cell">{q.quote_date ?? '—'}</td>
                                        <td className="td text-right font-medium text-foreground">{formatCurrency(q.total, q.currency)}</td>
                                        <td className="td"><div className="flex justify-end"><Link href={`/procurement/quotations/${q.id}`} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-secondary hover:text-primary" title="View"><ExternalLink className="h-4 w-4" /></Link></div></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination from={quotations.from} to={quotations.to} total={quotations.total} links={quotations.links} />
                </Card>
            </div>
        </ProcurementLayout>
    );
}
