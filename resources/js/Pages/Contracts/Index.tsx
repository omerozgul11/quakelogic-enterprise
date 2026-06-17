import { Head, Link } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { StatCard } from '@/Components/ui/StatCard';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { EmptyState } from '@/Components/ui/EmptyState';
import { formatCurrency } from '@/Lib/utils';
import { FileSignature, DollarSign, Receipt, Wallet, ExternalLink } from 'lucide-react';

interface ContractRow {
    id: number;
    proposal_id: number;
    proposal_number: string | null;
    project_name: string | null;
    company: string | null;
    owner: string | null;
    contract_number: string | null;
    po_number: string | null;
    invoice_number: string | null;
    stage: string;
    stage_label: string;
    payment_status: string;
    payment_label: string;
    contract_value: number;
    amount_paid: number;
    currency: string;
    milestones: number;
    milestones_done: number;
}

interface Props {
    contracts: ContractRow[];
    totals: { count: number; value: number; invoiced: number; paid: number; outstanding: number };
}

export default function ContractsIndex({ contracts, totals }: Props) {
    return (
        <AppLayout>
            <Head title="Contracts" />
            <div className="p-4 sm:p-6">
                <PageHeader
                    icon={FileSignature}
                    title="Contracts"
                    description="Post-award contract, PO, invoice & payment tracking for won proposals."
                />

                <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard title="Contract Value" value={formatCurrency(totals.value)} icon={DollarSign} tone="indigo" subtitle={`${totals.count} contract${totals.count === 1 ? '' : 's'} (USD)`} />
                    <StatCard title="Invoiced" value={formatCurrency(totals.invoiced)} icon={Receipt} tone="amber" subtitle="Total invoiced to date" />
                    <StatCard title="Paid" value={formatCurrency(totals.paid)} icon={Wallet} tone="emerald" subtitle="Payments received" />
                    <StatCard title="Outstanding" value={formatCurrency(totals.outstanding)} icon={DollarSign} tone="rose" subtitle="Value minus paid" />
                </div>

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b border-border bg-secondary/40">
                                <tr>
                                    <th className="th">Proposal</th>
                                    <th className="th">Stage</th>
                                    <th className="th">Payment</th>
                                    <th className="th hidden md:table-cell">Contract / PO #</th>
                                    <th className="th hidden lg:table-cell text-center">Milestones</th>
                                    <th className="th text-right">Value</th>
                                    <th className="th text-right">Paid</th>
                                    <th className="th" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {contracts.length === 0 ? (
                                    <tr>
                                        <td colSpan={8}>
                                            <EmptyState
                                                icon={FileSignature}
                                                title="No contracts yet"
                                                description="When a proposal is won, open it and add its contract details to start tracking PO, invoice and payment."
                                            />
                                        </td>
                                    </tr>
                                ) : contracts.map(c => (
                                    <tr key={c.id} className="row-link">
                                        <td className="td">
                                            <Link href={`/proposals/${c.proposal_id}`} className="block max-w-[24rem]">
                                                <p className="truncate text-sm font-medium text-foreground hover:text-primary">{c.project_name ?? '—'}</p>
                                                <p className="mt-0.5 font-mono text-[11px] text-muted-foreground">{c.proposal_number}{c.company ? ` · ${c.company}` : ''}</p>
                                            </Link>
                                        </td>
                                        <td className="td"><StatusBadge status={c.stage} label={c.stage_label} /></td>
                                        <td className="td"><StatusBadge status={c.payment_status} label={c.payment_label} /></td>
                                        <td className="td hidden md:table-cell text-muted-foreground">
                                            <span className="font-mono text-xs">{c.contract_number ?? '—'}{c.po_number ? ` / ${c.po_number}` : ''}</span>
                                        </td>
                                        <td className="td hidden lg:table-cell text-center text-muted-foreground">
                                            {c.milestones > 0 ? `${c.milestones_done}/${c.milestones}` : '—'}
                                        </td>
                                        <td className="td text-right font-medium text-foreground">{c.contract_value > 0 ? formatCurrency(c.contract_value, c.currency) : '—'}</td>
                                        <td className="td text-right text-muted-foreground">{c.amount_paid > 0 ? formatCurrency(c.amount_paid, c.currency) : '—'}</td>
                                        <td className="td">
                                            <Link href={`/proposals/${c.proposal_id}`} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-secondary hover:text-primary" title="Open proposal">
                                                <ExternalLink className="h-4 w-4" />
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>
        </AppLayout>
    );
}
