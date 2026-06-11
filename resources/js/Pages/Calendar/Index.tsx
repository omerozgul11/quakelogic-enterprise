import { Head, Link } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card, CardContent } from '@/Components/ui/Card';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { formatCurrency, formatDate, cn } from '@/Lib/utils';
import { CalendarDays, ChevronLeft, ChevronRight, FileText, Target } from 'lucide-react';
import { useMemo, useState } from 'react';

interface CalendarEvent {
    id: string;
    type: 'proposal' | 'opportunity';
    date: string; // YYYY-MM-DD
    title: string;
    subtitle: string;
    status: string;
    value: number;
    currency: string;
    url: string;
}

interface Props {
    events: CalendarEvent[];
    counts: { proposals: number; opportunities: number };
}

const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

const pad = (n: number) => String(n).padStart(2, '0');
const keyOf = (y: number, m: number, d: number) => `${y}-${pad(m + 1)}-${pad(d)}`;

const TYPE_STYLE: Record<CalendarEvent['type'], { chip: string; dot: string; icon: typeof FileText; label: string }> = {
    proposal: { chip: 'bg-primary/10 text-primary hover:bg-primary/20', dot: 'bg-primary', icon: FileText, label: 'Proposal' },
    opportunity: { chip: 'bg-blue-500/10 text-blue-600 hover:bg-blue-500/20 dark:text-blue-400', dot: 'bg-blue-500', icon: Target, label: 'Opportunity' },
};

