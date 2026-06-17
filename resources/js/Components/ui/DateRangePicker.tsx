import { useEffect, useState } from 'react';
import { cn } from '@/Lib/utils';
import { CalendarRange, X } from 'lucide-react';

interface Props {
    from?: string | null;
    to?: string | null;
    /** Whether a custom range is currently applied. */
    active: boolean;
    onApply: (from: string, to: string) => void;
    onClear: () => void;
}

/**
 * Compact from–to date range control, styled to sit next to the preset
 * period toggle groups used across report pages.
 */
export function DateRangePicker({ from, to, active, onApply, onClear }: Props) {
    const [draftFrom, setDraftFrom] = useState(from ?? '');
    const [draftTo, setDraftTo] = useState(to ?? '');

    useEffect(() => { setDraftFrom(from ?? ''); setDraftTo(to ?? ''); }, [from, to]);

    const ready = draftFrom !== '' && draftTo !== '';
    const dirty = draftFrom !== (from ?? '') || draftTo !== (to ?? '');

    return (
        <div className={cn(
            'inline-flex items-center gap-1 rounded-xl border bg-card p-1 transition-colors',
            active ? 'border-primary/50 ring-1 ring-primary/20' : 'border-border',
        )}>
            <CalendarRange className={cn('ml-2 h-4 w-4 shrink-0', active ? 'text-primary' : 'text-muted-foreground')} />
            <input
                type="date"
                value={draftFrom}
                onChange={e => setDraftFrom(e.target.value)}
                className="w-[8.5rem] rounded-lg border-0 bg-transparent px-1.5 py-1.5 text-sm text-foreground focus:outline-none focus:ring-0 [color-scheme:light] dark:[color-scheme:dark]"
                aria-label="From date"
            />
            <span className="text-xs text-muted-foreground">–</span>
            <input
                type="date"
                value={draftTo}
                onChange={e => setDraftTo(e.target.value)}
                className="w-[8.5rem] rounded-lg border-0 bg-transparent px-1.5 py-1.5 text-sm text-foreground focus:outline-none focus:ring-0 [color-scheme:light] dark:[color-scheme:dark]"
                aria-label="To date"
            />
            {ready && (dirty || !active) && (
                <button
                    onClick={() => onApply(draftFrom, draftTo)}
                    className="bg-brand-gradient rounded-lg px-3 py-1.5 text-sm font-medium text-white shadow-sm transition-opacity hover:opacity-95"
                >
                    Apply
                </button>
            )}
            {active && (
                <button
                    onClick={() => { setDraftFrom(''); setDraftTo(''); onClear(); }}
                    title="Clear custom range"
                    className="flex h-8 w-8 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                >
                    <X className="h-4 w-4" />
                </button>
            )}
        </div>
    );
}
