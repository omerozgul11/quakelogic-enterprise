import { Head, Link, router, useForm } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { Select } from '@/Components/ui/Select';
import { SearchInput } from '@/Components/ui/SearchInput';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Pagination } from '@/Components/ui/Pagination';
import { cn, formatCurrency, formatDate, formatRelativeDate, formatTime, getDueDateLabel, getDueDateColor, sourceLabel } from '@/Lib/utils';
import { PaginatedResponse, Opportunity } from '@/Types';
import { Plus, Upload, Search, X, ExternalLink, Target, Tag, ChevronUp, ChevronDown, ChevronsUpDown, Star, Sparkles, Lock, UserCheck } from 'lucide-react';
import { useState, useEffect } from 'react';

type OppRow = Opportunity & {
    owner?: { id: number; name: string } | null;
    ownership_locked?: boolean;
    assignment_stage?: string | null;
};

interface Props {
    opportunities: PaginatedResponse<OppRow>;
    filters: Record<string, string | string[]>;
    view: 'foryou' | 'saved' | 'all';
    counts: { all: number; foryou: number; saved: number };
    savedIds: number[];
    keywordOptions: string[];
    personalKeywords: string[];
    statuses: Array<{ value: string; label: string; color: string }>;
    sources: Array<{ value: string; label: string }>;
    can: { create: boolean; import: boolean };
}

