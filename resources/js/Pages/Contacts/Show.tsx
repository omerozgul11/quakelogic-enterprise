import { Head, Link } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { Contact } from '@/Types';
import { ArrowLeft, Mail, Phone, Linkedin, Star } from 'lucide-react';

interface Props {
    contact: Contact & {
        agency: { id: number; name: string } | null;
        company: { id: number; name: string } | null;
        activities: Array<{ id: number; type: string; summary: string; created_at: string; user: { name: string } | null }>;
    };
}

export default function ContactShow({ contact }: Props) {
    return (
        <AppLayout>
            <Head title={`${contact.first_name} ${contact.last_name}`} />
            <div className="p-6 max-w-4xl mx-auto">
                <div className="flex items-center gap-4 mb-6">
                    <Link href="/contacts" className="text-gray-400 hover:text-gray-600"><ArrowLeft className="h-5 w-5" /></Link>
                    <div className="flex items-center gap-4">
                        <div className="h-14 w-14 rounded-full bg-blue-100 text-blue-700 text-xl font-bold flex items-center justify-center">
                            {contact.first_name[0]}{contact.last_name[0]}
                        </div>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-xl font-bold text-gray-900">{contact.first_name} {contact.last_name}</h1>
                                {contact.is_decision_maker && (
                                    <span className="flex items-center gap-1 text-xs bg-amber-50 text-amber-700 px-2 py-1 rounded-full">
                                        <Star className="h-3 w-3" /> Decision Maker
                                    </span>
                                )}
                            </div>
                            {contact.title && <p className="text-sm text-gray-500">{contact.title}</p>}
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="space-y-4">
                        <div className="bg-white rounded-xl border border-gray-200 p-5">
                            <h3 className="text-sm font-semibold text-gray-900 mb-3">Contact Info</h3>
                            <div className="space-y-2">
                                {contact.email && (
                                    <a href={`mailto:${contact.email}`} className="flex items-center gap-2 text-sm text-blue-600 hover:underline">
                                        <Mail className="h-4 w-4 text-gray-400" />{contact.email}
                                    </a>
                                )}
                                {contact.phone && <div className="flex items-center gap-2 text-sm text-gray-600"><Phone className="h-4 w-4 text-gray-400" />{contact.phone}</div>}
                                {contact.linkedin_url && (
                                    <a href={contact.linkedin_url} target="_blank" rel="noopener noreferrer"
                                        className="flex items-center gap-2 text-sm text-blue-600 hover:underline">
                                        <Linkedin className="h-4 w-4 text-gray-400" />LinkedIn Profile
                                    </a>
                                )}
                            </div>
                        </div>

                        <div className="bg-white rounded-xl border border-gray-200 p-5">
                            <h3 className="text-sm font-semibold text-gray-900 mb-3">Organization</h3>
                            {contact.agency && (
                                <Link href={`/agencies/${contact.agency.id}`} className="text-sm text-blue-600 hover:underline">
                                    {contact.agency.name}
                                </Link>
                            )}
                            {contact.company && (
                                <Link href={`/companies/${contact.company.id}`} className="text-sm text-blue-600 hover:underline">
                                    {contact.company.name}
                                </Link>
                            )}
                            {!contact.agency && !contact.company && <p className="text-sm text-gray-500">Independent</p>}
                        </div>

                        {contact.notes && (
                            <div className="bg-white rounded-xl border border-gray-200 p-5">
                                <h3 className="text-sm font-semibold text-gray-900 mb-2">Notes</h3>
                                <p className="text-sm text-gray-600">{contact.notes}</p>
                            </div>
                        )}
                    </div>

                    <div className="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-6">
                        <h2 className="text-base font-semibold text-gray-900 mb-4">Activity History</h2>
                        {contact.activities.length === 0 ? (
                            <p className="text-sm text-gray-500 text-center py-8">No activities recorded yet.</p>
                        ) : (
                            <div className="space-y-4">
                                {contact.activities.map(a => (
                                    <div key={a.id} className="flex gap-3">
                                        <div className="h-8 w-8 rounded-full bg-gray-100 flex items-center justify-center shrink-0">
                                            <span className="text-xs text-gray-600">{a.type[0].toUpperCase()}</span>
                                        </div>
                                        <div>
                                            <p className="text-sm text-gray-900">{a.summary}</p>
                                            <p className="text-xs text-gray-400">{a.type} · {a.user?.name ?? 'System'} · {a.created_at}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
