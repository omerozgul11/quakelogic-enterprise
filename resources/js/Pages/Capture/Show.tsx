import { Head, Link, router } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { formatDate } from '@/Lib/utils';
import { CapturePlan } from '@/Types';
import { ArrowLeft, AlertTriangle, CheckSquare } from 'lucide-react';

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
            <div className="p-6 max-w-6xl mx-auto">
                <div className="flex items-center gap-4 mb-6">
                    <Link href="/capture" className="text-gray-400 hover:text-gray-600">
                        <ArrowLeft className="h-5 w-5" />
                    </Link>
                    <div className="flex-1">
                        <h1 className="text-xl font-bold text-gray-900 leading-tight">{capturePlan.opportunity?.title}</h1>
                        <p className="text-sm text-gray-500">{capturePlan.opportunity?.agency_name}</p>
                    </div>
                    {can.transition && allowedTransitions.length > 0 && (
                        <div className="flex gap-2">
                            {allowedTransitions.map(t => (
                                <button key={t} onClick={() => handleTransition(t)}
                                    className="px-3 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 capitalize">
                                    → {t.replace(/_/g, ' ')}
                                </button>
                            ))}
                        </div>
                    )}
                </div>

                {/* Stage Progress Bar */}
                <div className="bg-white rounded-xl border border-gray-200 p-5 mb-6">
                    <div className="flex items-center justify-between mb-3">
                        <h2 className="text-sm font-semibold text-gray-900">Capture Progress</h2>
                        <StatusBadge status={stage} />
                    </div>
                    <div className="flex gap-1">
                        {STAGE_ORDER.map((s, i) => (
                            <div key={s} className={`flex-1 h-2 rounded-full transition-colors ${
                                i < currentStageIndex ? 'bg-green-500' :
                                i === currentStageIndex ? 'bg-blue-600' : 'bg-gray-200'
                            }`} title={s.replace(/_/g, ' ')} />
                        ))}
                    </div>
                    <div className="flex justify-between mt-1">
                        <span className="text-xs text-gray-400">Discovery</span>
                        <span className="text-xs text-gray-400">Execution</span>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="lg:col-span-2 space-y-6">
                        {/* Risks */}
                        <div className="bg-white rounded-xl border border-gray-200 p-6">
                            <h2 className="text-base font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <AlertTriangle className="h-4 w-4 text-amber-500" /> Risks ({capturePlan.risks.length})
                            </h2>
                            {capturePlan.risks.length === 0 ? (
                                <p className="text-sm text-gray-500 text-center py-4">No risks identified.</p>
                            ) : (
                                <div className="space-y-3">
                                    {capturePlan.risks.map(risk => (
                                        <div key={risk.id} className="p-3 border border-gray-100 rounded-lg">
                                            <div className="flex items-start justify-between gap-2">
                                                <p className="text-sm font-medium text-gray-900">{risk.title}</p>
                                                <StatusBadge status={risk.status} />
                                            </div>
                                            <div className="flex gap-4 mt-2 text-xs text-gray-500">
                                                <span>Impact: <span className="font-medium capitalize">{risk.impact}</span></span>
                                                <span>Prob: <span className="font-medium capitalize">{risk.probability}</span></span>
                                            </div>
                                            {risk.mitigation && <p className="text-xs text-gray-600 mt-2">Mitigation: {risk.mitigation}</p>}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        {/* Tasks */}
                        <div className="bg-white rounded-xl border border-gray-200 p-6">
                            <h2 className="text-base font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <CheckSquare className="h-4 w-4 text-blue-500" /> Tasks ({capturePlan.tasks.length})
                            </h2>
                            {capturePlan.tasks.length === 0 ? (
                                <p className="text-sm text-gray-500 text-center py-4">No tasks yet.</p>
                            ) : (
                                <div className="space-y-2">
                                    {capturePlan.tasks.map(task => (
                                        <div key={task.id} className="flex items-center gap-3 p-3 border border-gray-100 rounded-lg">
                                            <div className={`h-2 w-2 rounded-full shrink-0 ${task.status === 'completed' ? 'bg-green-500' : task.status === 'in_progress' ? 'bg-blue-500' : 'bg-gray-300'}`} />
                                            <div className="flex-1">
                                                <p className={`text-sm ${task.status === 'completed' ? 'line-through text-gray-400' : 'text-gray-900'}`}>
                                                    {task.title}
                                                </p>
                                                <p className="text-xs text-gray-500">
                                                    {task.assignee?.name ?? 'Unassigned'}
                                                    {task.due_date ? ` · Due ${formatDate(task.due_date)}` : ''}
                                                </p>
                                            </div>
                                            <StatusBadge status={task.status} />
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-4">
                        <div className="bg-white rounded-xl border border-gray-200 p-5">
                            <h3 className="text-sm font-semibold text-gray-900 mb-3">Capture Details</h3>
                            <dl className="space-y-2">
                                {[
                                    ['Owner', capturePlan.owner?.name],
                                    ['Win Probability', capturePlan.win_probability ? `${capturePlan.win_probability}%` : null],
                                    ['Go/No-Go Decision', capturePlan.go_no_go_decision],
                                    ['RFP Release Date', capturePlan.rfp_release_date ? formatDate(capturePlan.rfp_release_date) : null],
                                    ['Proposal Due', capturePlan.opportunity?.due_date ? formatDate(capturePlan.opportunity.due_date) : null],
                                ].map(([label, value]) => (
                                    <div key={label as string} className="flex justify-between text-sm">
                                        <dt className="text-gray-500">{label}</dt>
                                        <dd className="font-medium text-gray-900 capitalize">{value ?? '—'}</dd>
                                    </div>
                                ))}
                            </dl>
                        </div>

                        <div className="bg-white rounded-xl border border-gray-200 p-5">
                            <h3 className="text-sm font-semibold text-gray-900 mb-3">Stage History</h3>
                            <div className="space-y-2">
                                {capturePlan.stage_history?.map(h => (
                                    <div key={h.id} className="text-xs">
                                        <p className="font-medium text-gray-700 capitalize">{h.stage.replace(/_/g, ' ')}</p>
                                        <p className="text-gray-400">{formatDate(h.created_at)} · {h.user?.name ?? 'System'}</p>
                                    </div>
                                )) ?? <p className="text-sm text-gray-400">No history.</p>}
                            </div>
                        </div>

                        <Link href={`/opportunities/${capturePlan.opportunity_id}`}
                            className="block w-full text-center text-sm text-blue-600 border border-blue-200 rounded-lg px-4 py-2 hover:bg-blue-50">
                            View Opportunity →
                        </Link>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
