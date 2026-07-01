import { Head, Link, router } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/Card';
import { formatCurrency, formatDate, sourceLabel } from '@/Lib/utils';
import { Select } from '@/Components/ui/Select';
import { Opportunity } from '@/Types';
import { ArrowLeft, Edit, Target, Building, ExternalLink, Rocket, Users, Mail, Phone, FileText, Eye, Download, Lock, Unlock, History, UserCheck, Clock, AlertTriangle } from 'lucide-react';
import { FilePreviewModal, PreviewFile } from '@/Components/ui/FilePreviewModal';
import { useState } from 'react';

const TONE: Record<string, string> = {
    gray: 'bg-slate-500/10 text-slate-600', slate: 'bg-slate-500/10 text-slate-600',
    blue: 'bg-blue-500/10 text-blue-600', indigo: 'bg-indigo-500/10 text-indigo-600',
    purple: 'bg-purple-500/10 text-purple-600', orange: 'bg-orange-500/10 text-orange-600',
    amber: 'bg-amber-500/10 text-amber-600', cyan: 'bg-cyan-500/10 text-cyan-600',
    green: 'bg-emerald-500/10 text-emerald-600', red: 'bg-rose-500/10 text-rose-600',
};
const tone = (c?: string) => TONE[c ?? 'gray'] ?? TONE.gray;

interface TimelineEntry { id: number; type: string; description: string; meta: Record<string, unknown> | null; user: string | null; at: string | null }
interface Lifecycle {
    stage: string | null; stage_label: string | null; stage_color: string | null;
    owner: { id: number; name: string; email: string | null } | null;
    assigned_to: { id: number; name: string; email: string | null } | null;
    ownership_locked: boolean;
    assigned_at: string | null; last_activity_at: string | null;
    days_since_activity: number | null; days_until_deadline: number | null;
    my_reaction: string | null; is_owner: boolean;
    my_match: { score: number; reasons: string[] | null; role: string | null } | null;
}
interface Choice { value: string; label: string; color?: string }
interface RecommendedOwner { user: string | null; score: number | null; role: string | null; reasons: string[] | null }
interface Health { score: number; category: 'healthy' | 'warning' | 'critical'; factors: Record<string, number> }

interface OppContact { id: number; name: string; title: string | null; email: string | null; phone: string | null }

interface SamDocument { index: number; name: string; preview_url: string; download_url: string }

interface Props {
    opportunity: Opportunity & {
        company?: { id: number; name: string } | null;
        assignments?: Array<{ id: number; user: { id: number; name: string } }>;
        competitors?: Array<{ id: number; company: { id: number; name: string } }>;
        proposals?: Array<{ id: number; proposal_number: string; status: string }>;
    };
    contacts: OppContact[];
    samDocuments: SamDocument[];
    samDocumentsPending?: boolean;
    timeline: TimelineEntry[];
    lifecycle: Lifecycle;
    recommendedOwners: RecommendedOwner[];
    health: Health;
    reactionOptions: Choice[];
    stageOptions: Choice[];
    assignableUsers: Array<{ id: number; name: string }>;
    can: { edit?: boolean; update?: boolean; delete: boolean; pursue: boolean; claim?: boolean; assign?: boolean; changeStage?: boolean };
}

const fmtAt = (at: string | null) => (at ? new Date(at).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : '');

function InfoRow({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="flex items-center justify-between gap-4 py-3 border-b border-border last:border-0">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="text-sm font-semibold text-foreground text-right">{value ?? '—'}</dd>
        </div>
    );
}

const HEALTH_TONE: Record<string, string> = { healthy: 'green', warning: 'amber', critical: 'red' };

