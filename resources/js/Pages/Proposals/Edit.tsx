import { Head, useForm } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card, CardContent } from '@/Components/ui/Card';
import { Select } from '@/Components/ui/Select';
import { NumberInput } from '@/Components/ui/NumberInput';
import { ArrowLeft, FileText, Users } from 'lucide-react';

interface CurrencyOption { value: string; label: string; symbol: string; name: string }

interface Props {
    proposal: {
        id: number;
        proposal_number: string;
        project_name: string;
        proposal_type: string;
        solicitation_number: string | null;
        company: string;
        proposal_value: string | number | null;
        award_value: string | number | null;
        currency: string;
        due_date: string | null;
        submission_date: string | null;
        award_date: string | null;
        expected_award_date: string | null;
        win_probability: number | null;
        status: string;
        owner_id: number | null;
        owner_name: string | null;
        team_member_ids: number[];
        description: string | null;
        scope_summary: string | null;
        notes: string | null;
        submission_methods: string[];
        submission_portal_url: string | null;
    };
    users: Array<{ id: number; name: string }>;
    currencies: CurrencyOption[];
    statusOptions: Array<{ value: string; label: string }>;
    proposalTypes: Array<{ value: string; label: string; description: string; has_value: boolean }>;
    isAdmin: boolean;
}

const SUBMISSION_METHODS: Array<{ value: string; label: string }> = [
    { value: 'email', label: 'Email' },
    { value: 'portal', label: 'Portal' },
    { value: 'mail', label: 'Mail' },
];

