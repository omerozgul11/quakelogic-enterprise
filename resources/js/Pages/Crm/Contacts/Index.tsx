import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { CrmLayout } from '@/Components/layout/CrmLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Pagination } from '@/Components/ui/Pagination';
import { ConfirmDialog } from '@/Components/ui/Modal';
import { Select } from '@/Components/ui/Select';
import { SearchInput } from '@/Components/ui/SearchInput';
import { CrmContactFormModal, EditableContact } from '@/Components/crm/CrmContactFormModal';
import { cn, getInitials, avatarGradient } from '@/Lib/utils';
import { PaginatedResponse } from '@/Types';
import { Users, Mail, Phone, Plus, Pencil, Trash2, Star, BadgeCheck } from 'lucide-react';

type ContactRow = EditableContact & {
    id: number;
    first_name: string;
    last_name: string;
    company?: { id: number; name: string } | null;
};

interface Props {
    contacts: PaginatedResponse<ContactRow>;
    filters: Record<string, string>;
    companies: Array<{ id: number; name: string }>;
    can: { manage: boolean };
}

export default function ContactsIndex({ contacts, filters, companies, can }: Props) {
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<ContactRow | null>(null);
    const [deleting, setDeleting] = useState<ContactRow | null>(null);
    const [processing, setProcessing] = useState(false);

    const openAdd = () => { setEditing(null); setFormOpen(true); };
    const openEdit = (c: ContactRow) => { setEditing(c); setFormOpen(true); };
    const confirmDelete = () => {
        if (!deleting) return;
        setProcessing(true);
        router.delete(`/crm/contacts/${deleting.id}`, { preserveScroll: true, onFinish: () => { setProcessing(false); setDeleting(null); } });
    };

    const handleFilter = (key: string, value: string) => {
        router.get('/crm/contacts', { ...filters, [key]: value || undefined }, { preserveState: true, preserveScroll: true, replace: true });
    };

    return (
        <CrmLayout>
            <Head title="Contacts · CRM" />
            <div className="p-4 sm:p-6">
                <PageHeader
                    icon={Users}
                    title="Contacts"
                    description={`${contacts.total} ${contacts.total === 1 ? 'contact' : 'contacts'}`}
                    actions={can.manage && <Button onClick={openAdd} icon={Plus}>Add Contact</Button>}
                />

                <Card className="mb-4 p-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <SearchInput className="min-w-0 flex-1 sm:max-w-sm" initial={filters.search ?? ''} onSearch={v => handleFilter('search', v)} placeholder="Search contacts…" />
                        <Select
                            value={filters.company_id ?? ''}
                            onChange={v => handleFilter('company_id', v)}
                            options={companies.map(c => ({ value: String(c.id), label: c.name }))}
                            placeholder="All clients"
                            className="w-full sm:w-56"
                        />
                    </div>
                </Card>

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b border-border bg-secondary/40">
                                <tr>
                                    <th className="th">Name</th>
                                    <th className="th hidden sm:table-cell">Title</th>
                                    <th className="th hidden md:table-cell">Client</th>
                                    <th className="th hidden lg:table-cell">Email</th>
                                    <th className="th" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {contacts.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={5}>
                                            <EmptyState icon={Users} title="No contacts found" description="Add the people you work with at your clients." action={can.manage && <Button onClick={openAdd} icon={Plus}>Add Contact</Button>} />
                                        </td>
                                    </tr>
                                ) : contacts.data.map(contact => (
                                    <tr key={contact.id} className="row-link">
                                        <td className="td">
                                            <div className="flex items-center gap-3">
                                                <div className={cn('flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br text-xs font-bold text-white', avatarGradient(`${contact.first_name} ${contact.last_name}`))}>
                                                    {getInitials(`${contact.first_name} ${contact.last_name}`)}
                                                </div>
                                                <span className="flex items-center gap-1.5 font-medium text-foreground">
                                                    {contact.first_name} {contact.last_name}
                                                    {contact.is_decision_maker && <BadgeCheck className="h-3.5 w-3.5 text-primary" />}
                                                    {contact.is_key_contact && <Star className="h-3.5 w-3.5 text-amber-500" />}
                                                </span>
                                            </div>
                                        </td>
                                        <td className="td hidden text-muted-foreground sm:table-cell">{contact.title || '—'}</td>
                                        <td className="td hidden text-muted-foreground md:table-cell">{contact.company?.name ?? '—'}</td>
                                        <td className="td hidden lg:table-cell">
                                            {contact.email ? <a href={`mailto:${contact.email}`} className="inline-flex items-center gap-1.5 text-primary hover:underline"><Mail className="h-3.5 w-3.5" /> {contact.email}</a> : <span className="text-muted-foreground">—</span>}
                                        </td>
                                        <td className="td">
                                            <div className="flex items-center justify-end gap-1">
                                                {contact.phone && <a href={`tel:${contact.phone}`} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-secondary hover:text-primary" title={contact.phone}><Phone className="h-4 w-4" /></a>}
                                                {can.manage && (
                                                    <>
                                                        <button onClick={() => openEdit(contact)} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground" title="Edit"><Pencil className="h-4 w-4" /></button>
                                                        <button onClick={() => setDeleting(contact)} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive" title="Delete"><Trash2 className="h-4 w-4" /></button>
                                                    </>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination from={contacts.from} to={contacts.to} total={contacts.total} links={contacts.links} />
                </Card>
            </div>

            {formOpen && <CrmContactFormModal key={editing?.id ?? 'new'} open onClose={() => setFormOpen(false)} contact={editing} companies={companies} />}
            <ConfirmDialog
                open={!!deleting}
                onClose={() => setDeleting(null)}
                onConfirm={confirmDelete}
                processing={processing}
                title="Delete contact?"
                message={deleting ? <>This removes <span className="font-medium text-foreground">{deleting.first_name} {deleting.last_name}</span>.</> : ''}
            />
        </CrmLayout>
    );
}
