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
import { ClipboardList, Plus, ExternalLink } from 'lucide-react';

interface PrRow {
    id: number; number: string; title: string; requester: string | null; department: string | null;
    status: string; status_label: string; status_color: string;
    total: number; currency: string; created_at: string | null;
}

interface Props {
    requests: PaginatedResponse<PrRow>;
    filters: Record<string, string>;
    statuses: { value: string; label: string }[];
    can: { manage: boolean };
}

export default function PurchaseRequestsIndex({ requests, filters, statuses, can }: Props) {
    const apply = (patch: Record<string, string | undefined>) => {
        router.get('/procurement/purchase-requests', { ...filters, ...patch }, { preserveState: true, preserveScroll: true, replace: true });
    };

    return (
        <ProcurementLayout>
            <Head title="Purchase Requests · Procurement" />
            <div className="p-4 sm:p-6">
                <PageHeader
                    icon={ClipboardList}
                    title="Purchase Requests"
                    description={`${requests.total} ${requests.total === 1 ? 'request' : 'requests'}`}
                    actions={can.manage && <Button href="/procurement/purchase-requests/create" icon={Plus}>New request</Button>}
                />

                <Card className="mb-4 flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                    <SearchInput className="w-full sm:max-w-sm" initial={filters.search ?? ''} onSearch={v => apply({ search: v || undefined })} placeholder="Search PR # or title…" />
                    <Select value={filters.status ?? ''} onChange={v => apply({ status: v || undefined })} placeholder="All status" options={statuses} />
                </Card>

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b border-border bg-secondary/40">
                                <tr>
                                    <th className="th">PR #</th>
                                    <th className="th">Title</th>
                                    <th className="th hidden md:table-cell">Requester</th>
                                    <th className="th">Status</th>
                                    <th className="th text-right">Total</th>
                                    <th className="th" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {requests.data.length === 0 ? (
                                    <tr><td colSpan={6}>
                                        <EmptyState icon={ClipboardList} title="No purchase requests"
                                            description="Raise a request for what you need — get it approved, then turn it into a quotation or purchase order."
                                            action={can.manage && <Button href="/procurement/purchase-requests/create" icon={Plus}>New request</Button>} />
                                    </td></tr>
                                ) : requests.data.map(pr => (
                                    <tr key={pr.id} className="row-link">
                                        <td className="td"><Link href={`/procurement/purchase-requests/${pr.id}`} className="font-mono font-medium text-foreground hover:text-primary">{pr.number}</Link></td>
                                        <td className="td text-foreground">{pr.title}</td>
                                        <td className="td hidden text-muted-foreground md:table-cell">{pr.requester ?? '—'}</td>
                                        <td className="td"><Pill color={pr.status_color} label={pr.status_label} /></td>
                                        <td className="td text-right font-medium text-foreground">{formatCurrency(pr.total, pr.currency)}</td>
                                        <td className="td">
                                            <div className="flex justify-end">
                                                <Link href={`/procurement/purchase-requests/${pr.id}`} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-secondary hover:text-primary" title="View"><ExternalLink className="h-4 w-4" /></Link>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination from={requests.from} to={requests.to} total={requests.total} links={requests.links} />
                </Card>
            </div>
        </ProcurementLayout>
    );
}