export default function CalendarIndex({ events, counts }: Props) {
    const today = new Date();
    const todayKey = keyOf(today.getFullYear(), today.getMonth(), today.getDate());

    const [view, setView] = useState({ year: today.getFullYear(), month: today.getMonth() });
    const [filters, setFilters] = useState({ proposal: true, opportunity: true });
    const [selected, setSelected] = useState<string>(todayKey);

    const byDate = useMemo(() => {
        const map: Record<string, CalendarEvent[]> = {};
        for (const e of events) {
            if (!filters[e.type]) continue;
            (map[e.date] ??= []).push(e);
        }
        return map;
    }, [events, filters]);

    const cells = useMemo(() => {
        const firstWeekday = new Date(view.year, view.month, 1).getDay();
        return Array.from({ length: 42 }, (_, i) => {
            const d = new Date(view.year, view.month, 1 - firstWeekday + i);
            return {
                key: keyOf(d.getFullYear(), d.getMonth(), d.getDate()),
                day: d.getDate(),
                inMonth: d.getMonth() === view.month,
            };
        });
    }, [view]);

    const shift = (delta: number) => {
        const m = view.month + delta;
        setView({ year: view.year + Math.floor(m / 12), month: ((m % 12) + 12) % 12 });
    };
    const goToday = () => {
        setView({ year: today.getFullYear(), month: today.getMonth() });
        setSelected(todayKey);
    };

    const selectedEvents = (byDate[selected] ?? []).slice().sort((a, b) => a.type.localeCompare(b.type) || b.value - a.value);

    return (
        <AppLayout>
            <Head title="Calendar" />
            <div className="p-6">
                <PageHeader
                    icon={CalendarDays}
                    title="Calendar"
                    description="Your proposals and the opportunities you're pursuing, placed automatically on their due dates."
                />

                {/* Controls */}
                <Card className="mb-4">
                    <CardContent className="flex flex-wrap items-center gap-3 py-4">
                        <div className="flex items-center gap-1">
                            <button onClick={() => shift(-1)} aria-label="Previous month" className="rounded-lg border border-border p-2 text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground">
                                <ChevronLeft className="h-4 w-4" />
                            </button>
                            <button onClick={() => shift(1)} aria-label="Next month" className="rounded-lg border border-border p-2 text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground">
                                <ChevronRight className="h-4 w-4" />
                            </button>
                            <button onClick={goToday} className="ml-1 rounded-lg border border-border px-3 py-1.5 text-sm font-medium text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground">
                                Today
                            </button>
                        </div>

                        <h2 className="text-lg font-semibold text-foreground">{MONTHS[view.month]} {view.year}</h2>

                        <div className="ml-auto flex flex-wrap items-center gap-2">
                            {(['proposal', 'opportunity'] as const).map(type => {
                                const s = TYPE_STYLE[type];
                                const on = filters[type];
                                const count = type === 'proposal' ? counts.proposals : counts.opportunities;
                                return (
                                    <button
                                        key={type}
                                        onClick={() => setFilters(f => ({ ...f, [type]: !f[type] }))}
                                        className={cn(
                                            'inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-medium transition',
                                            on ? 'border-border bg-card text-foreground' : 'border-dashed border-border bg-transparent text-muted-foreground/60',
                                        )}
                                    >
                                        <span className={cn('h-2.5 w-2.5 rounded-full', on ? s.dot : 'bg-muted-foreground/30')} />
                                        {s.label}s
                                        <span className="text-xs text-muted-foreground">{count}</span>
                                    </button>
                                );
                            })}
                        </div>
                    </CardContent>
                </Card>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_20rem]">
                    {/* Month grid */}
                    <Card className="overflow-hidden">
                        <div className="grid grid-cols-7 border-b border-border bg-secondary/40">
                            {WEEKDAYS.map(w => (
                                <div key={w} className="px-2 py-2 text-center text-xs font-semibold uppercase tracking-wide text-muted-foreground">{w}</div>
                            ))}
                        </div>
                        <div className="grid grid-cols-7">
                            {cells.map(cell => {
                                const dayEvents = byDate[cell.key] ?? [];
                                const isToday = cell.key === todayKey;
                                const isSelected = cell.key === selected;
                                return (
                                    <button
                                        key={cell.key}
                                        onClick={() => setSelected(cell.key)}
                                        className={cn(
                                            'flex min-h-[6.5rem] flex-col gap-1 border-b border-r border-border p-1.5 text-left align-top transition-colors',
                                            cell.inMonth ? 'bg-card hover:bg-secondary/40' : 'bg-secondary/20 text-muted-foreground/50',
                                            isSelected && 'ring-2 ring-inset ring-primary/50',
                                        )}
                                    >
                                        <span className={cn(
                                            'flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                                            isToday ? 'bg-primary text-white' : cell.inMonth ? 'text-foreground' : 'text-muted-foreground/50',
                                        )}>
                                            {cell.day}
                                        </span>
                                        <div className="flex flex-col gap-1 overflow-hidden">
                                            {dayEvents.slice(0, 3).map(e => {
                                                const s = TYPE_STYLE[e.type];
                                                return (
                                                    <Link
                                                        key={e.id}
                                                        href={e.url}
                                                        onClick={ev => ev.stopPropagation()}
                                                        className={cn('flex items-center gap-1 truncate rounded px-1.5 py-0.5 text-[11px] font-medium transition-colors', s.chip)}
                                                        title={e.title}
                                                    >
                                                        <span className={cn('h-1.5 w-1.5 shrink-0 rounded-full', s.dot)} />
                                                        <span className="truncate">{e.title}</span>
                                                    </Link>
                                                );
                                            })}
                                            {dayEvents.length > 3 && (
                                                <span className="px-1.5 text-[11px] font-medium text-muted-foreground">+{dayEvents.length - 3} more</span>
                                            )}
                                        </div>
                                    </button>
                                );
                            })}
                        </div>
                    </Card>

                    {/* Selected-day detail */}
                    <Card className="h-fit">
                        <CardContent className="py-4">
                            <p className="mb-3 text-sm font-semibold text-foreground">
                                {formatDate(selected)}
                                <span className="ml-1.5 font-normal text-muted-foreground">· {selectedEvents.length} {selectedEvents.length === 1 ? 'item' : 'items'}</span>
                            </p>
                            {selectedEvents.length === 0 ? (
                                <p className="py-6 text-center text-sm text-muted-foreground">Nothing due on this day.</p>
                            ) : (
                                <div className="space-y-2">
                                    {selectedEvents.map(e => {
                                        const s = TYPE_STYLE[e.type];
                                        const Icon = s.icon;
                                        return (
                                            <Link key={e.id} href={e.url} className="block rounded-xl border border-border p-3 transition-colors hover:bg-secondary/50">
                                                <div className="flex items-center gap-1.5">
                                                    <Icon className={cn('h-3.5 w-3.5', e.type === 'proposal' ? 'text-primary' : 'text-blue-500')} />
                                                    <span className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">{s.label}</span>
                                                    <span className="ml-auto"><StatusBadge status={e.status} /></span>
                                                </div>
                                                <p className="mt-1 line-clamp-2 text-sm font-medium text-foreground">{e.title}</p>
                                                <p className="mt-0.5 truncate text-xs text-muted-foreground">{e.subtitle}</p>
                                                {e.value > 0 && <p className="mt-1 text-xs font-semibold text-foreground">{formatCurrency(e.value, e.currency)}</p>}
                                            </Link>
                                        );
                                    })}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
