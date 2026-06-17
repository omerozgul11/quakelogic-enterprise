import { Head, Link, router, useForm } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Select } from '@/Components/ui/Select';
import { Checkbox } from '@/Components/ui/Checkbox';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { cn, formatDateTime, formatRelativeDate } from '@/Lib/utils';
import { MessageSquare, Send, Plus, Sparkles, ExternalLink, Mail, X, User as UserIcon, FileText, Pin, Trash2, CheckCheck } from 'lucide-react';
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
    unread: boolean;
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
    pinned: boolean;
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

    // Message ids that were unread when this inbox first loaded — keep their dot
    // visible for the session even after we mark them read on open, so you can see
    // what was new. Thread keys opened this session clear the list dot instantly.
    const [seenUnread] = useState<Set<number>>(() => new Set(threads.flatMap(t => t.messages.filter(m => m.unread).map(m => m.id))));
    const [openedKeys, setOpenedKeys] = useState<Set<string>>(new Set());

    const msgUnread = (m: ThreadMessage) => m.unread || seenUnread.has(m.id);
    const threadUnread = (t: Thread) => !openedKeys.has(t.key) && t.messages.some(m => m.unread);

    const reply = useForm({ subject: '', message: '' });
    const selected = threads.find(t => t.key === selectedKey) ?? null;

    // Bulk selection happens in the conversation list on the left: tick one or
    // more conversations, then mark them read or delete them. Selection is by
    // thread key; the message ids are gathered from the selected threads.
    const [selectedKeys, setSelectedKeys] = useState<Set<string>>(new Set());
    const clearSelection = () => setSelectedKeys(new Set());
    const toggleThread = (key: string) => setSelectedKeys(prev => {
        const next = new Set(prev);
        next.has(key) ? next.delete(key) : next.add(key);
        return next;
    });

    const allKeys = threads.map(t => t.key);
    const allSelected = allKeys.length > 0 && allKeys.every(k => selectedKeys.has(k));
    const toggleSelectAll = () => setSelectedKeys(prev =>
        allKeys.length > 0 && allKeys.every(k => prev.has(k)) ? new Set() : new Set(allKeys));

    const selectedMessageIds = () =>
        threads.filter(t => selectedKeys.has(t.key)).flatMap(t => t.messages.map(m => m.id));

    const markSelectedRead = () => {
        const ids = selectedMessageIds();
        if (!ids.length) { clearSelection(); return; }
        router.post('/follow-ups/read', { ids }, { preserveScroll: true, onSuccess: clearSelection });
    };

    const deleteIds = (ids: number[], confirmText: string) => {
        if (!ids.length) return;
        if (!confirm(confirmText)) return;
        router.post('/follow-ups/delete', { ids }, { preserveScroll: true, onSuccess: clearSelection });
    };

    const deleteSelected = () => {
        const ids = selectedMessageIds();
        const n = selectedKeys.size;
        deleteIds(ids, `Delete ${n} conversation${n === 1 ? '' : 's'} (${ids.length} message${ids.length === 1 ? '' : 's'})? This cannot be undone.`);
    };

    // Open a conversation; mark its unread messages read (clears the nav badge and
    // this thread's list dot) without disturbing the per-message dots in view.
    const togglePin = (key: string) => {
        router.post('/follow-ups/pin', { key }, { preserveScroll: true, preserveState: true, only: ['threads'] });
    };

    const openThread = (key: string) => {
        setSelectedKey(key);
        setComposing(false);
        const t = threads.find(x => x.key === key);
        const unreadIds = t ? t.messages.filter(m => m.unread).map(m => m.id) : [];
        if (unreadIds.length) {
            setOpenedKeys(prev => new Set(prev).add(key));
            router.post('/follow-ups/read', { ids: unreadIds }, { preserveScroll: true, preserveState: true, only: ['inbox_unread_count'] });
        }
    };

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
        { value: 'general', label: 'Daily Summary — note to self' },
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
                    <div className="card-surface flex max-h-[calc(100vh-16rem)] flex-col overflow-hidden">
                        {/* Bulk action bar — appears once one or more conversations are ticked */}
                        {selectedKeys.size > 0 && (
                            <div className="flex items-center gap-2 border-b border-border bg-secondary/40 px-3 py-2">
                                <Checkbox
                                    checked={allSelected}
                                    indeterminate={!allSelected}
                                    onChange={toggleSelectAll}
                                    ariaLabel="Select all conversations"
                                    title={allSelected ? 'Clear selection' : 'Select all'}
                                />
                                <span className="text-xs font-medium text-foreground">{selectedKeys.size} selected</span>
                                <div className="ml-auto flex items-center gap-1">
                                    <button type="button" onClick={markSelectedRead} title="Mark as read"
                                        className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground">
                                        <CheckCheck className="h-4 w-4" />
                                    </button>
                                    <button type="button" onClick={deleteSelected} title="Delete"
                                        className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive">
                                        <Trash2 className="h-4 w-4" />
                                    </button>
                                    <button type="button" onClick={clearSelection} title="Clear selection"
                                        className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground">
                                        <X className="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        )}
                        <div className="flex-1 overflow-y-auto">
                            {threads.map(t => {
                                const last = t.messages[t.messages.length - 1];
                                const preview = (last?.message || last?.subject || '').replace(/\s+/g, ' ').trim();
                                const active = t.key === selectedKey && !composing;
                                const unread = threadUnread(t);
                                const checked = selectedKeys.has(t.key);
                                const Icon = t.kind === 'direct' ? UserIcon : t.kind === 'proposal' ? FileText : MessageSquare;
                                return (
                                    <div
                                        key={t.key}
                                        className={cn(
                                            'group flex items-stretch border-b border-border transition-colors',
                                            checked ? 'bg-primary/[0.08]' : active ? 'bg-primary/[0.06]' : 'hover:bg-secondary/50',
                                        )}
                                    >
                                        <div className="flex shrink-0 items-center pl-3">
                                            <Checkbox checked={checked} onChange={() => toggleThread(t.key)} ariaLabel={`Select ${t.project_name}`} />
                                        </div>
                                        <button
                                            onClick={() => openThread(t.key)}
                                            className="flex min-w-0 flex-1 flex-col gap-0.5 px-3 py-3 text-left"
                                        >
                                            <div className="flex items-center gap-2">
                                                <span className={cn('h-2 w-2 shrink-0 rounded-full', unread ? 'bg-orange-500' : 'bg-transparent')} aria-hidden={!unread} />
                                                <Icon className="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                                                <span className={cn('min-w-0 flex-1 truncate text-sm text-foreground', unread ? 'font-bold' : 'font-semibold')}>{t.project_name}</span>
                                                <span className="shrink-0 text-[11px] text-muted-foreground">{t.last_at ? formatRelativeDate(t.last_at) : ''}</span>
                                                {t.kind === 'general' ? (
                                                    <span title="Always pinned" className="shrink-0 p-0.5 text-primary">
                                                        <Pin className="h-3.5 w-3.5 fill-current" />
                                                    </span>
                                                ) : (
                                                    <span
                                                        role="button"
                                                        tabIndex={0}
                                                        title={t.pinned ? 'Unpin' : 'Pin to top'}
                                                        onClick={e => { e.stopPropagation(); togglePin(t.key); }}
                                                        onKeyDown={e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); e.stopPropagation(); togglePin(t.key); } }}
                                                        className={cn('shrink-0 rounded p-0.5 transition-all hover:text-foreground',
                                                            t.pinned ? 'text-primary' : 'text-muted-foreground/50 opacity-0 group-hover:opacity-100')}
                                                    >
                                                        <Pin className={cn('h-3.5 w-3.5', t.pinned && 'fill-current')} />
                                                    </span>
                                                )}
                                            </div>
                                            <span className="truncate pl-7 font-mono text-[11px] text-muted-foreground">
                                                {t.proposal_number ?? (t.kind === 'direct' ? 'Direct message' : `${t.count} message${t.count === 1 ? '' : 's'}`)}
                                            </span>
                                            {preview && <span className="truncate pl-7 text-xs text-muted-foreground">{preview}</span>}
                                        </button>
                                    </div>
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
                                    {selected.messages.map(m => {
                                        const unread = msgUnread(m);
                                        return (
                                        <div key={m.id} className="group flex items-start gap-2">
                                            <span
                                                className={cn('mt-4 h-2 w-2 shrink-0 rounded-full', unread ? 'bg-orange-500' : 'bg-transparent')}
                                                title={unread ? 'Unread' : undefined}
                                                aria-hidden={!unread}
                                            />
                                            <div className={cn('flex-1 rounded-xl border p-4 transition-colors', m.mine ? 'border-primary/30 bg-primary/[0.04]' : 'border-border')}>
                                                <div className="mb-1 flex items-center gap-2">
                                                    <span className={cn('h-2 w-2 shrink-0 rounded-full', STATUS_DOT[m.status] ?? 'bg-slate-400')} />
                                                    <span className={cn('text-sm text-foreground', unread ? 'font-bold' : 'font-semibold')}>{m.subject || 'Message'}</span>
                                                    {m.automated && <span className="inline-flex items-center gap-1 rounded-full bg-primary/10 px-1.5 py-0.5 text-[10px] font-semibold text-primary"><Sparkles className="h-3 w-3" /> auto</span>}
                                                    <button
                                                        type="button"
                                                        onClick={() => deleteIds([m.id], 'Delete this message? This cannot be undone.')}
                                                        title="Delete message"
                                                        className="ml-auto shrink-0 text-muted-foreground/50 opacity-0 transition-all hover:text-destructive group-hover:opacity-100"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </button>
                                                </div>
                                                {m.message && <p className="whitespace-pre-line text-sm leading-relaxed text-foreground">{m.message}</p>}
                                                <p className="mt-2 text-[11px] text-muted-foreground">
                                                    {[m.mine ? 'You' : m.author, m.contact ? `to ${m.contact}` : null, formatDateTime(m.created_at)].filter(Boolean).join(' · ')}
                                                </p>
                                            </div>
                                        </div>
                                        );
                                    })}
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
