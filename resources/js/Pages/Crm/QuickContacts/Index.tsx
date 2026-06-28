import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { CrmLayout } from '@/Components/layout/CrmLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Pill } from '@/Components/ui/Pill';
import { EmptyState } from '@/Components/ui/EmptyState';
import { ConfirmDialog } from '@/Components/ui/Modal';
import { Select } from '@/Components/ui/Select';
import { SearchInput } from '@/Components/ui/SearchInput';
import { QuickContactFormModal, EditableQuickContact } from '@/Components/crm/QuickContactFormModal';
import { cn } from '@/Lib/utils';
import { PhoneCall, Phone, Mail, Globe, Plus, Pencil, Trash2, Pin, Building2 } from 'lucide-react';

interface QuickContactRow extends EditableQuickContact {
    id: number;
    name: string;
    category: string;
    category_label: string;
    category_color: string;
    is_pinned: boolean;
}

interface Props {
    contacts: QuickContactRow[];
    filters: Record<string, string>;
    categories: Array<{ value: string; label: string; color: string }>;
    can: { manage: boolean };
}

export default function QuickContactsIndex({ contacts, filters, categories, can }: Props) {
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<QuickContactRow | null>(null);
    const [deleting, setDeleting] = useState<QuickContactRow | null>(null);
    const [processing, setProcessing] = useState(false);

    const openAdd = () => { setEditing(null); setFormOpen(true); };
    const openEdit = (c: QuickContactRow) => { setEditing(c); setFormOpen(true); };
    const confirmDelete = () => {
        if (!deleting) return;
        setProcessing(true);
        router.delete(`/crm/quick-contacts/${deleting.id}`, { preserveScroll: true, onFinish: () => { setProcessing(false); setDeleting(null); } });
    };

    const handleFilter = (key: string, value: string) => {
        router.get('/crm/quick-contacts', { ...filters, [key]: value || undefined }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const telHref = (c: QuickContactRow) => {
        const digits = (c.phone ?? '').replace(/[^\d+]/g, '');
        return digits ? `tel:${digits}${c.extension ? `,${c.extension.replace(/[^\d]/g, '')}` : ''}` : undefined;
    };

    return (
        <CrmLayout>
            <Head title="Quick Contacts · CRM" />
            <div className="p-4 sm:p-6">
                <PageHeader
                    icon={PhoneCall}
                    title="Quick Contacts"
                    description="Frequently-dialed numbers — banks, carriers, agencies and support desks."
                    actions={can.manage && <Button onClick={openAdd} icon={Plus}>Add Quick Contact</Button>}
                />

                <Card className="mb-4 p-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <SearchInput className="min-w-0 flex-1 sm:max-w-sm" initial={filters.search ?? ''} onSearch={v => handleFilter('search', v)} placeholder="Search by name, org or number…" />
                        <Select
                            value={filters.category ?? ''}
                            onChange={v => handleFilter('category', v)}
                            options={categories.map(c => ({ value: c.value, label: c.label }))}
                            placeholder="All categories"
                            className="w-full sm:w-60"
                        />
                    </div>
                </Card>

                {contacts.length === 0 ? (
                    <Card>
                        <EmptyState
                            icon={PhoneCall}
                            title="No quick contacts yet"
                            description="Save the numbers you reach for often — like a bank's wire desk or a carrier's support line — so they're one tap away."
                            action={can.manage && <Button onClick={openAdd} icon={Plus}>Add Quick Contact</Button>}
                        />
                    </Card>
                ) : (
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {contacts.map(c => (
                            <Card key={c.id} className="flex flex-col p-4">
                                <div className="mb-2 flex items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <div className="flex items-center gap-1.5">
                                            {c.is_pinned && <Pin className="h-3.5 w-3.5 shrink-0 fill-current text-amber-500" />}
                                            <h3 className="truncate font-semibold text-foreground">{c.name}</h3>
                                        </div>
                                        {c.organization_name && (
                                            <p className="mt-0.5 flex items-center gap-1 truncate text-xs text-muted-foreground">
                                                <Building2 className="h-3 w-3 shrink-0" /> {c.organization_name}
                                            </p>
                                        )}
                                    </div>
                                    <Pill color={c.category_color} label={c.category_label} />
                                </div>

                                {c.phone && (
                                    <a
                                        href={telHref(c)}
                                        className="group mt-1 inline-flex items-center gap-2 text-lg font-semibold tracking-tight text-primary hover:underline"
                                    >
                                        <Phone className="h-4 w-4 shrink-0" />
                                        <span className="truncate">{c.phone}</span>
                                        {c.extension && <span className="text-sm font-normal text-muted-foreground">ext. {c.extension}</span>}
                                    </a>
                                )}

                                <div className="mt-2 space-y-1 text-sm">
                                    {c.email && (
                                        <a href={`mailto:${c.email}`} className="flex items-center gap-2 text-muted-foreground hover:text-primary">
                                            <Mail className="h-3.5 w-3.5 shrink-0" /> <span className="truncate">{c.email}</span>
                                        </a>
                                    )}
                                    {c.website && (
                                        <a href={c.website} target="_blank" rel="noreferrer" className="flex items-center gap-2 text-muted-foreground hover:text-primary">
                                            <Globe className="h-3.5 w-3.5 shrink-0" /> <span className="truncate">{c.website.replace(/^https?:\/\//, '')}</span>
                                        </a>
                                    )}
                                </div>

                                {c.notes && <p className="mt-2 whitespace-pre-line border-t border-border pt-2 text-xs text-muted-foreground">{c.notes}</p>}

                                {can.manage && (
                                    <div className="mt-3 flex items-center justify-end gap-1 border-t border-border pt-2">
                                        <button onClick={() => openEdit(c)} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground" title="Edit"><Pencil className="h-4 w-4" /></button>
                                        <button onClick={() => setDeleting(c)} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive" title="Delete"><Trash2 className="h-4 w-4" /></button>
                                    </div>
                                )}
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            {formOpen && <QuickContactFormModal key={editing?.id ?? 'new'} open onClose={() => setFormOpen(false)} contact={editing} categories={categories} />}
            <ConfirmDialog
                open={!!deleting}
                onClose={() => setDeleting(null)}
                onConfirm={confirmDelete}
                processing={processing}
                title="Remove quick contact?"
                message={deleting ? <>This removes <span className={cn('font-medium text-foreground')}>{deleting.name}</span> from your quick contacts.</> : ''}
            />
        </CrmLayout>
    );
}
