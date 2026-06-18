import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { FinanceLayout } from '@/Components/layout/FinanceLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Pill } from '@/Components/ui/Pill';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Pagination } from '@/Components/ui/Pagination';
import { SearchInput } from '@/Components/ui/SearchInput';
import { Select } from '@/Components/ui/Select';
import { CreditNoteModal } from '@/Components/finance/CreditNoteModal';
import { formatCurrency } from '@/Lib/utils';
import { PaginatedResponse } from '@/Types';
import { FileMinus, Plus, Check, Ban, Trash2 } from 'lucide-react';

interface CreditNoteRow {
    id: number; number: string; company: string | null; invoice: string | null;
    amount: number; currency: string; reason: string | null;
    status: string; status_label: string; status_color: string; issued_at: string | null;
}
interface FormData {
    companies: { id: number; name: string }[];
    invoices: { id: number; number: string; company_id: number | null }[];
}

interface Props {
    credit_notes: PaginatedResponse<CreditNoteRow>;
    filters: Record<string, string>;
    statuses: { value: string; label: string }[];
    form: FormData;
    can: { manage: boolean };
}

export default function CreditNotesIndex({ credit_notes, filters, statuses, form, can }: Props) {
    const [issueOpen, setIssueOpen] = useState(false);
    const apply = (patch: Record<string, string | undefined>) => router.get('/finance/credit-notes', { ...filters, ...patch }, { preserveState: true, preserveScroll: true, replace: true });

    return (
        <FinanceLayout>
            <Head title="Credit Notes · Finance" />
            <div className="p-4 sm:p-6">
                <PageHeader
                    icon={FileMinus}
                    title="Credit Notes"
                    description={`${credit_notes.total} ${credit_notes.total === 1 ? 'credit note' : 'credit notes'}`}
                    actions={can.manage && <Button onClick={() => setIssueOpen(true)} icon={Plus}>Issue Credit Note</Button>}
                />

                <Card className="mb-4 flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                    <SearchInput className="w-full sm:max-w-sm" initial={filters.search ?? ''} onSearch={v => apply({ search: v || undefined })} placeholder="Search # or reason…" />
                    <Select value={filters.status ?? ''} onChange={v => apply({ status: v || undefined })} placeholder="All status" options={statuses} />
                </Card>

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b border-border bg-secondary/40">
                                <tr>
                                    <th className="th">Credit note</th>
                                    <th className="th">Client</th>
                                    <th className="th hidden md:table-cell">Invoice</th>
                                    <th className="th text-right">Amount</th>
                                    <th className="th">Status</th>
                                    <th className="th" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {credit_notes.data.length === 0 ? (
                                    <tr><td colSpan={6}>
                                        <EmptyState icon={FileMinus} title="No credit notes"
                                            description="Issue a credit note for an overcharge, return or goodwill adjustment."
                                            action={can.manage && <Button onClick={() => setIssueOpen(true)} icon={Plus}>Issue Credit Note</Button>} />
                                    </td></tr>
                                ) : credit_notes.data.map(n => (
                                    <tr key={n.id}>
                                        <td className="td">
                                            <span className="font-mono font-medium text-foreground">{n.number}</span>
                                            {n.reason && <span className="block text-xs text-muted-foreground">{n.reason}</span>}
                                        </td>
                                        <td className="td text-foreground">{n.company ?? '—'}</td>
                                        <td className="td hidden md:table-cell">
                                            {n.invoice ? <Link href={`/finance/invoices`} className="font-mono text-xs text-primary hover:underline">{n.invoice}</Link> : <span className="text-muted-foreground">—</span>}
                                        </td>
                                        <td className="td text-right font-medium text-foreground">{formatCurrency(n.amount, n.currency)}</td>
                                        <td className="td"><Pill color={n.status_color} label={n.status_label} /></td>
                                        <td className="td">
                                            {can.manage && (
                                                <div className="flex items-center justify-end gap-1">
                                                    {n.status === 'open' && <button onClick={() => router.post(`/finance/credit-notes/${n.id}/apply`, {}, { preserveScroll: true })} className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-emerald-600" title="Apply"><Check className="h-4 w-4" /></button>}
                                                    {n.status !== 'void' && <button onClick={() => router.post(`/finance/credit-notes/${n.id}/void`, {}, { preserveScroll: true })} className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground" title="Void"><Ban className="h-4 w-4" /></button>}
                                                    <button onClick={() => router.delete(`/finance/credit-notes/${n.id}`, { preserveScroll: true })} className="rounded-md p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive" title="Delete"><Trash2 className="h-4 w-4" /></button>
                                                </div>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination from={credit_notes.from} to={credit_notes.to} total={credit_notes.total} links={credit_notes.links} />
                </Card>
            </div>

            {issueOpen && <CreditNoteModal open onClose={() => setIssueOpen(false)} companies={form.companies} invoices={form.invoices} />}
        </FinanceLayout>
    );
}
