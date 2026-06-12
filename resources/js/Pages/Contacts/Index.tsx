import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Pagination } from '@/Components/ui/Pagination';
import { ConfirmDialog } from '@/Components/ui/Modal';
import { ContactFormModal } from '@/Components/crm/ContactFormModal';
import { cn, getInitials, avatarGradient } from '@/Lib/utils';
import { Contact, PaginatedResponse } from '@/Types';
import { Users, Mail, Phone, Search, X, Plus, ExternalLink, Star, Pencil, Trash2 } from 'lucide-react';

type ContactRow = Contact & {
    agency: { id: number; name: string } | null;
    company: { id: number; name: string } | null;
};

interface Props {
    contacts: PaginatedResponse<ContactRow>;
    filters: Record<string, string>;
    companies: Array<{ id: number; name: string }>;
    can: { create: boolean; manage: boolean };
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
        router.delete(`/contacts/${deleting.id}`, {
            preserveScroll: true,
            onFinish: () => { setProcessing(false); setDeleting(null); },
        });
    };

    const handleSearch = (value: string) => {
        router.get('/contacts', value ? { search: value } : {}, { preserveState: true });
    };

    return (
        <AppLayout>
            <Head title="Contacts" />
            <div className="p-4 sm:p-6">
                <PageHeader
                    icon={Users}
                    title="Contacts"
                    description={`${contacts.total} ${contacts.total === 1 ? 'contact' : 'contacts'} in your network`}
                    actions={
                        can.create && (
                            <Button onClick={openAdd} icon={Plus}>
                                Add Contact
                            </Button>
                        )
                    }
                />

                {/* Filters */}
                <Card className="mb-4 p-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                        <div className="relative min-w-0 flex-1 sm:min-w-[18rem]">
                            <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <input
                                type="text"
                                placeholder="Search by name or email…"
                                defaultValue={filters.search ?? ''}
                                onKeyDown={e => e.key === 'Enter' && handleSearch((e.target as HTMLInputElement).value)}
                                className="input input-with-icon"
                            />
                        </div>
                        {filters.search && (
                            <button onClick={() => router.get('/contacts')} className="inline-flex items-center gap-1 text-sm font-medium text-destructive hover:underline">
                                <X className="h-4 w-4" /> Clear
                            </button>
                        )}
                    </div>
                </Card>

                {/* Table */}
                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b border-border bg-secondary/40">
                                <tr>
                                    <th className="th">Name</th>
                                    <th className="th hidden sm:table-cell">Title</th>
                                    <th className="th hidden md:table-cell">Organization</th>
                                    <th className="th">Contact</th>
                                    <th className="th" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {contacts.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={5}>
                                            <EmptyState
                                                icon={Users}
                                                title="No contacts found"
                                                description="Try adjusting your search, or add a new contact to your network."
                                                action={can.create && <Button onClick={openAdd} icon={Plus}>Add Contact</Button>}
                                            />
                                        </td>
                                    </tr>
                                ) : contacts.data.map(contact => {
                                    const name = `${contact.first_name} ${contact.last_name}`;
                                    return (
                                        <tr key={contact.id} className="row-link">
                                            <td className="td">
                                                <div className="flex items-center gap-3">
                                                    <div className={cn('flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br text-xs font-bold text-white', avatarGradient(name))}>
                                                        {getInitials(name)}
                                                    </div>
                                                    <div>
                                                        <Link href={`/contacts/${contact.id}`} className="font-medium text-foreground hover:text-primary">
                                                            {name}
                                                        </Link>
                                                        {contact.is_decision_maker && (
                                                            <span className="ml-2 inline-flex items-center gap-1 rounded-full bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-800">
                                                                <Star className="h-3 w-3" /> DM
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="td hidden text-muted-foreground sm:table-cell">{contact.title ?? '—'}</td>
                                            <td className="td hidden text-muted-foreground md:table-cell">
                                                {contact.agency?.name ?? contact.company?.name ?? '—'}
                                            </td>
                                            <td className="td">
                                                <div className="flex flex-col gap-1">
                                                    {contact.email && (
                                                        <a href={`mailto:${contact.email}`} className="flex items-center gap-1 text-xs text-primary hover:underline">
                                                            <Mail className="h-3 w-3" />{contact.email}
                                                        </a>
                                                    )}
                                                    {contact.phone && (
                                                        <span className="flex items-center gap-1 text-xs text-muted-foreground">
                                                            <Phone className="h-3 w-3" />{contact.phone}
                                                        </span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="td">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Link href={`/contacts/${contact.id}`} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-secondary hover:text-primary" title="View profile">
                                                        <ExternalLink className="h-4 w-4" />
                                                    </Link>
                                                    {can.manage && (
                                                        <>
                                                            <button onClick={() => openEdit(contact)} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground" title="Edit">
                                                                <Pencil className="h-4 w-4" />
                                                            </button>
                                                            <button onClick={() => setDeleting(contact)} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive" title="Delete">
                                                                <Trash2 className="h-4 w-4" />
                                                            </button>
                                                        </>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                    <Pagination from={contacts.from} to={contacts.to} total={contacts.total} links={contacts.links} />
                </Card>
            </div>

            {formOpen && (
                <ContactFormModal
                    key={editing?.id ?? 'new'}
                    open
                    onClose={() => setFormOpen(false)}
                    contact={editing}
                    companies={companies}
                />
            )}

            <ConfirmDialog
                open={!!deleting}
                onClose={() => setDeleting(null)}
                onConfirm={confirmDelete}
                processing={processing}
                title="Delete contact?"
                message={deleting ? <>This will remove <span className="font-medium text-foreground">{deleting.first_name} {deleting.last_name}</span> from your contacts. You can restore it later if needed.</> : ''}
            />
        </AppLayout>
    );
}
