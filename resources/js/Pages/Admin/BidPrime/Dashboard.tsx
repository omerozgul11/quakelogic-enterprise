import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Pill } from '@/Components/ui/Pill';
import { Select } from '@/Components/ui/Select';
import { cn, formatDate, formatDateTime, formatCurrency } from '@/Lib/utils';
import { Inbox, RefreshCw, DownloadCloud, Check, X, ExternalLink, AlertTriangle, Mail, Tags } from 'lucide-react';

interface Opportunity {
    id: number; title: string; agency_name: string | null; solicitation_number: string | null; naics_code: string | null;
    due_date: string | null; estimated_value: number | null; status: string | null;
    priority: string | null; priority_label: string | null; priority_color: string | null; relevance_score: number | null;
    matched_keywords: string[]; is_duplicate_flagged: boolean; bidprime_url: string | null;
}
interface EmailRow { id: number; subject: string | null; from: string | null; received_at: string | null; status: string; opportunities_found: number; parse_error: string | null }
interface ImportRow { id: number; status: string; channel: string; created: number; updated: number; skipped: number; errors: number; started_at: string | null; completed_at: string | null }
interface Props {
    stats: {
        emails_total: number; emails_parsed: number; emails_failed: number; emails_no_opps: number;
        opps_total: number; priority: { high: number; medium: number; low: number; not_relevant: number }; duplicates: number;
    };
    emails: EmailRow[];
    imports: ImportRow[];
    opportunities: { data: Opportunity[]; links: Array<{ url: string | null; label: string; active: boolean }> };
    priorities: Array<{ value: string; label: string; color: string }>;
    filters: { priority: string };
    inboxConfigured: boolean;
}

const EMAIL_STATUS_COLOR: Record<string, string> = { parsed: 'green', no_opportunities: 'gray', failed: 'red', pending: 'amber', skipped: 'gray' };
const post = (url: string) => router.post(url, {}, { preserveScroll: true });

