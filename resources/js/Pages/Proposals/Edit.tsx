import { Head, useForm } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card, CardContent } from '@/Components/ui/Card';
import { Select } from '@/Components/ui/Select';
import { ArrowLeft, FileText, Users } from 'lucide-react';

interface CurrencyOption { value: string; label: string; symbol: string; name: string }

interface Props {
    proposal: {
        id: number;
        proposal_number: string;
        project_name: string;
        solicitation_number: string | null;
        company: string;
        proposal_value: string | number | null;
        currency: string;
        due_date: string | null;
        status: string;
        owner_id: number | null;
        owner_name: string | null;
        team_member_ids: number[];
        description: string | null;
        scope_summary: string | null;
        notes: string | null;
        submission_methods: string[];
    };
    users: Array<{ id: number; name: string }>;
    currencies: CurrencyOption[];
    statusOptions: Array<{ value: string; label: string }>;
    isAdmin: boolean;
}

const SUBMISSION_METHODS: Array<{ value: string; label: string }> = [
    { value: 'email', label: 'Email' },
    { value: 'portal', label: 'Portal' },
    { value: 'mail', label: 'Mail' },
    { value: 'fax', label: 'Fax' },
    { value: 'hand_delivery', label: 'Hand delivery' },
];

export default function ProposalEdit({ proposal, users, currencies, statusOptions, isAdmin }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        project_name: proposal.project_name ?? '',
        solicitation_number: proposal.solicitation_number ?? '',
        company: proposal.company ?? '',
        proposal_value: proposal.proposal_value != null ? String(proposal.proposal_value) : '',
        currency: proposal.currency ?? 'USD',
        status: proposal.status ?? '',
        due_date: proposal.due_date ?? '',
        owner_id: proposal.owner_id ? String(proposal.owner_id) : '',
        team_member_ids: proposal.team_member_ids ?? [],
        scope_summary: proposal.scope_summary ?? '',
        description: proposal.description ?? '',
        notes: proposal.notes ?? '',
        submission_methods: proposal.submission_methods ?? [],
    });

    const ownerId = Number(data.owner_id);
    const symbol = currencies.find(c => c.value === data.currency)?.symbol ?? '$';

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
                            <div>
                                <label className="label">Project Name *</label>
                                <input type="text" value={data.project_name} onChange={e => setData('project_name', e.target.value)} className="input" required />
                                {errors.project_name && <p className="mt-1 text-xs text-destructive">{errors.project_name}</p>}
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
                                        <input type="number" value={data.proposal_value} onChange={e => setData('proposal_value', e.target.value)} className="input flex-1" min="0" step="0.01" />
                                    </div>
                                    {errors.proposal_value && <p className="mt-1 text-xs text-destructive">{errors.proposal_value}</p>}
                                </div>
                                <div>
                                    <label className="label">Due Date</label>
                                    <input type="date" value={data.due_date} onChange={e => setData('due_date', e.target.value)} className="input" />
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
                                <p className="mt-1 text-xs text-muted-foreground">Only the statuses you can move to next are shown.</p>
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
