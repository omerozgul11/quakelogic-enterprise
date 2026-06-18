import { Head, Link } from '@inertiajs/react';
import { FinanceLayout } from '@/Components/layout/FinanceLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { StatCard } from '@/Components/ui/StatCard';
import { Card } from '@/Components/ui/Card';
import { Pill } from '@/Components/ui/Pill';
import { EmptyState } from '@/Components/ui/EmptyState';
import { formatCurrency } from '@/Lib/utils';
import { Landmark, CircleDollarSign, AlertTriangle, TrendingUp, ReceiptText } from 'lucide-react';

interface Stats { outstanding: number; overdue: number; collected_month: number; open_invoices: number }
interface ReceivableRow { id: number; number: string; company: string | null; total: number; balance: number; currency: string; status_label: string; status_color: string; due_date: string | null; overdue: boolean }

interface Props {
    stats: Stats;
    receivables: ReceivableRow[];
    provider: string;
}

export default function FinanceDashboard({ stats, receivables, provider }: Props) {
    return (
        <FinanceLayout>
            <Head title="Finance" />
            <div className="p-4 sm:p-6">
                <PageHeader icon={Landmark} title="Finance" description="Accounts receivable & payments"
                    actions={<span className="chip capitalize">Gateway: {provider}</span>} />

                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <StatCard title="Outstanding" value={formatCurrency(stats.outstanding)} icon={CircleDollarSign} tone="indigo" href="/finance/invoices?due=unpaid" />
                    <StatCard title="Overdue" value={formatCurrency(stats.overdue)} icon={AlertTriangle} tone={stats.overdue > 0 ? 'rose' : 'emerald'} href="/finance/invoices?due=overdue" />
                    <StatCard title="Collected (MTD)" value={formatCurrency(stats.collected_month)} icon={TrendingUp} tone="teal" />
                    <StatCard title="Open invoices" value={stats.open_invoices} icon={ReceiptText} tone="sky" href="/finance/invoices" />
                </div>

                <Card className="mt-6 p-5">
                    <h2 className="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70"><ReceiptText className="h-4 w-4" /> Receivables</h2>
                    {receivables.length === 0 ? (
                        <EmptyState icon={ReceiptText} title="Nothing outstanding" description="All invoices are settled. Invoices are created in the CRM." />
                    ) : (
                        <div className="space-y-1.5">
                            {receivables.map(r => (
                                <Link key={r.id} href={`/finance/invoices/${r.id}`} className="flex items-center gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-secondary">
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-sm font-medium text-foreground">{r.company ?? '—'}</span>
                                        <span className="block truncate font-mono text-xs text-muted-foreground">{r.number}</span>
                                    </span>
                                    <span className="text-sm font-semibold text-foreground">{formatCurrency(r.balance, r.currency)}</span>
                                    <Pill color={r.status_color} label={r.status_label} />
                                    {r.due_date && <span className={r.overdue ? 'hidden text-xs font-semibold text-red-600 sm:inline' : 'hidden text-xs text-muted-foreground sm:inline'}>{r.overdue ? 'Overdue ' : 'Due '}{r.due_date}</span>}
                                </Link>
                            ))}
                        </div>
                    )}
                </Card>
            </div>
        </FinanceLayout>
    );
}
