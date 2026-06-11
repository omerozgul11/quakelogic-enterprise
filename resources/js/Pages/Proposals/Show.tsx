import { Head, router, useForm, usePage } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Select } from '@/Components/ui/Select';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { formatCurrency, formatDate } from '@/Lib/utils';
import { ProposalSubmission, SharedProps } from '@/Types';
import { ArrowLeft, FileText, Upload, Download, Users, ChevronRight, ChevronLeft, Sparkles, Building2, MessageSquare, Pencil, Eye, Trash2 } from 'lucide-react';
import { FilePreviewModal, PreviewFile } from '@/Components/ui/FilePreviewModal';
import { SubmitCelebration } from '@/Components/ui/SubmitCelebration';
import { useState, useEffect } from 'react';

interface ProposalFile {
    id: number;
    display_name: string;
    document_type: string | null;
    mime_type: string;
    size: number;
    version: number;
    created_at: string;
}

interface FollowUp {
    id: number;
    subject: string;
    status: string;
    type: string;
    scheduled_date: string | null;
    contact: { first_name: string; last_name: string; email: string | null; title: string | null } | null;
}

interface Extraction {
    output: Record<string, unknown>;
    provider: string;
    confidence: number | null;
    created_at: string | null;
}

interface Step {
    value: string;
    label: string;
    color: string;
}

interface Props {
    proposal: ProposalSubmission & {
        agency?: { id: number; name: string; email?: string | null } | null;
        company?: { id: number; name: string } | null;
        scope_summary?: string | null;
        notes?: string | null;
        files: ProposalFile[];
        follow_ups?: FollowUp[];
        team_members?: Array<{ id: number; user: { id: number; name: string } | null; role: string }>;
        status_history?: Array<{ id: number; to_status: string | null; from_status: string | null; notes: string | null; changed_at: string; changed_by: { name: string } | null }>;
    };
    stepNav: { previous: Step | null; next: Step | null };
    allowedTransitions: Step[];
    currencies: Array<{ value: string; label: string; symbol: string; name: string }>;
    extraction: Extraction | null;
    can: { edit: boolean; upload: boolean; transition: boolean; delete: boolean };
}

function formatSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1048576).toFixed(1)} MB`;
}

export default function ProposalShow({ proposal, stepNav, allowedTransitions, currencies, extraction, can }: Props) {
    const [showUpload, setShowUpload] = useState(false);
    const [preview, setPreview] = useState<PreviewFile | null>(null);
    const [editing, setEditing] = useState(false);
    const uploadForm = useForm({ file: null as File | null, document_type: '' });

    const detailsForm = useForm({
        project_name: proposal.project_name ?? '',
        company: proposal.company?.name ?? '',
        solicitation_number: proposal.solicitation_number ?? '',
        proposal_value: proposal.proposal_value != null ? String(proposal.proposal_value) : '',
        award_value: proposal.award_value != null ? String(proposal.award_value) : '',
        currency: (proposal.currency ?? 'USD'),
        due_date: (proposal.due_date ?? '').slice(0, 10),
        submission_date: (proposal.submission_date ?? '').slice(0, 10),
        award_date: (proposal.award_date ?? '').slice(0, 10),
        scope_summary: proposal.scope_summary ?? '',
    });

    const saveDetails = (e: React.FormEvent) => {
        e.preventDefault();
        detailsForm.put(`/proposals/${proposal.id}`, { preserveScroll: true, onSuccess: () => setEditing(false) });
    };

    const currencySymbol = currencies.find(c => c.value === detailsForm.data.currency)?.symbol ?? '$';

    // Confetti celebration when this proposal was just submitted.
    const flashCelebrate = usePage<SharedProps>().props.flash?.celebrate;
    const [celebrate, setCelebrate] = useState<string | null>(null);
    useEffect(() => {
        if (flashCelebrate) setCelebrate(flashCelebrate);
    }, [flashCelebrate]);

    const rawTeam = proposal.team_members ?? [];
    // Guarantee the owner is always shown on the team, even for proposals
    // created before owners were auto-attached.
    const ownerOnTeam = !proposal.owner || rawTeam.some(m => m.user?.id === proposal.owner?.id);
    const teamMembers = proposal.owner && !ownerOnTeam
        ? [{ id: -1, user: { id: proposal.owner.id, name: proposal.owner.name }, role: 'owner' }, ...rawTeam]
        : rawTeam;
    const statusHistory = proposal.status_history ?? [];
    const followUps = proposal.follow_ups ?? [];

    const handleTransition = (status: string) => {
        if (confirm(`Transition proposal to "${status.replace(/_/g, ' ')}"?`)) {
            router.post(`/proposals/${proposal.id}/transition`, { status });
        }
    };

    const handleDelete = () => {
        if (confirm(`Delete proposal ${proposal.proposal_number}? This cannot be undone.`)) {
            router.delete(`/proposals/${proposal.id}`);
        }
    };

    const handleUpload = (e: React.FormEvent) => {
        e.preventDefault();
        uploadForm.post(`/proposals/${proposal.id}/files`, {
            forceFormData: true,
            onSuccess: () => { setShowUpload(false); uploadForm.reset(); },
        });
    };

    const statusValue = typeof proposal.status === 'string' ? proposal.status : (proposal.status as any)?.value ?? 'draft';

    return (
        <AppLayout>
            <Head title={proposal.proposal_number} />
            <div className="mx-auto max-w-6xl p-6">
                <PageHeader
                    icon={FileText}
                    title={proposal.proposal_number}
                    description={proposal.project_name}
                    actions={
                        <>
                            <Button href="/proposals" variant="secondary" icon={ArrowLeft}>Back</Button>
                            {can.edit && <Button href={`/proposals/${proposal.id}/edit`} variant="secondary" icon={Pencil}>Edit</Button>}
                            {can.delete && <Button variant="danger" icon={Trash2} onClick={handleDelete}>Delete</Button>}
                            {can.transition && stepNav.previous && (
                                <Button variant="secondary" icon={ChevronLeft} onClick={() => handleTransition(stepNav.previous!.value)}>
                                    {stepNav.previous.label}
                                </Button>
                            )}
                            {can.transition && stepNav.next && (
                                <Button variant="primary" iconRight={ChevronRight} onClick={() => handleTransition(stepNav.next!.value)}>
                                    {stepNav.next.label}
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="mb-6 flex flex-wrap items-center gap-3">
                    <StatusBadge status={statusValue} />
                    {can.transition && allowedTransitions.length > 0 && (
                        <Select
                            value=""
                            onChange={v => v && handleTransition(v)}
                            placeholder="Change status…"
                            options={allowedTransitions.map(t => ({ value: t.value, label: t.label }))}
                            className="w-48"
                            size="sm"
                        />
                    )}
                    {extraction && (
                        <span className="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary">
                            <Sparkles className="h-3 w-3" /> Auto-extracted
                        </span>
                    )}
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        {/* Details */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Proposal Details</CardTitle>
                                {can.edit && !editing && (
                                    <Button variant="ghost" size="sm" icon={Pencil} onClick={() => setEditing(true)}>Edit</Button>
                                )}
                            </CardHeader>
                            <CardContent>
                                {editing ? (
                                    <form onSubmit={saveDetails} className="space-y-4">
                                        <div>
                                            <label className="label">Project Name *</label>
                                            <input type="text" value={detailsForm.data.project_name} onChange={e => detailsForm.setData('project_name', e.target.value)} className="input" required />
                                            {detailsForm.errors.project_name && <p className="mt-1 text-xs text-destructive">{detailsForm.errors.project_name}</p>}
                                        </div>
                                        <div className="grid grid-cols-2 gap-4">
                                            <div>
                                                <label className="label">Company</label>
                                                <input type="text" value={detailsForm.data.company} onChange={e => detailsForm.setData('company', e.target.value)} className="input" placeholder="Client / buyer organization" />
                                            </div>
                                            <div>
                                                <label className="label">Solicitation #</label>
                                                <input type="text" value={detailsForm.data.solicitation_number} onChange={e => detailsForm.setData('solicitation_number', e.target.value)} className="input" />
                                            </div>
                                        </div>
                                        <div className="grid grid-cols-2 gap-4">
                                            <div>
                                                <label className="label">Proposal Value ({currencySymbol})</label>
                                                <div className="flex gap-2">
                                                    <Select value={detailsForm.data.currency} onChange={v => detailsForm.setData('currency', v)} options={currencies.map(c => ({ value: c.value, label: c.value }))} className="w-24 shrink-0" />
                                                    <input type="number" value={detailsForm.data.proposal_value} onChange={e => detailsForm.setData('proposal_value', e.target.value)} className="input flex-1" min="0" step="0.01" />
                                                </div>
                                            </div>
                                            <div>
                                                <label className="label">Award Value ({currencySymbol})</label>
                                                <input type="number" value={detailsForm.data.award_value} onChange={e => detailsForm.setData('award_value', e.target.value)} className="input" min="0" step="0.01" />
                                            </div>
                                        </div>
                                        <div className="grid grid-cols-3 gap-4">
                                            <div>
                                                <label className="label">Due Date</label>
                                                <input type="date" value={detailsForm.data.due_date} onChange={e => detailsForm.setData('due_date', e.target.value)} className="input" />
                                            </div>
                                            <div>
                                                <label className="label">Submission Date</label>
                                                <input type="date" value={detailsForm.data.submission_date} onChange={e => detailsForm.setData('submission_date', e.target.value)} className="input" />
                                            </div>
                                            <div>
                                                <label className="label">Award Date</label>
                                                <input type="date" value={detailsForm.data.award_date} onChange={e => detailsForm.setData('award_date', e.target.value)} className="input" />
                                            </div>
                                        </div>
                                        <div>
                                            <label className="label">Scope Summary</label>
                                            <textarea value={detailsForm.data.scope_summary} onChange={e => detailsForm.setData('scope_summary', e.target.value)} rows={3} className="input" />
                                        </div>
                                        <div className="flex justify-end gap-2 pt-1">
                                            <Button type="button" variant="secondary" onClick={() => { setEditing(false); detailsForm.reset(); }}>Cancel</Button>
                                            <Button type="submit" disabled={detailsForm.processing}>{detailsForm.processing ? 'Saving…' : 'Save changes'}</Button>
                                        </div>
                                    </form>
                                ) : (
                                    <>
                                        <dl className="space-y-0">
                                            {([
                                                ['Company', proposal.company?.name],
                                                ['Solicitation #', proposal.solicitation_number],
                                                ['Proposal Value', proposal.proposal_value ? formatCurrency(proposal.proposal_value, proposal.currency) : null],
                                                ['Award Value', proposal.award_value ? formatCurrency(proposal.award_value, proposal.currency) : null],
                                                ['Due Date', proposal.due_date ? formatDate(proposal.due_date) : null],
                                                ['Submission Date', proposal.submission_date ? formatDate(proposal.submission_date) : null],
                                                ['Submission Method', ((proposal as { submission_methods?: string[] }).submission_methods ?? []).length
                                                    ? ((proposal as { submission_methods?: string[] }).submission_methods ?? [])
                                                        .map(m => ({ email: 'Email', portal: 'Portal', mail: 'Mail', fax: 'Fax', hand_delivery: 'Hand delivery' }[m] ?? m)).join(', ')
                                                    : null],
                                                ['Owner', proposal.owner?.name],
                                            ] as Array<[string, string | null | undefined]>).map(([label, value]) => (
                                                <div key={label} className="flex justify-between border-b border-border py-2.5 last:border-0">
                                                    <dt className="text-sm text-muted-foreground">{label}</dt>
                                                    <dd className="text-sm font-semibold text-foreground">{value ?? '—'}</dd>
                                                </div>
                                            ))}
                                        </dl>
                                        {proposal.scope_summary && (
                                            <div className="mt-4 border-t border-border pt-4">
                                                <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Scope Summary</p>
                                                <p className="text-sm leading-relaxed text-foreground">{proposal.scope_summary}</p>
                                            </div>
                                        )}
                                    </>
                                )}
                            </CardContent>
                        </Card>

                        {/* Notes — auto-generated by QuakeAI from the document (key dates, requests, specs) */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    {extraction && <Sparkles className="h-4 w-4 text-primary" />} Notes
                                </CardTitle>
                                {can.edit && <Button href={`/proposals/${proposal.id}/edit`} variant="ghost" size="sm" icon={Pencil}>Edit</Button>}
                            </CardHeader>
                            <CardContent>
                                {proposal.notes ? (
                                    <p className="whitespace-pre-line text-sm leading-relaxed text-foreground">{proposal.notes}</p>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        No notes yet. Upload a document and QuakeAI will generate notes (key dates, requests &amp; specs) automatically, or add them via Edit.
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        {/* Files */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Documents ({proposal.files.length})</CardTitle>
                                {can.upload && (
                                    <Button variant="ghost" size="sm" icon={Upload} onClick={() => setShowUpload(!showUpload)}>Upload</Button>
                                )}
                            </CardHeader>
                            <CardContent>
                                {showUpload && (
                                    <form onSubmit={handleUpload} className="mb-4 space-y-3 rounded-xl border border-border bg-secondary/40 p-4">
                                        <input type="file" accept=".pdf,.jpg,.jpeg,.png"
                                            onChange={e => uploadForm.setData('file', e.target.files?.[0] ?? null)}
                                            className="w-full text-sm text-muted-foreground" required />
                                        <input type="text" placeholder="Document type (optional)" value={uploadForm.data.document_type}
                                            onChange={e => uploadForm.setData('document_type', e.target.value)} className="input" />
                                        <p className="text-xs text-muted-foreground">
                                            <Sparkles className="mr-1 inline h-3 w-3 text-primary" />
                                            Only PDF and image (JPEG/PNG) files are accepted. PDFs are read automatically to fill in any missing details.
                                        </p>
                                        <div className="flex gap-2">
                                            <Button type="submit" size="sm" disabled={uploadForm.processing}>
                                                {uploadForm.processing ? 'Uploading…' : 'Upload'}
                                            </Button>
                                            <Button type="button" variant="secondary" size="sm" onClick={() => setShowUpload(false)}>Cancel</Button>
                                        </div>
                                    </form>
                                )}

                                {proposal.files.length === 0 ? (
                                    <EmptyState icon={FileText} title="No documents yet"
                                        description="Upload proposal documents to keep everything in one place." />
                                ) : (
                                    <div className="space-y-2">
                                        {proposal.files.map(file => (
                                            <div key={file.id} className="flex items-center gap-3 rounded-xl border border-border p-3 transition-colors hover:bg-secondary/50">
                                                <FileText className="h-4 w-4 shrink-0 text-muted-foreground" />
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-sm font-medium text-foreground">{file.display_name}</p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {[file.document_type, `v${file.version}`, formatSize(file.size), formatDate(file.created_at)].filter(Boolean).join(' · ')}
                                                    </p>
                                                </div>
                                                <button
                                                    type="button"
                                                    onClick={() => setPreview({
                                                        name: file.display_name,
                                                        mimeType: file.mime_type,
                                                        previewUrl: `/proposals/${proposal.id}/files/${file.id}/preview`,
                                                        downloadUrl: `/proposals/${proposal.id}/files/${file.id}/download`,
                                                    })}
                                                    title="Preview"
                                                    className="text-muted-foreground transition-colors hover:text-primary"
                                                >
                                                    <Eye className="h-4 w-4" />
                                                </button>
                                                <a href={`/proposals/${proposal.id}/files/${file.id}/download`} title="Download" className="text-muted-foreground transition-colors hover:text-primary">
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
                        {proposal.company && (
                            <Card>
                                <CardHeader><CardTitle className="flex items-center gap-2 text-sm"><Building2 className="h-4 w-4 text-muted-foreground" /> Company</CardTitle></CardHeader>
                                <CardContent className="space-y-2">
                                    <a href={`/companies/${proposal.company.id}`} className="block rounded-lg border border-border p-2.5 text-sm transition-colors hover:bg-secondary/50">
                                        <p className="font-medium text-foreground">{proposal.company.name}</p>
                                        <span className="text-xs text-muted-foreground">View company &amp; contacts →</span>
                                    </a>
                                </CardContent>
                            </Card>
                        )}

                        {followUps.length > 0 && (
                            <Card>
                                <CardHeader><CardTitle className="flex items-center gap-2 text-sm"><MessageSquare className="h-4 w-4 text-muted-foreground" /> Follow-Ups</CardTitle></CardHeader>
                                <CardContent className="space-y-2">
                                    {followUps.map(f => (
                                        <div key={f.id} className="rounded-lg border border-border p-2.5">
                                            <p className="text-sm font-medium text-foreground">{f.subject}</p>
                                            <p className="text-xs text-muted-foreground capitalize">
                                                {f.status}{f.scheduled_date ? ` · ${formatDate(f.scheduled_date)}` : ''}
                                                {f.contact ? ` · ${f.contact.first_name} ${f.contact.last_name}` : ''}
                                            </p>
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>
                        )}

                        <Card>
                            <CardHeader><CardTitle className="flex items-center gap-2 text-sm"><Users className="h-4 w-4 text-muted-foreground" /> Team Members</CardTitle></CardHeader>
                            <CardContent>
                                {teamMembers.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">No team members assigned.</p>
                                ) : (
                                    <div className="space-y-2">
                                        {teamMembers.map(m => (
                                            <div key={m.id} className="flex items-center gap-2">
                                                <div className="bg-brand-gradient flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold text-white">{m.user?.name?.[0] ?? '?'}</div>
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-sm text-foreground">{m.user?.name ?? 'Unknown'}</p>
                                                    <p className="text-xs capitalize text-muted-foreground">{m.role}</p>
                                                </div>
                                                {m.role === 'owner' && (
                                                    <span className="shrink-0 rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-primary">Owner</span>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {statusHistory.length > 0 && (
                            <Card>
                                <CardHeader><CardTitle className="text-sm">Status History</CardTitle></CardHeader>
                                <CardContent>
                                    <div className="space-y-3">
                                        {statusHistory.map(h => (
                                            <div key={h.id} className="flex gap-3 text-xs">
                                                <div className="mt-0.5"><ChevronRight className="h-3 w-3 text-muted-foreground" /></div>
                                                <div>
                                                    <p className="font-medium capitalize text-foreground">{(h.to_status ?? '').replace(/_/g, ' ')}</p>
                                                    <p className="text-muted-foreground">{formatDate(h.changed_at)} · {h.changed_by?.name ?? 'System'}</p>
                                                    {h.notes && <p className="mt-0.5 text-muted-foreground">{h.notes}</p>}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
            <FilePreviewModal file={preview} onClose={() => setPreview(null)} />
            {celebrate && <SubmitCelebration proposalNumber={celebrate} onClose={() => setCelebrate(null)} />}
        </AppLayout>
    );
}