export default function ProposalEdit({ proposal, users, currencies, statusOptions, proposalTypes, isAdmin }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        project_name: proposal.project_name ?? '',
        proposal_type: proposal.proposal_type ?? 'proposal',
        solicitation_number: proposal.solicitation_number ?? '',
        company: proposal.company ?? '',
        proposal_value: proposal.proposal_value != null ? String(proposal.proposal_value) : '',
        award_value: proposal.award_value != null ? String(proposal.award_value) : '',
        currency: proposal.currency ?? 'USD',
        status: proposal.status ?? '',
        due_date: proposal.due_date ?? '',
        submission_date: proposal.submission_date ?? '',
        award_date: proposal.award_date ?? '',
        expected_award_date: proposal.expected_award_date ?? '',
        win_probability: proposal.win_probability != null ? String(proposal.win_probability) : '',
        owner_id: proposal.owner_id ? String(proposal.owner_id) : '',
        team_member_ids: proposal.team_member_ids ?? [],
        scope_summary: proposal.scope_summary ?? '',
        description: proposal.description ?? '',
        notes: proposal.notes ?? '',
        submission_methods: (proposal.submission_methods ?? []).filter(m => SUBMISSION_METHODS.some(s => s.value === m)),
        submission_portal_url: proposal.submission_portal_url ?? '',
    });

    const ownerId = Number(data.owner_id);
    const symbol = currencies.find(c => c.value === data.currency)?.symbol ?? '$';
    // RFIs are informational only — they carry no proposal value.
    const typeHasValue = proposalTypes.find(t => t.value === data.proposal_type)?.has_value ?? true;

    const toggleMethod = (value: string) => {
        setData('submission_methods', data.submission_methods.includes(value)
            ? data.submission_methods.filter(m => m !== value)
            : [...data.submission_methods, value]);
    };

    const toggleMember = (id: number) => {
        if (id === ownerId) return; // the owner is always on the team
        setData('team_member_ids', data.team_member_ids.includes(id)
            ? data.team_member_ids.filter(m => m !== id)
            : [...data.team_member_ids, id]);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/proposals/${proposal.id}`);
    };

    return (
        <AppLayout>
            <Head title={`Edit ${proposal.proposal_number}`} />
            <div className="mx-auto max-w-3xl p-6">
                <PageHeader
                    icon={FileText}
                    title={`Edit ${proposal.proposal_number}`}
                    description="Review and adjust the details, then save."
                    actions={<Button href={`/proposals/${proposal.id}`} variant="secondary" icon={ArrowLeft}>Back</Button>}
                />

                <Card>
                    <CardContent className="pt-5">
                        <form onSubmit={submit} className="space-y-5">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="label">Project Name *</label>
                                    <input type="text" value={data.project_name} onChange={e => setData('project_name', e.target.value)} className="input" required />
                                    {errors.project_name && <p className="mt-1 text-xs text-destructive">{errors.project_name}</p>}
                                </div>
                                <div>
                                    <label className="label">Type</label>
                                    <Select
                                        value={data.proposal_type}
                                        onChange={v => setData('proposal_type', v)}
                                        options={proposalTypes.map(t => ({ value: t.value, label: `${t.label} — ${t.description}` }))}
                                        className="w-full"
                                    />
                                    {errors.proposal_type && <p className="mt-1 text-xs text-destructive">{errors.proposal_type}</p>}
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="label">Solicitation #</label>
                                    <input type="text" value={data.solicitation_number} onChange={e => setData('solicitation_number', e.target.value)} className="input" />
                                </div>
                                <div>
                                    <label className="label">Company</label>
                                    <input type="text" value={data.company} onChange={e => setData('company', e.target.value)} className="input" placeholder="Client / buyer organization" />
                                    {errors.company && <p className="mt-1 text-xs text-destructive">{errors.company}</p>}
                                </div>
                            </div>

                            {typeHasValue ? (
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="label">Proposal Value ({symbol})</label>
                                        <div className="flex gap-2">
                                            <Select
                                                value={data.currency}
                                                onChange={v => setData('currency', v)}
                                                options={currencies.map(c => ({ value: c.value, label: c.value }))}
                                                className="w-28 shrink-0"
                                            />
                                            <NumberInput value={data.proposal_value} onChange={e => setData('proposal_value', e.target.value)} className="input flex-1" />
                                        </div>
                                        {errors.proposal_value && <p className="mt-1 text-xs text-destructive">{errors.proposal_value}</p>}
                                    </div>
                                    <div>
                                        <label className="label">Award Value ({symbol})</label>
                                        <NumberInput value={data.award_value} onChange={e => setData('award_value', e.target.value)} className="input" />
                                    </div>
                                </div>
                            ) : (
                                <p className="rounded-lg border border-border bg-secondary/40 px-3 py-2 text-xs text-muted-foreground">
                                    RFIs are informational only — no proposal value is tracked for this type.
                                </p>
                            )}

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div>
                                    <label className="label">Due Date</label>
                                    <input type="date" value={data.due_date} onChange={e => setData('due_date', e.target.value)} className="input" />
                                </div>
                                <div>
                                    <label className="label">Submission Date</label>
                                    <input type="date" value={data.submission_date} onChange={e => setData('submission_date', e.target.value)} className="input" />
                                </div>
                                <div>
                                    <label className="label">Award Date</label>
                                    <input type="date" value={data.award_date} onChange={e => setData('award_date', e.target.value)} className="input" />
                                </div>
                            </div>

                            {/* Revenue forecasting (Phase 4): drives the weighted pipeline. */}
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="label">Win Probability</label>
                                    <Select
                                        value={data.win_probability}
                                        onChange={v => setData('win_probability', v)}
                                        options={[
                                            { value: '', label: '— not set —' },
                                            { value: '10', label: '10%' },
                                            { value: '25', label: '25%' },
                                            { value: '50', label: '50%' },
                                            { value: '75', label: '75%' },
                                            { value: '90', label: '90%' },
                                            { value: '100', label: '100%' },
                                        ]}
                                    />
                                    {errors.win_probability && <p className="mt-1 text-xs text-destructive">{errors.win_probability}</p>}
                                </div>
                                <div>
                                    <label className="label">Expected Award Date</label>
                                    <input type="date" value={data.expected_award_date} onChange={e => setData('expected_award_date', e.target.value)} className="input" />
                                </div>
                            </div>

                            <div>
                                <label className="label">Status</label>
                                <Select
                                    value={data.status}
                                    onChange={v => setData('status', v)}
                                    options={statusOptions.map(s => ({ value: s.value, label: s.label }))}
                                    className="w-full sm:w-72"
                                />
                                <p className="mt-1 text-xs text-muted-foreground">You can set the status to any stage.</p>
                                {errors.status && <p className="mt-1 text-xs text-destructive">{errors.status}</p>}
                            </div>

                            <div>
                                <label className="label">Owner</label>
                                {isAdmin ? (
                                    <Select
                                        value={data.owner_id}
                                        onChange={v => setData('owner_id', v)}
                                        placeholder="Select owner…"
                                        options={users.map(u => ({ value: String(u.id), label: u.name }))}
                                        className="w-full"
                                    />
                                ) : (
                                    <input type="text" value={proposal.owner_name ?? 'Unassigned'} disabled className="input cursor-not-allowed opacity-70" />
                                )}
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {isAdmin ? 'The owner is the person responsible for this proposal.' : 'Only an administrator can change the owner.'}
                                </p>
                            </div>

                            <div>
                                <label className="label flex items-center gap-1.5"><Users className="h-3.5 w-3.5" /> Team members <span className="font-normal text-muted-foreground">(attach anyone collaborating)</span></label>
                                <div className="flex flex-wrap gap-2">
                                    {users.map(u => {
                                        const isOwner = u.id === ownerId;
                                        const active = isOwner || data.team_member_ids.includes(u.id);
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
                                <label className="label">Submission Method <span className="font-normal text-muted-foreground">(select all that apply)</span></label>
                                <div className="flex flex-wrap gap-2">
                                    {SUBMISSION_METHODS.map(m => {
                                        const active = data.submission_methods.includes(m.value);
                                        return (
                                            <button
                                                key={m.value}
                                                type="button"
                                                onClick={() => toggleMethod(m.value)}
                                                className={`rounded-full border px-3.5 py-1.5 text-sm font-medium transition ${active ? 'border-primary bg-primary/10 text-primary' : 'border-border bg-card text-muted-foreground hover:bg-secondary hover:text-foreground'}`}
                                            >
                                                {m.label}
                                            </button>
                                        );
                                    })}
                                </div>
                                {data.submission_methods.includes('portal') && (
                                    <div className="mt-3">
                                        <label className="label">Submission Portal Link</label>
                                        <input
                                            type="url"
                                            inputMode="url"
                                            value={data.submission_portal_url}
                                            onChange={e => setData('submission_portal_url', e.target.value)}
                                            placeholder="https://portal.example.gov/submit"
                                            className="input"
                                        />
                                        <p className="mt-1 text-xs text-muted-foreground">Paste the portal URL where this proposal is submitted.</p>
                                        {errors.submission_portal_url && <p className="mt-1 text-xs text-destructive">{errors.submission_portal_url}</p>}
                                    </div>
                                )}
                            </div>

                            <div>
                                <label className="label">Scope Summary</label>
                                <textarea value={data.scope_summary} onChange={e => setData('scope_summary', e.target.value)} rows={3} className="input" />
                            </div>

                            <div>
                                <label className="label">Description / Notes</label>
                                <textarea value={data.description} onChange={e => setData('description', e.target.value)} rows={3} className="input" />
                            </div>

                            <div>
                                <label className="label">Internal Notes</label>
                                <textarea value={data.notes} onChange={e => setData('notes', e.target.value)} rows={2} className="input" />
                            </div>

                            <div className="flex justify-end gap-3 pt-2">
                                <Button href={`/proposals/${proposal.id}`} variant="secondary">Cancel</Button>
                                <Button type="submit" disabled={processing}>{processing ? 'Saving…' : 'Save Changes'}</Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
