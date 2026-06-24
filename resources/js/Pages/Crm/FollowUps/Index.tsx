import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { CrmLayout } from '@/Components/layout/CrmLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { FollowUpItem, FollowUpRow } from '@/Components/crm/FollowUpPanel';
import { FollowUpModal, EditableFollowUp } from '@/Components/crm/FollowUpModal';
import { CalendarCheck, Plus, ArrowUpRight } from 'lucide-react';
import { cn } from '@/Lib/utils';

interface Props {
    followUps: (FollowUpRow & { subject_label: string | null; subject_link: string | null })[];
    scope: 'mine' | 'all';
    owners: Array<{ id: number; name: string }>;
    currentUserId: number;
    priorities: string[];
}

function todayStr(): string {
    return new Date().toISOString().slice(0, 10);
}

export default function FollowUpsIndex({ followUps, scope, owners, currentUserId, priorities }: Props) {
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<FollowUpRow | null>(null);

    const t = todayStr();
    const openItems = followUps.filter(f => f.status === 'open');
    const groups = [
        { key: 'overdue', label: 'Overdue', tone: 'text-rose-600 dark:text-rose-400', items: openItems.filter(f => f.due_date && f.due_date < t) },
        { key: 'today', label: 'Today', tone: 'text-amber-600 dark:text-amber-400', items: openItems.filter(f => f.due_date === t) },
        { key: 'upcoming', label: 'Upcoming', tone: 'text-foreground', items: openItems.filter(f => f.due_date && f.due_date > t) },
        { key: 'done', label: 'Completed', tone: 'text-muted-foreground', items: followUps.filter(f => f.status === 'done') },
    ];

    const openEdit = (f: FollowUpRow) => { setEditing(f); setModalOpen(true); };
    const editable: EditableFollowUp | null = editing && {
        id: editing.id, title: editing.title, notes: editing.notes,
        due_date: editing.due_date, priority: editing.priority, assigned_to: editing.assigned_to,
    };

    const setScope = (s: 'mine' | 'all') =>
        router.get('/crm/follow-ups', { scope: s }, { preserveState: true, preserveScroll: true, replace: true });

    return (
        <CrmLayout>
            <Head title="Follow-ups · CRM" />
            <div className="mx-auto max-w-4xl px-4 py-6 sm:px-6">
                <PageHeader
                    icon={CalendarCheck}
                    title="Follow-ups"
                    description="Your call-backs and next steps, by due date."
                    actions={
                        <div className="flex items-center gap-2">
                            <div className="flex rounded-lg border border-border p-0.5">
                                {(['mine', 'all'] as const).map(s => (
                                    <button key={s} onClick={() => setScope(s)}
                                        className={cn('rounded-md px-3 py-1 text-sm font-medium capitalize transition-colors', scope === s ? 'bg-secondary text-foreground' : 'text-muted-foreground hover:text-foreground')}>
                                        {s === 'mine' ? 'Mine' : 'Team'}
                                    </button>
                                ))}
                            </div>
                            <Button icon={Plus} onClick={() => { setEditing(null); setModalOpen(true); }}>New follow-up</Button>
                        </div>
                    }
                />

                <div className="space-y-6">
                    {groups.map(g => (
                        <section key={g.key}>
                            <h2 className={cn('mb-2 flex items-center gap-2 text-sm font-bold uppercase tracking-wider', g.tone)}>
                                {g.label}
                                <span className="rounded-full bg-secondary px-1.5 text-xs font-medium text-muted-foreground">{g.items.length}</span>
                            </h2>
                            {g.items.length === 0 ? (
                                <p className="rounded-lg border border-dashed border-border px-3 py-4 text-center text-sm text-muted-foreground">Nothing here.</p>
                            ) : (
                                <div className="space-y-2">
                                    {g.items.map(f => (
                                        <div key={f.id} className="relative">
                                            <FollowUpItem f={f} onEdit={openEdit} />
                                            {f.subject_label && (
                                                <div className="mt-1 pl-9 text-xs text-muted-foreground">
                                                    {f.subject_link ? (
                                                        <Link href={f.subject_link} className="inline-flex items-center gap-1 hover:text-primary">
                                                            {f.subject_label} <ArrowUpRight className="h-3 w-3" />
                                                        </Link>
                                                    ) : f.subject_label}
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </section>
                    ))}
                </div>
            </div>

            {modalOpen && (
                <FollowUpModal
                    open
                    onClose={() => setModalOpen(false)}
                    followUp={editable}
                    owners={owners}
                    currentUserId={currentUserId}
                    priorities={priorities}
                />
            )}
        </CrmLayout>
    );
}
