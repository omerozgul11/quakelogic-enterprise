import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { cn } from '@/Lib/utils';
import { Check, ChevronDown, Search } from 'lucide-react';

export interface SelectOption {
    value: string;
    label: string;
}

interface Props {
    value: string;
    options: SelectOption[];
    onChange: (value: string) => void;
    /** Shown when nothing is selected; also offered as the first (clearing) option. */
    placeholder?: string;
    className?: string;
    size?: 'sm' | 'md';
    /** Adds a filter box at the top of the popup — use for long lists (companies, people…). */
    searchable?: boolean;
    /** Placeholder for the filter box when `searchable`. */
    searchPlaceholder?: string;
}

/**
 * Custom dropdown that renders identically on every OS — a styled button with
 * a card-style popup list, replacing the native <select> chrome. The popup is
 * portaled to <body> so it never gets clipped by scrollable containers.
 */
export function Select({ value, options, onChange, placeholder, className, size = 'md', searchable = false, searchPlaceholder = 'Search…' }: Props) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [pos, setPos] = useState<{ top: number; left: number; width: number; maxHeight: number; origin: 'top' | 'bottom' } | null>(null);
    const buttonRef = useRef<HTMLButtonElement>(null);
    const menuRef = useRef<HTMLDivElement>(null);
    const searchRef = useRef<HTMLInputElement>(null);

    const current = options.find(o => o.value === value);
    const q = query.trim().toLowerCase();
    const filtered = searchable && q ? options.filter(o => o.label.toLowerCase().includes(q)) : options;
    // Hide the clearing placeholder while actively searching so an empty result
    // collapses `items` to length 0 and the "No matches" state below can render.
    const items: SelectOption[] = placeholder && !q ? [{ value: '', label: placeholder }, ...filtered] : filtered;

    const openMenu = () => {
        const rect = buttonRef.current?.getBoundingClientRect();
        if (!rect) return;
        const below = window.innerHeight - rect.bottom - 12;
        const maxHeight = Math.min(288, Math.max(below, 160));
        // Flip above the button when there's clearly more room there.
        const flip = below < 160 && rect.top > 300;
        const top = flip ? rect.top - maxHeight - 4 : rect.bottom + 4;
        setPos({ top, left: rect.left, width: rect.width, maxHeight, origin: flip ? 'bottom' : 'top' });
        setQuery('');
        setOpen(true);
    };

    useEffect(() => {
        if (!open) return;
        const close = () => setOpen(false);
        // Page scrolls close the menu (its fixed position would go stale), but
        // scrolling the option list itself must not.
        const onScroll = (e: Event) => {
            if (menuRef.current && e.target instanceof Node && menuRef.current.contains(e.target)) return;
            setOpen(false);
        };
        const onKey = (e: KeyboardEvent) => e.key === 'Escape' && setOpen(false);
        window.addEventListener('resize', close);
        document.addEventListener('scroll', onScroll, true);
        window.addEventListener('keydown', onKey);
        return () => {
            window.removeEventListener('resize', close);
            document.removeEventListener('scroll', onScroll, true);
            window.removeEventListener('keydown', onKey);
        };
    }, [open]);

    // Focus the filter box as soon as the searchable popup opens.
    useEffect(() => {
        if (open && searchable) searchRef.current?.focus();
    }, [open, searchable]);

    const pick = (v: string) => {
        setOpen(false);
        setQuery('');
        if (v !== value) onChange(v);
    };

    return (
        <>
            <button
                ref={buttonRef}
                type="button"
                onClick={() => (open ? setOpen(false) : openMenu())}
                className={cn(
                    'inline-flex items-center justify-between gap-2 rounded-lg border border-input bg-card text-left transition-colors',
                    'hover:border-muted-foreground/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/50',
                    size === 'sm' ? 'h-8 pl-2.5 pr-2 text-xs' : 'h-10 pl-3 pr-2.5 text-sm',
                    className,
                )}
            >
                <span className={cn('truncate', current ? 'text-foreground' : 'text-muted-foreground')}>
                    {current?.label ?? placeholder ?? 'Select…'}
                </span>
                <ChevronDown className={cn('shrink-0 text-muted-foreground transition-transform', open && 'rotate-180', size === 'sm' ? 'h-3.5 w-3.5' : 'h-4 w-4')} />
            </button>

            {open && pos && createPortal(
                <>
                    {/* z-index sits above Modal (z-[120]) so selects work inside dialogs. */}
                    <div className="fixed inset-0 z-[129]" onClick={() => setOpen(false)} />
                    <div
                        ref={menuRef}
                        className={cn(
                            'animate-dropdown fixed z-[130] overflow-y-auto overscroll-contain rounded-xl border border-border bg-card py-1 shadow-xl ring-1 ring-black/5 dark:ring-white/10',
                            pos.origin === 'bottom' ? 'origin-bottom' : 'origin-top',
                        )}
                        style={{ top: pos.top, left: pos.left, minWidth: pos.width, maxHeight: pos.maxHeight }}
                    >
                        {searchable && (
                            <div className="sticky top-0 z-10 border-b border-border bg-card p-1.5">
                                <div className="relative">
                                    <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                                    <input
                                        ref={searchRef}
                                        value={query}
                                        onChange={e => setQuery(e.target.value)}
                                        placeholder={searchPlaceholder}
                                        className="h-8 w-full rounded-md border border-input bg-card pl-8 pr-2 text-xs text-foreground placeholder:text-muted-foreground focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/50"
                                    />
                                </div>
                            </div>
                        )}
                        {items.length === 0 && (
                            <p className="px-3 py-3 text-center text-xs text-muted-foreground">No matches</p>
                        )}
                        {items.map(o => {
                            const selected = o.value === value || (o.value === '' && !current);
                            return (
                                <button
                                    key={o.value || '__all'}
                                    type="button"
                                    onClick={() => pick(o.value)}
                                    className={cn(
                                        'flex w-full items-center justify-between gap-3 px-3 py-2 text-left transition-colors hover:bg-secondary',
                                        size === 'sm' ? 'text-xs' : 'text-sm',
                                        selected ? 'font-medium text-primary' : 'text-foreground',
                                    )}
                                >
                                    <span className="truncate">{o.label}</span>
                                    {selected && <Check className="h-4 w-4 shrink-0" />}
                                </button>
                            );
                        })}
                    </div>
                </>,
                document.body,
            )}
        </>
    );
}
