import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Select } from '@/Components/ui/Select';
import { Modal, ConfirmDialog } from '@/Components/ui/Modal';
import { LibraryBig, Plus, Pencil, Trash2, Copy, Check } from 'lucide-react';

interface Template {
    id: number;
    category: string; category_label: string;
    title: string;
    content: string | null;
    is_active: boolean;
    author: string | null;
    updated_at: string | null;
}
interface Option { value: string; label: string }
interface Props {
    templates: Template[];
    categories: Option[];
    can: { manage: boolean };
}

const blank = { category: 'technical_narrative', title: '', content: '', is_active: true };

export default function TemplatesIndex({ templates, categories, can }: Props) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<Template | null>(null);
    const [deleting, setDeleting] = useState<Template | null>(null);
    const [copied, setCopied] = useState<number | null>(null);
    const [cat, setCat] = useState<string>('all');
    const form = useForm<typeof blank>({ ...blank });

    const visible = cat === 'all' ? templates : templates.filter(t => t.category === cat);
    const catLabel = (v: string) => categories.find(c => c.value === v)?.label ?? v;

    const openAdd = () => { setEditing(null); form.setData({ ...blank }); setOpen(true); };
    const openEdit = (t: Template) => {
        setEditing(t);
        form.setData({ category: t.category, title: t.title, content: t.content ?? '', is_active: t.is_active });
        setOpen(true);
    };
    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => setOpen(false) };
        if (editing) form.put(`/templates/${editing.id}`, opts);
        else form.post('/templates', opts);
    };
    const copy = (t: Template) => {
        navigator.clipboard?.writeText(t.content ?? '').then(() => { setCopied(t.id); setTimeout(() => setCopied(null), 1500); });
    };

    return (
        <AppLayout>
            <Head title="Template Library" />
            <div className="p-4 sm:p-6">
                <PageHeader
                    icon={LibraryBig}
                    title="Template Library"
                    description="Reusable proposal content — company profiles, technical narratives, QA/QC, warranty, training, installation & support plans."
                    actions={can.manage && <Button onClick={openAdd} icon={Plus}>New Template</Button>}
                />

                {templates.length === 0 ? (
                    <Card className="p-2">
                        <EmptyState icon={LibraryBig} title="No templates yet" description="Build a library of reusable proposal sections your team can drop into new proposals." action={can.manage && <Button onClick={openAdd} icon={Plus}>New Template</Button>} />
                    </Card>
                ) : (
                    <>
                        {/* Category filter */}
                        <div className="mb-4 flex flex-wrap gap-1.5">
                            {[{ value: 'all', label: 'All' }, ...categories].map(c => (
                                <button
                                    key={c.value}
                                    onClick={() => setCat(c.value)}
                                    className={`rounded-full px-3 py-1 text-xs font-medium transition-colors ${cat === c.value ? 'bg-brand-gradient text-white shadow-sm' : 'bg-secondary text-muted-foreground hover:text-foreground'}`}
                                >
                                    {c.label}
                                </button>
                            ))}
                        </div>

                        {/* Cards flow 2–3 per row */}
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {visible.map(t => (
                                <Card key={t.id} className="group flex flex-col overflow-hidden p-0">
                                    {/* Document preview thumbnail (like Google Docs) */}
                                    <button
                                        onClick={() => can.manage ? openEdit(t) : copy(t)}
                                        className="relative block h-44 w-full overflow-hidden border-b border-border bg-white px-3.5 py-3 text-left dark:bg-neutral-900"
                                        title={can.manage ? 'Open template' : 'Copy content'}
                                    >
                                        <span className="absolute left-2 top-2 z-10 rounded-full bg-secondary/90 px-2 py-0.5 text-[10px] font-medium text-muted-foreground backdrop-blur">{catLabel(t.category)}</span>
                                        <p className="whitespace-pre-wrap break-words font-mono text-[6.5px] leading-[1.55] text-neutral-700 dark:text-neutral-300">
                                            {t.content || 'Empty document'}
                                        </p>
                                        <div className="pointer-events-none absolute inset-x-0 bottom-0 h-12 bg-gradient-to-t from-white to-transparent dark:from-neutral-900" />
                                        {!t.is_active && <span className="absolute right-2 top-2 rounded-full bg-secondary px-2 py-0.5 text-[10px] font-medium text-muted-foreground">inactive</span>}
                                    </button>
                                    <div className="flex items-center justify-between gap-2 p-3">
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-semibold text-foreground">{t.title}</p>
                                            <p className="truncate text-[11px] text-muted-foreground">{t.author ?? '—'}</p>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-1">
                                            <button onClick={() => copy(t)} className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-primary" title="Copy content">
                                                {copied === t.id ? <Check className="h-4 w-4 text-emerald-500" /> : <Copy className="h-4 w-4" />}
                                            </button>
                                            {can.manage && (
                                                <>
                                                    <button onClick={() => openEdit(t)} className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground" title="Edit"><Pencil className="h-4 w-4" /></button>
                                                    <button onClick={() => setDeleting(t)} className="rounded-md p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive" title="Delete"><Trash2 className="h-4 w-4" /></button>
                                                </>
                                            )}
                                        </div>
                                    </div>
                                </Card>
                            ))}
                        </div>
                    </>
                )}
            </div>

            <Modal open={open} onClose={() => setOpen(false)} title={editing ? 'Edit template' : 'New template'} size="xl">
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div className="sm:col-span-2">
                            <label className="label">Title *</label>
                            <input className="input" value={form.data.title} onChange={e => form.setData('title', e.target.value)} required />
                            {form.errors.title && <p className="mt-1 text-xs text-destructive">{form.errors.title}</p>}
                        </div>
                        <div>
                            <label className="label">Category *</label>
                            <Select value={form.data.category} onChange={v => form.setData('category', v)} options={categories} />
                        </div>
                    </div>
                    <div>
                        <label className="label">Content</label>
                        <textarea className="input font-mono text-sm" rows={14} value={form.data.content} onChange={e => form.setData('content', e.target.value)} placeholder="Reusable proposal text — supports plain text / markdown." />
                    </div>
                    <label className="flex items-center gap-2 text-sm text-foreground">
                        <input type="checkbox" checked={form.data.is_active} onChange={e => form.setData('is_active', e.target.checked)} className="h-4 w-4 rounded border-border" />
                        Active (available for use)
                    </label>
                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="secondary" onClick={() => setOpen(false)}>Cancel</Button>
                        <Button type="submit" disabled={form.processing}>{editing ? 'Save' : 'Create'}</Button>
                    </div>
                </form>
            </Modal>

            <ConfirmDialog
                open={!!deleting}
                onClose={() => setDeleting(null)}
                onConfirm={() => deleting && router.delete(`/templates/${deleting.id}`, { preserveScroll: true, onFinish: () => setDeleting(null) })}
                title="Delete template?"
                message={deleting ? `"${deleting.title}" will be removed from the library.` : ''}
            />
        </AppLayout>
    );
}
