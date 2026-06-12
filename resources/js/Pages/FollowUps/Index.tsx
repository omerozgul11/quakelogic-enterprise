import { Head, Link, useForm } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Select } from '@/Components/ui/Select';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { cn, formatDateTime, formatRelativeDate } from '@/Lib/utils';
import { MessageSquare, Send, Plus, Sparkles, ExternalLink, Mail, X, User as UserIcon, FileText } from 'lucide-react';
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
    to: string | null;
    mine: boolean;
    contact: string | null;
    automated: boolean;
}

interface Thread {
    key: string;
    kind: 'proposal' | 'general' | 'direct';
    proposal_id: number | null;
    recipient_id: number | null;
    proposal_number: string | null;
    project_name: string;
    status: string | null;
    messages: ThreadMessage[];
    count: number;
    last_at: string | null;
}

interface Props {
    threads: Thread[];
    proposals: Array<{ id: number; proposal_number: string; project_name: string }>;
    users: Array<{ id: number; name: string }>;
    mailbox: { connected: boolean; email: string | null };
}

const STATUS_DOT: Record<string, string> = {
    scheduled: 'bg-blue-500', sent: 'bg-emerald-500', responded: 'bg-violet-500', overdue: 'bg-rose-500', cancelled: 'bg-slate-400',
};

