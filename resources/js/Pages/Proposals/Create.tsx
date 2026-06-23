import { Head, useForm } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/Card';
import { Select } from '@/Components/ui/Select';
import { NumberInput } from '@/Components/ui/NumberInput';
import { ArrowLeft, FileText, UploadCloud, Sparkles, Loader2, X, FileCheck2, Users } from 'lucide-react';
import { useRef, useState } from 'react';

interface CurrencyOption { value: string; label: string; symbol: string; name: string }

interface Props {
    opportunities: Array<{ id: number; title: string; solicitation_number: string | null }>;
    users: Array<{ id: number; name: string }>;
    currencies: CurrencyOption[];
    proposalTypes: Array<{ value: string; label: string; description: string; has_value: boolean }>;
    isAdmin: boolean;
    currentUser: { id: number; name: string };
    prefill?: { due_date: string | null };
}

const ACCEPT = '.pdf,.jpg,.jpeg,.png';

const SUBMISSION_METHODS: Array<{ value: string; label: string }> = [
    { value: 'email', label: 'Email' },
    { value: 'portal', label: 'Portal' },
    { value: 'mail', label: 'Mail' },
];

export default function ProposalCreate({ opportunities, users, currencies, proposalTypes, isAdmin, currentUser, prefill }: Props) {
    const [dragging, setDragging] = useState(false);
    const fileInput = useRef<HTMLInputElement>(null);

    // Auto-intake: drop one or more documents, we create + extract everything
    // from the most complete one.
    const intake = useForm<{ documents: File[] }>({ documents: [] });

    // Manual creation with an optional attached document.
    const manual = useForm({
        project_name: '',
        proposal_type: 'proposal',
        opportunity_id: '',
        company: '',
        solicitation_number: '',
        proposal_value: '',
        currency: 'USD',
        due_date: prefill?.due_date ?? '',
        submission_methods: [] as string[],
        submission_portal_url: '',
        owner_id: String(currentUser.id),
        team_member_ids: [] as number[],
        description: '',
        document: null as File | null,
    });

    const ownerId = Number(manual.data.owner_id);
    const symbol = currencies.find(c => c.value === manual.data.currency)?.symbol ?? '$';
    // RFIs are informational only — they carry no proposal value.
    const typeHasValue = proposalTypes.find(t => t.value === manual.data.proposal_type)?.has_value ?? true;

    const toggleMember = (id: number) => {
        if (id === ownerId) return; // the owner is always on the team
        manual.setData('team_member_ids', manual.data.team_member_ids.includes(id)
            ? manual.data.team_member_ids.filter(m => m !== id)
            : [...manual.data.team_member_ids, id]);
    };

    const toggleMethod = (value: string) => {
        manual.setData('submission_methods', manual.data.submission_methods.includes(value)
            ? manual.data.submission_methods.filter(m => m !== value)
            : [...manual.data.submission_methods, value]);
    };

    const submitIntake = (files: File[]) => {
        if (!files.length) return;
        intake.setData('documents', files);
        intake.post('/proposals/intake', { forceFormData: true });
    };

    const onDrop = (e: React.DragEvent) => {
        e.preventDefault();
        setDragging(false);
        const files = Array.from(e.dataTransfer.files ?? []);
        if (files.length) submitIntake(files);
    };

    const submitManual = (e: React.FormEvent) => {
        e.preventDefault();
        manual.post('/proposals', { forceFormData: true });
    };

    const handleOpportunityChange = (id: string) => {
        const opp = opportunities.find(o => String(o.id) === id);
        manual.setData(prev => ({
            ...prev,
            opportunity_id: id,
            project_name: opp ? opp.title : prev.project_name,
            solicitation_number: opp?.solicitation_number ?? prev.solicitation_number,
        }));
    };

    return (
        <AppLayout>
            <Head title="New Proposal" />
            <div className="mx-auto max-w-3xl p-6">
                <PageHeader
                    icon={FileText}
                    title="Create Proposal"
                    description="Drop a document to auto-extract everything, or fill it in manually."
                    actions={<Button href="/proposals" variant="secondary" icon={ArrowLeft}>Back</Button>}
                />

                {/* Auto intake drop-zone */}
                <Card className="mb-6 overflow-hidden">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Sparkles className="h-4 w-4 text-primary" /> Auto-create from documents
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div
                            onDragOver={e => { e.preventDefault(); setDragging(true); }}
                            onDragLeave={() => setDragging(false)}
                            onDrop={onDrop}
                            onClick={() => !intake.processing && fileInput.current?.click()}
                            className={[
                                'relative flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed px-6 py-10 text-center transition-all',
                                dragging ? 'border-primary bg-primary/5 scale-[1.01]' : 'border-border hover:border-primary/50 hover:bg-secondary/40',
                            ].join(' ')}
                        >
                            <input
                                ref={fileInput}
                                type="file"
                                accept={ACCEPT}
                                multiple
                                className="hidden"
                                onChange={e => submitIntake(Array.from(e.target.files ?? []))}
                            />

                            {intake.processing ? (
                                <>
                                    <Loader2 className="mb-3 h-9 w-9 animate-spin text-primary" />
                                    <p className="text-sm font-semibold text-foreground">
                                        Reading {intake.data.documents.length > 1 ? `${intake.data.documents.length} documents` : 'your document'}…
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">Extracting company, contact, value, dates and scope from the most complete one.</p>
                                </>
                            ) : (
                                <>
                                    <div className="bg-brand-gradient shadow-glow mb-3 flex h-12 w-12 items-center justify-center rounded-2xl">
                                        <UploadCloud className="h-6 w-6 text-white" />
                                    </div>
                                    <p className="text-sm font-semibold text-foreground">
                                        Drop your proposal documents here, or <span className="text-primary">browse</span>
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Drop one or many — PDF or image (JPEG/PNG). We'll read them, pick the most complete one to fill
                                        in the proposal, company, contact &amp; follow-ups, and attach the rest.
                                    </p>
                                </>
                            )}
                        </div>
                        {(intake.errors.documents || (intake.errors as Record<string, string>)['documents.0']) && (
                            <p className="mt-2 text-xs text-destructive">{intake.errors.documents || (intake.errors as Record<string, string>)['documents.0']}</p>
                        )}
                    </CardContent>
                </Card>

                {/* Manual creation */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-muted-foreground">Or create manually</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submitManual} className="space-y-5">
                            <div>
                                <label className="label">Linked Opportunity</label>
                                <Select
                                    value={manual.data.opportunity_id}
                                    onChange={v => handleOpportunityChange(v)}
                                    placeholder="None (standalone proposal)"
                                    options={opportunities.map(o => ({
                                        value: String(o.id),
                                        label: `${o.solicitation_number ? `[${o.solicitation_number}] ` : ''}${o.title}`,
                                    }))}
                                    className="w-full"
                                />
                            </div>

                            <div>
                                <label className="label">Type *</label>
                                <Select
                                    value={manual.data.proposal_type}
                                    onChange={v => manual.setData('proposal_type', v)}
                                    options={proposalTypes.map(t => ({ value: t.value, label: `${t.label} — ${t.description}` }))}
                                    className="w-full"
                                />
                                <p className="mt-1 text-xs text-muted-foreground">What kind of document is this? RFIs are informational and carry no value.</p>
                            </div>

                            <div>
                                <label className="label">Project Name *</label>
                                <input type="text" value={manual.data.project_name} onChange={e => manual.setData('project_name', e.target.value)}
                                    className="input" required placeholder="Full project name as it will appear in the proposal" />
                                {manual.errors.project_name && <p className="mt-1 text-xs text-destructive">{manual.errors.project_name}</p>}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="label">Solicitation # *</label>
                                    <input type="text" required value={manual.data.solicitation_number} onChange={e => manual.setData('solicitation_number', e.target.value)} className="input" />
                                    {manual.errors.solicitation_number && <p className="mt-1 text-xs text-destructive">{manual.errors.solicitation_number}</p>}
                                </div>
                                <div>
                                    <label className="label">Company *</label>
                                    <input type="text" required value={manual.data.company} onChange={e => manual.setData('company', e.target.value)}
                                        className="input" placeholder="Client / buyer organization" />
                                    {manual.errors.company && <p className="mt-1 text-xs text-destructive">{manual.errors.company}</p>}
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                {typeHasValue && (
                                    <div>
                                        <label className="label">Proposal Value ({symbol}) *</label>
                                        <div className="flex gap-2">
                                            <Select
                                                value={manual.data.currency}
                                                onChange={v => manual.setData('currency', v)}
                                                options={currencies.map(c => ({ value: c.value, label: c.value }))}
                                                className="w-28 shrink-0"
                                            />
                                            <NumberInput value={manual.data.proposal_value} onChange={e => manual.setData('proposal_value', e.target.value)} className="input flex-1" placeholder="0.00" />
                                        </div>
                                        {manual.errors.proposal_value && <p className="mt-1 text-xs text-destructive">{manual.errors.proposal_value}</p>}
                                    </div>
                                )}
                                <div>
                                    <label className="label">Due Date *</label>
                                    <input type="date" required value={manual.data.due_date} onChange={e => manual.setData('due_date', e.target.value)} className="input" />
                                    {manual.errors.due_date && <p className="mt-1 text-xs text-destructive">{manual.errors.due_date}</p>}
                                </div>
                            </div>

                            <div>
                                <label className="label">Submission Method</label>
                                <div className="flex flex-wrap gap-2">
                                    {SUBMISSION_METHODS.map(m => {
                                        const active = manual.data.submission_methods.includes(m.value);
                                        return (
                                            <button
                                                key={m.value}
                                                type="button"
                                                onClick={() => toggleMethod(m.value)}
                                                className={`inline-flex items-center gap-1 rounded-full border px-3.5 py-1.5 text-sm font-medium transition ${active ? 'border-primary bg-primary/10 text-primary' : 'border-border bg-card text-muted-foreground hover:bg-secondary hover:text-foreground'}`}
                                            >
                                                {m.label}
                                            </button>
                                        );
                                    })}
                                </div>
                                <p className="mt-1 text-xs text-muted-foreground">How will this proposal be submitted? Pick any that apply.</p>
                                {manual.errors.submission_methods && <p className="mt-1 text-xs text-destructive">{manual.errors.submission_methods}</p>}
                                {manual.data.submission_methods.includes('portal') && (
                                    <div className="mt-3">
                                        <label className="label">Submission Portal Link</label>
                                        <input
                                            type="url"
                                            inputMode="url"
                                            value={manual.data.submission_portal_url}
                                            onChange={e => manual.setData('submission_portal_url', e.target.value)}
                                            placeholder="https://portal.example.gov/submit"
                                            className="input"
                                        />
                                        <p className="mt-1 text-xs text-muted-foreground">Paste the portal URL where this proposal is submitted.</p>
                                        {manual.errors.submission_portal_url && <p className="mt-1 text-xs text-destructive">{manual.errors.submission_portal_url}</p>}
                                    </div>
                                )}
                            </div>

                            <div>
                                <label className="label">Owner</label>
                                {isAdmin ? (
                                    <Select
                                        value={manual.data.owner_id}
                                        onChange={v => manual.setData('owner_id', v)}
                                        placeholder="Select owner…"
                                        options={users.map(u => ({ value: String(u.id), label: u.name }))}
                                        className="w-full"
                                    />
                                ) : (
                                    <input type="text" value={`${currentUser.name} (you)`} disabled className="input cursor-not-allowed opacity-70" />
                                )}
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {isAdmin ? 'The owner is the person responsible for this proposal.' : 'Only an administrator can assign a different owner.'}
                                </p>
                            </div>

                            <div>
                                <label className="label flex items-center gap-1.5"><Users className="h-3.5 w-3.5" /> Team members <span className="font-normal text-muted-foreground">(attach anyone collaborating)</span></label>
                                <div className="flex flex-wrap gap-2">
                                    {users.map(u => {
                                        const isOwner = u.id === ownerId;
                                        const active = isOwner || manual.data.team_member_ids.includes(u.id);
                                        return (
                                            <button
                                                key={u.id}
                                                type="button"
                                                onClick={() => toggleMember(u.id)}
                                                disabled={isOwner}
                                                className={`inline-flex items-center gap-1 rounded-full border px-3.5 py-1.5 text-sm font-medium transition ${active ? 'border-primary bg-primary/10 text-primary' : 'border-border bg-card text-muted-foreground hover:bg-secondary hover:text-foreground'} ${isOwner ? 'cursor-not-allowed' : ''}`}
                                            >
                                                {u.name}{isOwner && <span className="text-[10px] uppercase tracking-wide opacity-70">· owner</span>}
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>

                            <div>
                                <label className="label">Description / Notes</label>
                                <textarea value={manual.data.description} onChange={e => manual.setData('description', e.target.value)} rows={4} className="input" placeholder="Any additional notes or context..." />
                            </div>

                            <div>
                                <label className="label">Attach a document (optional)</label>
                                {manual.data.document ? (
                                    <div className="flex items-center gap-2 rounded-lg border border-border bg-secondary/40 px-3 py-2 text-sm">
                                        <FileCheck2 className="h-4 w-4 text-primary" />
                                        <span className="flex-1 truncate text-foreground">{manual.data.document.name}</span>
                                        <button type="button" onClick={() => manual.setData('document', null)} className="text-muted-foreground hover:text-destructive">
                                            <X className="h-4 w-4" />
                                        </button>
                                    </div>
                                ) : (
                                    <input type="file" accept={ACCEPT} onChange={e => manual.setData('document', e.target.files?.[0] ?? null)}
                                        className="block w-full text-sm text-muted-foreground file:mr-3 file:rounded-lg file:border-0 file:bg-secondary file:px-3 file:py-2 file:text-sm file:font-medium file:text-foreground hover:file:bg-secondary/70" />
                                )}
                                <p className="mt-1 text-xs text-muted-foreground">If attached, it will be parsed to fill any blanks above.</p>
                            </div>

                            <div className="flex justify-end gap-3 pt-2">
                                <Button href="/proposals" variant="secondary">Cancel</Button>
                                <Button type="submit" disabled={manual.processing}>
                                    {manual.processing ? 'Creating…' : 'Create Proposal'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
