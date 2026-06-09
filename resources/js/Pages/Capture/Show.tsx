import { Head, Link, router } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/Card';
import { StatCard } from '@/Components/ui/StatCard';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { formatDate, formatCurrency, cn } from '@/Lib/utils';
import { CapturePlan } from '@/Types';
import { ArrowLeft, ArrowRight, AlertTriangle, CheckSquare, Target, Percent, DollarSign, ExternalLink } from 'lucide-react';

const STAGE_ORDER = ['discovery', 'qualification', 'pursuit', 'proposal_development', 'submission', 'evaluation', 'award', 'execution'];

interface Risk { id: number; title: string; impact: string; probability: string; mitigation: string | null; status: string }
interface Task { id: number; title: string; status: string; due_date: string | null; assignee: { name: string } | null }

interface Props {
    capturePlan: CapturePlan & {
        opportunity: { id: number; title: string; agency_name: string | null; due_date: string | null; estimated_value: number | null };
        owner: { id: number; name: string } | null;
        risks: Risk[];
        tasks: Task[];
        stage_history: Array<{ id: number; stage: string; created_at: string; user: { name: string } | null }>;
    };
    allowedTransitions: string[];
    can: { edit: boolean; transition: boolean };
}

export default function CaptureShow({ capturePlan, allowedTransitions, can }: Props) {
    const stage = typeof capturePlan.stage === 'string' ? capturePlan.stage : (capturePlan.stage as any)?.value ?? 'discovery';
    const currentStageIndex = STAGE_ORDER.indexOf(stage);

    const handleTransition = (newStage: string) => {
        if (confirm(`Advance capture to "${newStage.replace(/_/g, ' ')}"?`)) {
            router.post(`/capture/${capturePlan.id}/transition`, { stage: newStage });
        }
    };

    return (
        <AppLayout>
            <Head title={`Capture — ${capturePlan.opportunity?.title ?? ''}`} />
            <div className="mx-auto max-w-6xl p-6">
                <PageHeader
                    icon={Target}
                    title={capturePlan.opportunity?.title ?? 'Capture Plan'}
                    description={capturePlan.opportunity?.agency_name ?? undefined}
                    actions={
                        <>
                            <Button href="/capture" variant="secondary" icon={ArrowLeft}>
                                Back
                            </Button>
                            {can.transition && allowedTransitions.map(t => (
                                <Button key={t} variant="primary" iconRight={ArrowRight} onClick={() => handleTransition(t)} className="capitalize">
                                    {t.replace(/_/g, ' ')}
                                </Button>
                            ))}
                        </>
                    }
                />

                {/* Metrics */}
                <div className="stagger mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <StatCard
                        title="Win Probability"
                        value={capturePlan.win_probability ? `${capturePlan.win_probability}%` : '—'}
                        icon={Percent}
                        tone="emerald"
                    />
                    <StatCard
                        title="Estimated Value"
                        value={capturePlan.opportunity?.estimated_value ? formatCurrency(capturePlan.opportunity.estimated_value) : '—'}
                        icon={DollarSign}
                        tone="indigo"
                    />
                    <StatCard
                        title="Open Tasks"
                        value={capturePlan.tasks.filter(t => t.status !== 'completed').length}
                        subtitle={`${capturePlan.tasks.length} total`}
                        icon={CheckSquare}
                        tone="sky"
                    />
                </div>

                {/* Stage Progress Bar */}
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle>Capture Progress</CardTitle>
                        <StatusBadge status={stage} />
                    </CardHeader>
                    <CardContent>
                        <div className="flex gap-1">
                            {STAGE_ORDER.map((s, i) => (
                                <div key={s} className={cn(
                                    'h-2 flex-1 rounded-full transition-colors',
                                    i < currentStageIndex ? 'bg-emerald-500' :
                                    i === currentStageIndex ? 'bg-brand-gradient' : 'bg-secondary'
                                )} title={s.replace(/_/g, ' ')} />
                            ))}
                        </div>
                        <div className="mt-1 flex justify-between">
                            <span className="text-xs text-muted-foreground">Discovery</span>
                            <span className="text-xs text-muted-foreground">Execution</span>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        {/* Risks */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <AlertTriangle className="h-4 w-4 text-amber-500" /> Risks ({capturePlan.risks.length})
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {capturePlan.risks.length === 0 ? (
                                    <p className="py-4 text-center text-sm text-muted-foreground">No risks identified.</p>
                                ) : (
                                    <div className="space-y-3">
                                        {capturePlan.risks.map(risk => (
                                            <div key={risk.id} className="rounded-xl border border-border p-3">
                                                <div className="flex items-start justify-between gap-2">
                                                    <p className="text-sm font-medium text-foreground">{risk.title}</p>
                                                    <StatusBadge status={risk.status} />
                                                </div>
                                                <div className="mt-2 flex gap-4 text-xs text-muted-foreground">
                                                    <span>Impact: <span className="font-medium capitalize text-foreground">{risk.impact}</span></span>
                                                    <span>Prob: <span className="font-medium capitalize text-foreground">{risk.probability}</span></span>
                                                </div>
                                                {risk.mitigation && <p className="mt-2 text-xs text-muted-foreground">Mitigation: {risk.mitigation}</p>}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Tasks */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <CheckSquare className="h-4 w-4 text-primary" /> Tasks ({capturePlan.tasks.length})
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {capturePlan.tasks.length === 0 ? (
                                    <p className="py-4 text-center text-sm text-muted-foreground">No tasks yet.</p>
                                ) : (
                                    <div className="space-y-2">
                                        {capturePlan.tasks.map(task => (
                                            <div key={task.id} className="flex items-center gap-3 rounded-xl border border-border p-3">
                                                <div className={cn(
                                                    'h-2 w-2 shrink-0 rounded-full',
                                                    task.status === 'completed' ? 'bg-emerald-500' : task.status === 'in_progress' ? 'bg-primary' : 'bg-muted-foreground/40'
                                                )} />
                                                <div className="flex-1">
                                                    <p className={cn('text-sm', task.status === 'completed' ? 'text-muted-foreground line-through' : 'text-foreground')}>
                                                        {task.title}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {task.assignee?.name ?? 'Unassigned'}
                                                        {task.due_date ? ` · Due ${formatDate(task.due_date)}` : ''}
                                                    </p>
                                                </div>
                                                <StatusBadge status={task.status} />
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Capture Details</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <dl className="space-y-2.5">
                                    {[
                                        ['Owner', capturePlan.owner?.name],
                                        ['Win Probability', capturePlan.win_probability ? `${capturePlan.win_probability}%` : null],
                                        ['Go/No-Go Decision', capturePlan.go_no_go_decision],
                                        ['RFP Release Date', capturePlan.rfp_release_date ? formatDate(capturePlan.rfp_release_date) : null],
                                        ['Proposal Due', capturePlan.opportunity?.due_date ? formatDate(capturePlan.opportunity.due_date) : null],
                                    ].map(([label, value]) => (
                                        <div key={label as string} className="flex justify-between gap-2 text-sm">
                                            <dt className="text-muted-foreground">{label}</dt>
                                            <dd className="font-semibold capitalize text-foreground">{value ?? '—'}</dd>
                                        </div>
                                    ))}
                                </dl>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Stage History</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-2.5">
                                    {capturePlan.stage_history?.map(h => (
                                        <div key={h.id} className="text-xs">
                                            <p className="font-medium capitalize text-foreground">{h.stage.replace(/_/g, ' ')}</p>
                                            <p className="text-muted-foreground">{formatDate(h.created_at)} · {h.user?.name ?? 'System'}</p>
                                        </div>
                                    )) ?? <p className="text-sm text-muted-foreground">No history.</p>}
                                </div>
                            </CardContent>
                        </Card>

                        <Button href={`/opportunities/${capturePlan.opportunity_id}`} variant="secondary" iconRight={ExternalLink} className="w-full">
                            View Opportunity
                        </Button>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
