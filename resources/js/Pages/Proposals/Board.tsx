import { Head, Link, router } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { Select } from '@/Components/ui/Select';
import { cn, formatCurrency, formatDate, getDueDateColor } from '@/Lib/utils';
import { KanbanSquare, Plus, List, LayoutGrid, GripVertical } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Card {
    id: number;
    proposal_number: string;
    project_name: string;
    status: string;
    value: number;
    currency: string;
    due_date: string | null;
    company: string | null;
    owner: string | null;
}

interface Props {
    proposals: Card[];
    statuses: Array<{ value: string; label: string; color: string }>;
    can: { create: boolean; move: boolean };
}

const COLUMN_ACCENT: Record<string, string> = {
    blue: 'bg-blue-500', indigo: 'bg-indigo-500', purple: 'bg-purple-500', violet: 'bg-violet-500',
    amber: 'bg-amber-500', orange: 'bg-orange-500', yellow: 'bg-yellow-500', green: 'bg-emerald-500',
    emerald: 'bg-emerald-500', teal: 'bg-teal-500', cyan: 'bg-cyan-500', red: 'bg-rose-500',
    rose: 'bg-rose-500', gray: 'bg-slate-400', slate: 'bg-slate-400',
};

type ViewMode = 'kanban' | 'list';

export default function ProposalsBoard({ proposals, statuses, can }: Props) {
    const [cards, setCards] = useState<Card[]>(proposals);
    const [dragId, setDragId] = useState<number | null>(null);
    const [overCol, setOverCol] = useState<string | null>(null);
    const [view, setView] = useState<ViewMode>('kanban');

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
    const listCards = [...cards].sort((a, b) => statusOrder.indexOf(a.status) - statusOrder.indexOf(b.status));

    return (
        <AppLayout>
            <Head title="Applications" />
            <div className="p-6">
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
                                                {c.due_date && (
                                                    <p className={`mt-1 pl-5 text-[10px] font-medium ${getDueDateColor(c.due_date)}`}>Due {formatDate(c.due_date)}</p>
                                                )}
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
                                        <th className="th w-2/5">Proposal</th>
                                        <th className="th">Status</th>
                                        <th className="th">Company / Owner</th>
                                        <th className="th text-right">Value</th>
                                        <th className="th">Due</th>
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
                                            <td className="td text-muted-foreground">{c.company ?? c.owner ?? '—'}</td>
                                            <td className="td whitespace-nowrap text-right font-medium text-foreground">{c.value > 0 ? formatCurrency(c.value, c.currency) : '—'}</td>
                                            <td className="td whitespace-nowrap">
                                                {c.due_date
                                                    ? <span className={`text-xs font-medium ${getDueDateColor(c.due_date)}`}>{formatDate(c.due_date)}</span>
                                                    : <span className="text-xs text-muted-foreground">—</span>}
                                            </td>
                                        </tr>
                                    ))}
                                    {listCards.length === 0 && (
                                        <tr><td className="td py-10 text-center text-sm text-muted-foreground" colSpan={5}>No proposals yet.</td></tr>
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
