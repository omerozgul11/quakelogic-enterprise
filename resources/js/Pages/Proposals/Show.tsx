import { Head, router, useForm, usePage } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Select } from '@/Components/ui/Select';
import { NumberInput } from '@/Components/ui/NumberInput';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { FileDropzone } from '@/Components/ui/FileDropzone';
import { formatCurrency, formatDate, formatDateTime, healthDotClass, ProposalHealth, proposalTypeLabel, proposalTypeColor, proposalTypeHasValue } from '@/Lib/utils';
import { ProposalSubmission, SharedProps } from '@/Types';
import { ArrowLeft, FileText, Upload, Download, Users, ChevronRight, ChevronLeft, Sparkles, Building2, MessageSquare, Pencil, Eye, Trash2, Lock, ExternalLink, CheckCircle } from 'lucide-react';
import { FilePreviewModal, PreviewFile } from '@/Components/ui/FilePreviewModal';
import { SubmitCelebration } from '@/Components/ui/SubmitCelebration';
import { ProposalCountdown, Countdown } from '@/Components/proposal/ProposalCountdown';
import { CostMarginPanel, CostLine, MarginSummary } from '@/Components/proposal/CostMarginPanel';
import { ProposalWriterPanel, SavedSection } from '@/Components/proposal/ProposalWriterPanel';
import { MailTrackingPanel, MailTracking } from '@/Components/proposal/MailTrackingPanel';
import { ContractPanel, ContractData } from '@/Components/proposal/ContractPanel';
import { LossAnalysisPanel, LossData } from '@/Components/proposal/LossAnalysisPanel';
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

interface SamDocument {
    index: number;
    name: string;
    preview_url: string;
    download_url: string;
    extract_url: string;
}

interface SamDocuments {
    linked: boolean;
    opportunity_title?: string;
    documents: SamDocument[];
    can_extract: boolean;
    notice_url?: string | null;
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
        loss_reason?: string | null;
        loss_competitor?: string | null;
        loss_competitor_price?: number | string | null;
        debrief_requested?: boolean;
        protest_recommended?: boolean;
        lessons_learned?: string | null;
        loss_assessment?: string | null;
        created_at?: string | null;
    };
    createdBy?: { id: number; name: string } | null;
    stepNav: { previous: Step | null; next: Step | null };
    countdown: Countdown | null;
    proposalTypes: Array<{ value: string; label: string; description: string; has_value: boolean }>;
    costs: CostLine[];
    margin: MarginSummary;
    costCategories: Array<{ value: string; label: string }>;
    proposalSections: Array<{ value: string; label: string }>;
    savedSections: SavedSection[];
    allowedTransitions: Step[];
    samDocuments: SamDocuments;
    currencies: Array<{ value: string; label: string; symbol: string; name: string }>;
    extraction: Extraction | null;
    health: ProposalHealth | null;
    readiness: { score: number; ready: boolean; threshold: number; items: Array<{ key: string; label: string; done: boolean }> };
    contract: ContractData | null;
    contractOptions: { stages: Array<{ value: string; label: string }>; paymentStatuses: Array<{ value: string; label: string }> };
    can: { edit: boolean; upload: boolean; transition: boolean; delete: boolean; editStyle?: boolean };
    mailTracking?: MailTracking;
}

function formatSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1048576).toFixed(1)} MB`;
}

export default function ProposalShow({ proposal, createdBy, stepNav, countdown, proposalTypes, costs, margin, costCategories, proposalSections, savedSections, allowedTransitions, samDocuments, currencies, extraction, health, readiness, contract, contractOptions, can, mailTracking }: Props) {
    const [showUpload, setShowUpload] = useState(false);
    const [preview, setPreview] = useState<PreviewFile | null>(null);
    const [editing, setEditing] = useState(false);
    const uploadForm = useForm({ files: [] as File[], document_type: '' });

    const detailsForm = useForm({
        project_name: proposal.project_name ?? '',
        proposal_type: proposal.proposal_type ?? 'proposal',
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

    // RFIs are informational only — hide the value fields for them.
    const proposalType = proposal.proposal_type ?? 'proposal';
    const showValue = proposalTypeHasValue(proposalType);
    const editTypeHasValue = proposalTypeHasValue(detailsForm.data.proposal_type);

    const submissionMethods = (proposal as { submission_methods?: string[] }).submission_methods ?? [];
    const submissionPortalUrl = (proposal as { submission_portal_url?: string | null }).submission_portal_url ?? null;
    const detailRows: Array<[string, string | null | undefined]> = [
        ['Type', proposalTypeLabel(proposalType)],
        ['Company', proposal.company?.name],
        ['Solicitation #', proposal.solicitation_number],
        ...(showValue
            ? ([
                ['Proposal Value', proposal.proposal_value ? formatCurrency(proposal.proposal_value, proposal.currency) : null],
                ['Award Value', proposal.award_value ? formatCurrency(proposal.award_value, proposal.currency) : null],
            ] as Array<[string, string | null | undefined]>)
            : []),
        ['Due Date', proposal.due_date ? formatDate(proposal.due_date) : null],
        ['Submission Date', proposal.submission_date ? formatDate(proposal.submission_date) : null],
        ['Submission Method', submissionMethods.length
            ? submissionMethods.map(m => ({ email: 'Email', portal: 'Portal', mail: 'Mail' }[m] ?? m)).join(', ')
            : null],
        ['Owner', proposal.owner?.name],
        ['Added to platform', proposal.created_at
            ? `${formatDateTime(proposal.created_at)}${createdBy ? ` by ${createdBy.name}` : ''}`
            : null],
    ];

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

    const handleLogContact = () => {
        const note = window.prompt('Log a client contact (optional note — call, email, meeting):', '');
        if (note === null) return; // cancelled
        router.post(`/proposals/${proposal.id}/log-contact`, { note }, { preserveScroll: true });
    };

    const [draftingEmail, setDraftingEmail] = useState(false);
    const handleDraftFollowUp = () => {
        setDraftingEmail(true);
        router.post(`/proposals/${proposal.id}/draft-follow-up`, {}, { preserveScroll: true, onFinish: () => setDraftingEmail(false) });
    };

    const handleUpload = (e: React.FormEvent) => {
        e.preventDefault();
        if (uploadForm.data.files.length === 0) return;
        uploadForm.post(`/proposals/${proposal.id}/files`, {
            forceFormData: true,
            onSuccess: () => { setShowUpload(false); uploadForm.reset(); },
        });
    };

    const extractSam = (url: string) => {
        if (confirm('Read this SAM.gov document and use it to fill in any blank proposal fields?')) {
            router.post(url, {}, { preserveScroll: true });
        }
    };

    const statusValue = typeof proposal.status === 'string' ? proposal.status : (proposal.status as any)?.value ?? 'in_progress';

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

                {!can.edit && (
                    <div className="mb-6 flex items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 p-3.5 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200">
                        <Lock className="mt-0.5 h-4 w-4 shrink-0" />
                        <p>
                            You're not the owner of this document
                            {proposal.owner?.name ? <> — it's currently owned by <span className="font-semibold">{proposal.owner.name}</span></> : ''}.
                            You have read-only access.
                        </p>
                    </div>
                )}

                <div className="mb-6 flex flex-wrap items-center gap-3">
                    <span className={`inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold ${proposalTypeColor(proposalType)}`} title="Document type">
                        {proposalTypeLabel(proposalType)}
                    </span>
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
                    {health && (
                        <span className="inline-flex items-center gap-1.5 rounded-full bg-secondary px-2.5 py-1 text-xs font-medium text-muted-foreground" title="Days since last logged client contact">
                            <span className={`inline-block h-2.5 w-2.5 rounded-full ${healthDotClass(health.color)}`} />
                            {health.label}
                        </span>
                    )}
                    {can.edit && (
                        <Button variant="secondary" size="sm" icon={MessageSquare} onClick={handleLogContact}>
                            Log client contact
                        </Button>
                    )}
                    {can.edit && (
                        <Button variant="secondary" size="sm" icon={Sparkles} onClick={handleDraftFollowUp} disabled={draftingEmail}>
                            {draftingEmail ? 'Drafting…' : 'Draft follow-up (AI)'}
                        </Button>
                    )}
                </div>

                {countdown && <ProposalCountdown countdown={countdown} />}

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        {/* Submission readiness (Phase 17) — pre-submission checklist + score */}
                        {statusValue === 'in_progress' && (
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between">
                                    <CardTitle>Submission Readiness</CardTitle>
                                    <span className={`text-2xl font-bold tabular-nums ${readiness.ready ? 'text-emerald-600' : readiness.score >= 40 ? 'text-amber-600' : 'text-red-600'}`}>
                                        {readiness.score}%
                                    </span>
                                </CardHeader>
                                <CardContent>
                                    <div className="mb-3 h-2 w-full overflow-hidden rounded-full bg-secondary">
                                        <div
                                            className={`h-full rounded-full transition-all ${readiness.ready ? 'bg-emerald-500' : readiness.score >= 40 ? 'bg-amber-500' : 'bg-red-500'}`}
                                            style={{ width: `${readiness.score}%` }}
                                        />
                                    </div>
                                    <p className="mb-3 text-xs text-muted-foreground">
                                        {readiness.ready
                                            ? `Ready to submit (target ${readiness.threshold}%).`
                                            : `Below the ${readiness.threshold}% target — complete the items below before submitting.`}
                                    </p>
                                    <ul className="grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                                        {readiness.items.map(item => (
                                            <li key={item.key} className="flex items-center gap-2 text-sm">
                                                {item.done
                                                    ? <CheckCircle className="h-4 w-4 shrink-0 text-emerald-500" />
                                                    : <Lock className="h-4 w-4 shrink-0 text-muted-foreground/50" />}
                                                <span className={item.done ? 'text-foreground' : 'text-muted-foreground'}>{item.label}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </CardContent>
                            </Card>
                        )}

                        {/* Contract & financials (Phase 5) — shown once a proposal is won, or if one already exists */}
                        {(['awarded', 'completed'].includes(statusValue) || contract) && (
                            <ContractPanel
                                proposalId={proposal.id}
                                contract={contract}
                                options={contractOptions}
                                currencies={currencies}
                                canEdit={can.edit}
                            />
                        )}

                        {/* Loss analysis (Phase 19) — for lost or protested bids */}
                        {['lost', 'protested'].includes(statusValue) && (
                            <LossAnalysisPanel
                                proposalId={proposal.id}
                                canEdit={can.edit}
                                data={{
                                    loss_reason: proposal.loss_reason ?? null,
                                    loss_competitor: proposal.loss_competitor ?? null,
                                    loss_competitor_price: proposal.loss_competitor_price ?? null,
                                    debrief_requested: !!proposal.debrief_requested,
                                    protest_recommended: !!proposal.protest_recommended,
                                    lessons_learned: proposal.lessons_learned ?? null,
                                    loss_assessment: proposal.loss_assessment ?? null,
                                }}
                            />
                        )}

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
                                    <form onSubmit={saveDetails} className="animate-panel space-y-4">
                                        <div className="grid grid-cols-2 gap-4">
                                            <div>
                                                <label className="label">Project Name *</label>
                                                <input type="text" value={detailsForm.data.project_name} onChange={e => detailsForm.setData('project_name', e.target.value)} className="input" required />
                                                {detailsForm.errors.project_name && <p className="mt-1 text-xs text-destructive">{detailsForm.errors.project_name}</p>}
                                            </div>
                                            <div>
                                                <label className="label">Type</label>
                                                <Select
                                                    value={detailsForm.data.proposal_type}
                                                    onChange={v => detailsForm.setData('proposal_type', v)}
                                                    options={proposalTypes.map(t => ({ value: t.value, label: `${t.label} — ${t.description}` }))}
                                                    className="w-full"
                                                />
                                            </div>
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
                                        {editTypeHasValue ? (
                                            <div className="grid grid-cols-2 gap-4">
                                                <div>
                                                    <label className="label">Proposal Value ({currencySymbol})</label>
                                                    <div className="flex gap-2">
                                                        <Select value={detailsForm.data.currency} onChange={v => detailsForm.setData('currency', v)} options={currencies.map(c => ({ value: c.value, label: c.value }))} className="w-24 shrink-0" />
                                                        <NumberInput value={detailsForm.data.proposal_value} onChange={e => detailsForm.setData('proposal_value', e.target.value)} className="input flex-1" />
                                                    </div>
                                                </div>
                                                <div>
                                                    <label className="label">Award Value ({currencySymbol})</label>
                                                    <NumberInput value={detailsForm.data.award_value} onChange={e => detailsForm.setData('award_value', e.target.value)} className="input" />
                                                </div>
                                            </div>
                                        ) : (
                                            <p className="rounded-lg border border-border bg-secondary/40 px-3 py-2 text-xs text-muted-foreground">
                                                RFIs are informational only — no proposal value is tracked.
                                            </p>
                                        )}
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
                                            {detailRows.map(([label, value]) => (
                                                <div key={label} className="flex justify-between border-b border-border py-2.5 last:border-0">
                                                    <dt className="text-sm text-muted-foreground">{label}</dt>
                                                    <dd className="text-sm font-semibold text-foreground">{value ?? '—'}</dd>
                                                </div>
                                            ))}
                                        </dl>
                                        {submissionPortalUrl && submissionMethods.includes('portal') && (
                                            <div className="flex items-center justify-between gap-3 border-b border-border py-2.5">
                                                <dt className="shrink-0 text-sm text-muted-foreground">Submission Portal</dt>
                                                <a
                                                    href={submissionPortalUrl}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="inline-flex min-w-0 items-center gap-1 truncate text-sm font-semibold text-primary hover:underline"
                                                >
                                                    <span className="truncate">Open submission portal</span>
                                                    <ExternalLink className="h-3.5 w-3.5 shrink-0" />
                                                </a>
                                            </div>
                                        )}
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

                        {/* Cost & margin — bid vs. direct costs → potential profit (RFIs carry no value) */}
                        {showValue && (
                            <CostMarginPanel
                                proposalId={proposal.id}
                                costs={costs}
                                margin={margin}
                                categories={costCategories}
                                canEdit={can.edit}
                            />
                        )}

                        {/* Proposal Writer — AI-draft full proposal sections */}
                        <ProposalWriterPanel proposalId={proposal.id} sections={proposalSections} savedSections={savedSections} canEdit={can.edit} canEditStyle={!!can.editStyle} />

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
                                        <FileDropzone
                                            files={uploadForm.data.files}
                                            onChange={fs => uploadForm.setData('files', fs)}
                                            hint="Drop one or more PDF / image files here. Up to 100 MB each."
                                        />
                                        <input type="text" placeholder="Document type (optional — applied to all)" value={uploadForm.data.document_type}
                                            onChange={e => uploadForm.setData('document_type', e.target.value)} className="input" />
                                        {uploadForm.errors.files && <p className="text-xs text-destructive">{uploadForm.errors.files}</p>}
                                        <div className="flex gap-2">
                                            <Button type="submit" size="sm" disabled={uploadForm.processing || uploadForm.data.files.length === 0}>
                                                {uploadForm.processing ? 'Uploading…' : `Upload${uploadForm.data.files.length ? ` ${uploadForm.data.files.length} file${uploadForm.data.files.length === 1 ? '' : 's'}` : ''}`}
                                            </Button>
                                            <Button type="button" variant="secondary" size="sm" onClick={() => { setShowUpload(false); uploadForm.reset(); }}>Cancel</Button>
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
                        <MailTrackingPanel tracking={mailTracking} proposalId={proposal.id} />
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

                        {/* Solicitation documents pulled live from the linked SAM.gov opportunity */}
                        <Card>
                            <CardHeader><CardTitle className="flex items-center gap-2 text-sm"><FileText className="h-4 w-4 text-muted-foreground" /> Solicitation Documents</CardTitle></CardHeader>
                            <CardContent>
                                {!samDocuments.linked ? (
                                    <p className="text-sm text-muted-foreground">Not linked to a SAM.gov opportunity, so there are no solicitation documents to pull.</p>
                                ) : samDocuments.documents.length === 0 ? (
                                    <div className="text-sm text-muted-foreground">
                                        <p>This SAM.gov notice has no downloadable attachments — the details are in the description, or behind the notice's portal link.</p>
                                        {samDocuments.notice_url && (
                                            <a href={samDocuments.notice_url} target="_blank" rel="noopener noreferrer" className="mt-1.5 inline-flex items-center gap-1 font-medium text-primary hover:underline">
                                                View the full notice on SAM.gov <ExternalLink className="h-3 w-3" />
                                            </a>
                                        )}
                                    </div>
                                ) : (
                                    <div className="space-y-2">
                                        {samDocuments.documents.map(d => (
                                            <div key={d.index} className="flex items-center gap-2 rounded-lg border border-border p-2.5">
                                                <FileText className="h-4 w-4 shrink-0 text-muted-foreground" />
                                                <span className="min-w-0 flex-1 truncate text-sm text-foreground" title={d.name}>{d.name}</span>
                                                <button
                                                    type="button"
                                                    onClick={() => setPreview({ name: d.name, mimeType: 'application/pdf', previewUrl: d.preview_url, downloadUrl: d.download_url })}
                                                    title="Preview"
                                                    className="text-muted-foreground transition-colors hover:text-primary"
                                                >
                                                    <Eye className="h-4 w-4" />
                                                </button>
                                                <a href={d.download_url} title="Download" className="text-muted-foreground transition-colors hover:text-primary">
                                                    <Download className="h-4 w-4" />
                                                </a>
                                                {samDocuments.can_extract && (
                                                    <button
                                                        type="button"
                                                        onClick={() => extractSam(d.extract_url)}
                                                        title="Use to fill in proposal details"
                                                        className="text-muted-foreground transition-colors hover:text-primary"
                                                    >
                                                        <Sparkles className="h-4 w-4" />
                                                    </button>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
            <FilePreviewModal file={preview} onClose={() => setPreview(null)} />
            {celebrate && <SubmitCelebration proposalNumber={celebrate} onClose={() => setCelebrate(null)} />}
        </AppLayout>
    );
}
