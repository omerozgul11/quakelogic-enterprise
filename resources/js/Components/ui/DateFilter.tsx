import { Select } from '@/Components/ui/Select';
import { cn, APP_TZ } from '@/Lib/utils';
import { CalendarRange, X } from 'lucide-react';

export interface DateFilterValue {
    date_field?: string;
    from?: string;
    to?: string;
}

const FIELDS = [
    { value: 'due_date', label: 'Due date' },
    { value: 'submission_date', label: 'Submission date' },
    { value: 'created_at', label: 'Created date' },
];

const pad = (n: number) => String(n).padStart(2, '0');

/** Today's year/month/day as it falls in Pacific time. */
function pstParts(): { y: number; m: number; d: number } {
    const s = new Intl.DateTimeFormat('en-CA', { timeZone: APP_TZ, year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
    const [y, m, d] = s.split('-').map(Number);
    return { y, m, d };
}

function thisMonth(): [string, string] {
    const { y, m } = pstParts();
    const last = new Date(Date.UTC(y, m, 0)).getUTCDate();
    return [`${y}-${pad(m)}-01`, `${y}-${pad(m)}-${pad(last)}`];
}

function thisYear(): [string, string] {
    const { y } = pstParts();
    return [`${y}-01-01`, `${y}-12-31`];
}

const dateInput = 'w-[8.25rem] rounded-lg border-0 bg-transparent px-1.5 py-1.5 text-sm text-foreground focus:outline-none focus:ring-0 [color-scheme:light] dark:[color-scheme:dark]';

/**
 * A compact "filter records by date" control: pick which date to filter on
 * (due / submission / created), then a custom from–to range or a quick preset.
 * Applies on every change. Dates are interpreted in Pacific time server-side.
 */
export function DateFilter({ value, onChange }: { value: DateFilterValue; onChange: (v: DateFilterValue) => void }) {
    const field = value.date_field || 'due_date';
    const active = !!(value.from || value.to);
    const apply = (from?: string, to?: string) => onChange({ date_field: field, from: from || undefined, to: to || undefined });

    return (
        <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
            <Select
                value={field}
                onChange={v => onChange({ date_field: v, from: value.from, to: value.to })}
                options={FIELDS}
                className="w-full sm:w-40"
            />
            <div className={cn('flex items-center gap-1 rounded-xl border bg-card p-1', active ? 'border-primary/50 ring-1 ring-primary/20' : 'border-border')}>
                <CalendarRange className={cn('ml-1.5 h-4 w-4 shrink-0', active ? 'text-primary' : 'text-muted-foreground')} />
                <input type="date" value={value.from ?? ''} onChange={e => apply(e.target.value, value.to)} className={dateInput} aria-label="From date" />
                <span className="text-xs text-muted-foreground">–</span>
                <input type="date" value={value.to ?? ''} onChange={e => apply(value.from, e.target.value)} className={dateInput} aria-label="To date" />
                {active && (
                    <button onClick={() => onChange({ date_field: field })} title="Clear dates" className="flex h-7 w-7 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground">
                        <X className="h-4 w-4" />
                    </button>
                )}
            </div>
            <div className="flex gap-1">
                {([['This month', thisMonth], ['This year', thisYear]] as const).map(([label, range]) => (
                    <button
                        key={label}
                        onClick={() => { const [f, t] = range(); apply(f, t); }}
                        className="rounded-lg border border-border bg-card px-2.5 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                    >
                        {label}
                    </button>
                ))}
            </div>
        </div>
    );
}
