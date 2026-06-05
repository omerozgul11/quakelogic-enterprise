import { Head, Link, router } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { Company, PaginatedResponse } from '@/Types';
import { Building2, ExternalLink, Search, X, Plus } from 'lucide-react';

const TYPE_COLORS: Record<string, string> = {
    competitor: 'bg-red-100 text-red-700',
    partner: 'bg-green-100 text-green-700',
    vendor: 'bg-purple-100 text-purple-700',
    subcontractor: 'bg-blue-100 text-blue-700',
    teaming_partner: 'bg-amber-100 text-amber-700',
};

interface Props {
    companies: PaginatedResponse<Company & { contacts_count: number }>;
    filters: Record<string, string>;
    can: { create: boolean };
}

export default function CompaniesIndex({ companies, filters, can }: Props) {
    const handleFilter = (key: string, value: string) => {
        router.get('/companies', { ...filters, [key]: value || undefined }, { preserveState: true });
    };

    return (
        <AppLayout>
            <Head title="Companies" />
            <div className="p-6">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Companies</h1>
                        <p className="text-gray-500 mt-1">{companies.total} companies in CRM</p>
                    </div>
                    {can.create && (
                        <Link href="/companies/create" className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">
                            <Plus className="h-4 w-4" /> Add Company
                        </Link>
                    )}
                </div>

                <div className="bg-white rounded-xl border border-gray-200 p-4 mb-4">
                    <div className="flex gap-3">
                        <div className="relative">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                            <input type="text" placeholder="Search companies..." defaultValue={filters.search ?? ''}
                                onKeyDown={e => e.key === 'Enter' && handleFilter('search', (e.target as HTMLInputElement).value)}
                                className="pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 w-64" />
                        </div>
                        <select value={filters.type ?? ''} onChange={e => handleFilter('type', e.target.value)}
                            className="text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All Types</option>
                            {Object.keys(TYPE_COLORS).map(t => (
                                <option key={t} value={t}>{t.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</option>
                            ))}
                        </select>
                        {Object.keys(filters).length > 0 && (
                            <button onClick={() => router.get('/companies')} className="flex items-center gap-1 text-sm text-red-600">
                                <X className="h-4 w-4" /> Clear
                            </button>
                        )}
                    </div>
                </div>

                <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-gray-200 bg-gray-50">
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Company</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Type</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Contacts</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Location</th>
                                <th className="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {companies.data.length === 0 ? (
                                <tr><td colSpan={5} className="text-center py-12 text-gray-500">
                                    <Building2 className="h-12 w-12 mx-auto mb-3 text-gray-300" />
                                    <p>No companies found</p>
                                </td></tr>
                            ) : companies.data.map(company => (
                                <tr key={company.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3">
                                        <Link href={`/companies/${company.id}`} className="text-sm font-medium text-blue-600 hover:underline">
                                            {company.name}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3">
                                        {company.type && (
                                            <span className={`text-xs px-2 py-1 rounded-full ${TYPE_COLORS[company.type] ?? 'bg-gray-100 text-gray-600'}`}>
                                                {company.type.replace(/_/g, ' ')}
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-gray-700">{company.contacts_count}</td>
                                    <td className="px-4 py-3 text-sm text-gray-500">
                                        {[company.city, company.state].filter(Boolean).join(', ') || '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Link href={`/companies/${company.id}`} className="text-gray-400 hover:text-gray-600">
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
