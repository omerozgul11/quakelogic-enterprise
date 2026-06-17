import { Head, Link, router } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Select } from '@/Components/ui/Select';
import { Pagination } from '@/Components/ui/Pagination';
import { DateRangePicker } from '@/Components/ui/DateRangePicker';
import { cn, formatDateTime, getInitials, avatarGradient } from '@/Lib/utils';
import { ScrollText, ExternalLink } from 'lucide-react';

interface LogRow {
    id: number;
    user: string;
    event: 'created' | 'updated' | 'deleted';
    action: string;
    subject_type: string;
    subject_label: string;
    subject_url: string | null;
    changes: string[];
    at: string | null;
}
interface Option { value: string; label: string }
interface Props {
    logs: { data: LogRow[]; from: number | null; to: number | null; total: number; links: Array<{ url: string | null; label: string; active: boolean }> };
    filters: { user_id: string | null; event: string | null; period: string; from: string | null; to: string | null };
    users: Array<{ id: number; name: string }>;
    events: Option[];
}

const PERIODS = [
    { value: 'day', label: 'Today' },
    { value: 'week', label: 'This Week' },
    { value: 'month', label: 'This Month' },
    { value: 'year', label: 'This Year' },
    { value: 'all', label: 'All Time' },
];

const EVENT_STYLE: Record<string, string> = {
    created: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
    updated: 'bg-blue-100 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300',
    deleted: 'bg-rose-100 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300',
};

export default function AuditLogs({ logs, filters, users, events }: Props) {
    const base = () => {
        const q: Record<string, string> = {};
        if (filters.user_id) q.user_id = filters.user_id;
        if (filters.event) q.event = filters.event;
        if (filters.period === 'custom' && filters.from && filters.to) { q.from = filters.from; q.to = filters.to; }
        else if (filters.period) q.period = filters.period;
        return q;
    };
    const go = (patch: Record<string, string | undefined>) => {
        const q: Record<string, string | undefined> = { ...base(), ...patch };
        Object.keys(q).forEach(k => { if (q[k] === undefined || q[k] === '') delete q[k]; });
        router.get('/admin/audit-logs', q as Record<string, string>, { preserveState: true, preserveScroll: true, replace: true });
    };

    return (
        <AppLayout>
            <Head title="Audit Log" />
            <div className="p-6">
                <PageHeader
                    icon={ScrollText}
                    title="Audit Log"
                    description="Every action each user takes — added, edited, submitted, deleted — with timestamps. Admin-only."
                />

                {/* Filters */}
                <Card className="mb-4 p-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                        <div className="inline-flex rounded-lg bg-secondary p-0.5 text-xs font-medium">
                            {PERIODS.map(p => (
                                <button
                                    key={p.value}
                                    onClick={() => go({ period: p.value, from: undefined, to: undefined })}
                                    className={cn('rounded-md px-2.5 py-1 transition-colors',
                                        filters.period === p.value ? 'bg-brand-gradient text-white shadow-sm' : 'text-muted-foreground hover:text-foreground')}
                                >
                                    {p.label}
                                </button>
                            ))}
                        </div>
                        <DateRangePicker
                            from={filters.from}
                            to={filters.to}
                            active={filters.period === 'custom'}
                            onApply={(f, t) => go({ from: f, to: t, period: undefined })}
                            onClear={() => go({ period: 'month', from: undefined, to: undefined })}
                        />
                        <Select
                            value={filters.user_id ?? ''}
                            onChange={v => go({ user_id: v || undefined })}
                            options={users.map(u => ({ value: String(u.id), label: u.name }))}
                            placeholder="All users"
                            className="w-full sm:w-48"
                        />
                        <Select
                            value={filters.event ?? ''}
                            onChange={v => go({ event: v || undefined })}
                            options={events}
                            placeholder="All actions"
                            className="w-full sm:w-44"
                        />
                    </div>
                </Card>

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b border-border bg-secondary/40">
                                <tr>
                                    <th className="th">User</th>
                                    <th className="th">Action</th>
                                    <th className="th">Item</th>
                                    <th className="th hidden lg:table-cell">Changed</th>
                                    <th className="th text-right">When</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {logs.data.length === 0 ? (
                                    <tr><td colSpan={5}><EmptyState icon={ScrollText} title="No activity in this period" description="Try a wider time range or clear the filters." /></td></tr>
                                ) : logs.data.map(log => (
                                    <tr key={log.id} className="transition-colors hover:bg-secondary/40">
                                        <td className="td">
                                            <div className="flex items-center gap-2.5">
                                                <span className={cn('flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gradient-to-br text-[10px] font-bold text-white', avatarGradient(log.user))}>
                                                    {getInitials(log.user)}
                                                </span>
                                                <span className="text-sm font-medium text-foreground">{log.user}</span>
                                            </div>
                                        </td>
                                        <td className="td">
                                            <span className={cn('inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium', EVENT_STYLE[log.event] ?? 'bg-gray-100 text-gray-700')}>
                                                {log.action}
                                            </span>
                                        </td>
                                        <td className="td">
                                            <div className="flex items-center gap-1.5">
                                                <span className="text-[11px] uppercase tracking-wide text-muted-foreground">{log.subject_type}</span>
                                                {log.subject_url ? (
                                                    <Link href={log.subject_url} className="inline-flex items-center gap-1 text-sm font-medium text-foreground hover:text-primary">
                                                        <span className="max-w-[20rem] truncate">{log.subject_label || '—'}</span>
                                                        <ExternalLink className="h-3 w-3 shrink-0 opacity-60" />
                                                    </Link>
                                                ) : (
                                                    <span className="max-w-[20rem] truncate text-sm text-muted-foreground">{log.subject_label || '—'}</span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="td hidden lg:table-cell">
                                            {log.changes.length > 0 ? (
                                                <div className="flex flex-wrap gap-1">
                                                    {log.changes.slice(0, 5).map(c => (
                                                        <span key={c} className="rounded bg-secondary px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground">{c}</span>
                                                    ))}
                                                    {log.changes.length > 5 && <span className="text-[10px] text-muted-foreground">+{log.changes.length - 5}</span>}
                                                </div>
                                            ) : <span className="text-muted-foreground">—</span>}
                                        </td>
                                        <td className="td whitespace-nowrap text-right text-xs text-muted-foreground">{log.at ? formatDateTime(log.at) : '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination from={logs.from} to={logs.to} total={logs.total} links={logs.links} />
                </Card>
            </div>
        </AppLayout>
    );
}
