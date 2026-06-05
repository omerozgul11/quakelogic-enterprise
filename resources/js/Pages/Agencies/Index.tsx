import { Head, Link, router } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { Agency, PaginatedResponse } from '@/Types';
import { Building, ExternalLink, Search, X, Plus } from 'lucide-react';

interface Props {
    agencies: PaginatedResponse<Agency & { contacts_count: number; opportunities_count: number }>;
    filters: Record<string, string>;
    can: { create: boolean };
}

export default function AgenciesIndex({ agencies, filters, can }: Props) {
    const handleFilter = (value: string) => {
        router.get('/agencies', value ? { search: value } : {}, { preserveState: true });
    };

    return (
        <AppLayout>
            <Head title="Agencies" />
            <div className="p-6">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Agencies</h1>
                        <p className="text-gray-500 mt-1">{agencies.total} agencies in CRM</p>
                    </div>
                    {can.create && (
                        <Link href="/agencies/create" className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">
                            <Plus className="h-4 w-4" /> Add Agency
                        </Link>
                    )}
                </div>

                <div className="bg-white rounded-xl border border-gray-200 p-4 mb-4">
                    <div className="flex gap-3">
                        <div className="relative">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                            <input type="text" placeholder="Search agencies..." defaultValue={filters.search ?? ''}
                                onKeyDown={e => e.key === 'Enter' && handleFilter((e.target as HTMLInputElement).value)}
                                className="pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 w-64" />
                        </div>
                        {filters.search && (
                            <button onClick={() => router.get('/agencies')} className="flex items-center gap-1 text-sm text-red-600">
                                <X className="h-4 w-4" /> Clear
                            </button>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    {agencies.data.length === 0 ? (
                        <div className="col-span-3 text-center py-16 text-gray-500">
                            <Building className="h-16 w-16 mx-auto mb-4 text-gray-300" />
                            <p className="font-medium">No agencies found</p>
                        </div>
                    ) : agencies.data.map(agency => (
                        <Link key={agency.id} href={`/agencies/${agency.id}`}
                            className="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md transition-shadow">
                            <div className="flex items-start gap-3">
                                <div className="h-10 w-10 rounded-full bg-blue-100 text-blue-700 font-bold flex items-center justify-center text-sm shrink-0">
                                    {agency.acronym ?? agency.name[0]}
                                </div>
                                <div className="flex-1 min-w-0">
                                    <p className="font-semibold text-gray-900 truncate">{agency.name}</p>
                                    {agency.acronym && <p className="text-sm text-gray-500">{agency.acronym}</p>}
                                    <div className="flex gap-4 mt-2 text-xs text-gray-400">
                                        <span>{agency.contacts_count} contacts</span>
                                        <span>{agency.opportunities_count} opportunities</span>
                                    </div>
                                </div>
                                <ExternalLink className="h-4 w-4 text-gray-300 shrink-0" />
                            </div>
                            {(agency.city || agency.state) && (
                                <p className="text-xs text-gray-500 mt-3">
                                    {[agency.city, agency.state].filter(Boolean).join(', ')}
                                </p>
                            )}
                        </Link>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
