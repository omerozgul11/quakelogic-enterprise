import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { CrmLayout } from '@/Components/layout/CrmLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { EmptyState } from '@/Components/ui/EmptyState';
import { ConfirmDialog } from '@/Components/ui/Modal';
import { CopyCheck, Building2, Users, Merge, ShieldCheck } from 'lucide-react';
import { cn } from '@/Lib/utils';

interface Member { id: number; name: string; detail: string | null; related: number; created_at: string | null }
interface Group { key: string; label: string; members: Member[] }

interface Props {
    companyGroups: Group[];
    contactGroups: Group[];
}

export default function DuplicatesIndex({ companyGroups, contactGroups }: Props) {
    const total = companyGroups.length + contactGroups.length;

    return (
        <CrmLayout>
            <Head title="Duplicates · CRM" />
            <div className="mx-auto max-w-4xl px-4 py-6 sm:px-6">
                <PageHeader
                    icon={CopyCheck}
                    title="Duplicate records"
                    description="Review likely duplicates and merge them into one clean record."
                />

                <div className="mb-6 flex items-start gap-3 rounded-xl border border-border bg-secondary/40 px-4 py-3 text-sm text-muted-foreground">
                    <ShieldCheck className="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" />
                    <p>Merging moves every linked lead, contact, project, invoice and timeline entry onto the record you keep, fills in any blank fields, then archives the duplicate. The archived record is <span className="font-medium text-foreground">soft-deleted and recoverable</span> — nothing is permanently removed.</p>
                </div>

                {total === 0 ? (
                    <EmptyState icon={CopyCheck} title="No duplicates found" description="Your companies and contacts look clean. Re-check after importing new data." />
                ) : (
                    <div className="space-y-8">
                        {companyGroups.length > 0 && (
                            <Section title="Companies" icon={Building2} type="company" groups={companyGroups} />
                        )}
                        {contactGroups.length > 0 && (
                            <Section title="Contacts" icon={Users} type="contact" groups={contactGroups} />
                        )}
                    </div>
                )}
            </div>
        </CrmLayout>
    );
}

function Section({ title, icon: Icon, type, groups }: { title: string; icon: React.ComponentType<{ className?: string }>; type: 'company' | 'contact'; groups: Group[] }) {
    return (
        <section>
            <h2 className="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70">
                <Icon className="h-4 w-4" /> {title} <span className="rounded-full bg-secondary px-1.5 text-xs font-medium">{groups.length}</span>
            </h2>
            <div className="space-y-3">
                {groups.map(g => <GroupCard key={g.key} group={g} type={type} />)}
            </div>
        </section>
    );
}

function GroupCard({ group, type }: { group: Group; type: 'company' | 'contact' }) {
    // Default the survivor to the member with the most linked records.
    const suggested = [...group.members].sort((a, b) => b.related - a.related)[0]?.id ?? group.members[0].id;
    const [primary, setPrimary] = useState<number>(suggested);
    const [confirming, setConfirming] = useState(false);
    const [processing, setProcessing] = useState(false);

    const duplicateIds = group.members.filter(m => m.id !== primary).map(m => m.id);

    const doMerge = () => {
        setProcessing(true);
        router.post('/crm/duplicates/merge', { type, primary_id: primary, duplicate_ids: duplicateIds }, {
            preserveScroll: true,
            onFinish: () => { setProcessing(false); setConfirming(false); },
        });
    };

    return (
        <div className="card-surface p-4">
            <div className="mb-3 flex items-center justify-between gap-2">
                <p className="text-sm font-semibold text-foreground">{group.label}</p>
                <Button size="sm" icon={Merge} onClick={() => setConfirming(true)}>Merge {duplicateIds.length}</Button>
            </div>
            <div className="space-y-1.5">
                {group.members.map(m => {
                    const isPrimary = m.id === primary;
                    return (
                        <label key={m.id} className={cn('flex cursor-pointer items-center gap-3 rounded-lg border px-3 py-2 transition-colors', isPrimary ? 'border-primary/50 bg-secondary' : 'border-border hover:bg-secondary/50')}>
                            <input type="radio" name={`primary-${group.key}`} checked={isPrimary} onChange={() => setPrimary(m.id)} className="h-4 w-4 accent-[var(--primary)]" />
                            <span className="min-w-0 flex-1">
                                <span className="block truncate text-sm font-medium text-foreground">{m.name}</span>
                                {m.detail && <span className="block truncate text-xs text-muted-foreground">{m.detail}</span>}
                            </span>
                            <span className="shrink-0 text-xs text-muted-foreground">{m.related} linked{m.created_at ? ` · ${m.created_at}` : ''}</span>
                            {isPrimary && <span className="chip shrink-0">Keep</span>}
                        </label>
                    );
                })}
            </div>

            <ConfirmDialog
                open={confirming}
                onClose={() => setConfirming(false)}
                onConfirm={doMerge}
                processing={processing}
                confirmLabel="Merge"
                title="Merge duplicates?"
                message={<>Everything linked to the other {duplicateIds.length} record(s) will move onto <span className="font-medium text-foreground">{group.members.find(m => m.id === primary)?.name}</span>. The duplicates are archived (recoverable), not deleted.</>}
            />
        </div>
    );
}
