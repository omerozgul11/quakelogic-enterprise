import { useState } from 'react';
import { Link } from '@inertiajs/react';
import { CalendarCheck, Plus, ArrowRight, StickyNote, Phone, Mail, CalendarDays, GitBranch, Sparkles, Trophy, CheckSquare, Bot } from 'lucide-react';
import { FollowUpItem, FollowUpRow } from '@/Components/crm/FollowUpPanel';
import { FollowUpModal } from '@/Components/crm/FollowUpModal';
import { cn } from '@/Lib/utils';

interface Queue {
    overdue: FollowUpRow[];
    today: FollowUpRow[];
    upcoming: FollowUpRow[];
    counts: { overdue: number; today: number; upcoming: number };
}

interface ActivityFeedItem {
    id: number;
    type: string;
    body: string | null;
    user: string | null;
    subject_label: string | null;
    subject_link: string | null;
    happened_at: string | null;
}

interface Meta {
    owners: Array<{ id: number; name: string }>;
    currentUserId: number;
    priorities: string[];
}

const ICONS: Record<string, typeof StickyNote> = {
    note: StickyNote, call: Phone, email: Mail, meeting: CalendarDays,
    stage_change: GitBranch, created: Sparkles, converted: Trophy, task: CheckSquare, system: Bot,
};

function relative(iso: string | null): string {
    if (!iso) return '';
    const m = Math.floor(Math.max(0, Date.now() - new Date(iso).getTime()) / 60000);
    if (m < 1) return 'now';
    if (m < 60) return `${m}m`;
    const h = Math.floor(m / 60);
    if (h < 24) return `${h}h`;
    return `${Math.floor(h / 24)}d`;
}

export function DashboardFollowUps({ queue, activity, meta }: { queue: Queue; activity: ActivityFeedItem[]; meta: Meta }) {
    const [modalOpen, setModalOpen] = useState(false);
    const visible = [...queue.overdue, ...queue.today, ...queue.upcoming].slice(0, 6);

    return (
        <div className="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
            {/* My follow-ups */}
            <div className="card-surface p-5">
                <div className="mb-3 flex items-center justify-between">
                    <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70">
                        <CalendarCheck className="h-4 w-4" /> My follow-ups
                    </h2>
                    <div className="flex items-center gap-3">
                        <button onClick={() => setModalOpen(true)} className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline">
                            <Plus className="h-3.5 w-3.5" /> New
                        </button>
                        <Link href="/crm/follow-ups" className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline">All <ArrowRight className="h-3 w-3" /></Link>
                    </div>
                </div>

                <div className="mb-3 flex gap-2">
                    {([['overdue', 'Overdue', 'text-rose-600 dark:text-rose-400'], ['today', 'Today', 'text-amber-600 dark:text-amber-400'], ['upcoming', 'Upcoming', 'text-foreground']] as const).map(([k, label, tone]) => (
                        <div key={k} className="flex-1 rounded-lg border border-border bg-secondary/40 px-3 py-2 text-center">
                            <p className={cn('text-xl font-bold tabular-nums', tone)}>{queue.counts[k]}</p>
                            <p className="text-[11px] font-medium text-muted-foreground">{label}</p>
                        </div>
                    ))}
                </div>

                {visible.length === 0 ? (
                    <p className="py-4 text-center text-sm text-muted-foreground">You're all caught up. 🎉</p>
                ) : (
                    <div className="space-y-2">
                        {visible.map(f => <FollowUpItem key={f.id} f={f} />)}
                    </div>
                )}
            </div>

            {/* Recent activity */}
            <div className="card-surface p-5">
                <h2 className="mb-3 text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Recent activity</h2>
                {activity.length === 0 ? (
                    <p className="py-4 text-center text-sm text-muted-foreground">No activity logged yet.</p>
                ) : (
                    <ul className="space-y-3">
                        {activity.map(a => {
                            const Icon = ICONS[a.type] ?? StickyNote;
                            return (
                                <li key={a.id} className="flex gap-2.5 text-sm">
                                    <Icon className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-foreground">
                                            {a.body ?? a.type}
                                            {a.subject_label && (
                                                <> · {a.subject_link
                                                    ? <Link href={a.subject_link} className="font-medium text-primary hover:underline">{a.subject_label}</Link>
                                                    : <span className="font-medium">{a.subject_label}</span>}</>
                                            )}
                                        </p>
                                        <p className="text-xs text-muted-foreground">{a.user ?? 'System'} · {relative(a.happened_at)} ago</p>
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>

            {modalOpen && (
                <FollowUpModal
                    open
                    onClose={() => setModalOpen(false)}
                    owners={meta.owners}
                    currentUserId={meta.currentUserId}
                    priorities={meta.priorities}
                />
            )}
        </div>
    );
}
