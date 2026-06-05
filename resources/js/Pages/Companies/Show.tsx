import { Head, Link } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { Company } from '@/Types';
import { ArrowLeft, Mail, Phone, Globe } from 'lucide-react';

interface Props {
    company: Company & {
        contacts: Array<{ id: number; first_name: string; last_name: string; title: string | null; email: string | null }>;
    };
}

export default function CompanyShow({ company }: Props) {
    return (
        <AppLayout>
            <Head title={company.name} />
            <div className="p-6 max-w-4xl mx-auto">
                <div className="flex items-center gap-4 mb-6">
                    <Link href="/companies" className="text-gray-400 hover:text-gray-600"><ArrowLeft className="h-5 w-5" /></Link>
                    <div>
                        <h1 className="text-xl font-bold text-gray-900">{company.name}</h1>
                        {company.type && <span className="text-xs text-gray-500 capitalize">{company.type.replace(/_/g, ' ')}</span>}
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="bg-white rounded-xl border border-gray-200 p-5 space-y-2">
                        <h3 className="text-sm font-semibold text-gray-900 mb-3">Details</h3>
                        {company.website && (
                            <div className="flex items-center gap-2 text-sm text-gray-600"><Globe className="h-4 w-4 text-gray-400" />
                                <a href={company.website} target="_blank" className="text-blue-600 hover:underline truncate">{company.website}</a>
                            </div>
                        )}
                        {company.phone && <div className="flex items-center gap-2 text-sm text-gray-600"><Phone className="h-4 w-4 text-gray-400" />{company.phone}</div>}
                        {company.email && <div className="flex items-center gap-2 text-sm text-gray-600"><Mail className="h-4 w-4 text-gray-400" />{company.email}</div>}
                        {company.description && <p className="text-sm text-gray-600 pt-2">{company.description}</p>}
                    </div>

                    <div className="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-6">
                        <h2 className="text-base font-semibold text-gray-900 mb-4">Contacts ({company.contacts.length})</h2>
                        {company.contacts.length === 0 ? (
                            <p className="text-sm text-gray-500">No contacts linked to this company.</p>
                        ) : (
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                {company.contacts.map(c => (
                                    <div key={c.id} className="flex items-start gap-3 p-3 border border-gray-100 rounded-lg">
                                        <div className="h-8 w-8 rounded-full bg-gray-100 text-gray-600 text-xs font-bold flex items-center justify-center shrink-0">
                                            {c.first_name[0]}{c.last_name[0]}
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium text-gray-900">{c.first_name} {c.last_name}</p>
                                            {c.title && <p className="text-xs text-gray-500">{c.title}</p>}
                                            {c.email && <p className="text-xs text-blue-600">{c.email}</p>}
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
