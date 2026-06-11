import { useEffect, useRef, useState } from 'react';
import { Link } from '@inertiajs/react';
import { cn } from '@/Lib/utils';
import { QuakeAiIcon } from '@/Components/ui/QuakeAiIcon';
import { Send, X, ArrowUpRight } from 'lucide-react';

interface Msg { role: 'user' | 'assistant'; content: string }

const GREETING: Msg = {
    role: 'assistant',
    content: "Hi! I'm QuakeAI. Ask me about your proposals, opportunities, deadlines, or anything in your portal.",
};

export function QuakeAiChat({ active }: { active?: boolean }) {
    const [open, setOpen] = useState(false);
    const [messages, setMessages] = useState<Msg[]>([GREETING]);
    const [input, setInput] = useState('');
    const [sending, setSending] = useState(false);
    const boxRef = useRef<HTMLDivElement>(null);
    const scrollRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        const h = (e: MouseEvent) => { if (boxRef.current && !boxRef.current.contains(e.target as Node)) setOpen(false); };
        document.addEventListener('mousedown', h);
        return () => document.removeEventListener('mousedown', h);
    }, []);

    useEffect(() => {
        if (open) setTimeout(() => inputRef.current?.focus(), 50);
    }, [open]);

    useEffect(() => {
        scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight, behavior: 'smooth' });
    }, [messages, sending]);

    const send = async () => {
        const text = input.trim();
        if (!text || sending) return;
        const history = messages.filter((_, i) => i > 0).slice(-8);
        const next = [...messages, { role: 'user', content: text } as Msg];
        setMessages(next);
        setInput('');
        setSending(true);
        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const res = await fetch('/ai/chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                body: JSON.stringify({ message: text, history }),
            });
            const data = await res.json();
            setMessages(m => [...m, { role: 'assistant', content: data.reply ?? 'Sorry, no response.' }]);
        } catch {
            setMessages(m => [...m, { role: 'assistant', content: 'Sorry — something went wrong. Please try again.' }]);
        } finally {
            setSending(false);
        }
    };

    return (
        <div ref={boxRef} className="relative">
            <button
                onClick={() => setOpen(v => !v)}
                title="Ask QuakeAI"
                className={cn(
                    'flex h-9 w-9 items-center justify-center rounded-full transition-colors',
                    open || active ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-secondary hover:text-foreground'
                )}
            >
                <QuakeAiIcon className="h-[20px] w-[20px]" />
            </button>

            {open && (
                <div className="animate-scale-in absolute right-0 top-11 z-30 flex h-[26rem] w-[22rem] origin-top-right flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-2xl ring-1 ring-black/5 dark:ring-white/10">
                    <div className="bg-brand-gradient flex items-center justify-between px-4 py-3 text-white">
                        <span className="flex items-center gap-2">
                            <QuakeAiIcon className="h-5 w-5" />
                            <span className="text-sm font-bold">Ask QuakeAI</span>
                        </span>
                        <button onClick={() => setOpen(false)} className="rounded-full p-1 transition-colors hover:bg-white/20">
                            <X className="h-4 w-4" />
                        </button>
                    </div>

                    <div ref={scrollRef} className="flex-1 space-y-3 overflow-y-auto bg-secondary/30 p-3">
                        {messages.map((m, i) => (
                            <div key={i} className={cn('flex', m.role === 'user' ? 'justify-end' : 'justify-start')}>
                                <div className={cn(
                                    'animate-rise max-w-[80%] whitespace-pre-line rounded-2xl px-3 py-2 text-sm leading-relaxed',
                                    m.role === 'user'
                                        ? 'rounded-br-sm bg-primary text-white'
                                        : 'rounded-bl-sm border border-border bg-card text-foreground'
                                )}>
                                    {m.content}
                                </div>
                            </div>
                        ))}
                        {sending && (
                            <div className="flex justify-start">
                                <div className="flex gap-1 rounded-2xl rounded-bl-sm border border-border bg-card px-3 py-2.5">
                                    <span className="h-1.5 w-1.5 animate-bounce rounded-full bg-muted-foreground [animation-delay:-0.2s]" />
                                    <span className="h-1.5 w-1.5 animate-bounce rounded-full bg-muted-foreground [animation-delay:-0.1s]" />
                                    <span className="h-1.5 w-1.5 animate-bounce rounded-full bg-muted-foreground" />
                                </div>
                            </div>
                        )}
                    </div>

                    <div className="border-t border-border p-2.5">
                        <div className="flex items-end gap-2">
                            <input
                                ref={inputRef}
                                value={input}
                                onChange={e => setInput(e.target.value)}
                                onKeyDown={e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); } }}
                                placeholder="Ask anything…"
                                className="h-9 flex-1 rounded-full border border-border bg-secondary/50 px-3.5 text-sm text-foreground placeholder:text-muted-foreground focus:border-primary/40 focus:bg-card focus:outline-none focus:ring-2 focus:ring-primary/20"
                            />
                            <button
                                onClick={send}
                                disabled={sending || !input.trim()}
                                className="bg-brand-gradient flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-white transition-opacity hover:opacity-95 disabled:opacity-40"
                            >
                                <Send className="h-4 w-4" />
                            </button>
                        </div>
                        <Link href="/ai" onClick={() => setOpen(false)} className="mt-2 flex items-center justify-center gap-1 text-[11px] font-medium text-muted-foreground hover:text-primary">
                            Open full QuakeAI <ArrowUpRight className="h-3 w-3" />
                        </Link>
                    </div>
                </div>
            )}
        </div>
    );
}