export default function OpportunitiesIndex({ opportunities, filters, view, counts, savedIds, keywordOptions, personalKeywords, statuses, sources, can }: Props) {
    const savedSet = new Set(savedIds);
    const setView = (v: string) => router.get('/opportunities', { ...filters, view: v }, { preserveState: true, preserveScroll: true });
    const toggleSave = (e: React.MouseEvent, id: number) => {
        e.preventDefault();
        e.stopPropagation();
        router.post(`/opportunities/${id}/save`, {}, { preserveScroll: true });
    };
    const TABS: Array<{ key: string; label: string; count: number; icon?: typeof Star }> = [
        { key: 'foryou', label: 'For You', count: counts.foryou, icon: Sparkles },
        { key: 'saved', label: 'Saved', count: counts.saved, icon: Star },
        { key: 'all', label: 'All', count: counts.all },
    ];
    const [showImportModal, setShowImportModal] = useState(false);
    const [newKeyword, setNewKeyword] = useState('');
    const { data, setData, post, processing } = useForm({ naics_codes: [] as string[], keywords: '' });

    const selectedKeywords: string[] = Array.isArray(filters.keywords) ? filters.keywords : [];
    const hasFilters = !!(filters.status || filters.source || filters.search || filters.naics || selectedKeywords.length);

    // Auto-refresh the pipeline every 5 minutes (the server also purges expired
    // opportunities and pulls fresh ones from SAM.gov, throttled).
    useEffect(() => {
        const id = setInterval(() => {
            router.reload({ only: ['opportunities'] });
        }, 5 * 60 * 1000);
        return () => clearInterval(id);
    }, []);

    const handleFilter = (key: string, value: string) => {
        router.get('/opportunities', { ...filters, [key]: value || undefined }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const toggleKeyword = (kw: string) => {
        const next = selectedKeywords.includes(kw)
            ? selectedKeywords.filter(k => k !== kw)
            : [...selectedKeywords, kw];
        router.get('/opportunities', { ...filters, keywords: next.length ? next : undefined }, { preserveState: true, preserveScroll: true });
    };

    const addKeyword = (e: React.FormEvent) => {
        e.preventDefault();
        const kw = newKeyword.trim();
        if (!kw) return;
        router.post('/opportunities/keywords', { keyword: kw }, { preserveScroll: true, onSuccess: () => setNewKeyword('') });
    };

    const removeKeyword = (kw: string) => {
        router.delete('/opportunities/keywords', { data: { keyword: kw }, preserveScroll: true });
    };

    const sort = typeof filters.sort === 'string' ? filters.sort : 'created_at';
    const direction = filters.direction === 'asc' ? 'asc' : 'desc';
    const DEFAULT_DIR: Record<string, 'asc' | 'desc'> = {
        estimated_value: 'desc', due_date: 'asc', title: 'asc', agency_name: 'asc', status: 'asc',
    };
    const setSort = (field: string) => {
        const dir = sort === field ? (direction === 'asc' ? 'desc' : 'asc') : (DEFAULT_DIR[field] ?? 'asc');
        router.get('/opportunities', { ...filters, sort: field, direction: dir }, { preserveState: true, preserveScroll: true });
    };
    const SortHeader = ({ field, label, align = 'left', className }: { field: string; label: string; align?: 'left' | 'right'; className?: string }) => (
        <th className={cn('th cursor-pointer select-none transition-colors hover:text-foreground', className)} onClick={() => setSort(field)}>
            <span className={cn('inline-flex items-center gap-1', align === 'right' && 'justify-end')}>
                {label}
                {sort === field
                    ? (direction === 'asc' ? <ChevronUp className="h-3.5 w-3.5 text-primary" /> : <ChevronDown className="h-3.5 w-3.5 text-primary" />)
                    : <ChevronsUpDown className="h-3 w-3 text-muted-foreground/40" />}
            </span>
        </th>
    );

    const handleImport = (e: React.FormEvent) => {
        e.preventDefault();
        post('/opportunities/import/sam-gov', { onSuccess: () => setShowImportModal(false) });
    };

    return (
        <AppLayout>
            <Head title="Opportunities" />
            <div className="p-4 sm:p-6">
                <PageHeader
                    icon={Target}
                    title="Opportunities"
                    description={`${opportunities.total} ${opportunities.total === 1 ? 'opportunity' : 'opportunities'} in your pipeline`}
                    actions={
                        <>
                            {can.import && (
                                <Button variant="secondary" icon={Upload} onClick={() => setShowImportModal(true)}>
                                    Import from SAM.gov
                                </Button>
                            )}
                            {can.create && (
                                <Button href="/opportunities/create" icon={Plus}>
                                    Add Opportunity
                                </Button>
                            )}
                        </>
                    }
                />

                {/* For You / Saved / All */}
                <div className="mb-4 inline-flex w-fit gap-1 rounded-xl border border-border bg-card p-1">
                    {TABS.map(t => {
                        const active = view === t.key;
                        const Icon = t.icon;
                        return (
                            <button
                                key={t.key}
                                onClick={() => setView(t.key)}
                                className={cn(
                                    'inline-flex items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-sm font-medium transition',
                                    active ? 'bg-primary/[0.12] text-primary ring-1 ring-inset ring-primary/20' : 'text-muted-foreground hover:bg-secondary hover:text-foreground',
                                )}
                            >
                                {Icon && <Icon className="h-3.5 w-3.5" />}
                                {t.label}
                                <span className={cn('rounded-full px-1.5 py-0.5 text-[10px] font-semibold tabular-nums', active ? 'bg-primary/15 text-primary' : 'bg-secondary')}>{t.count}</span>
                            </button>
                        );
                    })}
                </div>

                {/* Filters */}
                <Card className="mb-4 p-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                        <SearchInput
                            className="min-w-0 flex-1 sm:min-w-[18rem]"
                            initial={typeof filters.search === 'string' ? filters.search : ''}
                            onSearch={v => handleFilter('search', v)}
                            placeholder="Search by keyword — title, agency, description…"
                        />
                        <Select
                            value={(filters.status as string) ?? ''}
                            onChange={v => handleFilter('status', v)}
                            options={statuses.map(s => ({ value: s.value, label: s.label }))}
                            placeholder="All Statuses"
                            className="w-full sm:w-44"
                        />
                        <Select
                            value={(filters.source as string) ?? ''}
                            onChange={v => handleFilter('source', v)}
                            options={sources.map(s => ({ value: s.value, label: s.label }))}
                            placeholder="All Sources"
                            className="w-full sm:w-40"
                        />
                        {hasFilters && (
                            <button onClick={() => router.get('/opportunities')} className="inline-flex items-center gap-1 text-sm font-medium text-destructive hover:underline">
                                <X className="h-4 w-4" /> Clear
                            </button>
                        )}
                    </div>

                    <div className="mt-3 flex flex-wrap items-center gap-2 border-t border-border pt-3">
                        <span className="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground">
                            <Tag className="h-3.5 w-3.5" /> Keywords
                        </span>
                        {keywordOptions.map(kw => {
                            const active = selectedKeywords.includes(kw);
                            return (
                                <button
                                    key={kw}
                                    onClick={() => toggleKeyword(kw)}
                                    className={cn(
                                        'rounded-full border px-3 py-1 text-xs font-medium capitalize transition',
                                        active
                                            ? 'border-primary bg-primary/10 text-primary'
                                            : 'border-border bg-card text-muted-foreground hover:bg-secondary hover:text-foreground',
                                    )}
                                >
                                    {kw}
                                </button>
                            );
                        })}

                        {/* Your private keywords — only you can see/use these. */}
                        {personalKeywords.map(kw => {
                            const active = selectedKeywords.includes(kw);
                            return (
                                <span
                                    key={kw}
                                    className={cn(
                                        'inline-flex items-center gap-1 rounded-full border py-1 pl-3 pr-1.5 text-xs font-medium capitalize transition',
                                        active
                                            ? 'border-primary bg-primary/10 text-primary'
                                            : 'border-dashed border-primary/40 bg-card text-muted-foreground hover:bg-secondary hover:text-foreground',
                                    )}
                                    title="Your private keyword"
                                >
                                    <button onClick={() => toggleKeyword(kw)}>{kw}</button>
                                    <button onClick={() => removeKeyword(kw)} title="Remove keyword" className="rounded-full p-0.5 hover:bg-destructive/15 hover:text-destructive">
                                        <X className="h-3 w-3" />
                                    </button>
                                </span>
                            );
                        })}

                        {/* Add a private keyword */}
                        <form onSubmit={addKeyword} className="inline-flex items-center">
                            <input
                                type="text"
                                value={newKeyword}
                                onChange={e => setNewKeyword(e.target.value)}
                                placeholder="+ add keyword"
                                className="h-7 w-32 rounded-full border border-dashed border-border bg-card px-3 text-xs text-foreground placeholder:text-muted-foreground/70 focus:border-primary focus:outline-none focus:ring-1 focus:ring-orange-400"
                            />
                        </form>

                        {selectedKeywords.length > 0 && (
                            <button
                                onClick={() => router.get('/opportunities', { ...filters, keywords: undefined }, { preserveState: true, preserveScroll: true })}
                                className="text-xs font-medium text-destructive hover:underline"
                            >
                                Clear keywords
                            </button>
                        )}
                    </div>
                </Card>

                {/* Table */}
                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b border-border bg-secondary/40">
                                <tr>
                                    <th className="th w-8" />
                                    <SortHeader field="title" label="Title" />
                                    <SortHeader field="agency_name" label="Agency" className="hidden md:table-cell" />
                                    <SortHeader field="status" label="Status" />
                                    <SortHeader field="estimated_value" label="Value" />
                                    <SortHeader field="due_date" label="Due Date" className="hidden sm:table-cell" />
                                    <SortHeader field="created_at" label="Added" className="hidden lg:table-cell" />
                                    <th className="th hidden lg:table-cell">Source</th>
                                    <th className="th" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {opportunities.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={9}>
                                            {view === 'foryou' && personalKeywords.length === 0 ? (
                                                <EmptyState
                                                    icon={Sparkles}
                                                    title="Build your For You feed"
                                                    description="Add a few keywords below (e.g. cybersecurity, cloud, logistics) and matching opportunities will show up here — refreshed automatically."
                                                />
                                            ) : view === 'saved' ? (
                                                <EmptyState
                                                    icon={Star}
                                                    title="No saved opportunities"
                                                    description="Click the star on any opportunity to save it here for quick access."
                                                />
                                            ) : (
                                                <EmptyState
                                                    icon={Target}
                                                    title="No opportunities found"
                                                    description="Try adjusting your filters, or import fresh opportunities from SAM.gov."
                                                    action={can.import && <Button variant="secondary" icon={Upload} onClick={() => setShowImportModal(true)}>Import from SAM.gov</Button>}
                                                />
                                            )}
                                        </td>
                                    </tr>
                                ) : opportunities.data.map(opp => (
                                    <tr key={opp.id} className="row-link">
                                        <td className="td w-8">
                                            <button
                                                type="button"
                                                onClick={e => toggleSave(e, opp.id)}
                                                title={savedSet.has(opp.id) ? 'Remove from saved' : 'Save to your favorites'}
                                                className="text-muted-foreground transition-colors hover:text-amber-500"
                                            >
                                                <Star className={cn('h-4 w-4', savedSet.has(opp.id) && 'fill-amber-400 text-amber-500')} />
                                            </button>
                                        </td>
                                        <td className="td max-w-md">
                                            <Link href={`/opportunities/${opp.id}`} className="font-medium text-foreground hover:text-primary line-clamp-2">
                                                {opp.title}
                                            </Link>
                                            {opp.solicitation_number && (
                                                <p className="mt-0.5 text-xs text-muted-foreground">{opp.solicitation_number}</p>
                                            )}
                                            {opp.owner && (
                                                <p className="mt-0.5 inline-flex items-center gap-1 text-xs text-muted-foreground" title={opp.ownership_locked ? `Owned by ${opp.owner.name} (locked)` : `Owned by ${opp.owner.name}`}>
                                                    {opp.ownership_locked ? <Lock className="h-3 w-3 text-amber-500" /> : <UserCheck className="h-3 w-3" />}
                                                    {opp.owner.name}
                                                </p>
                                            )}
                                        </td>
                                        <td className="td hidden text-muted-foreground md:table-cell">{opp.agency_name ?? '—'}</td>
                                        <td className="td"><StatusBadge status={opp.status} /></td>
                                        <td className="td font-medium">{formatCurrency(opp.estimated_value)}</td>
                                        <td className="td hidden sm:table-cell">
                                            <span className={`text-sm font-medium ${getDueDateColor(opp.due_date)}`}>
                                                {opp.due_date ? getDueDateLabel(opp.due_date) : '—'}
                                            </span>
                                            {opp.due_date && <p className="text-xs text-muted-foreground">{formatDate(opp.due_date)}</p>}
                                        </td>
                                        <td className="td hidden lg:table-cell">
                                            <span className="text-sm text-foreground">{formatRelativeDate(opp.created_at)}</span>
                                            <p className="text-xs text-muted-foreground">
                                                {formatRelativeDate(opp.created_at) !== formatDate(opp.created_at)
                                                    ? `${formatDate(opp.created_at)} · ${formatTime(opp.created_at)}`
                                                    : formatTime(opp.created_at)}
                                            </p>
                                        </td>
                                        <td className="td hidden lg:table-cell">
                                            <span className="chip">{sourceLabel(opp.source)}</span>
                                        </td>
                                        <td className="td">
                                            <Link href={`/opportunities/${opp.id}`} className="text-muted-foreground transition-colors hover:text-primary">
                                                <ExternalLink className="h-4 w-4" />
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination from={opportunities.from} to={opportunities.to} total={opportunities.total} links={opportunities.links} />
                </Card>
            </div>

            {/* Import Modal */}
            {showImportModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div className="animate-fade-in fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onClick={() => setShowImportModal(false)} />
                    <div className="card-surface animate-scale-in relative w-full max-w-md p-6">
                        <h2 className="text-lg font-semibold text-foreground">Import from SAM.gov</h2>
                        <p className="mt-1 text-sm text-muted-foreground">Pull the latest federal opportunities into your pipeline.</p>
                        <form onSubmit={handleImport} className="mt-5 space-y-4">
                            <div>
                                <label className="label">Keywords (optional)</label>
                                <input
                                    type="text"
                                    value={data.keywords}
                                    onChange={e => setData('keywords', e.target.value)}
                                    placeholder="e.g., cybersecurity, cloud, AI"
                                    className="input"
                                />
                            </div>
                            <p className="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-300">
                                Connected to SAM.gov. Your pipeline also refreshes automatically on login — fresh
                                opportunities are pulled in and past-due ones are removed. Use this to pull on demand.
                            </p>
                            <div className="flex justify-end gap-3">
                                <Button type="button" variant="secondary" onClick={() => setShowImportModal(false)}>Cancel</Button>
                                <Button type="submit" disabled={processing}>{processing ? 'Importing…' : 'Start Import'}</Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
