import { Head, Link, router } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { Select } from '@/Components/ui/Select';
import { DateFilter, DateFilterValue } from '@/Components/ui/DateFilter';
import { cn, formatCurrency, formatDate, getDueDateColor, getDaysUntil, getDaysSince, getElapsedColor } from '@/Lib/utils';
import { KanbanSquare, Plus, List, LayoutGrid, GripVertical, Search, X, Wallet, FileText, ChevronUp, ChevronDown, ChevronsUpDown } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Option { value: string; label: string }

interface Card {
    id: number;
    proposal_number: string;
    project_name: string;
    status: string;
    value: number;
    currency: string;
    due_date: string | null;
    submission_date: string | null;
    documents: number;
    company: string | null;
    owner: string | null;
}

interface Props {
    proposals: Card[];
    statuses: Array<{ value: string; label: string; color: string }>;
    owners: Option[];
    companies: Option[];
    filters: Record<string, string>;
    totals: { count: number; value: number };
    can: { create: boolean; move: boolean };
}

// Subtle, professional blue for a due date that hasn't passed yet — easily
// readable against the card in both light and dark mode, without being loud.
const DUE_UPCOMING = 'text-blue-600 dark:text-blue-400';

/**
 * Due-date display for the Applications board:
 *  - not yet passed (today or later) → the date in a calm blue;
 *  - overdue + already submitted → days elapsed since due, graded green/yellow/red;
 *  - overdue + not submitted → the date in red.
 */
function dueDisplay(submissionDate: string | null, dueDate: string | null) {
    if (!dueDate) return <span className="text-xs text-muted-foreground">—</span>;

    const days = getDaysUntil(dueDate);
    if (days != null && days >= 0) {
        return <span className={cn('text-xs font-medium', DUE_UPCOMING)}>{formatDate(dueDate)}</span>;
    }

    if (submissionDate) {
        const since = getDaysSince(dueDate) ?? 0;
        return <span className={cn('text-xs', getElapsedColor(since))}>{since}d since due</span>;
    }
    return <span className={cn('text-xs font-medium', getDueDateColor(dueDate))}>{formatDate(dueDate)}</span>;
}

const COLUMN_ACCENT: Record<string, string> = {
    blue: 'bg-blue-500', indigo: 'bg-indigo-500', purple: 'bg-purple-500', violet: 'bg-violet-500',
    amber: 'bg-amber-500', orange: 'bg-orange-500', yellow: 'bg-yellow-500', green: 'bg-emerald-500',
    emerald: 'bg-emerald-500', teal: 'bg-teal-500', cyan: 'bg-cyan-500', red: 'bg-rose-500',
    rose: 'bg-rose-500', gray: 'bg-slate-400', slate: 'bg-slate-400',
};

type ViewMode = 'kanban' | 'list';

