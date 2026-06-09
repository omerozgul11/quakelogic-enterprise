import { Head, Link } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card, CardHeader, CardTitle } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { AiAnalysis } from '@/Types';
import { Sparkles, ExternalLink, Clock, CheckCircle, AlertCircle, Target, BarChart3, FileText, ArrowRight } from 'lucide-react';
import { formatDateTime } from '@/Lib/utils';

type AnalysisRow = AiAnalysis & {
    created_by?: { id: number; name: string } | null;
    created_by_user?: { id: number; name: string } | null;
    subject_type?: string | null;
    subject_id?: number | string | null;
};

interface Props {
    recentAnalyses?: AnalysisRow[];
    aiProvider?: string;
    aiAvailable?: boolean;
    analysisTypes?: Array<{ value: string; label: string }>;
}

const STATUS_ICONS: Record<string, React.ComponentType<{ className?: string }>> = {
    pending: Clock,
    processing: Clock,
    completed: CheckCircle,
    failed: AlertCircle,
    cancelled: AlertCircle,
};

const STATUS_COLORS: Record<string, string> = {
    pending: 'text-amber-500',
    processing: 'text-sky-500',
    completed: 'text-emerald-500',
    failed: 'text-destructive',
    cancelled: 'text-muted-foreground',
};

const QUICK_ACTIONS = [
    { label: 'Go/No-Go Analysis', desc: 'AI-powered bid decision recommendation', type: 'go_no_go', icon: Target },
    { label: 'Win Probability', desc: 'Estimate win likelihood based on past data', type: 'win_probability', icon: BarChart3 },
    { label: 'Proposal Summary', desc: 'Generate an executive summary from an RFP', type: 'proposal_summary', icon: FileText },
];

export default function AiIndex({ recentAnalyses = [], aiProvider = 'unknown', aiAvailable = true }: Props) {
    const isDemo = aiProvider === 'fake' || aiProvider === 'unknown';

    return (
        <AppLayout>
            <Head title="Ask QuakeAI" />
            <div className="p-6">
                <PageHeader
                    icon={Sparkles}
                    eyebrow={isDemo ? 'Demo mode' : undefined}
                    title="Ask QuakeAI"
                    description={`Provider: ${aiProvider}${aiAvailable ? '' : ' · unavailable'}`}
                />

                {/* Quick Actions */}
                <div className="stagger mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                    {QUICK_ACTIONS.map(action => {
                        const Icon = action.icon;
                        return (
                            <Link key={action.type} href="/opportunities" className="block">
                                <Card hover className="animate-rise h-full p-5">
                                    <div className="bg-brand-gradient shadow-glow mb-3 flex h-10 w-10 items-center justify-center rounded-xl">
                                        <Icon className="h-5 w-5 text-white" />
                                    </div>
                                    <h3 className="text-sm font-semibold text-foreground">{action.label}</h3>
                                    <p className="mt-1 text-xs text-muted-foreground">{action.desc}</p>
                                    <span className="mt-3 inline-flex items-center gap-1 text-xs font-medium text-primary">
                                        Select an opportunity <ArrowRight className="h-3.5 w-3.5" />
                                    </span>
                                </Card>
                            </Link>
                        );
                    })}
                </div>

                {/* Recent Analyses */}
                <Card className="overflow-hidden">
                    <CardHeader>
                        <CardTitle>Recent Analyses</CardTitle>
                    </CardHeader>
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b border-border bg-secondary/40">
                                <tr>
                                    <th className="th">Type</th>
                                    <th className="th">Subject</th>
                                    <th className="th">Status</th>
                                    <th className="th">By</th>
                                    <th className="th">Date</th>
                                    <th className="th" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {recentAnalyses.length === 0 ? (
                                    <tr>
                                        <td colSpan={6}>
                                            <EmptyState
                                                icon={Sparkles}
                                                title="No AI analyses yet"
                                                description="Select an opportunity to run a Go/No-Go, win probability, or summary analysis."
                                            />
                                        </td>
                                    </tr>
                                ) : recentAnalyses.map(a => {
                                    const statusKey = typeof a.status === 'string' ? a.status : (a.status as { value?: string } | null)?.value ?? 'pending';
                                    const StatusIcon = STATUS_ICONS[statusKey] ?? Clock;
                                    const who = a.created_by_user?.name ?? a.created_by?.name ?? '—';
                                    const subject = a.subject_type
                                        ? `${String(a.subject_type).split('\\').pop()}${a.subject_id ? ` #${a.subject_id}` : ''}`
                                        : '—';
                                    return (
                                        <tr key={a.id} className="row-link">
                                            <td className="td">
                                                <span className="chip capitalize">{String(a.analysis_type ?? '').replace(/_/g, ' ')}</span>
                                            </td>
                                            <td className="td max-w-xs truncate text-muted-foreground">{subject}</td>
                                            <td className="td">
                                                <div className={`flex items-center gap-1.5 ${STATUS_COLORS[statusKey] ?? 'text-muted-foreground'}`}>
                                                    <StatusIcon className="h-4 w-4" />
                                                    <span className="text-xs font-medium capitalize">{statusKey}</span>
                                                </div>
                                            </td>
                                            <td className="td text-muted-foreground">{who}</td>
                                            <td className="td text-muted-foreground">{formatDateTime(a.created_at)}</td>
                                            <td className="td">
                                                <Link href={`/ai/${a.id}`} className="text-muted-foreground transition-colors hover:text-primary">
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
