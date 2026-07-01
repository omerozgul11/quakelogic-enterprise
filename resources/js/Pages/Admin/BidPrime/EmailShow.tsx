import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { Button } from '@/Components/ui/Button';
import { Pill } from '@/Components/ui/Pill';
import { cn, formatDate, formatDateTime, formatCurrency } from '@/Lib/utils';
import { ArrowLeft, RefreshCw, Check, X, ExternalLink, AlertTriangle } from 'lucide-react';

interface Opportunity {
    id: number; title: string; agency_name: string | null; solicitation_number: string | null; naics_code: string | null;
    due_date: string | null; estimated_value: number | null; status: string | null;
    priority: string | null; priority_label: string | null; priority_color: string | null; relevance_score: number | null;
    matched_keywords: string[]; is_duplicate_flagged: boolean; bidprime_url: string | null; score_breakdown: Record<string, unknown> | null;
}
interface Item { item_id: number; action: string; external_id: string | null; title: string | null; opportunity: Opportunity | null }
interface Props {
    email: {
        id: number; subject: string | null; from: string | null; received_at: string | null; status: string;
        opportunities_found: number; parse_error: string | null; thread_id: string | null; gmail_message_id: string | null;
        raw_html: string | null; raw_text: string | null; processed_at: string | null;
    };
    opportunities: Item[];
}

const STATUS_COLOR: Record<string, string> = { parsed: 'green', no_opportunities: 'gray', failed: 'red', pending: 'amber', skipped: 'gray' };
const post = (url: string) => router.post(url, {}, { preserveScroll: true });

export default function BidPrimeEmailShow({ email, opportunities }: Props) {
    const [view, setView] = useState<'rendered' | 'text'>(email.raw_html ? 'rendered' : 'text');

    return (
        <AppLayout>
            <Head title={`${email.subject ?? 'Email'} · BidPrime`} />
            <div className="mx-auto max-w-5xl px-4 py-6 sm:px-6">
                <Link href="/admin/bidprime" className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" /> BidPrime intake
                </Link>

                <div className="card-surface mb-5 p-5">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                                <Pill color={STATUS_COLOR[email.status] ?? 'gray'} label={email.status.replace('_', ' ')} />
                                <h1 className="text-lg font-bold text-foreground">{email.subject ?? '(no subject)'}</h1>
                            </div>
                            <p className="mt-1 text-sm text-muted-foreground">{[email.from, email.received_at ? formatDateTime(email.received_at) : null, `${email.opportunities_found} opportunity(ies)`].filter(Boolean).join(' · ')}</p>
                            {email.gmail_message_id && <p className="mt-0.5 font-mono text-xs text-muted-foreground/70">{email.gmail_message_id}</p>}
                        </div>
                        <Button variant="secondary" icon={RefreshCw} onClick={() => post(`/admin/bidprime/emails/${email.id}/reprocess`)}>Reprocess</Button>
                    </div>
                    {email.parse_error && (
                        <div className="mt-3 flex items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">
                            <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" /> <span className="whitespace-pre-line">{email.parse_error}</span>
                        </div>
                    )}
                </div>

                <h2 className="mb-3 text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Extracted opportunities ({opportunities.length})</h2>
                {opportunities.length === 0 ? (
                    <div className="card-surface mb-6 p-8 text-center text-sm text-muted-foreground">No opportunities were extracted from this email.</div>
                ) : (
                    <div className="card-surface mb-6 divide-y divide-border p-0">
                        {opportunities.map(item => {
                            const o = item.opportunity;
                            return (
                                <div key={item.item_id} className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center">
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            {o?.priority_label && <Pill color={o.priority_color ?? 'gray'} label={o.priority_label} />}
                                            {o?.relevance_score != null && <span className="rounded-full bg-secondary px-1.5 text-xs font-medium text-muted-foreground">{o.relevance_score}</span>}
                                            {o
                                                ? <Link href={`/opportunities/${o.id}`} className="truncate text-sm font-medium text-foreground hover:text-primary">{o.title}</Link>
                                                : <span className="truncate text-sm font-medium text-foreground">{item.title}</span>}
                                            <span className="rounded-full bg-secondary px-2 py-0.5 text-[10px] font-semibold uppercase text-muted-foreground">{item.action.replace('_', ' ')}</span>
                                            {o?.is_duplicate_flagged && <span className="rounded-full bg-amber-500/10 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:text-amber-400">Duplicate</span>}
                                        </div>
                                        {o && <p className="mt-0.5 text-xs text-muted-foreground">{[o.agency_name, o.solicitation_number, o.naics_code ? `NAICS ${o.naics_code}` : null, o.due_date ? `due ${formatDate(o.due_date)}` : null, o.estimated_value ? formatCurrency(o.estimated_value) : null].filter(Boolean).join(' · ')}</p>}
                                        {o && o.matched_keywords.length > 0 && <p className="mt-1 flex flex-wrap gap-1">{o.matched_keywords.slice(0, 10).map(k => <span key={k} className="rounded bg-primary/10 px-1.5 text-[10px] font-medium text-primary">{k}</span>)}</p>}
                                    </div>
                                    {o && (
                                        <div className="flex shrink-0 items-center gap-1.5">
                                            {o.bidprime_url && <a href={o.bidprime_url} target="_blank" rel="noopener noreferrer" className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground" title="Open in BidPrime"><ExternalLink className="h-4 w-4" /></a>}
                                            <button onClick={() => post(`/admin/bidprime/opportunities/${o.id}/approve`)} className="inline-flex items-center gap-1 rounded-md bg-emerald-500/10 px-2 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-500/20 dark:text-emerald-400"><Check className="h-3.5 w-3.5" /> Approve</button>
                                            <button onClick={() => post(`/admin/bidprime/opportunities/${o.id}/reject`)} className="inline-flex items-center gap-1 rounded-md bg-destructive/10 px-2 py-1 text-xs font-medium text-destructive hover:bg-destructive/20"><X className="h-3.5 w-3.5" /> Reject</button>
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                )}

                {/* Original email (preserved) */}
                <div className="card-surface p-0">
                    <div className="flex items-center justify-between border-b border-border px-4 py-2.5">
                        <h2 className="text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Original email</h2>
                        {email.raw_html && email.raw_text && (
                            <div className="flex gap-1 text-xs">
                                <button onClick={() => setView('rendered')} className={cn('rounded px-2 py-1', view === 'rendered' ? 'bg-primary text-white' : 'bg-secondary text-muted-foreground')}>Rendered</button>
                                <button onClick={() => setView('text')} className={cn('rounded px-2 py-1', view === 'text' ? 'bg-primary text-white' : 'bg-secondary text-muted-foreground')}>Text</button>
                            </div>
                        )}
                    </div>
                    <div className="p-4">
                        {view === 'rendered' && email.raw_html
                            ? <iframe title="Original email" sandbox="" className="h-[480px] w-full rounded-lg border border-border bg-white" srcDoc={email.raw_html} />
                            : <pre className="max-h-[480px] overflow-auto whitespace-pre-wrap rounded-lg bg-secondary/40 p-3 text-xs text-foreground">{email.raw_text ?? email.raw_html ?? '(empty)'}</pre>}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
