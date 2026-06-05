import { Head, Link } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { Agency } from '@/Types';
import { ArrowLeft, Mail, Phone, Globe, User } from 'lucide-react';

interface Props {
    agency: Agency & {
        contacts: Array<{ id: number; first_name: string; last_name: string; title: string | null; email: string | null; phone: string | null; is_decision_maker: boolean }>;
        opportunities: Array<{ id: number; title: string; status: string; due_date: string | null }>;
    };
}

export default function AgencyShow({ agency }: Props) {
    return (
        <AppLayout>
            <Head title={agency.name} />
            <div className="p-6 max-w-5xl mx-auto">
                <div className="flex items-center gap-4 mb-6">
                    <Link href="/agencies" className="text-gray-400 hover:text-gray-600"><ArrowLeft className="h-5 w-5" /></Link>
                    <div className="flex items-center gap-3">
                        <div className="h-12 w-12 rounded-full bg-blue-100 text-blue-700 font-bold flex items-center justify-center">
                            {agency.acronym ?? agency.name[0]}
                        </div>
                        <div>
                            <h1 className="text-xl font-bold text-gray-900">{agency.name}</h1>
                            {agency.acronym && <p className="text-sm text-gray-500">{agency.acronym}</p>}
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="space-y-4">
                        <div className="bg-white rounded-xl border border-gray-200 p-5">
                            <h3 className="text-sm font-semibold text-gray-900 mb-3">Contact Info</h3>
                            <dl className="space-y-2 text-sm">
                                {agency.website && (
                                    <div className="flex items-center gap-2 text-gray-600">
                                        <Globe className="h-4 w-4 text-gray-400" />
                                        <a href={agency.website} target="_blank" rel="noopener noreferrer" className="text-blue-600 hover:underline truncate">{agency.website}</a>
                                    </div>
                                )}
                                {agency.phone && <div className="flex items-center gap-2 text-gray-600"><Phone className="h-4 w-4 text-gray-400" />{agency.phone}</div>}
                                {agency.email && <div className="flex items-center gap-2 text-gray-600"><Mail className="h-4 w-4 text-gray-400" />{agency.email}</div>}
                                {(agency.city || agency.state) && (
                                    <div className="text-gray-600">{[agency.city, agency.state, agency.zip_code].filter(Boolean).join(', ')}</div>
                                )}
                            </dl>
                        </div>
                    </div>

                    <div className="lg:col-span-2 space-y-6">
                        <div className="bg-white rounded-xl border border-gray-200 p-6">
                            <h2 className="text-base font-semibold text-gray-900 mb-4">Contacts ({agency.contacts.length})</h2>
                            {agency.contacts.length === 0 ? (
                                <p className="text-sm text-gray-500">No contacts yet.</p>
                            ) : (
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    {agency.contacts.map(c => (
                                        <div key={c.id} className="flex items-start gap-3 p-3 border border-gray-100 rounded-lg">
                                            <div className="h-8 w-8 rounded-full bg-gray-100 text-gray-600 text-xs font-bold flex items-center justify-center shrink-0">
                                                {c.first_name[0]}{c.last_name[0]}
                                            </div>
                                            <div className="min-w-0">
                                                <p className="text-sm font-medium text-gray-900">
                                                    {c.first_name} {c.last_name}
                                                    {c.is_decision_maker && <span className="ml-2 text-xs text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded">Decision Maker</span>}
                                                </p>
                                                {c.title && <p className="text-xs text-gray-500 truncate">{c.title}</p>}
                                                {c.email && <p className="text-xs text-blue-600 truncate">{c.email}</p>}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        <div className="bg-white rounded-xl border border-gray-200 p-6">
                            <h2 className="text-base font-semibold text-gray-900 mb-4">Opportunities ({agency.opportunities.length})</h2>
                            {agency.opportunities.length === 0 ? (
                                <p className="text-sm text-gray-500">No opportunities linked.</p>
                            ) : (
                                <div className="space-y-2">
                                    {agency.opportunities.map(o => (
                                        <Link key={o.id} href={`/opportunities/${o.id}`}
                                            className="flex items-center justify-between p-3 border border-gray-100 rounded-lg hover:bg-gray-50">
                                            <span className="text-sm text-gray-900 truncate flex-1">{o.title}</span>
                                            <span className="text-xs text-gray-500 ml-2 capitalize">{o.status.replace(/_/g, ' ')}</span>
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
