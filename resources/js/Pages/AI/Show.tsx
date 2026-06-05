import { Head, Link } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { AiAnalysis } from '@/Types';
import { ArrowLeft, Brain, CheckCircle } from 'lucide-react';
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
            <div className="p-6 max-w-4xl mx-auto">
                <div className="flex items-center gap-4 mb-6">
                    <Link href="/ai" className="text-gray-400 hover:text-gray-600"><ArrowLeft className="h-5 w-5" /></Link>
                    <div className="flex-1">
                        <h1 className="text-xl font-bold text-gray-900 flex items-center gap-2">
                            <Brain className="h-5 w-5 text-purple-500" />
                            AI Analysis
                            <span className="text-sm bg-purple-100 text-purple-700 px-2 py-1 rounded-full capitalize font-normal">
                                {(analysis.analysis_type ?? '').replace(/_/g, ' ')}
                            </span>
                        </h1>
                        <p className="text-sm text-gray-500 mt-0.5">
                            Subject: {analysis.subject_type} #{analysis.subject_id} · {formatDateTime(analysis.created_at)}
                        </p>
                    </div>
                </div>

                {statusValue === 'completed' && output && (
                    <div className="bg-white rounded-xl border border-gray-200 p-6 mb-6">
                        <h2 className="text-base font-semibold text-gray-900 mb-4">Analysis Output</h2>
                        {typeof output === 'object' ? (
                            <div className="space-y-3">
                                {Object.entries(output as Record<string, unknown>).map(([key, value]) => (
                                    <div key={key} className="border-b border-gray-100 pb-3 last:border-0">
                                        <dt className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">
                                            {key.replace(/_/g, ' ')}
                                        </dt>
                                        <dd className="text-sm text-gray-900">
                                            {typeof value === 'object' ? (
                                                <pre className="text-xs bg-gray-50 rounded p-3 overflow-auto">{JSON.stringify(value, null, 2)}</pre>
                                            ) : String(value)}
                                        </dd>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-sm text-gray-700 whitespace-pre-wrap">{String(output)}</p>
                        )}
                    </div>
                )}

                {statusValue !== 'completed' && (
                    <div className="bg-white rounded-xl border border-gray-200 p-6 text-center">
                        <p className="text-gray-500">Analysis status: <span className="font-medium capitalize">{statusValue}</span></p>
                    </div>
                )}

                {/* Metadata */}
                <div className="bg-white rounded-xl border border-gray-200 p-5">
                    <h3 className="text-sm font-semibold text-gray-900 mb-3">Metadata</h3>
                    <dl className="grid grid-cols-2 gap-3 text-sm">
                        <div><dt className="text-gray-500">Created by</dt><dd className="font-medium">{analysis.created_by_user?.name ?? '—'}</dd></div>
                        <div><dt className="text-gray-500">Status</dt><dd className="font-medium capitalize">{statusValue}</dd></div>
                        {analysis.human_decision && (
                            <div className="col-span-2">
                                <dt className="text-gray-500">Human Decision</dt>
                                <dd className="font-medium flex items-center gap-1 mt-0.5">
                                    <CheckCircle className="h-4 w-4 text-green-500" />
                                    {analysis.human_decision}
                                    {analysis.reviewed_by_user && <span className="text-gray-500">· by {analysis.reviewed_by_user.name}</span>}
                                </dd>
                            </div>
                        )}
                    </dl>
                </div>
            </div>
        </AppLayout>
    );
}
