import { useEffect, useRef, useState } from 'react';
import { Link } from '@inertiajs/react';
import { cn } from '@/Lib/utils';
import { QuakeBot } from '@/Components/ui/QuakeBot';
import { QuakeBotShipScene, DeliveryLoader } from '@/Components/ui/QuakeBotShipScene';
import { Send, X, ArrowUpRight } from 'lucide-react';

interface Msg { role: 'user' | 'assistant'; content: string }

/**
 * QuakeBot for the Shipments app — same assistant and /ai/chat endpoint (which
 * is organisation-scoped and already aware of the user's shipments), wrapped in
 * a delivery-themed presentation: the empty state plays the truck → loading →
 * ship animation, and the thinking indicator is a little parcel truck driving.
 */
export function ShipmentsAiChat() {
    const [open, setOpen] = useState(false);
    const [messages, setMessages] = useState<Msg[]>([]);
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
        const history = messages.slice(-8);
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
                title="Ask QuakeBot"
                className={cn(
                    'flex h-9 w-9 items-center justify-center rounded-full transition-colors',
                    open ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-secondary hover:text-foreground'
                )}
            >
                <QuakeBot className="h-[24px] w-[24px]" />
            </button>

            {open && (
                <div className="animate-scale-in absolute right-0 top-11 z-30 flex h-[26rem] w-[22rem] origin-top-right flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-2xl ring-1 ring-black/5 dark:ring-white/10">
                    <div className="bg-brand-gradient flex items-center justify-between px-4 py-3 text-white">
                        <span className="flex items-center gap-2">
                            <span className="flex h-6 w-6 items-center justify-center rounded-full bg-white/90 ring-1 ring-white/40">
                                <QuakeBot className="h-5 w-5" />
                            </span>
                            <span className="text-sm font-bold">Ask QuakeBot</span>
                        </span>
                        <button onClick={() => setOpen(false)} className="rounded-full p-1 transition-colors hover:bg-white/20">
                            <X className="h-4 w-4" />
                        </button>
                    </div>

                    <div ref={scrollRef} className="flex-1 space-y-3 overflow-y-auto bg-secondary/30 p-3">
                        {messages.length === 0 && !sending && (
                            <div className="flex h-full flex-col items-center justify-center px-5 text-center">
                                <QuakeBotShipScene />
                                <p className="mt-2 text-sm font-medium text-foreground">How can I help?</p>
                                <p className="mt-1 text-xs text-muted-foreground">Ask about your shipments, deliveries, tracking, or deadlines.</p>
                            </div>
                        )}
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
                                <div className="flex items-center gap-2 rounded-2xl rounded-bl-sm border border-border bg-card px-3 py-1.5">
                                    <DeliveryLoader />
                                    <span className="text-xs text-muted-foreground">Delivering…</span>
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
                                placeholder="Ask about a shipment…"
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
                            Open full QuakeBot <ArrowUpRight className="h-3 w-3" />
                        </Link>
                    </div>
                </div>
            )}
        </div>
    );
}
