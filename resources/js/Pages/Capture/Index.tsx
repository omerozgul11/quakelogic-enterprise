import { Head, Link, router } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { formatDate, cn } from '@/Lib/utils';
import { CapturePlan } from '@/Types';
import { PaginatedResponse } from '@/Types';
import { Target, ExternalLink, Search, X } from 'lucide-react';

const STAGES = ['discovery', 'qualification', 'pursuit', 'proposal_development', 'submission', 'evaluation', 'award', 'execution'];

interface Props {
    capturePlans: PaginatedResponse<CapturePlan & {
        opportunity: { id: number; title: string; agency_name: string | null; due_date: string | null };
        owner: { id: number; name: string } | null;
    }>;
    filters: Record<string, string>;
}

export default function CaptureIndex({ capturePlans, filters }: Props) {
    const handleFilter = (key: string, value: string) => {
        router.get('/capture', { ...filters, [key]: value || undefined }, { preserveState: true });
    };

    return (
        <AppLayout>
            <Head title="Capture Management" />
            <div className="p-6">
                <PageHeader
                    icon={Target}
                    title="Capture Management"
                    description={`${capturePlans.total} active capture ${capturePlans.total === 1 ? 'plan' : 'plans'}`}
                />

                {/* Filters */}
                <Card className="mb-4 p-4">
                    <div className="flex flex-wrap items-center gap-3">
                        <div className="relative min-w-[18rem] flex-1">
                            <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <input
                                type="text"
                                placeholder="Search opportunities…"
                                defaultValue={filters.search ?? ''}
                                onKeyDown={e => e.key === 'Enter' && handleFilter('search', (e.target as HTMLInputElement).value)}
                                className="input input-with-icon"
                            />
                        </div>
                        <select value={filters.stage ?? ''} onChange={e => handleFilter('stage', e.target.value)} className="select">
                            <option value="">All Stages</option>
                            {STAGES.map(s => (
                                <option key={s} value={s}>{s.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</option>
                            ))}
                        </select>
                        {Object.keys(filters).length > 0 && (
                            <button onClick={() => router.get('/capture')} className="inline-flex items-center gap-1 text-sm font-medium text-destructive hover:underline">
                                <X className="h-4 w-4" /> Clear
                            </button>
                        )}
                    </div>
                </Card>

                {/* Stage pipeline view */}
                <div className="stagger mb-6 grid grid-cols-4 gap-2 overflow-x-auto lg:grid-cols-8">
                    {STAGES.map(stage => {
                        const count = capturePlans.data.filter(p => {
                            const s = typeof p.stage === 'string' ? p.stage : (p.stage as any)?.value ?? '';
                            return s === stage;
                        }).length;
                        const active = filters.stage === stage;
                        return (
                            <button
                                key={stage}
                                onClick={() => handleFilter('stage', active ? '' : stage)}
                                className={cn(
                                    'card-surface card-hover rounded-xl p-3 text-center text-xs font-medium transition-all',
                                    active ? 'bg-brand-gradient text-white shadow-glow' : 'text-muted-foreground'
                                )}
                            >
                                <p className={cn('text-lg font-bold', active ? 'text-white' : 'text-foreground')}>{count}</p>
                                <p className="capitalize">{stage.replace(/_/g, ' ')}</p>
                            </button>
                        );
                    })}
                </div>

                {/* Table */}
                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b border-border bg-secondary/40">
                                <tr>
                                    <th className="th">Opportunity</th>
                                    <th className="th">Stage</th>
                                    <th className="th">Win Prob.</th>
                                    <th className="th">Owner</th>
                                    <th className="th">Due Date</th>
                                    <th className="th" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {capturePlans.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6}>
                                            <EmptyState
                                                icon={Target}
                                                title="No capture plans found"
                                                description="Try adjusting your filters to find active capture plans."
                                            />
                                        </td>
                                    </tr>
                                ) : capturePlans.data.map(plan => {
                                    const stage = typeof plan.stage === 'string' ? plan.stage : (plan.stage as any)?.value ?? 'discovery';
                                    return (
                                        <tr key={plan.id} className="row-link">
                                            <td className="td">
                                                <Link href={`/capture/${plan.id}`} className="line-clamp-1 font-medium text-foreground hover:text-primary">
                                                    {plan.opportunity?.title ?? 'Unknown'}
                                                </Link>
                                                <p className="text-xs text-muted-foreground">{plan.opportunity?.agency_name}</p>
                                            </td>
                                            <td className="td"><StatusBadge status={stage} /></td>
                                            <td className="td font-medium">
                                                {plan.win_probability ? `${plan.win_probability}%` : '—'}
                                            </td>
                                            <td className="td text-muted-foreground">{plan.owner?.name ?? '—'}</td>
                                            <td className="td text-muted-foreground">
                                                {plan.opportunity?.due_date ? formatDate(plan.opportunity.due_date) : '—'}
                                            </td>
                                            <td className="td">
                                                <Link href={`/capture/${plan.id}`} className="text-muted-foreground transition-colors hover:text-primary">
                                                    <ExternalLink className="h-4 w-4" />
                                                </Link>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>
        </AppLayout>
    );
}
