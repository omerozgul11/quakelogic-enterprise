import { Head } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { AiAnalysis } from '@/Types';
import { ArrowLeft, Sparkles, CheckCircle } from 'lucide-react';
import { formatDateTime } from '@/Lib/utils';

interface Props {
    analysis: AiAnalysis & {
        created_by_user: { id: number; name: string } | null;
        reviewed_by_user: { id: number; name: string } | null;
    };
}

export default function AiShow({ analysis }: Props) {
    const statusValue = typeof analysis.status === 'string' ? analysis.status : (analysis.status as any)?.value ?? 'pending';
    const output = analysis.human_modified_output ?? analysis.ai_output;

    return (
        <AppLayout>
            <Head title="AI Analysis" />
            <div className="mx-auto max-w-4xl p-6">
                <PageHeader
                    icon={Sparkles}
                    eyebrow={(analysis.analysis_type ?? '').replace(/_/g, ' ')}
                    title="AI Analysis"
                    description={`${analysis.subject_type} #${analysis.subject_id} · ${formatDateTime(analysis.created_at)}`}
                    actions={
                        <Button href="/ai" variant="secondary" icon={ArrowLeft}>
                            Back
                        </Button>
                    }
                />

                {statusValue === 'completed' && output && (
                    <Card className="mb-6">
                        <CardHeader>
                            <CardTitle>Analysis Output</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {typeof output === 'object' ? (
                                <div className="divide-y divide-border">
                                    {Object.entries(output as Record<string, unknown>).map(([key, value]) => (
                                        <div key={key} className="py-3 first:pt-0 last:pb-0">
                                            <dt className="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                                {key.replace(/_/g, ' ')}
                                            </dt>
                                            <dd className="text-sm text-foreground">
                                                {typeof value === 'object' ? (
                                                    <pre className="overflow-auto rounded-lg bg-secondary/60 p-3 text-xs">{JSON.stringify(value, null, 2)}</pre>
                                                ) : String(value)}
                                            </dd>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="whitespace-pre-wrap text-sm text-foreground">{String(output)}</p>
                            )}
                        </CardContent>
                    </Card>
                )}

                {statusValue !== 'completed' && (
                    <Card className="mb-6 p-6 text-center">
                        <p className="text-sm text-muted-foreground">
                            Analysis status: <StatusBadge status={statusValue} />
                        </p>
                    </Card>
                )}

                {/* Metadata */}
                <Card>
                    <CardHeader>
                        <CardTitle>Metadata</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl className="space-y-3 text-sm">
                            <div className="flex items-center justify-between">
                                <dt className="text-muted-foreground">Created by</dt>
                                <dd className="font-semibold text-foreground">{analysis.created_by_user?.name ?? '—'}</dd>
                            </div>
                            <div className="flex items-center justify-between">
                                <dt className="text-muted-foreground">Status</dt>
                                <dd><StatusBadge status={statusValue} /></dd>
                            </div>
                            {analysis.human_decision && (
                                <div className="flex items-center justify-between">
                                    <dt className="text-muted-foreground">Human Decision</dt>
                                    <dd className="flex items-center gap-1.5 font-semibold text-foreground">
                                        <CheckCircle className="h-4 w-4 text-emerald-500" />
                                        {analysis.human_decision}
                                        {analysis.reviewed_by_user && (
                                            <span className="font-normal text-muted-foreground">· by {analysis.reviewed_by_user.name}</span>
                                        )}
                                    </dd>
                                </div>
                            )}
                        </dl>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
