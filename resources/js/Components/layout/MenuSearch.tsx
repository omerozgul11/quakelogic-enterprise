import { Search, X } from 'lucide-react';

interface MenuSearchProps {
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
}

// Search box pinned at the top of a sidebar. The host layout does the actual
// filtering with `menuMatches` so it can respect its own group/section shape.
// NOTE: the host must render its <SidebarBody> as a function call (not <SidebarBody/>)
// so this input keeps focus across keystrokes — see the layout components.
export function MenuSearch({ value, onChange, placeholder = 'Search menu…' }: MenuSearchProps) {
    return (
        <div className="px-3 pb-1.5 pt-1">
            <div className="relative">
                <Search className="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground/70" />
                <input
                    type="text"
                    value={value}
                    onChange={e => onChange(e.target.value)}
                    onKeyDown={e => { if (e.key === 'Escape') onChange(''); }}
                    placeholder={placeholder}
                    aria-label="Search menu"
                    autoComplete="off"
                    spellCheck={false}
                    className="w-full rounded-lg border border-border/60 bg-secondary/40 py-1.5 pl-8 pr-8 text-sm text-foreground outline-none transition-colors placeholder:text-muted-foreground/60 focus:border-primary/50 focus:bg-background focus:ring-1 focus:ring-primary/30"
                />
                {value !== '' && (
                    <button
                        type="button"
                        onClick={() => onChange('')}
                        aria-label="Clear search"
                        className="absolute right-1.5 top-1/2 -translate-y-1/2 rounded p-0.5 text-muted-foreground/70 transition-colors hover:text-foreground"
                    >
                        <X className="h-3.5 w-3.5" />
                    </button>
                )}
            </div>
        </div>
    );
}

// Case-insensitive substring match used to filter menu items by their label.
export function menuMatches(label: string, query: string): boolean {
    const q = query.trim().toLowerCase();
    return q === '' || label.toLowerCase().includes(q);
}
