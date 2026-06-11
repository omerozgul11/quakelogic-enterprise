import { Head, Link, useForm } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Select } from '@/Components/ui/Select';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { cn, formatDateTime, formatRelativeDate } from '@/Lib/utils';
import { MessageSquare, Send, Plus, Sparkles, ExternalLink, Mail, X } from 'lucide-react';
import { useState } from 'react';

interface ThreadMessage {
    id: number;
    subject: string | null;
    message: string | null;
    type: string;
    status: string;
    scheduled_date: string | null;
    sent_at: string | null;
    created_at: string | null;
    author: string | null;
    contact: string | null;
    automated: boolean;
}

interface Thread {
    id: number;
    proposal_number: string;
    project_name: string;
    status: string;
    messages: ThreadMessage[];
    count: number;
    last_at: string | null;
}

interface Props {
    threads: Thread[];
    proposals: Array<{ id: number; proposal_number: string; project_name: string }>;
    mailbox: { connected: boolean; email: string | null };
}

const STATUS_DOT: Record<string, string> = {
    scheduled: 'bg-blue-500', sent: 'bg-emerald-500', responded: 'bg-violet-500', overdue: 'bg-rose-500', cancelled: 'bg-slate-400',
};

export default function FollowUpsIndex({ threads, proposals, mailbox }: Props) {
    const [selectedId, setSelectedId] = useState<number | null>(threads[0]?.id ?? null);
    const [composing, setComposing] = useState(false);

    const reply = useForm({ proposal_submission_id: '', subject: '', message: '' });
    const selected = threads.find(t => t.id === selectedId) ?? null;

    const sendReply = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selected || !reply.data.message.trim()) return;
        reply.transform(d => ({ ...d, proposal_submission_id: String(selected.id), type: 'note', subject: 'Re: ' + selected.project_name }));
        reply.post('/follow-ups', { preserveScroll: true, preserveState: true, onSuccess: () => reply.setData('message', '') });
    };

    const startConversation = (e: React.FormEvent) => {
        e.preventDefault();
        const pid = Number(reply.data.proposal_submission_id);
        if (!pid || !reply.data.message.trim()) return;
        reply.transform(d => ({ ...d, type: 'note', subject: d.subject || 'Follow-up' }));
        reply.post('/follow-ups', {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => { setComposing(false); reply.reset(); setSelectedId(pid); },
        });
    };

    return (
        <AppLayout>
            <Head title="Follow-Ups" />
            <div className="p-6">
                <PageHeader
                    icon={MessageSquare}
                    title="Follow-Ups"
                    description="Every proposal's follow-up thread in one place."
                    actions={<Button icon={Plus} onClick={() => { setComposing(true); reply.reset(); }}>New message</Button>}
                />

                {!mailbox.connected && (
                    <div className="mb-4 flex items-center gap-2 rounded-xl border border-border bg-secondary/40 px-4 py-2.5 text-sm text-muted-foreground">
                        <Mail className="h-4 w-4 shrink-0 text-primary" />
                        <span>Messages are tracked here as a thread. <Link href="/settings" className="font-medium text-primary hover:underline">Connect your work email</Link> to send them as real emails.</span>
                    </div>
                )}

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-[20rem_1fr]">
                    {/* Conversation list */}
                    <div className="card-surface overflow-hidden">
                        <div className="max-h-[calc(100vh-16rem)] overflow-y-auto">
                            {threads.length === 0 ? (
                                <div className="p-6"><EmptyState icon={MessageSquare} title="No threads yet" description="Start a conversation on one of your proposals." /></div>
                            ) : (
                                threads.map(t => {
                                    const last = t.messages[t.messages.length - 1];
                                    const preview = (last?.message || last?.subject || '').replace(/\s+/g, ' ').trim();
                                    const active = t.id === selectedId && !composing;
                                    return (
                                        <button
                                            key={t.id}
                                            onClick={() => { setSelectedId(t.id); setComposing(false); }}
                                            className={cn(
                                                'flex w-full flex-col gap-0.5 border-b border-border px-4 py-3 text-left transition-colors',
                                                active ? 'bg-primary/[0.06]' : 'hover:bg-secondary/50',
                                            )}
                                        >
                                            <div className="flex items-center gap-2">
                                                <span className="min-w-0 flex-1 truncate text-sm font-semibold text-foreground">{t.project_name}</span>
                                                <span className="shrink-0 text-[11px] text-muted-foreground">{formatRelativeDate(t.last_at)}</span>
                                            </div>
                                            <span className="truncate font-mono text-[11px] text-muted-foreground">{t.proposal_number}</span>
                                            {preview && <span className="truncate text-xs text-muted-foreground">{preview}</span>}
                                        </button>
                                    );
                                })
                            )}
                        </div>
                    </div>

                    {/* Thread / composer */}
                    <div className="card-surface flex max-h-[calc(100vh-16rem)] flex-col overflow-hidden">
                        {composing ? (
                            <form onSubmit={startConversation} className="flex flex-1 flex-col">
                                <div className="flex items-center justify-between border-b border-border px-5 py-3">
                                    <p className="text-sm font-semibold text-foreground">New message</p>
                                    <button type="button" onClick={() => setComposing(false)} className="text-muted-foreground hover:text-foreground"><X className="h-4 w-4" /></button>
                                </div>
                                <div className="space-y-4 p-5">
                                    <div>
                                        <label className="label">Proposal</label>
                                        <Select
                                            value={reply.data.proposal_submission_id}
                                            onChange={v => reply.setData('proposal_submission_id', v)}
                                            placeholder="Choose a proposal…"
                                            options={proposals.map(p => ({ value: String(p.id), label: `${p.proposal_number} — ${p.project_name}` }))}
                                            className="w-full"
                                        />
                                    </div>
                                    <div>
                                        <label className="label">Subject <span className="font-normal text-muted-foreground">(optional)</span></label>
                                        <input type="text" value={reply.data.subject} onChange={e => reply.setData('subject', e.target.value)} className="input" placeholder="Follow-up" />
                                    </div>
                                    <div>
                                        <label className="label">Message</label>
                                        <textarea value={reply.data.message} onChange={e => reply.setData('message', e.target.value)} rows={6} className="input" placeholder="Write your follow-up…" required />
                                    </div>
                                    <div className="flex justify-end gap-2">
                                        <Button type="button" variant="secondary" onClick={() => setComposing(false)}>Cancel</Button>
                                        <Button type="submit" icon={Send} disabled={reply.processing || !reply.data.proposal_submission_id || !reply.data.message.trim()}>Add to thread</Button>
                                    </div>
                                </div>
                            </form>
                        ) : selected ? (
                            <>
                                <div className="flex items-center justify-between gap-3 border-b border-border px-5 py-3">
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-semibold text-foreground">{selected.project_name}</p>
                                        <p className="font-mono text-[11px] text-muted-foreground">{selected.proposal_number}</p>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-2">
                                        <StatusBadge status={selected.status} />
                                        <Link href={`/proposals/${selected.id}`} className="text-muted-foreground transition-colors hover:text-primary" title="Open proposal"><ExternalLink className="h-4 w-4" /></Link>
                                    </div>
                                </div>

                                <div className="flex-1 space-y-3 overflow-y-auto p-5">
                                    {selected.messages.map(m => (
                                        <div key={m.id} className="rounded-xl border border-border p-4">
                                            <div className="mb-1 flex items-center gap-2">
                                                <span className={cn('h-2 w-2 shrink-0 rounded-full', STATUS_DOT[m.status] ?? 'bg-slate-400')} />
                                                <span className="text-sm font-semibold text-foreground">{m.subject || 'Follow-up'}</span>
                                                {m.automated && <span className="inline-flex items-center gap-1 rounded-full bg-primary/10 px-1.5 py-0.5 text-[10px] font-semibold text-primary"><Sparkles className="h-3 w-3" /> auto</span>}
                                                <span className="ml-auto text-[11px] capitalize text-muted-foreground">{m.status}</span>
                                            </div>
                                            {m.message && <p className="whitespace-pre-line text-sm leading-relaxed text-foreground">{m.message}</p>}
                                            <p className="mt-2 text-[11px] text-muted-foreground">
                                                {[m.author, m.contact ? `to ${m.contact}` : null, formatDateTime(m.created_at)].filter(Boolean).join(' · ')}
                                            </p>
                                        </div>
                                    ))}
                                </div>

                                <form onSubmit={sendReply} className="border-t border-border p-3">
                                    <div className="flex items-end gap-2">
                                        <textarea
                                            value={reply.data.message}
                                            onChange={e => reply.setData('message', e.target.value)}
                                            rows={2}
                                            className="input flex-1 resize-none"
                                            placeholder={`Reply on ${selected.proposal_number}…`}
                                            onKeyDown={e => { if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) sendReply(e); }}
                                        />
                                        <Button type="submit" icon={Send} disabled={reply.processing || !reply.data.message.trim()}>Send</Button>
                                    </div>
                                    <p className="mt-1 px-1 text-[11px] text-muted-foreground">⌘/Ctrl + Enter to send{mailbox.connected ? '' : ' · tracked as a thread'}</p>
                                </form>
                            </>
                        ) : (
                            <div className="flex flex-1 items-center justify-center p-8">
                                <EmptyState icon={MessageSquare} title="Select a conversation" description="Pick a proposal thread on the left, or start a new message." />
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
