import { Head, router } from '@inertiajs/react';
import { ExpenseLayout } from '@/Components/layout/ExpenseLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { formatCurrency } from '@/Lib/utils';
import { BarChart3, Download } from 'lucide-react';

interface Breakdown { name: string; total: number }

interface Props {
    filters: { from: string; to: string };
    total: number;
    byCategory: Breakdown[];
    byPerson: Breakdown[];
    byVendor: Breakdown[];
    billableSplit: { billable: number; non_billable: number };
}

function BreakdownCard({ title, rows, total }: { title: string; rows: Breakdown[]; total: number }) {
    const max = Math.max(1, ...rows.map(r => r.total));
    return (
        <Card className="p-5">
            <h2 className="text-sm font-semibold text-foreground">{title}</h2>
            {rows.length === 0 ? (
                <p className="mt-4 text-sm text-muted-foreground">No data for this range.</p>
            ) : (
                <ul className="mt-4 space-y-3">
                    {rows.map(r => (
                        <li key={r.name}>
                            <div className="flex items-center justify-between text-sm">
                                <span className="truncate text-foreground">{r.name}</span>
                                <span className="ml-3 shrink-0 font-medium text-foreground">{formatCurrency(r.total)}</span>
                            </div>
                            <div className="mt-1 h-1.5 overflow-hidden rounded-full bg-secondary">
                                <div className="h-full rounded-full bg-indigo-500" style={{ width: `${(r.total / max) * 100}%` }} />
                            </div>
                        </li>
                    ))}
                </ul>
            )}
            {total > 0 && rows.length > 0 && (
                <p className="mt-4 border-t border-border pt-3 text-xs text-muted-foreground">Total {formatCurrency(total)}</p>
            )}
        </Card>
    );
}

export default function ReportsIndex({ filters, total, byCategory, byPerson, byVendor, billableSplit }: Props) {
    const setRange = (patch: Partial<typeof filters>) => {
        router.get('/expenses/reports', { ...filters, ...patch }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const billableTotal = billableSplit.billable + billableSplit.non_billable;
    const billablePct = billableTotal > 0 ? Math.round((billableSplit.billable / billableTotal) * 100) : 0;
    const exportUrl = `/expenses/reports/export?from=${filters.from}&to=${filters.to}`;

    return (
        <ExpenseLayout>
            <Head title="Reports · Expenses" />
            <div className="p-4 sm:p-6">
                <PageHeader
                    icon={BarChart3}
                    title="Reports"
                    description="Approved spend across categories, people and vendors"
                    actions={<a href={exportUrl}><Button variant="secondary" icon={Download}>Export CSV</Button></a>}
                />

                <Card className="mb-6 flex flex-col gap-3 p-4 sm:flex-row sm:items-end">
                    <div>
                        <label className="label">From</label>
                        <input type="date" className="input" value={filters.from} onChange={e => setRange({ from: e.target.value })} />
                    </div>
                    <div>
                        <label className="label">To</label>
                        <input type="date" className="input" value={filters.to} onChange={e => setRange({ to: e.target.value })} />
                    </div>
                    <div className="sm:ml-auto sm:text-right">
                        <p className="text-xs uppercase tracking-wide text-muted-foreground/70">Total spend</p>
                        <p className="text-2xl font-bold text-foreground">{formatCurrency(total)}</p>
                    </div>
                </Card>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <BreakdownCard title="By category" rows={byCategory} total={total} />
                    <BreakdownCard title="By person" rows={byPerson} total={total} />
                    <BreakdownCard title="By vendor" rows={byVendor} total={total} />
                </div>

                <Card className="mt-6 p-5">
                    <h2 className="text-sm font-semibold text-foreground">Billable vs non-billable</h2>
                    <div className="mt-4 flex h-3 overflow-hidden rounded-full bg-secondary">
                        <div className="h-full bg-emerald-500" style={{ width: `${billablePct}%` }} />
                    </div>
                    <div className="mt-3 flex justify-between text-sm">
                        <span className="text-foreground"><span className="font-semibold">{formatCurrency(billableSplit.billable)}</span> billable ({billablePct}%)</span>
                        <span className="text-muted-foreground"><span className="font-semibold text-foreground">{formatCurrency(billableSplit.non_billable)}</span> non-billable</span>
                    </div>
                </Card>
            </div>
        </ExpenseLayout>
    );
}