export default function BidPrimeDashboard({ stats, emails, imports, opportunities, priorities, filters, inboxConfigured }: Props) {
    const [busy, setBusy] = useState(false);
    const action = (url: string) => router.post(url, {}, { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false) });

    const tiles: Array<[string, number | string, string]> = [
        ['Emails', stats.emails_total, 'text-foreground'],
        ['Parsed', stats.emails_parsed, 'text-emerald-600 dark:text-emerald-400'],
        ['Failed', stats.emails_failed, 'text-red-600 dark:text-red-400'],
        ['Opportunities', stats.opps_total, 'text-foreground'],
        ['High priority', stats.priority.high, 'text-red-600 dark:text-red-400'],
        ['Duplicates', stats.duplicates, 'text-amber-600 dark:text-amber-400'],
    ];

    return (
        <AppLayout>
            <Head title="BidPrime Intake · Admin" />
            <div className="mx-auto max-w-6xl px-4 py-6 sm:px-6">
                <PageHeader
                    eyebrow="Admin"
                    title="BidPrime email intake"
                    description="Opportunities parsed from BidPrime alert emails — review, reprocess and triage."
                    icon={Inbox}
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <a href="/admin/keyword-groups"><Button variant="secondary" icon={Tags}>Keyword Groups</Button></a>
                            <Button variant="secondary" icon={RefreshCw} onClick={() => action('/admin/bidprime/reprocess-failed')} disabled={busy}>Reprocess failed</Button>
                            <Button variant="secondary" icon={RefreshCw} onClick={() => action('/admin/bidprime/reprocess-recent')} disabled={busy}>Reprocess 7 days</Button>
                            <Button icon={DownloadCloud} onClick={() => action('/admin/bidprime/import-now')} disabled={busy}>{busy ? 'Working…' : 'Import now'}</Button>
                        </div>
                    }
                />

                {!inboxConfigured && (
                    <div className="mb-5 flex items-start gap-2 rounded-xl border border-amber-500/30 bg-amber-500/5 p-3 text-sm text-amber-700 dark:text-amber-400">
                        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                        <span>Using the <strong>fake inbox</strong> (demo data). Set <code>GMAIL_INGEST_ENABLED=true</code> and a Gmail App Password to read the live mailbox.</span>
                    </div>
                )}

                <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    {tiles.map(([label, value, tone]) => (
                        <div key={label} className="card-surface p-4">
                            <p className="text-xs text-muted-foreground">{label}</p>
                            <p className={cn('mt-1 text-2xl font-bold', tone)}>{value}</p>
                        </div>
                    ))}
                </div>

                {/* Opportunities by priority */}
                <section className="mb-8">
                    <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <h2 className="text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Parsed opportunities</h2>
                        <Select className="w-48" value={filters.priority} onChange={v => router.get('/admin/bidprime', v ? { priority: v } : {}, { preserveScroll: true, preserveState: true })}
                            placeholder="All priorities" options={priorities.map(p => ({ value: p.value, label: p.label }))} />
                    </div>
                    {opportunities.data.length === 0 ? (
                        <div className="card-surface p-8 text-center text-sm text-muted-foreground">No opportunities{filters.priority ? ' at this priority' : ''} yet.</div>
                    ) : (
                        <div className="card-surface divide-y divide-border p-0">
                            {opportunities.data.map(o => (
                                <div key={o.id} className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center">
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            {o.priority_label && <Pill color={o.priority_color ?? 'gray'} label={o.priority_label} />}
                                            {o.relevance_score != null && <span className="rounded-full bg-secondary px-1.5 text-xs font-medium text-muted-foreground">{o.relevance_score}</span>}
                                            <Link href={`/opportunities/${o.id}`} className="truncate text-sm font-medium text-foreground hover:text-primary">{o.title}</Link>
                                            {o.is_duplicate_flagged && <span className="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:text-amber-400">Duplicate</span>}
                                            {o.status && <span className="rounded-full bg-secondary px-2 py-0.5 text-[10px] font-semibold uppercase text-muted-foreground">{o.status.replace('_', ' ')}</span>}
                                        </div>
                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                            {[o.agency_name, o.solicitation_number, o.naics_code ? `NAICS ${o.naics_code}` : null, o.due_date ? `due ${formatDate(o.due_date)}` : null, o.estimated_value ? formatCurrency(o.estimated_value) : null].filter(Boolean).join(' · ')}
                                        </p>
                                        {o.matched_keywords.length > 0 && <p className="mt-1 flex flex-wrap gap-1">{o.matched_keywords.slice(0, 8).map(k => <span key={k} className="rounded bg-primary/10 px-1.5 text-[10px] font-medium text-primary">{k}</span>)}</p>}
                                    </div>
                                    <div className="flex shrink-0 items-center gap-1.5">
                                        {o.bidprime_url && <a href={o.bidprime_url} target="_blank" rel="noopener noreferrer" className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground" title="Open in BidPrime"><ExternalLink className="h-4 w-4" /></a>}
                                        <button onClick={() => post(`/admin/bidprime/opportunities/${o.id}/approve`)} className="inline-flex items-center gap-1 rounded-md bg-emerald-500/10 px-2 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-500/20 dark:text-emerald-400" title="Approve (qualify)"><Check className="h-3.5 w-3.5" /> Approve</button>
                                        <button onClick={() => post(`/admin/bidprime/opportunities/${o.id}/reject`)} className="inline-flex items-center gap-1 rounded-md bg-destructive/10 px-2 py-1 text-xs font-medium text-destructive hover:bg-destructive/20" title="Reject (no-bid)"><X className="h-3.5 w-3.5" /> Reject</button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                    {opportunities.links.length > 3 && (
                        <div className="mt-3 flex flex-wrap gap-1">
                            {opportunities.links.map((l, i) => l.url
                                ? <button key={i} onClick={() => router.get(l.url!, {}, { preserveScroll: true, preserveState: true })} className={cn('rounded px-2 py-1 text-xs', l.active ? 'bg-primary text-white' : 'bg-secondary text-muted-foreground hover:text-foreground')} dangerouslySetInnerHTML={{ __html: l.label }} />
                                : <span key={i} className="px-2 py-1 text-xs text-muted-foreground/50" dangerouslySetInnerHTML={{ __html: l.label }} />)}
                        </div>
                    )}
                </section>

                {/* Imported emails */}
                <section className="mb-8">
                    <h2 className="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70"><Mail className="h-4 w-4" /> Imported emails</h2>
                    {emails.length === 0 ? (
                        <div className="card-surface p-8 text-center text-sm text-muted-foreground">No emails imported yet — use “Import now”.</div>
                    ) : (
                        <div className="card-surface divide-y divide-border p-0">
                            {emails.map(e => (
                                <div key={e.id} className="flex items-center gap-3 px-4 py-3">
                                    <div className="min-w-0 flex-1">
                                        <Link href={`/admin/bidprime/emails/${e.id}`} className="flex items-center gap-2 truncate text-sm font-medium text-foreground hover:text-primary">
                                            <Pill color={EMAIL_STATUS_COLOR[e.status] ?? 'gray'} label={e.status.replace('_', ' ')} />
                                            <span className="truncate">{e.subject ?? '(no subject)'}</span>
                                        </Link>
                                        <p className="text-xs text-muted-foreground">{[e.from, e.received_at ? formatDateTime(e.received_at) : null, `${e.opportunities_found} opp(s)`].filter(Boolean).join(' · ')}{e.parse_error ? ` · ${e.parse_error}` : ''}</p>
                                    </div>
                                    <button onClick={() => post(`/admin/bidprime/emails/${e.id}/reprocess`)} className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground" title="Reprocess"><RefreshCw className="h-4 w-4" /></button>
                                </div>
                            ))}
                        </div>
                    )}
                </section>

                {/* Import logs */}
                <section>
                    <h2 className="mb-3 text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Import logs</h2>
                    {imports.length === 0 ? (
                        <div className="card-surface p-8 text-center text-sm text-muted-foreground">No import runs yet.</div>
                    ) : (
                        <div className="card-surface divide-y divide-border p-0">
                            {imports.map(i => (
                                <div key={i.id} className="flex items-center gap-3 px-4 py-2.5 text-sm">
                                    <span className="font-mono text-xs text-muted-foreground">#{i.id}</span>
                                    <Pill color={i.status === 'completed' ? 'green' : i.status === 'failed' ? 'red' : 'amber'} label={i.status} />
                                    <span className="rounded bg-secondary px-1.5 text-[10px] font-semibold uppercase text-muted-foreground">{i.channel}</span>
                                    <span className="text-muted-foreground">{i.created} created · {i.updated} updated · {i.skipped} dup · {i.errors} err</span>
                                    <span className="ml-auto text-xs text-muted-foreground">{i.completed_at ? formatDateTime(i.completed_at) : (i.started_at ? formatDateTime(i.started_at) : '')}</span>
                                </div>
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
