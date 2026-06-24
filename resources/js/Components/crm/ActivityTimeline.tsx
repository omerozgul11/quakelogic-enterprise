import { useState } from 'react';
import { router } from '@inertiajs/react';
import {
    StickyNote, Phone, Mail, CalendarDays, GitBranch, Sparkles,
    Trophy, Bot, CheckSquare, Trash2, Send,
} from 'lucide-react';
import { cn } from '@/Lib/utils';

export type ActivitySubject = 'lead' | 'company' | 'contact';

export interface ActivityItem {
    id: number;
    type: string;
    body: string | null;
    meta: Record<string, unknown> | null;
    user: string | null;
    user_id: number | null;
    happened_at: string | null;
    can_delete: boolean;
}

const TYPE_META: Record<string, { label: string; icon: typeof StickyNote; tone: string }> = {
    note: { label: 'Note', icon: StickyNote, tone: 'bg-secondary text-muted-foreground' },
    call: { label: 'Call', icon: Phone, tone: 'bg-sky-500/15 text-sky-600 dark:text-sky-400' },
    email: { label: 'Email', icon: Mail, tone: 'bg-violet-500/15 text-violet-600 dark:text-violet-400' },
    meeting: { label: 'Meeting', icon: CalendarDays, tone: 'bg-amber-500/15 text-amber-600 dark:text-amber-400' },
    stage_change: { label: 'Stage', icon: GitBranch, tone: 'bg-indigo-500/15 text-indigo-600 dark:text-indigo-400' },
    created: { label: 'Created', icon: Sparkles, tone: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' },
    converted: { label: 'Converted', icon: Trophy, tone: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' },
    task: { label: 'Follow-up', icon: CheckSquare, tone: 'bg-teal-500/15 text-teal-600 dark:text-teal-400' },
    system: { label: 'System', icon: Bot, tone: 'bg-secondary text-muted-foreground' },
};

const COMPOSER_TYPES: { value: string; label: string; icon: typeof StickyNote }[] = [
    { value: 'note', label: 'Note', icon: StickyNote },
    { value: 'call', label: 'Call', icon: Phone },
    { value: 'email', label: 'Email', icon: Mail },
    { value: 'meeting', label: 'Meeting', icon: CalendarDays },
];

function relativeTime(iso: string | null): string {
    if (!iso) return '';
    const then = new Date(iso).getTime();
    const diff = Math.max(0, Date.now() - then);
    const m = Math.floor(diff / 60000);
    if (m < 1) return 'just now';
    if (m < 60) return `${m}m ago`;
    const h = Math.floor(m / 60);
    if (h < 24) return `${h}h ago`;
    const d = Math.floor(h / 24);
    if (d < 30) return `${d}d ago`;
    return new Date(iso).toLocaleDateString();
}

interface Props {
    subject: ActivitySubject;
    subjectId: number;
    activities: ActivityItem[];
}

export function ActivityTimeline({ subject, subjectId, activities }: Props) {
    const [type, setType] = useState('note');
    const [body, setBody] = useState('');
    const [busy, setBusy] = useState(false);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!body.trim()) return;
        setBusy(true);
        router.post('/crm/activities', { subject, subject_id: subjectId, type, body }, {
            preserveScroll: true,
            onSuccess: () => setBody(''),
            onFinish: () => setBusy(false),
        });
    };

    const remove = (id: number) => {
        if (!confirm('Remove this entry?')) return;
        router.delete(`/crm/activities/${id}`, { preserveScroll: true });
    };

    return (
        <div className="card-surface p-5">
            <h2 className="mb-4 text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Activity</h2>

            <form onSubmit={submit} className="mb-5">
                <div className="mb-2 flex flex-wrap gap-1.5">
                    {COMPOSER_TYPES.map(t => (
                        <button
                            key={t.value}
                            type="button"
                            onClick={() => setType(t.value)}
                            className={cn(
                                'inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs font-medium transition-colors',
                                type === t.value ? 'border-primary/40 bg-secondary text-foreground' : 'border-border text-muted-foreground hover:bg-secondary'
                            )}
                        >
                            <t.icon className="h-3.5 w-3.5" />
                            {t.label}
                        </button>
                    ))}
                </div>
                <div className="flex items-end gap-2">
                    <textarea
                        value={body}
                        onChange={e => setBody(e.target.value)}
                        rows={2}
                        placeholder={`Log a ${type}…`}
                        className="input min-h-[44px] flex-1 resize-y py-2"
                        onKeyDown={e => { if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') submit(e); }}
                    />
                    <button
                        type="submit"
                        disabled={busy || !body.trim()}
                        className="inline-flex h-10 shrink-0 items-center gap-1.5 rounded-lg bg-brand-gradient px-3.5 text-sm font-medium text-white shadow-glow ring-1 ring-inset ring-white/15 transition-all hover:brightness-[1.04] disabled:opacity-50"
                    >
                        <Send className="h-4 w-4" />
                        Log
                    </button>
                </div>
            </form>

            {activities.length === 0 ? (
                <p className="py-6 text-center text-sm text-muted-foreground">No activity yet — log a call, email or note above.</p>
            ) : (
                <ol className="relative space-y-4 before:absolute before:bottom-2 before:left-[15px] before:top-2 before:w-px before:bg-border">
                    {activities.map(a => {
                        const meta = TYPE_META[a.type] ?? TYPE_META.note;
                        return (
                            <li key={a.id} className="group relative flex gap-3">
                                <span className={cn('relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full ring-4 ring-card', meta.tone)}>
                                    <meta.icon className="h-4 w-4" />
                                </span>
                                <div className="min-w-0 flex-1 pt-1">
                                    <div className="flex items-center gap-2">
                                        <span className="text-xs font-semibold text-foreground">{meta.label}</span>
                                        <span className="text-xs text-muted-foreground">
                                            {a.user ?? 'System'} · {relativeTime(a.happened_at)}
                                        </span>
                                        {a.can_delete && (
                                            <button
                                                type="button"
                                                onClick={() => remove(a.id)}
                                                className="ml-auto rounded p-1 text-muted-foreground opacity-0 transition-opacity hover:text-destructive group-hover:opacity-100"
                                                title="Remove"
                                            >
                                                <Trash2 className="h-3.5 w-3.5" />
                                            </button>
                                        )}
                                    </div>
                                    {a.body && <p className="mt-0.5 whitespace-pre-wrap break-words text-sm text-foreground/90">{a.body}</p>}
                                </div>
                            </li>
                        );
                    })}
                </ol>
            )}
        </div>
    );
}
