import { Head, Link, router } from '@inertiajs/react';
import { FinanceLayout } from '@/Components/layout/FinanceLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { Pill } from '@/Components/ui/Pill';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Pagination } from '@/Components/ui/Pagination';
import { SearchInput } from '@/Components/ui/SearchInput';
import { Select } from '@/Components/ui/Select';
import { formatCurrency } from '@/Lib/utils';
import { PaginatedResponse } from '@/Types';
import { ReceiptText, ExternalLink } from 'lucide-react';

interface InvoiceRow {
    id: number; number: string; company: string | null;
    total: number; amount_paid: number; balance: number; currency: string;
    status: string; status_label: string; status_color: string;
    issue_date: string | null; due_date: string | null; overdue: boolean;
}

interface Props {
    invoices: PaginatedResponse<InvoiceRow>;
    filters: Record<string, string>;
    statuses: { value: string; label: string }[];
    can: { pay: boolean };
}

export default function FinanceInvoicesIndex({ invoices, filters, statuses }: Props) {
    const apply = (patch: Record<string, string | undefined>) => router.get('/finance/invoices', { ...filters, ...patch }, { preserveState: true, preserveScroll: true, replace: true });

    return (
        <FinanceLayout>
            <Head title="Invoices · Finance" />
            <div className="p-4 sm:p-6">
                <PageHeader icon={ReceiptText} title="Invoices" description={`${invoices.total} ${invoices.total === 1 ? 'invoice' : 'invoices'}`} />

                <Card className="mb-4 flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                    <SearchInput className="w-full sm:max-w-sm" initial={filters.search ?? ''} onSearch={v => apply({ search: v || undefined })} placeholder="Search # or client…" />
                    <div className="flex gap-2">
                        <Select value={filters.status ?? ''} onChange={v => apply({ status: v || undefined })} placeholder="All status" options={statuses} />
                        <Select value={filters.due ?? ''} onChange={v => apply({ due: v || undefined })} placeholder="Any" options={[{ value: 'unpaid', label: 'Unpaid' }, { value: 'overdue', label: 'Overdue' }]} />
                    </div>
                </Card>

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b border-border bg-secondary/40">
                                <tr>
                                    <th className="th">Invoice</th>
                                    <th className="th">Client</th>
                                    <th className="th">Status</th>
                                    <th className="th text-right">Total</th>
                                    <th className="th text-right">Balance</th>
                                    <th className="th hidden sm:table-cell">Due</th>
                                    <th className="th" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {invoices.data.length === 0 ? (
                                    <tr><td colSpan={7}>
                                        <EmptyState icon={ReceiptText} title="No invoices"
                                            description="Invoices are created in the CRM; they appear here for AR & payment collection." />
                                    </td></tr>
                                ) : invoices.data.map(i => (
                                    <tr key={i.id} className="row-link">
                                        <td className="td"><Link href={`/finance/invoices/${i.id}`} className="font-mono font-medium text-foreground hover:text-primary">{i.number}</Link></td>
                                        <td className="td text-foreground">{i.company ?? '—'}</td>
                                        <td className="td"><Pill color={i.status_color} label={i.status_label} /></td>
                                        <td className="td text-right text-muted-foreground">{formatCurrency(i.total, i.currency)}</td>
                                        <td className="td text-right font-medium text-foreground">{formatCurrency(i.balance, i.currency)}</td>
                                        <td className="td hidden sm:table-cell"><span className={i.overdue ? 'font-semibold text-red-600' : 'text-muted-foreground'}>{i.due_date ?? '—'}</span></td>
                                        <td className="td"><div className="flex justify-end"><Link href={`/finance/invoices/${i.id}`} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-secondary hover:text-primary" title="View"><ExternalLink className="h-4 w-4" /></Link></div></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination from={invoices.from} to={invoices.to} total={invoices.total} links={invoices.links} />
                </Card>
            </div>
        </FinanceLayout>
    );
}
