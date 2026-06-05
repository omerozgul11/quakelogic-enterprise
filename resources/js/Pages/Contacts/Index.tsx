import { Head, Link, router } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { Contact, PaginatedResponse } from '@/Types';
import { Users, Mail, Phone, Search, X, Plus, ExternalLink } from 'lucide-react';

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
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Contacts</h1>
                        <p className="text-gray-500 mt-1">{contacts.total} contacts</p>
                    </div>
                    {can.create && (
                        <Link href="/contacts/create" className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">
                            <Plus className="h-4 w-4" /> Add Contact
                        </Link>
                    )}
                </div>

                <div className="bg-white rounded-xl border border-gray-200 p-4 mb-4">
                    <div className="flex gap-3">
                        <div className="relative">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                            <input type="text" placeholder="Search by name or email..." defaultValue={filters.search ?? ''}
                                onKeyDown={e => e.key === 'Enter' && handleSearch((e.target as HTMLInputElement).value)}
                                className="pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 w-72" />
                        </div>
                        {filters.search && (
                            <button onClick={() => router.get('/contacts')} className="flex items-center gap-1 text-sm text-red-600">
                                <X className="h-4 w-4" /> Clear
                            </button>
                        )}
                    </div>
                </div>

                <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-gray-200 bg-gray-50">
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Name</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Title</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Organization</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Contact</th>
                                <th className="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {contacts.data.length === 0 ? (
                                <tr><td colSpan={5} className="text-center py-12 text-gray-500">
                                    <Users className="h-12 w-12 mx-auto mb-3 text-gray-300" />
                                    <p>No contacts found</p>
                                </td></tr>
                            ) : contacts.data.map(contact => (
                                <tr key={contact.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3">
                                        <Link href={`/contacts/${contact.id}`} className="text-sm font-medium text-blue-600 hover:underline">
                                            {contact.first_name} {contact.last_name}
                                        </Link>
                                        {contact.is_decision_maker && (
                                            <span className="ml-2 text-xs bg-amber-50 text-amber-700 px-1.5 py-0.5 rounded">DM</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-gray-600">{contact.title ?? '—'}</td>
                                    <td className="px-4 py-3 text-sm text-gray-600">
                                        {contact.agency?.name ?? contact.company?.name ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-col gap-1">
                                            {contact.email && (
                                                <a href={`mailto:${contact.email}`} className="text-xs text-blue-600 flex items-center gap-1 hover:underline">
                                                    <Mail className="h-3 w-3" />{contact.email}
                                                </a>
                                            )}
                                            {contact.phone && (
                                                <span className="text-xs text-gray-500 flex items-center gap-1">
                                                    <Phone className="h-3 w-3" />{contact.phone}
                                                </span>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <Link href={`/contacts/${contact.id}`} className="text-gray-400 hover:text-gray-600">
                                            <ExternalLink className="h-4 w-4" />
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
