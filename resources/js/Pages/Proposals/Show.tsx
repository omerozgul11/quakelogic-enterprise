import { Head, Link, router, useForm } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { formatCurrency, formatDate } from '@/Lib/utils';
import { ProposalSubmission } from '@/Types';
import { ArrowLeft, FileText, Upload, Download, Trash2, Users, ChevronRight } from 'lucide-react';
import { useState } from 'react';

interface ProposalFile {
    id: number;
    display_name: string;
    document_type: string | null;
    size: number;
    file_size_formatted: string;
    version: number;
    created_at: string;
    uploaded_by_user: { id: number; name: string } | null;
}

interface Props {
    proposal: ProposalSubmission & {
        files: ProposalFile[];
        team_members: Array<{ id: number; user: { id: number; name: string }; role: string }>;
        status_history: Array<{ id: number; status: string; notes: string | null; created_at: string; user: { name: string } | null }>;
    };
    allowedTransitions: string[];
    can: { edit: boolean; upload: boolean; transition: boolean };
}

export default function ProposalShow({ proposal, allowedTransitions, can }: Props) {
    const [showUpload, setShowUpload] = useState(false);
    const uploadForm = useForm({ file: null as File | null, document_type: '', notes: '' });

    const handleTransition = (status: string) => {
        if (confirm(`Transition proposal to "${status.replace(/_/g, ' ')}"?`)) {
            router.post(`/proposals/${proposal.id}/transition`, { status });
        }
    };

    const handleUpload = (e: React.FormEvent) => {
        e.preventDefault();
        uploadForm.post(`/proposals/${proposal.id}/files`, {
            onSuccess: () => { setShowUpload(false); uploadForm.reset(); },
        });
    };

    const statusValue = typeof proposal.status === 'string' ? proposal.status : (proposal.status as any)?.value ?? 'draft';

    return (
        <AppLayout>
            <Head title={proposal.proposal_number} />
            <div className="p-6 max-w-6xl mx-auto">
                <div className="flex items-center gap-4 mb-6">
                    <Link href="/proposals" className="text-gray-400 hover:text-gray-600">
                        <ArrowLeft className="h-5 w-5" />
                    </Link>
                    <div className="flex-1">
                        <div className="flex items-center gap-3">
                            <h1 className="text-xl font-bold text-gray-900 font-mono">{proposal.proposal_number}</h1>
                            <StatusBadge status={statusValue} />
                        </div>
                        <p className="text-sm text-gray-500 mt-0.5">{proposal.project_name}</p>
                    </div>
                    {can.transition && allowedTransitions.length > 0 && (
                        <div className="flex gap-2">
                            {allowedTransitions.map(t => (
                                <button key={t} onClick={() => handleTransition(t)}
                                    className="px-3 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                    → {t.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}
                                </button>
                            ))}
                        </div>
                    )}
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="lg:col-span-2 space-y-6">
                        {/* Details */}
                        <div className="bg-white rounded-xl border border-gray-200 p-6">
                            <h2 className="text-base font-semibold text-gray-900 mb-4">Proposal Details</h2>
                            <dl className="space-y-0">
                                {[
                                    ['Agency', proposal.agency_name],
                                    ['Solicitation #', proposal.solicitation_number],
                                    ['Proposal Value', formatCurrency(proposal.proposal_value)],
                                    ['Award Value', proposal.award_value ? formatCurrency(proposal.award_value) : null],
                                    ['Due Date', proposal.due_date ? formatDate(proposal.due_date) : null],
                                    ['Submission Date', proposal.submission_date ? formatDate(proposal.submission_date) : null],
                                    ['Win Probability', proposal.win_probability ? `${proposal.win_probability}%` : null],
                                    ['Owner', proposal.owner?.name],
                                ].map(([label, value]) => (
                                    <div key={label as string} className="flex justify-between py-2.5 border-b border-gray-100 last:border-0">
                                        <dt className="text-sm text-gray-500">{label}</dt>
                                        <dd className="text-sm font-medium text-gray-900">{value ?? '—'}</dd>
                                    </div>
                                ))}
                            </dl>
                        </div>

                        {/* Files */}
                        <div className="bg-white rounded-xl border border-gray-200 p-6">
                            <div className="flex items-center justify-between mb-4">
                                <h2 className="text-base font-semibold text-gray-900">Documents ({proposal.files.length})</h2>
                                {can.upload && (
                                    <button onClick={() => setShowUpload(!showUpload)}
                                        className="flex items-center gap-1 text-sm text-blue-600 hover:underline">
                                        <Upload className="h-4 w-4" /> Upload
                                    </button>
                                )}
                            </div>

                            {showUpload && (
                                <form onSubmit={handleUpload} className="mb-4 p-4 bg-gray-50 rounded-lg space-y-3">
                                    <input type="file" onChange={e => uploadForm.setData('file', e.target.files?.[0] ?? null)}
                                        className="w-full text-sm" required />
                                    <input type="text" placeholder="Document type (optional)" value={uploadForm.data.document_type}
                                        onChange={e => uploadForm.setData('document_type', e.target.value)}
                                        className="w-full border border-gray-200 rounded px-3 py-1.5 text-sm" />
                                    <div className="flex gap-2">
                                        <button type="submit" disabled={uploadForm.processing}
                                            className="px-3 py-1.5 text-sm bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50">
                                            Upload
                                        </button>
                                        <button type="button" onClick={() => setShowUpload(false)}
                                            className="px-3 py-1.5 text-sm border border-gray-200 rounded hover:bg-gray-50">
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            )}

                            {proposal.files.length === 0 ? (
                                <p className="text-sm text-gray-500 text-center py-6">No documents uploaded yet.</p>
                            ) : (
                                <div className="space-y-2">
                                    {proposal.files.map(file => (
                                        <div key={file.id} className="flex items-center gap-3 p-3 border border-gray-100 rounded-lg hover:bg-gray-50">
                                            <FileText className="h-4 w-4 text-gray-400 shrink-0" />
                                            <div className="flex-1 min-w-0">
                                                <p className="text-sm font-medium text-gray-900 truncate">{file.display_name}</p>
                                                <p className="text-xs text-gray-500">
                                                    {file.document_type} · v{file.version} · {file.file_size_formatted} · {formatDate(file.created_at)}
                                                </p>
                                            </div>
                                            <a href={`/proposals/${proposal.id}/files/${file.id}/download`}
                                                className="text-gray-400 hover:text-blue-600">
                                                <Download className="h-4 w-4" />
                                            </a>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-4">
                        {/* Team */}
                        <div className="bg-white rounded-xl border border-gray-200 p-5">
                            <h3 className="text-sm font-semibold text-gray-900 mb-3 flex items-center gap-2">
                                <Users className="h-4 w-4" /> Team Members
                            </h3>
                            {proposal.team_members.length === 0 ? (
                                <p className="text-sm text-gray-500">No team members assigned.</p>
                            ) : (
                                <div className="space-y-2">
                                    {proposal.team_members.map(m => (
                                        <div key={m.id} className="flex items-center gap-2">
                                            <div className="h-7 w-7 rounded-full bg-blue-100 text-blue-700 text-xs font-bold flex items-center justify-center">
                                                {m.user.name[0]}
                                            </div>
                                            <div>
                                                <p className="text-sm text-gray-900">{m.user.name}</p>
                                                <p className="text-xs text-gray-500">{m.role}</p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        {/* Status History */}
                        <div className="bg-white rounded-xl border border-gray-200 p-5">
                            <h3 className="text-sm font-semibold text-gray-900 mb-3">Status History</h3>
                            <div className="space-y-3">
                                {proposal.status_history.map(h => (
                                    <div key={h.id} className="flex gap-3 text-xs">
                                        <div className="mt-0.5">
                                            <ChevronRight className="h-3 w-3 text-gray-400" />
                                        </div>
                                        <div>
                                            <p className="font-medium text-gray-900 capitalize">{h.status.replace(/_/g, ' ')}</p>
                                            <p className="text-gray-500">{formatDate(h.created_at)} · {h.user?.name ?? 'System'}</p>
                                            {h.notes && <p className="text-gray-600 mt-0.5">{h.notes}</p>}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
