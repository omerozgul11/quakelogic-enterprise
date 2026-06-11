import { Head, router, useForm } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card, CardContent } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { cn, formatCurrency, formatDate, formatTime } from '@/Lib/utils';
import { TrendingUp, Search, ExternalLink, Tag, X } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Award {
    title: string;
    agency: string | null;
    naics: string | null;
    amount: number | null;
    awardee: string | null;
    award_date: string | null;
    solicitation_number: string | null;
    posted_date: string | null;
    set_aside: string | null;
    url: string | null;
    matched_keyword?: string | null;
}

interface Props {
    awards: Award[];
    filters: { keyword?: string; naics?: string };
    searched: boolean;
    stats: { count: number; priced: number; median: number | null; max: number | null; min: number | null };
    feedKeywords: string[];
    personalKeywords: string[];
    refreshedAt: string | null;
    connected: boolean;
}

const RELOAD_PROPS = ['awards', 'stats', 'feedKeywords', 'personalKeywords', 'refreshedAt'];

export default function MarketPricingIndex({ awards, filters, searched, stats, feedKeywords, personalKeywords, refreshedAt, connected }: Props) {
    const { data, setData, get, processing } = useForm({
        keyword: filters.keyword ?? '',
        naics: filters.naics ?? '',
    });
    const [newKeyword, setNewKeyword] = useState('');
    const [activeKws, setActiveKws] = useState<string[]>([]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        get('/market-pricing', { preserveState: true, preserveScroll: true });
    };

    // Same cadence as Opportunities: the server feed refreshes every 5 minutes
    // in the background, so re-pull the page data on the same interval.
    useEffect(() => {
        const id = setInterval(() => {
            router.reload({ only: RELOAD_PROPS });
        }, 5 * 60 * 1000);
        return () => clearInterval(id);
    }, []);

    // A null refreshedAt means a personal feed is being built in the background
    // right now (e.g. a keyword was just saved) — re-check shortly.
    useEffect(() => {
        if (searched || refreshedAt !== null) return;
        const id = setTimeout(() => router.reload({ only: RELOAD_PROPS }), 15 * 1000);
        return () => clearTimeout(id);
    }, [searched, refreshedAt]);

    const lower = (s: string) => s.toLowerCase();
    const personalSet = new Set(personalKeywords.map(lower));
    const sharedChips = feedKeywords.filter(k => !personalSet.has(lower(k)));

    const toggleKeyword = (kw: string) => {
        const k = lower(kw);
        setActiveKws(ks => (ks.includes(k) ? ks.filter(x => x !== k) : [...ks, k]));
    };

    const addKeyword = (e: React.FormEvent) => {
        e.preventDefault();
        const kw = newKeyword.trim();
        if (!kw) return;
        router.post('/market-pricing/keywords', { keyword: kw }, { preserveScroll: true, onSuccess: () => setNewKeyword('') });
    };

    const removeKeyword = (kw: string) => {
        setActiveKws(ks => ks.filter(x => x !== lower(kw)));
        router.delete('/market-pricing/keywords', { data: { keyword: kw }, preserveScroll: true });
    };

    const shownAwards = !searched && activeKws.length > 0
        ? awards.filter(a => a.matched_keyword && activeKws.includes(lower(a.matched_keyword)))
        : awards;

    return (
        <AppLayout>
            <Head title="Market Pricing" />
            <div className="p-6">
                <PageHeader
                    icon={TrendingUp}
                    title="Market Pricing"
                    description="Past awarded contracts from SAM.gov — see what similar projects went for to benchmark your pricing."
                />

                <Card className="mb-4">
                    <CardContent className="pt-5">
                        <form onSubmit={submit} className="flex flex-wrap items-end gap-3">
                            <div className="min-w-[16rem] flex-1">
                                <label className="label">Keyword</label>
                                <div className="relative">
                                    <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                    <input
                                        type="text"
                                        value={data.keyword}
                                        onChange={e => setData('keyword', e.target.value)}
                                        placeholder="e.g., seismic monitoring, inspection…"
                                        className="input input-with-icon"
                                    />
                                </div>
                            </div>
                            <div className="w-40">
                                <label className="label">NAICS code</label>
                                <input type="text" value={data.naics} onChange={e => setData('naics', e.target.value)} placeholder="e.g., 334513" className="input" />
                            </div>
                            <Button type="submit" icon={Search} disabled={processing}>{processing ? 'Searching…' : 'Search awards'}</Button>
                        </form>
                        {!connected && (
                            <p className="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-300">
                                SAM.gov isn’t configured — showing representative demo award data.
                            </p>
                        )}

                        {/* Focus areas: presaved chips + your private saved keywords. */}
                        <div className="mt-4 flex flex-wrap items-center gap-2 border-t border-border pt-3">
                            <span className="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground">
                                <Tag className="h-3.5 w-3.5" /> Focus areas
                            </span>
                            {sharedChips.map(kw => {
                                const active = activeKws.includes(lower(kw));
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
                                const active = activeKws.includes(lower(kw));
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

                            <form onSubmit={addKeyword} className="inline-flex items-center">
                                <input
                                    type="text"
                                    value={newKeyword}
                                    onChange={e => setNewKeyword(e.target.value)}
                                    placeholder="+ add keyword"
                                    className="h-7 w-32 rounded-full border border-dashed border-border bg-card px-3 text-xs text-foreground placeholder:text-muted-foreground/70 focus:border-primary focus:outline-none focus:ring-1 focus:ring-orange-400"
                                />
                            </form>

                            {activeKws.length > 0 && (
                                <button onClick={() => setActiveKws([])} className="text-xs font-medium text-destructive hover:underline">
                                    Clear filter
                                </button>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {stats.priced > 0 && (
                    <div className="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        {[
                            ['Awards found', String(stats.count)],
                            ['Median award', stats.median != null ? formatCurrency(stats.median) : '—'],
                            ['Highest', stats.max != null ? formatCurrency(stats.max) : '—'],
                            ['Lowest', stats.min != null ? formatCurrency(stats.min) : '—'],
                        ].map(([label, value]) => (
                            <div key={label} className="rounded-2xl border border-border bg-card p-4">
                                <p className="text-xs font-medium text-muted-foreground">{label}</p>
                                <p className="mt-1 text-xl font-bold text-foreground">{value}</p>
                            </div>
                        ))}
                    </div>
                )}

                <Card className="overflow-hidden">
                    {!searched && (
                        <div className="flex flex-wrap items-center gap-2 border-b border-border bg-secondary/40 px-4 py-3">
                            <span className="inline-flex items-center gap-1.5 text-sm font-semibold text-foreground">
                                <TrendingUp className="h-4 w-4 text-primary" /> Most recent awards in your focus areas
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {shownAwards.length} {shownAwards.length === 1 ? 'award' : 'awards'}
                            </span>
                            {refreshedAt ? (
                                <span className="ml-auto text-xs text-muted-foreground">Updated {formatTime(refreshedAt)}</span>
                            ) : (
                                <span className="ml-auto text-xs font-medium text-primary">Building your feed…</span>
                            )}
                        </div>
                    )}
                    {shownAwards.length === 0 ? (
                        <CardContent className="py-4">
                            {searched ? (
                                <EmptyState icon={Search} title="No awards found" description="Try a broader keyword or a different NAICS code." />
                            ) : awards.length > 0 ? (
                                <EmptyState icon={Tag} title="Nothing matches the selected focus areas" description="Pick different chips or clear the filter." />
                            ) : refreshedAt === null ? (
                                <EmptyState icon={TrendingUp} title="Building your feed" description="Pulling awards matching your focus areas from SAM.gov — this page will refresh itself in a few seconds." />
                            ) : (
                                <EmptyState icon={TrendingUp} title="No recent awards yet" description="The background sync pulls awards in your focus areas every few minutes — or search above for any keyword or NAICS code." />
                            )}
                        </CardContent>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead className="border-b border-border bg-secondary/40">
                                    <tr>
                                        <th className="th">Contract</th>
                                        <th className="th">Agency</th>
                                        <th className="th">NAICS</th>
                                        <th className="th">Awardee</th>
                                        <th className="th">Awarded</th>
                                        <th className="th text-right">Award amount</th>
                                        <th className="th" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {shownAwards.map((a, i) => (
                                        <tr key={i} className="row-link">
                                            <td className="td max-w-md">
                                                <p className="font-medium text-foreground line-clamp-2">{a.title}</p>
                                                <p className="mt-0.5 flex items-center gap-2 text-xs text-muted-foreground">
                                                    {a.solicitation_number && <span className="font-mono">{a.solicitation_number}</span>}
                                                    {a.matched_keyword && <span className="rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-medium capitalize text-primary">{a.matched_keyword}</span>}
                                                </p>
                                            </td>
                                            <td className="td max-w-[12rem] text-muted-foreground"><span className="line-clamp-2">{a.agency ?? '—'}</span></td>
                                            <td className="td text-muted-foreground">{a.naics ?? '—'}</td>
                                            <td className="td text-muted-foreground">{a.awardee ?? '—'}</td>
                                            <td className="td text-muted-foreground">{a.award_date ? formatDate(a.award_date) : (a.posted_date ? formatDate(a.posted_date) : '—')}</td>
                                            <td className="td text-right font-semibold text-foreground">{a.amount != null ? formatCurrency(a.amount) : '—'}</td>
                                            <td className="td">
                                                {a.url && (
                                                    <a href={a.url} target="_blank" rel="noreferrer" className="text-muted-foreground transition-colors hover:text-primary" title="View on SAM.gov">
                                                        <ExternalLink className="h-4 w-4" />
                                                    </a>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}
