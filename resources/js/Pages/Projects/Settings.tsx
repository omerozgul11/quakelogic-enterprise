import { Head, Link, useForm } from '@inertiajs/react';
import { ProjectsLayout } from '@/Components/layout/ProjectsLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Select } from '@/Components/ui/Select';
import { cn } from '@/Lib/utils';
import { ArrowLeft, Settings as SettingsIcon } from 'lucide-react';

interface Props {
    settings: {
        auto_create_on_award: boolean;
        default_status: string;
        default_manager_rule: string;
        number_prefix: string;
        notify_on_create: boolean;
        default_member_ids: number[];
    };
    statuses: Array<{ value: string; label: string }>;
    managerRules: Array<{ value: string; label: string }>;
    users: Array<{ id: number; name: string }>;
}

function Toggle({ checked, onChange, label, description }: { checked: boolean; onChange: (v: boolean) => void; label: string; description: string }) {
    return (
        <button type="button" onClick={() => onChange(!checked)} className="flex w-full items-start justify-between gap-4 text-left">
            <span>
                <span className="block text-sm font-medium text-foreground">{label}</span>
                <span className="block text-xs text-muted-foreground">{description}</span>
            </span>
            <span className={cn('relative mt-0.5 inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors', checked ? 'bg-primary' : 'bg-secondary')}>
                <span className={cn('inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform', checked ? 'translate-x-5' : 'translate-x-0.5')} />
            </span>
        </button>
    );
}

export default function ProjectSettings({ settings, statuses, managerRules, users }: Props) {
    const form = useForm({
        auto_create_on_award: settings.auto_create_on_award,
        default_status: settings.default_status,
        default_manager_rule: settings.default_manager_rule,
        number_prefix: settings.number_prefix,
        notify_on_create: settings.notify_on_create,
        default_member_ids: settings.default_member_ids ?? [],
    });

    const year = new Date().getFullYear();
    const preview = `${(form.data.number_prefix || 'QL-PROJ').replace(/-+$/, '')}-${year}-001`;

    const toggleMember = (id: number) => {
        const next = form.data.default_member_ids.includes(id)
            ? form.data.default_member_ids.filter(m => m !== id)
            : [...form.data.default_member_ids, id];
        form.setData('default_member_ids', next);
    };

    return (
        <ProjectsLayout>
            <Head title="Project Settings · Projects" />
            <div className="mx-auto max-w-3xl px-4 py-6 sm:px-6">
                <Link href="/projects" className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" /> Projects
                </Link>
                <PageHeader icon={SettingsIcon} title="Project Settings" description="Govern how awarded proposals become projects" />

                <div className="space-y-5">
                    <Card className="space-y-4 p-5">
                        <h3 className="text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Automation</h3>
                        <Toggle checked={form.data.auto_create_on_award} onChange={v => form.setData('auto_create_on_award', v)}
                            label="Create projects automatically on award" description="When a proposal is marked Awarded, a linked project is created automatically. Turn off to create them manually." />
                        <Toggle checked={form.data.notify_on_create} onChange={v => form.setData('notify_on_create', v)}
                            label="Notify on creation" description="Notify the project owner and admins whenever a project is created." />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label className="label">Default status for new projects</label>
                                <Select className="w-full" value={form.data.default_status} onChange={v => form.setData('default_status', v)} options={statuses.map(s => ({ value: s.value, label: s.label }))} />
                            </div>
                            <div>
                                <label className="label">Project manager assignment</label>
                                <Select className="w-full" value={form.data.default_manager_rule} onChange={v => form.setData('default_manager_rule', v)} options={managerRules.map(r => ({ value: r.value, label: r.label }))} />
                            </div>
                        </div>
                    </Card>

                    <Card className="space-y-3 p-5">
                        <h3 className="text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Numbering</h3>
                        <div className="grid gap-4 sm:grid-cols-2 sm:items-end">
                            <div>
                                <label className="label">Project number prefix</label>
                                <input className="input" value={form.data.number_prefix} onChange={e => form.setData('number_prefix', e.target.value)} placeholder="QL-PROJ" />
                                {form.errors.number_prefix && <p className="mt-1 text-xs text-destructive">{form.errors.number_prefix}</p>}
                            </div>
                            <div className="text-sm text-muted-foreground">Next number looks like <span className="font-mono font-medium text-foreground">{preview}</span></div>
                        </div>
                    </Card>

                    <Card className="space-y-3 p-5">
                        <h3 className="text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Default team members</h3>
                        <p className="text-xs text-muted-foreground">These users are added to every newly created project's team.</p>
                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            {users.map(u => (
                                <label key={u.id} className={cn('flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm transition-colors',
                                    form.data.default_member_ids.includes(u.id) ? 'border-primary bg-primary/10 text-foreground' : 'border-border text-muted-foreground hover:bg-secondary')}>
                                    <input type="checkbox" className="h-4 w-4 rounded border-border text-primary" checked={form.data.default_member_ids.includes(u.id)} onChange={() => toggleMember(u.id)} />
                                    <span className="truncate">{u.name}</span>
                                </label>
                            ))}
                        </div>
                    </Card>

                    <div className="flex justify-end">
                        <Button onClick={() => form.put('/projects/settings', { preserveScroll: true })} disabled={form.processing}>
                            {form.processing ? 'Saving…' : 'Save Settings'}
                        </Button>
                    </div>
                </div>
            </div>
        </ProjectsLayout>
    );
}
