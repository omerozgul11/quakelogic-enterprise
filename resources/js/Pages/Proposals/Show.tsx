import { Head, router, useForm } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { formatCurrency, formatDate } from '@/Lib/utils';
import { ProposalSubmission } from '@/Types';
import { ArrowLeft, FileText, Upload, Download, Users, ChevronRight } from 'lucide-react';
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
                <PageHeader
                    icon={FileText}
                    title={proposal.proposal_number}
                    description={proposal.project_name}
                    actions={
                        <>
                            <Button href="/proposals" variant="secondary" icon={ArrowLeft}>
                                Back
                            </Button>
                            {can.transition && allowedTransitions.map(t => (
                                <Button key={t} variant="primary" iconRight={ChevronRight} onClick={() => handleTransition(t)}>
                                    {t.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}
                                </Button>
                            ))}
                        </>
                    }
                />

                <div className="mb-6 flex items-center gap-3">
                    <StatusBadge status={statusValue} />
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="lg:col-span-2 space-y-6">
                        {/* Details */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Proposal Details</CardTitle>
                            </CardHeader>
                            <CardContent>
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
                                        <div key={label as string} className="flex justify-between py-2.5 border-b border-border last:border-0">
                                            <dt className="text-sm text-muted-foreground">{label}</dt>
                                            <dd className="text-sm font-semibold text-foreground">{value ?? '—'}</dd>
                                        </div>
                                    ))}
                                </dl>
                            </CardContent>
                        </Card>

                        {/* Files */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Documents ({proposal.files.length})</CardTitle>
                                {can.upload && (
                                    <Button variant="ghost" size="sm" icon={Upload} onClick={() => setShowUpload(!showUpload)}>
                                        Upload
                                    </Button>
                                )}
                            </CardHeader>
                            <CardContent>
                                {showUpload && (
                                    <form onSubmit={handleUpload} className="mb-4 rounded-xl border border-border bg-secondary/40 p-4 space-y-3">
                                        <input type="file" onChange={e => uploadForm.setData('file', e.target.files?.[0] ?? null)}
                                            className="w-full text-sm text-muted-foreground" required />
                                        <input type="text" placeholder="Document type (optional)" value={uploadForm.data.document_type}
                                            onChange={e => uploadForm.setData('document_type', e.target.value)}
                                            className="input" />
                                        <div className="flex gap-2">
                                            <Button type="submit" size="sm" disabled={uploadForm.processing}>
                                                Upload
                                            </Button>
                                            <Button type="button" variant="secondary" size="sm" onClick={() => setShowUpload(false)}>
                                                Cancel
                                            </Button>
                                        </div>
                                    </form>
                                )}

                                {proposal.files.length === 0 ? (
                                    <EmptyState
                                        icon={FileText}
                                        title="No documents yet"
                                        description="Upload proposal documents to keep everything in one place."
                                    />
                                ) : (
                                    <div className="space-y-2">
                                        {proposal.files.map(file => (
                                            <div key={file.id} className="flex items-center gap-3 rounded-xl border border-border p-3 transition-colors hover:bg-secondary/50">
                                                <FileText className="h-4 w-4 text-muted-foreground shrink-0" />
                                                <div className="flex-1 min-w-0">
                                                    <p className="text-sm font-medium text-foreground truncate">{file.display_name}</p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {file.document_type} · v{file.version} · {file.file_size_formatted} · {formatDate(file.created_at)}
                                                    </p>
                                                </div>
                                                <a href={`/proposals/${proposal.id}/files/${file.id}/download`}
                                                    className="text-muted-foreground transition-colors hover:text-primary">
                                                    <Download className="h-4 w-4" />
                                                </a>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">
                        {/* Team */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-sm">
                                    <Users className="h-4 w-4 text-muted-foreground" /> Team Members
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {proposal.team_members.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">No team members assigned.</p>
                                ) : (
                                    <div className="space-y-2">
                                        {proposal.team_members.map(m => (
                                            <div key={m.id} className="flex items-center gap-2">
                                                <div className="bg-brand-gradient flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold text-white">
                                                    {m.user.name[0]}
                                                </div>
                                                <div>
                                                    <p className="text-sm text-foreground">{m.user.name}</p>
                                                    <p className="text-xs text-muted-foreground">{m.role}</p>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Status History */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-sm">Status History</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-3">
                                    {proposal.status_history.map(h => (
                                        <div key={h.id} className="flex gap-3 text-xs">
                                            <div className="mt-0.5">
                                                <ChevronRight className="h-3 w-3 text-muted-foreground" />
                                            </div>
                                            <div>
                                                <p className="font-medium text-foreground capitalize">{h.status.replace(/_/g, ' ')}</p>
                                                <p className="text-muted-foreground">{formatDate(h.created_at)} · {h.user?.name ?? 'System'}</p>
                                                {h.notes && <p className="mt-0.5 text-muted-foreground">{h.notes}</p>}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