export default function FollowUpsIndex({ threads, proposals, users, mailbox }: Props) {
    const [selectedKey, setSelectedKey] = useState<string | null>(threads[0]?.key ?? null);
    const [composing, setComposing] = useState(false);
    const [target, setTarget] = useState('general');

    const reply = useForm({ subject: '', message: '' });
    const selected = threads.find(t => t.key === selectedKey) ?? null;

    // Reply within the open conversation — replies to the same coworker /
    // proposal the thread belongs to.
    const sendReply = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selected || !reply.data.message.trim()) return;
        reply.transform(d => ({
            ...d,
            proposal_submission_id: selected.kind === 'proposal' ? String(selected.proposal_id) : '',
            assigned_to: selected.kind === 'direct' ? String(selected.recipient_id) : '',
            type: selected.kind === 'direct' ? 'message' : 'note',
            subject: selected.kind === 'proposal' ? 'Re: ' + selected.project_name
                : selected.kind === 'direct' ? 'Message' : 'Note',
        }));
        reply.post('/follow-ups', { preserveScroll: true, preserveState: true, onSuccess: () => reply.setData('message', '') });
    };

    // New message — "To" can be a coworker (direct), the general stream, or a proposal.
    const startConversation = (e: React.FormEvent) => {
        e.preventDefault();
        if (!reply.data.message.trim()) return;
        const [kind, idStr] = target.includes(':') ? target.split(':') : [target, ''];
        const landingKey = kind === 'user' ? `direct-${idStr}` : kind === 'proposal' ? `proposal-${idStr}` : 'general';
        reply.transform(d => ({
            ...d,
            proposal_submission_id: kind === 'proposal' ? idStr : '',
            assigned_to: kind === 'user' ? idStr : '',
            type: kind === 'user' ? 'message' : 'note',
            subject: d.subject || (kind === 'user' ? 'Message' : kind === 'proposal' ? 'Follow-up' : 'Note'),
        }));
        reply.post('/follow-ups', {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => { setComposing(false); reply.reset(); setTarget('general'); setSelectedKey(landingKey); },
        });
    };

    const targetOptions = [
        ...users.map(u => ({ value: `user:${u.id}`, label: `${u.name}` })),
        { value: 'general', label: 'General — note to self' },
        ...proposals.map(p => ({ value: `proposal:${p.id}`, label: `${p.proposal_number} — ${p.project_name}` })),
    ];

    const replyPlaceholder = !selected ? ''
        : selected.kind === 'proposal' ? `Reply on ${selected.proposal_number}…`
        : selected.kind === 'direct' ? `Message ${selected.project_name}…`
        : 'Write a note…';

    return (
        <AppLayout>
            <Head title="Inbox" />
            <div className="p-6">
                <PageHeader
                    icon={MessageSquare}
                    title="Inbox"
                    description="Message your team, track proposal follow-ups, and see your updates — all in one place."
                    actions={<Button icon={Plus} onClick={() => { setComposing(true); reply.reset(); }}>New message</Button>}
                />

                {!mailbox.connected && (
                    <div className="mb-4 flex items-center gap-2 rounded-xl border border-border bg-secondary/40 px-4 py-2.5 text-sm text-muted-foreground">
                        <Mail className="h-4 w-4 shrink-0 text-primary" />
                        <span>Messages to teammates are delivered in-app instantly. <Link href="/settings" className="font-medium text-primary hover:underline">Connect your work email</Link> to also send client follow-ups as real emails.</span>
                    </div>
                )}

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-[20rem_1fr]">
                    {/* Conversation list */}
                    <div className="card-surface overflow-hidden">
                        <div className="max-h-[calc(100vh-16rem)] overflow-y-auto">
                            {threads.map(t => {
                                const last = t.messages[t.messages.length - 1];
                                const preview = (last?.message || last?.subject || '').replace(/\s+/g, ' ').trim();
                                const active = t.key === selectedKey && !composing;
                                const Icon = t.kind === 'direct' ? UserIcon : t.kind === 'proposal' ? FileText : MessageSquare;
                                return (
                                    <button
                                        key={t.key}
                                        onClick={() => { setSelectedKey(t.key); setComposing(false); }}
                                        className={cn(
                                            'flex w-full flex-col gap-0.5 border-b border-border px-4 py-3 text-left transition-colors',
                                            active ? 'bg-primary/[0.06]' : 'hover:bg-secondary/50',
                                        )}
                                    >
                                        <div className="flex items-center gap-2">
                                            <Icon className="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                                            <span className="min-w-0 flex-1 truncate text-sm font-semibold text-foreground">{t.project_name}</span>
                                            <span className="shrink-0 text-[11px] text-muted-foreground">{t.last_at ? formatRelativeDate(t.last_at) : ''}</span>
                                        </div>
                                        <span className="truncate pl-5 font-mono text-[11px] text-muted-foreground">
                                            {t.proposal_number ?? (t.kind === 'direct' ? 'Direct message' : `${t.count} message${t.count === 1 ? '' : 's'}`)}
                                        </span>
                                        {preview && <span className="truncate pl-5 text-xs text-muted-foreground">{preview}</span>}
                                    </button>
                                );
                            })}
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
                                        <label className="label">To</label>
                                        <Select
                                            value={target}
                                            onChange={v => setTarget(v)}
                                            options={targetOptions}
                                            className="w-full"
                                        />
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Pick a teammate to send a direct message, or a proposal to add to its thread.
                                        </p>
                                    </div>
                                    <div>
                                        <label className="label">Subject <span className="font-normal text-muted-foreground">(optional)</span></label>
                                        <input type="text" value={reply.data.subject} onChange={e => reply.setData('subject', e.target.value)} className="input" placeholder="Subject" />
                                    </div>
                                    <div>
                                        <label className="label">Message</label>
                                        <textarea value={reply.data.message} onChange={e => reply.setData('message', e.target.value)} rows={6} className="input" placeholder="Write your message…" required />
                                    </div>
                                    <div className="flex justify-end gap-2">
                                        <Button type="button" variant="secondary" onClick={() => setComposing(false)}>Cancel</Button>
                                        <Button type="submit" icon={Send} disabled={reply.processing || !reply.data.message.trim()}>Send</Button>
                                    </div>
                                </div>
                            </form>
                        ) : selected ? (
                            <>
                                <div className="flex items-center justify-between gap-3 border-b border-border px-5 py-3">
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-semibold text-foreground">{selected.project_name}</p>
                                        {selected.proposal_number
                                            ? <p className="font-mono text-[11px] text-muted-foreground">{selected.proposal_number}</p>
                                            : <p className="text-[11px] text-muted-foreground">{selected.kind === 'direct' ? 'Direct message' : 'Notes & updates'}</p>}
                                    </div>
                                    {selected.kind === 'proposal' && (
                                        <div className="flex shrink-0 items-center gap-2">
                                            {selected.status && <StatusBadge status={selected.status} />}
                                            <Link href={`/proposals/${selected.proposal_id}`} className="text-muted-foreground transition-colors hover:text-primary" title="Open proposal"><ExternalLink className="h-4 w-4" /></Link>
                                        </div>
                                    )}
                                </div>

                                <div className="flex-1 space-y-3 overflow-y-auto p-5">
                                    {selected.messages.length === 0 && (
                                        <p className="py-8 text-center text-sm text-muted-foreground">No messages yet — say hello below.</p>
                                    )}
                                    {selected.messages.map(m => (
                                        <div key={m.id} className={cn('rounded-xl border p-4', m.mine ? 'border-primary/30 bg-primary/[0.04]' : 'border-border')}>
                                            <div className="mb-1 flex items-center gap-2">
                                                <span className={cn('h-2 w-2 shrink-0 rounded-full', STATUS_DOT[m.status] ?? 'bg-slate-400')} />
                                                <span className="text-sm font-semibold text-foreground">{m.subject || 'Message'}</span>
                                                {m.automated && <span className="inline-flex items-center gap-1 rounded-full bg-primary/10 px-1.5 py-0.5 text-[10px] font-semibold text-primary"><Sparkles className="h-3 w-3" /> auto</span>}
                                            </div>
                                            {m.message && <p className="whitespace-pre-line text-sm leading-relaxed text-foreground">{m.message}</p>}
                                            <p className="mt-2 text-[11px] text-muted-foreground">
                                                {[m.mine ? 'You' : m.author, m.contact ? `to ${m.contact}` : null, formatDateTime(m.created_at)].filter(Boolean).join(' · ')}
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
                                            placeholder={replyPlaceholder}
                                            onKeyDown={e => { if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) sendReply(e); }}
                                        />
                                        <Button type="submit" icon={Send} disabled={reply.processing || !reply.data.message.trim()}>Send</Button>
                                    </div>
                                    <p className="mt-1 px-1 text-[11px] text-muted-foreground">
                                        ⌘/Ctrl + Enter to send{selected.kind === 'direct' ? ' · delivered in-app to your teammate' : ''}
                                    </p>
                                </form>
                            </>
                        ) : (
                            <div className="flex flex-1 items-center justify-center p-8">
                                <EmptyState icon={MessageSquare} title="Select a conversation" description="Pick a conversation on the left, or start a new message." />
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
