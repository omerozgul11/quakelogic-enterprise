import { Head, Link, router } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Pagination } from '@/Components/ui/Pagination';
import { cn, getInitials, avatarGradient } from '@/Lib/utils';
import { Contact, PaginatedResponse } from '@/Types';
import { Users, Mail, Phone, Search, X, Plus, ExternalLink, Star } from 'lucide-react';

interface Props {
    contacts: PaginatedResponse<Contact & {
        agency: { id: number; name: string } | null;
        company: { id: number; name: string } | null;
    }>;
    filters: Record<string, string>;
    can: { create: boolean };
}

export default function ContactsIndex({ contacts, filters, can }: Props) {
    const handleSearch = (value: string) => {
        router.get('/contacts', value ? { search: value } : {}, { preserveState: true });
    };

    return (
        <AppLayout>
            <Head title="Contacts" />
            <div className="p-6">
                <PageHeader
                    icon={Users}
                    title="Contacts"
                    description={`${contacts.total} ${contacts.total === 1 ? 'contact' : 'contacts'} in your network`}
                    actions={
                        can.create && (
                            <Button href="/contacts/create" icon={Plus}>
                                Add Contact
                            </Button>
                        )
                    }
                />

                {/* Filters */}
                <Card className="mb-4 p-4">
                    <div className="flex flex-wrap items-center gap-3">
                        <div className="relative min-w-[18rem] flex-1">
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
                                    <th className="th">Title</th>
                                    <th className="th">Organization</th>
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
                                                action={can.create && <Button href="/contacts/create" icon={Plus}>Add Contact</Button>}
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
                                            <td className="td text-muted-foreground">{contact.title ?? '—'}</td>
                                            <td className="td text-muted-foreground">
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
                                                <Link href={`/contacts/${contact.id}`} className="text-muted-foreground transition-colors hover:text-primary">
                                                    <ExternalLink className="h-4 w-4" />
                                                </Link>
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
        </AppLayout>
    );
}