export default function OpportunityShow({ opportunity, contacts, samDocuments, samDocumentsPending, timeline, lifecycle, recommendedOwners, health, reactionOptions, stageOptions, assignableUsers, can }: Props) {
    const application = opportunity.proposals?.[0];
    const [preview, setPreview] = useState<PreviewFile | null>(null);

    const handleDelete = () => {
        if (confirm('Delete this opportunity? This cannot be undone.')) {
            router.delete(`/opportunities/${opportunity.id}`);
        }
    };

    const pursue = () => router.post(`/opportunities/${opportunity.id}/pursue`);
    const act = (path: string, data: Record<string, string | number> = {}) =>
        router.post(`/opportunities/${opportunity.id}/${path}`, data, { preserveScroll: true });
    const release = () => {
        if (confirm('Release ownership? The opportunity will become unassigned.')) act('release');
    };

    const deadline = lifecycle.days_until_deadline;
    const deadlineAtRisk = deadline !== null && deadline <= 7;

    return (
        <AppLayout>
            <Head title={opportunity.title} />
            <div className="p-6 max-w-6xl mx-auto">
                <PageHeader
                    icon={Target}
                    title={opportunity.title}
                    description={opportunity.solicitation_number ? `Solicitation: ${opportunity.solicitation_number}` : undefined}
                    actions={
                        <>
                            <Button variant="secondary" icon={ArrowLeft} href="/opportunities">
                                Back
                            </Button>
                            {(can.update ?? can.edit) && (
                                <Button variant="secondary" icon={Edit} href={`/opportunities/${opportunity.id}/edit`}>
                                    Edit
                                </Button>
                            )}
                            {application ? (
                                <Button icon={Rocket} href={`/proposals/${application.id}`}>
                                    View Application
                                </Button>
                            ) : can.pursue && (
                                <Button icon={Rocket} onClick={pursue}>
                                    Pursue / Start Application
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Main Details */}
                    <div className="lg:col-span-2 space-y-6">
                        <Card className="animate-rise">
                            <CardHeader>
                                <CardTitle>Opportunity Details</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <dl>
                                    <InfoRow label="Company / Agency" value={opportunity.company?.name ?? opportunity.agency_name} />
                                    <InfoRow label="Estimated Value" value={formatCurrency(opportunity.estimated_value)} />
                                    <InfoRow label="Due Date" value={opportunity.due_date ? formatDate(opportunity.due_date) : null} />
                                    <InfoRow label="Posted Date" value={opportunity.posted_date ? formatDate(opportunity.posted_date) : null} />
                                    <InfoRow label="NAICS Code" value={opportunity.naics_code} />
                                    <InfoRow label="Set-Aside" value={opportunity.set_aside_type} />
                                    <InfoRow label="Place of Performance" value={opportunity.place_of_performance} />
                                    <InfoRow label="Source" value={<span className="chip">{sourceLabel(opportunity.source)}</span>} />
                                    <InfoRow label="SAM.gov" value={
                                        <a href={opportunity.sam_url} target="_blank" rel="noopener noreferrer"
                                            className="inline-flex items-center gap-1 text-primary hover:underline">
                                            View on SAM.gov <ExternalLink className="h-3 w-3" />
                                        </a>
                                    } />
                                    {opportunity.source_url && opportunity.source !== 'sam_gov' && (
                                        <InfoRow label="Source Link" value={
                                            <a href={opportunity.source_url} target="_blank" rel="noopener noreferrer"
                                                className="inline-flex items-center gap-1 text-primary hover:underline">
                                                View on {sourceLabel(opportunity.source)} <ExternalLink className="h-3 w-3" />
                                            </a>
                                        } />
                                    )}
                                </dl>
                            </CardContent>
                        </Card>

                        {opportunity.description && (
                            <Card className="animate-rise">
                                <CardHeader>
                                    <CardTitle>Description</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm text-muted-foreground whitespace-pre-wrap leading-relaxed">
                                        {opportunity.description}
                                    </p>
                                </CardContent>
                            </Card>
                        )}

                        {/* Solicitation documents pulled live from the SAM.gov record */}
                        <Card className="animate-rise">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <FileText className="h-4 w-4 text-muted-foreground" /> Solicitation Documents
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {samDocuments.length === 0 ? (
                                    <div className="text-sm text-muted-foreground">
                                        {opportunity.source === 'sam_gov' && samDocumentsPending ? (
                                            <p>We're still pulling this notice's solicitation documents from SAM.gov — check back shortly. In the meantime, open them directly on the notice.</p>
                                        ) : opportunity.source === 'sam_gov' ? (
                                            <p>This SAM.gov notice has no downloadable attachments — the details are in the description, or behind the notice's portal link.</p>
                                        ) : (
                                            <p>No attachments were pulled automatically — the solicitation documents are on the original posting.</p>
                                        )}
                                        {opportunity.source !== 'sam_gov' && opportunity.source_url ? (
                                            <a href={opportunity.source_url} target="_blank" rel="noopener noreferrer" className="mt-1.5 inline-flex items-center gap-1 font-medium text-primary hover:underline">
                                                Open the original posting on {sourceLabel(opportunity.source)} <ExternalLink className="h-3 w-3" />
                                            </a>
                                        ) : (
                                            <a href={opportunity.sam_url} target="_blank" rel="noopener noreferrer" className="mt-1.5 inline-flex items-center gap-1 font-medium text-primary hover:underline">
                                                View the full notice on SAM.gov <ExternalLink className="h-3 w-3" />
                                            </a>
                                        )}
                                    </div>
                                ) : (
                                    <div className="space-y-2">
                                        {samDocuments.map(d => (
                                            <div key={d.index} className="flex items-center gap-2 rounded-lg border border-border p-2.5 transition-colors hover:bg-secondary/50">
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
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Immutable opportunity timeline */}
                        <Card className="animate-rise">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <History className="h-4 w-4 text-muted-foreground" /> Timeline
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {timeline.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">No activity recorded yet.</p>
                                ) : (
                                    <div className="space-y-3">
                                        {timeline.map(e => (
                                            <div key={`${e.type}-${e.id}-${e.at}`} className="flex gap-3">
                                                <div className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-primary/50" />
                                                <div className="min-w-0 flex-1 border-b border-border pb-3 last:border-0 last:pb-0">
                                                    <p className="text-sm text-foreground">{e.description}</p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {e.user ? `${e.user} · ` : ''}{fmtAt(e.at)}
                                                    </p>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-4">
                        {/* Contacts — shown above the status bubbles */}
                        <Card className="animate-rise">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-sm">
                                    <Users className="h-4 w-4 text-muted-foreground" /> Contacts
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {contacts.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">No contacts yet. Pursue this opportunity and upload a document to extract them.</p>
                                ) : (
                                    <div className="space-y-3">
                                        {contacts.map(c => (
                                            <div key={c.id} className="border-b border-border pb-3 last:border-0 last:pb-0">
                                                <p className="text-sm font-medium text-foreground">{c.name}</p>
                                                {c.title && <p className="text-xs text-muted-foreground">{c.title}</p>}
                                                {c.email && (
                                                    <a href={`mailto:${c.email}`} className="mt-1 flex items-center gap-1.5 text-xs text-primary hover:underline">
                                                        <Mail className="h-3 w-3" /> {c.email}
                                                    </a>
                                                )}
                                                {c.phone && (
                                                    <p className="mt-0.5 flex items-center gap-1.5 text-xs text-muted-foreground">
                                                        <Phone className="h-3 w-3" /> {c.phone}
                                                    </p>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Ownership & assignment lifecycle */}
                        <Card className="animate-rise">
                            <CardHeader>
                                <CardTitle className="text-sm">Ownership &amp; Assignment</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex flex-wrap items-center gap-2">
                                    <StatusBadge status={opportunity.status} />
                                    {lifecycle.stage_label && (
                                        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${tone(lifecycle.stage_color ?? undefined)}`}>
                                            {lifecycle.stage_label}
                                        </span>
                                    )}
                                    <span className={`inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold ${tone(HEALTH_TONE[health.category])}`} title={`Deadline ${health.factors.deadline} · Activity ${health.factors.activity} · Progress ${health.factors.progress} · Engagement ${health.factors.engagement}`}>
                                        Health {health.score}
                                    </span>
                                </div>

                                {/* Owner / lock state */}
                                <div className="rounded-lg border border-border p-3 text-sm">
                                    {lifecycle.owner ? (
                                        <div className="flex items-center gap-2">
                                            {lifecycle.ownership_locked ? <Lock className="h-4 w-4 text-amber-500" /> : <UserCheck className="h-4 w-4 text-muted-foreground" />}
                                            <span className="text-foreground">
                                                Owned by <span className="font-semibold">{lifecycle.is_owner ? 'you' : lifecycle.owner.name}</span>
                                                {lifecycle.ownership_locked && <span className="text-muted-foreground"> · locked</span>}
                                            </span>
                                        </div>
                                    ) : (
                                        <p className="text-muted-foreground">Unassigned — no one owns this yet.</p>
                                    )}
                                    {(lifecycle.last_activity_at || deadline !== null) && (
                                        <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                            {lifecycle.last_activity_at && (
                                                <span className="inline-flex items-center gap-1"><Clock className="h-3 w-3" /> Active {fmtAt(lifecycle.last_activity_at)}</span>
                                            )}
                                            {deadline !== null && (
                                                <span className={`inline-flex items-center gap-1 ${deadlineAtRisk ? 'font-semibold text-rose-600' : ''}`}>
                                                    {deadlineAtRisk && <AlertTriangle className="h-3 w-3" />}
                                                    {deadline < 0 ? `${Math.abs(deadline)}d overdue` : deadline === 0 ? 'Due today' : `${deadline}d to deadline`}
                                                </span>
                                            )}
                                        </div>
                                    )}
                                </div>

                                {/* Claim / release */}
                                {lifecycle.is_owner ? (
                                    <Button variant="secondary" icon={Unlock} className="w-full" onClick={release}>Release ownership</Button>
                                ) : (
                                    can.claim && (
                                        <Button icon={Lock} className="w-full" onClick={() => act('claim')}>
                                            Claim &amp; start (In Progress)
                                        </Button>
                                    )
                                )}
                                {!lifecycle.is_owner && lifecycle.ownership_locked && !can.assign && (
                                    <p className="text-xs text-muted-foreground">This opportunity is locked to its owner. Ask an admin to reassign it.</p>
                                )}

                                {/* Stage picker (owner / manager) */}
                                {can.changeStage && lifecycle.owner && (
                                    <div>
                                        <label className="label">Work stage</label>
                                        <Select
                                            value={lifecycle.stage ?? ''}
                                            onChange={v => act('stage', { stage: v })}
                                            options={stageOptions.map(s => ({ value: s.value, label: s.label }))}
                                            className="w-full"
                                        />
                                    </div>
                                )}

                                {/* (Re)assign (manager only) */}
                                {can.assign && assignableUsers.length > 0 && (
                                    <div>
                                        <label className="label">Assign owner</label>
                                        <Select
                                            value={lifecycle.owner ? String(lifecycle.owner.id) : ''}
                                            onChange={v => act('assign', { user_id: v })}
                                            placeholder="Assign to…"
                                            options={assignableUsers.map(u => ({ value: String(u.id), label: u.name }))}
                                            className="w-full"
                                        />
                                    </div>
                                )}

                                {/* AI-recommended owners (managers) */}
                                {can.assign && recommendedOwners.length > 0 && (
                                    <div className="rounded-lg border border-border p-3">
                                        <p className="mb-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Recommended owners</p>
                                        <div className="space-y-1.5">
                                            {recommendedOwners.map((r, i) => (
                                                <div key={i} className="flex items-center justify-between gap-2 text-sm">
                                                    <span className="flex items-center gap-1.5 text-foreground">
                                                        <span className={`rounded px-1.5 py-0.5 text-[10px] font-bold uppercase ${r.role === 'primary' ? tone('green') : tone('blue')}`}>{r.role}</span>
                                                        {r.user}
                                                    </span>
                                                    {r.score !== null && <span className="font-semibold text-muted-foreground">{Math.round(r.score)}%</span>}
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                {/* This user's own fit */}
                                {lifecycle.my_match && (
                                    <div className="flex items-center gap-2 text-sm">
                                        <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${tone(lifecycle.my_match.score >= 60 ? 'green' : lifecycle.my_match.score >= 35 ? 'blue' : 'gray')}`}>
                                            {Math.round(lifecycle.my_match.score)}% match for you
                                        </span>
                                        {lifecycle.my_match.role && <span className="text-xs text-muted-foreground">★ recommended ({lifecycle.my_match.role})</span>}
                                    </div>
                                )}

                                {/* Personal reaction (triage) */}
                                <div>
                                    <label className="label">My status</label>
                                    <div className="flex flex-wrap gap-1.5">
                                        {reactionOptions.map(r => {
                                            const active = lifecycle.my_reaction === r.value;
                                            return (
                                                <button
                                                    key={r.value}
                                                    type="button"
                                                    onClick={() => act('react', { reaction: r.value })}
                                                    className={`rounded-full px-2.5 py-1 text-xs font-medium transition ${active ? tone(r.color) + ' ring-1 ring-inset ring-current' : 'border border-border text-muted-foreground hover:bg-secondary'}`}
                                                >
                                                    {r.label}
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Team */}
                        {opportunity.assignments && opportunity.assignments.length > 0 && (
                            <Card className="animate-rise">
                                <CardHeader>
                                    <CardTitle className="text-sm">Assigned Team</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-2">
                                        {opportunity.assignments.map(a => (
                                            <div key={a.id} className="flex items-center gap-2">
                                                <div className="bg-brand-gradient flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold text-white">
                                                    {a.user.name[0]}
                                                </div>
                                                <span className="text-sm text-foreground">{a.user.name}</span>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Competitors */}
                        {opportunity.competitors && opportunity.competitors.length > 0 && (
                            <Card className="animate-rise">
                                <CardHeader>
                                    <CardTitle className="text-sm">Known Competitors</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-2">
                                        {opportunity.competitors.map(c => (
                                            <div key={c.id} className="flex items-center gap-2 text-sm text-foreground">
                                                <Building className="h-3 w-3 text-muted-foreground" />
                                                {c.company.name}
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {can.delete && (
                            <Button variant="danger" className="w-full" onClick={handleDelete}>
                                Delete Opportunity
                            </Button>
                        )}
                    </div>
                </div>
            </div>
            <FilePreviewModal file={preview} onClose={() => setPreview(null)} />
        </AppLayout>
    );
}
