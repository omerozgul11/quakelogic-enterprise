import { useEffect, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { cn } from '@/Lib/utils';
import { QuakeBot } from '@/Components/ui/QuakeBot';
import { QuakeBotScene } from '@/Components/ui/QuakeBotScene';
import { ChatMsg, loadChat, saveChat, onChatChanged } from '@/Lib/chatStore';
import { Send } from 'lucide-react';

type Msg = ChatMsg;

const SUGGESTIONS = [
    'Which proposals are due this week?',
    'Summarize my active pipeline',
    'What needs my attention today?',
];

/**
 * Full-height QuakeBot chat panel for the Ask QuakeAI page (left column).
 * Talks to POST /ai/chat — same contract as the floating widget, and shares the
 * same persisted conversation (see Lib/chatStore) so it survives navigation.
 */
export function AiChatPanel({ provider, available = true }: { provider?: string; available?: boolean }) {
    const uid = (usePage().props as { auth?: { user?: { id?: number } } }).auth?.user?.id ?? 'anon';
    const [messages, setMessages] = useState<Msg[]>([]);
    const [input, setInput] = useState('');
    const [sending, setSending] = useState(false);
    const scrollRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLTextAreaElement>(null);

    useEffect(() => {
        setMessages(loadChat(uid));
        return onChatChanged(() => setMessages(prev => {
            const stored = loadChat(uid);
            return JSON.stringify(prev) === JSON.stringify(stored) ? prev : stored;
        }));
    }, [uid]);

    useEffect(() => {
        scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight, behavior: 'smooth' });
    }, [messages, sending]);

    const sendText = async (text: string) => {
        const value = text.trim();
        if (!value || sending) return;
        const history = messages.slice(-8);
        setMessages(m => { const u = [...m, { role: 'user', content: value } as Msg]; saveChat(uid, u); return u; });
        setInput('');
        setSending(true);
        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const res = await fetch('/ai/chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                body: JSON.stringify({ message: value, history }),
            });
            const data = await res.json();
            setMessages(m => { const u = [...m, { role: 'assistant', content: data.reply ?? 'Sorry, no response.' } as Msg]; saveChat(uid, u); return u; });
        } catch {
            setMessages(m => { const u = [...m, { role: 'assistant', content: 'Sorry — something went wrong. Please try again.' } as Msg]; saveChat(uid, u); return u; });
        } finally {
            setSending(false);
            setTimeout(() => inputRef.current?.focus(), 30);
        }
    };

    return (
        <div className="card-surface flex h-full min-h-[30rem] flex-col overflow-hidden lg:min-h-0">
            {/* dedicated mascot stage */}
            <div className="shrink-0 overflow-hidden border-b border-border/60 bg-gradient-to-b from-primary/[0.08] via-primary/[0.03] to-transparent px-3 pb-2 pt-3">
                <QuakeBotScene />
            </div>

            {/* identity row */}
            <div className="flex shrink-0 items-center gap-2 border-b border-border/60 px-4 py-2">
                <span className={cn('h-2 w-2 shrink-0 rounded-full', available ? 'bg-emerald-500' : 'bg-muted-foreground')} />
                <span className="text-sm font-bold text-foreground">QuakeBot</span>
                <span className="truncate text-[11px] text-muted-foreground">{available ? 'online' : 'offline'}{provider ? ` · ${provider}` : ''}</span>
            </div>

            {/* messages */}
            <div ref={scrollRef} className="flex-1 space-y-4 overflow-y-auto p-4">
                {messages.length === 0 && !sending && (
                    <div className="flex h-full flex-col items-center justify-center px-6 text-center">
                        <QuakeBot className="h-10 w-10" />
                        <p className="mt-3 text-sm font-medium text-foreground">How can I help with your proposals?</p>
                        <p className="mt-1 text-xs text-muted-foreground">Ask about deadlines, your pipeline, or anything in the portal.</p>
                    </div>
                )}
                {messages.map((m, i) => (
                    <div key={i} className={cn('flex items-end gap-2', m.role === 'user' ? 'justify-end' : 'justify-start')}>
                        {m.role === 'assistant' && <QuakeBot className="mb-0.5 h-7 w-7 shrink-0" />}
                        <div className={cn(
                            'animate-rise max-w-[80%] whitespace-pre-line rounded-2xl px-3.5 py-2.5 text-sm leading-relaxed',
                            m.role === 'user'
                                ? 'rounded-br-sm bg-primary text-white'
                                : 'rounded-bl-sm border border-border bg-card text-foreground'
                        )}>
                            {m.content}
                        </div>
                    </div>
                ))}
                {sending && (
                    <div className="flex items-end gap-2">
                        <QuakeBot className="mb-0.5 h-7 w-7 shrink-0" />
                        <div className="flex gap-1 rounded-2xl rounded-bl-sm border border-border bg-card px-3.5 py-3">
                            <span className="h-1.5 w-1.5 animate-bounce rounded-full bg-muted-foreground [animation-delay:-0.2s]" />
                            <span className="h-1.5 w-1.5 animate-bounce rounded-full bg-muted-foreground [animation-delay:-0.1s]" />
                            <span className="h-1.5 w-1.5 animate-bounce rounded-full bg-muted-foreground" />
                        </div>
                    </div>
                )}
            </div>

            {/* suggestion chips (only before the first question) */}
            {messages.length === 0 && (
                <div className="flex flex-wrap gap-2 px-4 pb-1">
                    {SUGGESTIONS.map(s => (
                        <button
                            key={s}
                            onClick={() => sendText(s)}
                            className="rounded-full border border-border bg-secondary/40 px-3 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:border-primary/40 hover:text-foreground"
                        >
                            {s}
                        </button>
                    ))}
                </div>
            )}

            {/* input */}
            <div className="border-t border-border/60 p-3">
                <div className="flex items-end gap-2">
                    <textarea
                        ref={inputRef}
                        value={input}
                        onChange={e => setInput(e.target.value)}
                        onKeyDown={e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendText(input); } }}
                        rows={1}
                        placeholder="Ask QuakeBot anything…"
                        className="max-h-32 min-h-[2.5rem] flex-1 resize-none rounded-2xl border border-border bg-secondary/50 px-3.5 py-2.5 text-sm text-foreground placeholder:text-muted-foreground focus:border-primary/40 focus:bg-card focus:outline-none focus:ring-2 focus:ring-primary/20"
                    />
                    <button
                        onClick={() => sendText(input)}
                        disabled={sending || !input.trim()}
                        className="bg-brand-gradient flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-white transition-opacity hover:opacity-95 disabled:opacity-40"
                    >
                        <Send className="h-4 w-4" />
                    </button>
                </div>
            </div>
        </div>
    );
}
