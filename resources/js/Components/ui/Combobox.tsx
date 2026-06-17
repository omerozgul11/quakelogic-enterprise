import { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { cn } from '@/Lib/utils';
import { Check, ChevronDown, Search, X } from 'lucide-react';

export interface ComboboxOption {
    value: string;
    label: string;
}

interface Props {
    value: string;
    options: ComboboxOption[];
    onChange: (value: string) => void;
    placeholder?: string;
    className?: string;
}

/**
 * Searchable single-select: a text input that filters the options as you type,
 * with a portaled card-style list (matching <Select>). Clearable via the × when
 * a value is set. Keyboard: ↑/↓ to move, Enter to choose, Esc to close.
 */
export function Combobox({ value, options, onChange, placeholder = 'Type to search…', className }: Props) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [highlight, setHighlight] = useState(0);
    const [pos, setPos] = useState<{ top: number; left: number; width: number; maxHeight: number } | null>(null);
    const wrapRef = useRef<HTMLDivElement>(null);
    const menuRef = useRef<HTMLDivElement>(null);

    const selected = options.find(o => o.value === value);

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        return q ? options.filter(o => o.label.toLowerCase().includes(q)) : options;
    }, [query, options]);

    const place = () => {
        const rect = wrapRef.current?.getBoundingClientRect();
        if (!rect) return;
        const below = window.innerHeight - rect.bottom - 12;
        const maxHeight = Math.min(300, Math.max(below, 180));
        setPos({ top: rect.bottom + 4, left: rect.left, width: rect.width, maxHeight });
    };

    const openMenu = () => { place(); setOpen(true); setQuery(''); setHighlight(0); };

    useEffect(() => {
        if (!open) return;
        place();
        const onScroll = (e: Event) => { if (menuRef.current && e.target instanceof Node && menuRef.current.contains(e.target)) return; setOpen(false); };
        const onResize = () => setOpen(false);
        const onDown = (e: MouseEvent) => {
            const t = e.target as Node;
            if (wrapRef.current?.contains(t) || menuRef.current?.contains(t)) return;
            setOpen(false);
        };
        document.addEventListener('scroll', onScroll, true);
        window.addEventListener('resize', onResize);
        document.addEventListener('mousedown', onDown);
        return () => {
            document.removeEventListener('scroll', onScroll, true);
            window.removeEventListener('resize', onResize);
            document.removeEventListener('mousedown', onDown);
        };
    }, [open]);

    const pick = (v: string) => { setOpen(false); setQuery(''); if (v !== value) onChange(v); };
    const clear = () => { setQuery(''); if (value) onChange(''); };

    const onKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'Escape') { setOpen(false); return; }
        if (!open && (e.key === 'ArrowDown' || e.key === 'Enter')) { openMenu(); return; }
        if (e.key === 'ArrowDown') { e.preventDefault(); setHighlight(h => Math.min(h + 1, filtered.length - 1)); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); setHighlight(h => Math.max(h - 1, 0)); }
        else if (e.key === 'Enter') { e.preventDefault(); const it = filtered[highlight]; if (it) pick(it.value); }
    };

    return (
        <div ref={wrapRef} className={cn('relative', className)}>
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <input
                type="text"
                value={open ? query : (selected?.label ?? '')}
                placeholder={selected?.label ?? placeholder}
                onFocus={openMenu}
                onChange={e => { if (!open) openMenu(); setQuery(e.target.value); setHighlight(0); }}
                onKeyDown={onKeyDown}
                className="input input-with-icon pr-9"
            />
            {selected && !open ? (
                <button type="button" onClick={clear} title="Clear" className="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground">
                    <X className="h-4 w-4" />
                </button>
            ) : (
                <ChevronDown className={cn('pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground transition-transform', open && 'rotate-180')} />
            )}

            {open && pos && createPortal(
                <div
                    ref={menuRef}
                    className="animate-dropdown fixed z-[130] overflow-y-auto overscroll-contain rounded-xl border border-border bg-card py-1 shadow-xl ring-1 ring-black/5 dark:ring-white/10"
                    style={{ top: pos.top, left: pos.left, width: pos.width, maxHeight: pos.maxHeight }}
                >
                    {filtered.length === 0 ? (
                        <p className="px-3 py-2 text-sm text-muted-foreground">No matches</p>
                    ) : filtered.map((o, i) => {
                        const sel = o.value === value;
                        return (
                            <button
                                key={o.value}
                                type="button"
                                onMouseEnter={() => setHighlight(i)}
                                onClick={() => pick(o.value)}
                                className={cn(
                                    'flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm transition-colors',
                                    i === highlight ? 'bg-secondary' : '',
                                    sel ? 'font-medium text-primary' : 'text-foreground',
                                )}
                            >
                                <span className="truncate">{o.label}</span>
                                {sel && <Check className="h-4 w-4 shrink-0" />}
                            </button>
                        );
                    })}
                </div>,
                document.body,
            )}
        </div>
    );
}