export default function ProposalsBoard({ proposals, statuses, owners, companies, filters, totals, can }: Props) {
    const [cards, setCards] = useState<Card[]>(proposals);
    const [dragId, setDragId] = useState<number | null>(null);
    const [overCol, setOverCol] = useState<string | null>(null);
    const [view, setView] = useState<ViewMode>('kanban');
    const [sort, setSort] = useState<{ field: string; dir: 'asc' | 'desc' }>({ field: 'status', dir: 'asc' });

    const hasFilters = Object.values(filters).some(Boolean);
    const setFilter = (key: string, value: string) =>
        router.get('/proposals/board', { ...filters, [key]: value || undefined }, { preserveState: true, preserveScroll: true });
    const setDate = (v: DateFilterValue) =>
        router.get('/proposals/board', { ...filters, date_field: v.date_field, from: v.from, to: v.to }, { preserveState: true, preserveScroll: true });

    useEffect(() => {
        const saved = localStorage.getItem('proposals-board-view');
        if (saved === 'list' || saved === 'kanban') setView(saved);
    }, []);
    useEffect(() => setCards(proposals), [proposals]);

    const switchView = (v: ViewMode) => {
        setView(v);
        localStorage.setItem('proposals-board-view', v);
    };

    const move = (id: number, status: string) => {
        const card = cards.find(c => c.id === id);
        if (!card || card.status === status) return;
        // Optimistic move; server confirms (and reverts on an invalid transition).
        setCards(cs => cs.map(c => (c.id === id ? { ...c, status } : c)));
        router.post(`/proposals/${id}/move`, { status }, {
            preserveScroll: true,
            preserveState: true,
            onError: () => router.reload({ only: ['proposals'] }),
            onSuccess: () => router.reload({ only: ['proposals'] }),
        });
    };

    const statusOrder = statuses.map(s => s.value);

    // List-view sorting is client-side — the board loads every row (no pagination),
    // so any column can sort instantly. Empty values fall to the bottom of an asc sort.
    const toggleSort = (field: string) =>
        setSort(s => (s.field === field ? { field, dir: s.dir === 'asc' ? 'desc' : 'asc' } : { field, dir: 'asc' }));
    const byStatus = (a: Card, b: Card) => statusOrder.indexOf(a.status) - statusOrder.indexOf(b.status);
    const listCards = [...cards].sort((a, b) => {
        let c: number;
        switch (sort.field) {
            case 'value': c = (a.value ?? 0) - (b.value ?? 0); break;
            case 'documents': c = (a.documents ?? 0) - (b.documents ?? 0); break;
            case 'due': c = (a.due_date ?? '￿').localeCompare(b.due_date ?? '￿'); break;
            case 'company': c = (a.company ?? '￿').localeCompare(b.company ?? '￿'); break;
            case 'owner': c = (a.owner ?? '￿').localeCompare(b.owner ?? '￿'); break;
            case 'project': c = a.project_name.localeCompare(b.project_name); break;
            default: c = byStatus(a, b); break; // 'status'
        }
        if (c === 0 && sort.field !== 'status') c = byStatus(a, b);
        return sort.dir === 'asc' ? c : -c;
    });

    const SortHeader = ({ field, label, className }: { field: string; label: string; className?: string }) => (
        <th className={cn('th cursor-pointer select-none transition-colors hover:text-foreground', className)} onClick={() => toggleSort(field)}>
            <span className="inline-flex items-center gap-1">
                {label}
                {sort.field === field
                    ? (sort.dir === 'asc' ? <ChevronUp className="h-3.5 w-3.5 text-primary" /> : <ChevronDown className="h-3.5 w-3.5 text-primary" />)
                    : <ChevronsUpDown className="h-3 w-3 text-muted-foreground/40" />}
            </span>
        </th>
    );

    return (
        <AppLayout>
            <Head title="Applications" />
            <div className="p-4 sm:p-6">
                <PageHeader
                    icon={KanbanSquare}
                    title="Applications"
                    description="Track every proposal by stage. Drag a card to change its status."
                    actions={
                        <>
                            <div className="inline-flex rounded-xl border border-border bg-card p-1">
                                {([['kanban', LayoutGrid, 'Kanban'], ['list', List, 'List']] as const).map(([v, Icon, label]) => (
                                    <button
                                        key={v}
                                        onClick={() => switchView(v)}
                                        className={cn(
                                            'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium transition',
                                            view === v ? 'bg-brand-gradient text-white shadow-sm' : 'text-muted-foreground hover:text-foreground',
                                        )}
                                    >
                                        <Icon className="h-4 w-4" /> {label}
                                    </button>
                                ))}
                            </div>
                            {can.create && <Button href="/proposals/create" icon={Plus}>New Proposal</Button>}
                        </>
                    }
                />

                {/* Filters */}
                <Card className="mb-4 p-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                        <div className="relative min-w-0 flex-1 sm:min-w-[16rem]">
                            <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <input
                                type="text"
                                placeholder="Search applications…"
                                defaultValue={filters.search ?? ''}
                                onKeyDown={e => e.key === 'Enter' && setFilter('search', (e.target as HTMLInputElement).value)}
                                className="input input-with-icon"
                            />
                        </div>
                        <Select
                            value={filters.status ?? ''}
                            onChange={v => setFilter('status', v)}
                            options={statuses.map(s => ({ value: s.value, label: s.label }))}
                            placeholder="All Statuses"
                            className="w-full sm:w-44"
                        />
                        <Select
                            value={filters.owner_id ?? ''}
                            onChange={v => setFilter('owner_id', v)}
                            options={owners}
                            placeholder="All Owners"
                            className="w-full sm:w-44"
                        />
                        <Select
                            value={filters.company_id ?? ''}
                            onChange={v => setFilter('company_id', v)}
                            options={companies}
                            placeholder="All Companies"
                            className="w-full sm:w-48"
                        />
                        {hasFilters && (
                            <button onClick={() => router.get('/proposals/board')} className="inline-flex items-center gap-1 text-sm font-medium text-destructive hover:underline">
                                <X className="h-4 w-4" /> Clear
                            </button>
                        )}
                    </div>
                    <div className="mt-3 border-t border-border pt-3">
                        <DateFilter
                            value={{ date_field: filters.date_field, from: filters.from, to: filters.to }}
                            onChange={setDate}
                        />
                    </div>
                </Card>

                {/* Filtered summary — total value (USD) across the selected set */}
                <div className="mb-4 flex flex-wrap items-center gap-x-5 gap-y-2 rounded-xl border border-border bg-secondary/30 px-4 py-3">
                    <span className="inline-flex items-center gap-2 text-sm">
                        <KanbanSquare className="h-4 w-4 text-muted-foreground" />
                        <span className="text-muted-foreground">{hasFilters ? 'Filtered' : 'Total'}</span>
                        <span className="font-semibold text-foreground">{totals.count}</span>
                        <span className="text-muted-foreground">application{totals.count === 1 ? '' : 's'}</span>
                    </span>
                    <span className="inline-flex items-center gap-2 text-sm" title="Sum of proposal values, normalised to USD across all currencies">
                        <Wallet className="h-4 w-4 text-muted-foreground" />
                        <span className="text-muted-foreground">Total value</span>
                        <span className="font-semibold text-foreground">{formatCurrency(totals.value)}</span>
                        <span className="text-xs text-muted-foreground/70">USD</span>
                    </span>
                </div>

                {view === 'kanban' ? (
                    <div className="grid grid-cols-2 gap-2.5 pb-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                        {statuses.map(col => {
                            const colCards = cards.filter(c => c.status === col.value);
                            return (
                                <div
                                    key={col.value}
                                    onDragOver={e => { e.preventDefault(); setOverCol(col.value); }}
                                    onDragLeave={() => setOverCol(c => (c === col.value ? null : c))}
                                    onDrop={e => { e.preventDefault(); setOverCol(null); if (dragId != null && can.move) move(dragId, col.value); }}
                                    className={`flex min-w-0 flex-col rounded-2xl border border-border bg-secondary/30 transition-colors ${overCol === col.value ? 'ring-2 ring-primary/40 bg-primary/[0.04]' : ''}`}
                                >
                                    <div className="flex items-center gap-1.5 px-2.5 py-2">
                                        <span className={`h-2.5 w-2.5 shrink-0 rounded-full ${COLUMN_ACCENT[col.color] ?? 'bg-slate-400'}`} />
                                        <span className="min-w-0 flex-1 truncate text-sm font-semibold text-foreground" title={col.label}>{col.label}</span>
                                        <span className="shrink-0 rounded-full bg-card px-1.5 py-0.5 text-[11px] font-medium text-muted-foreground">{colCards.length}</span>
                                    </div>

                                    <div className="flex min-h-[4rem] flex-1 flex-col gap-1.5 px-1.5 pb-2">
                                        {colCards.map(c => (
                                            <div
                                                key={c.id}
                                                draggable={can.move}
                                                onDragStart={() => setDragId(c.id)}
                                                onDragEnd={() => setDragId(null)}
                                                className={`group rounded-lg border border-border bg-card p-2.5 shadow-sm transition-all hover:shadow-lift ${can.move ? 'cursor-grab active:cursor-grabbing' : ''} ${dragId === c.id ? 'opacity-50' : ''}`}
                                            >
                                                <div className="flex items-start gap-1.5">
                                                    {can.move && <GripVertical className="mt-0.5 h-3.5 w-3.5 shrink-0 text-muted-foreground/40 group-hover:text-muted-foreground" />}
                                                    <Link href={`/proposals/${c.id}`} className="min-w-0 flex-1">
                                                        <p className="truncate text-sm font-semibold leading-snug text-foreground hover:text-primary">{c.project_name}</p>
                                                        <p className="mt-0.5 font-mono text-[10px] text-muted-foreground">{c.proposal_number}</p>
                                                    </Link>
                                                </div>
                                                <div className="mt-1.5 flex items-center justify-between gap-2 pl-5">
                                                    <span className="truncate text-[11px] text-muted-foreground">{c.company ?? c.owner ?? '—'}</span>
                                                    {c.value > 0 && <span className="shrink-0 text-[11px] font-semibold text-foreground">{formatCurrency(c.value, c.currency)}</span>}
                                                </div>
                                                <div className="mt-1 flex items-center justify-between gap-2 pl-5">
                                                    {dueDisplay(c.submission_date, c.due_date)}
                                                    {c.documents > 0 && (
                                                        <span className="inline-flex shrink-0 items-center gap-0.5 text-[10px] text-muted-foreground" title={`${c.documents} document${c.documents === 1 ? '' : 's'}`}>
                                                            <FileText className="h-3 w-3" />{c.documents}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                        {colCards.length === 0 && (
                                            <div className="rounded-xl border border-dashed border-border py-6 text-center text-xs text-muted-foreground/60">Drop here</div>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                ) : (
                    <div className="card-surface overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead className="border-b border-border bg-secondary/40">
                                    <tr>
                                        <SortHeader field="project" label="Proposal" className="w-2/5" />
                                        <SortHeader field="status" label="Status" />
                                        <SortHeader field="company" label="Company" className="hidden md:table-cell" />
                                        <SortHeader field="owner" label="Owner" className="hidden lg:table-cell" />
                                        <SortHeader field="documents" label="Docs" className="hidden text-center sm:table-cell" />
                                        <SortHeader field="value" label="Value" className="text-right" />
                                        <SortHeader field="due" label="Due" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {listCards.map(c => (
                                        <tr key={c.id} className="row-link">
                                            <td className="td">
                                                <Link href={`/proposals/${c.id}`} className="block max-w-[28rem]">
                                                    <p className="line-clamp-2 break-words text-sm font-medium leading-snug text-foreground hover:text-primary">{c.project_name}</p>
                                                    <p className="mt-0.5 font-mono text-[11px] text-muted-foreground">{c.proposal_number}</p>
                                                </Link>
                                            </td>
                                            <td className="td">
                                                {can.move ? (
                                                    <Select
                                                        size="sm"
                                                        className="w-44"
                                                        value={c.status}
                                                        onChange={v => move(c.id, v)}
                                                        options={statuses.map(s => ({ value: s.value, label: s.label }))}
                                                    />
                                                ) : (
                                                    <StatusBadge status={c.status} />
                                                )}
                                            </td>
                                            <td className="td hidden text-muted-foreground md:table-cell">{c.company ?? '—'}</td>
                                            <td className="td hidden text-muted-foreground lg:table-cell">{c.owner ?? '—'}</td>
                                            <td className="td hidden text-center sm:table-cell">
                                                <span className="inline-flex items-center gap-1 text-sm text-muted-foreground" title={`${c.documents} document${c.documents === 1 ? '' : 's'} attached`}>
                                                    <FileText className="h-3.5 w-3.5" />{c.documents}
                                                </span>
                                            </td>
                                            <td className="td whitespace-nowrap text-right font-medium text-foreground">{c.value > 0 ? formatCurrency(c.value, c.currency) : '—'}</td>
                                            <td className="td whitespace-nowrap">{dueDisplay(c.submission_date, c.due_date)}</td>
                                        </tr>
                                    ))}
                                    {listCards.length === 0 && (
                                        <tr><td className="td py-10 text-center text-sm text-muted-foreground" colSpan={7}>No proposals yet.</td></tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
