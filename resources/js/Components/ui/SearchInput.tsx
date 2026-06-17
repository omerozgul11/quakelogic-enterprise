import { useEffect, useRef, useState } from 'react';
import { Search, X } from 'lucide-react';
import { cn } from '@/Lib/utils';

interface SearchInputProps {
    /** Current server-side query (e.g. filters.search). */
    initial?: string;
    /** Called (debounced) on every keystroke/delete with the new query. */
    onSearch: (value: string) => void;
    placeholder?: string;
    className?: string;
    inputClassName?: string;
    delay?: number;
}

/**
 * Live, debounced search box. Filters the underlying table on every keystroke or
 * delete (no Enter needed). Echo-safe: it adopts external query changes (e.g. a
 * "Clear filters" button) without clobbering in-flight typing.
 */
export function SearchInput({ initial = '', onSearch, placeholder = 'Search…', className, inputClassName, delay = 300 }: SearchInputProps) {
    const [value, setValue] = useState(initial);
    const sent = useRef(initial);       // last value pushed to / adopted from the server
    const cb = useRef(onSearch);
    cb.current = onSearch;

    // Debounced push as the user types or deletes.
    useEffect(() => {
        if (value === sent.current) return; // mount, or an echo we already have
        const id = setTimeout(() => { sent.current = value; cb.current(value); }, delay);
        return () => clearTimeout(id);
    }, [value, delay]);

    // Adopt external query changes (server echoes equal `sent` and are ignored,
    // so this only fires for things like a "Clear filters" reset).
    useEffect(() => {
        if (initial !== sent.current) { sent.current = initial; setValue(initial); }
    }, [initial]);

    return (
        <div className={cn('relative', className)}>
            <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <input
                type="text"
                value={value}
                onChange={e => setValue(e.target.value)}
                placeholder={placeholder}
                className={cn('input input-with-icon pr-9', inputClassName)}
            />
            {value && (
                <button
                    type="button"
                    onClick={() => setValue('')}
                    aria-label="Clear search"
                    className="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground transition-colors hover:text-foreground"
                >
                    <X className="h-4 w-4" />
                </button>
            )}
        </div>
    );
}
