import { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { Search, FileText, Target, Building2, Users, X } from 'lucide-react';

const ICONS: Record<string, React.ComponentType<{ className?: string }>> = {
    'file-text': FileText,
    target: Target,
    building: Building2,
    users: Users,
    search: Search,
};

interface Item { label: string; sub: string | null; url: string }
interface Group { label: string; icon: string; items: Item[] }

export function GlobalSearch() {
    const [q, setQ] = useState('');
    const [groups, setGroups] = useState<Group[]>([]);
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);
    const boxRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const h = (e: KeyboardEvent) => {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                inputRef.current?.focus();
            }
        };
        window.addEventListener('keydown', h);
        return () => window.removeEventListener('keydown', h);
    }, []);

    useEffect(() => {
        if (q.trim().length < 2) { setGroups([]); return; }
        setLoading(true);
        const controller = new AbortController();
        const t = setTimeout(async () => {
            try {
                const r = await fetch(`/search?q=${encodeURIComponent(q)}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    signal: controller.signal,
                });
                const d = await r.json();
                setGroups(d.groups ?? []);
            } catch { /* aborted */ }
            finally { setLoading(false); }
        }, 220);
        return () => { clearTimeout(t); controller.abort(); };
    }, [q]);

    useEffect(() => {
        const h = (e: MouseEvent) => {
            if (boxRef.current && !boxRef.current.contains(e.target as Node)) setOpen(false);
        };
        document.addEventListener('mousedown', h);
        return () => document.removeEventListener('mousedown', h);
    }, []);

    const go = (url: string) => { setOpen(false); setQ(''); setGroups([]); router.visit(url); };
    const hasResults = groups.length > 0;

    return (
        <div ref={boxRef} className="relative hidden flex-1 sm:block sm:max-w-md">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <input
                ref={inputRef}
                value={q}
                onChange={e => { setQ(e.target.value); setOpen(true); }}
                onFocus={() => setOpen(true)}
                placeholder="Search everything…  (⌘K)"
                className="h-9 w-full rounded-full border border-border bg-secondary/50 pl-9 pr-8 text-sm text-foreground placeholder:text-muted-foreground focus:border-primary/40 focus:bg-card focus:outline-none focus:ring-2 focus:ring-primary/20"
            />
            {q && (
                <button onClick={() => { setQ(''); setGroups([]); }} className="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground">
                    <X className="h-4 w-4" />
                </button>
            )}
            {open && q.trim().length >= 2 && (
                <div className="animate-dropdown origin-top absolute left-0 right-0 top-11 z-40 max-h-[70vh] overflow-y-auto rounded-xl border border-border bg-card py-1 shadow-xl ring-1 ring-black/5 dark:ring-white/10">
                    {loading && !hasResults && <p className="px-4 py-3 text-sm text-muted-foreground">Searching…</p>}
                    {!loading && !hasResults && <p className="px-4 py-3 text-sm text-muted-foreground">No results for “{q}”.</p>}
                    {groups.map(g => {
                        const Icon = ICONS[g.icon] ?? Search;
                        return (
                            <div key={g.label}>
                                <p className="px-4 pb-1 pt-2 text-[10px] font-bold uppercase tracking-wider text-muted-foreground/70">{g.label}</p>
                                {g.items.map((it, i) => (
                                    <button key={i} onClick={() => go(it.url)} className="flex w-full items-center gap-3 px-4 py-2 text-left transition-colors hover:bg-secondary">
                                        <Icon className="h-4 w-4 shrink-0 text-muted-foreground" />
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate text-sm font-medium text-foreground">{it.label}</span>
                                            {it.sub && <span className="block truncate text-xs text-muted-foreground">{it.sub}</span>}
                                        </span>
                                    </button>
                                ))}
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
