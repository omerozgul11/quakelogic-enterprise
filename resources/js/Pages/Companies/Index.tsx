import { Head, Link, router } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Pagination } from '@/Components/ui/Pagination';
import { cn, getInitials, avatarGradient } from '@/Lib/utils';
import { Company, PaginatedResponse } from '@/Types';
import { Building2, ExternalLink, Search, X, Plus } from 'lucide-react';

interface Props {
    companies: PaginatedResponse<Company & { contacts_count: number }>;
    filters: Record<string, string>;
    can: { create: boolean };
}

const TYPES = ['competitor', 'partner', 'vendor', 'subcontractor', 'teaming_partner'];

export default function CompaniesIndex({ companies, filters, can }: Props) {
    const handleFilter = (key: string, value: string) => {
        router.get('/companies', { ...filters, [key]: value || undefined }, { preserveState: true });
    };

    return (
        <AppLayout>
            <Head title="Companies" />
            <div className="p-6">
                <PageHeader
                    icon={Building2}
                    title="Companies"
                    description={`${companies.total} ${companies.total === 1 ? 'company' : 'companies'} in your CRM`}
                    actions={
                        can.create && (
                            <Button href="/companies/create" icon={Plus}>
                                Add Company
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
                                placeholder="Search companies…"
                                defaultValue={filters.search ?? ''}
                                onKeyDown={e => e.key === 'Enter' && handleFilter('search', (e.target as HTMLInputElement).value)}
                                className="input input-with-icon"
                            />
                        </div>
                        <select value={filters.type ?? ''} onChange={e => handleFilter('type', e.target.value)} className="select">
                            <option value="">All Types</option>
                            {TYPES.map(t => (
                                <option key={t} value={t}>{t.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</option>
                            ))}
                        </select>
                        {Object.keys(filters).length > 0 && (
                            <button onClick={() => router.get('/companies')} className="inline-flex items-center gap-1 text-sm font-medium text-destructive hover:underline">
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
                                    <th className="th">Company</th>
                                    <th className="th">Type</th>
                                    <th className="th">Contacts</th>
                                    <th className="th">Location</th>
                                    <th className="th" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {companies.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={5}>
                                            <EmptyState
                                                icon={Building2}
                                                title="No companies found"
                                                description="Try adjusting your filters, or add a new company to your CRM."
                                                action={can.create && <Button href="/companies/create" icon={Plus}>Add Company</Button>}
                                            />
                                        </td>
                                    </tr>
                                ) : companies.data.map(company => (
                                    <tr key={company.id} className="row-link">
                                        <td className="td">
                                            <div className="flex items-center gap-3">
                                                <div className={cn('flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br text-xs font-bold text-white', avatarGradient(company.name))}>
                                                    {getInitials(company.name)}
                                                </div>
                                                <Link href={`/companies/${company.id}`} className="font-medium text-foreground hover:text-primary">
                                                    {company.name}
                                                </Link>
                                            </div>
                                        </td>
                                        <td className="td">
                                            {company.type && (
                                                <span className="chip capitalize">{company.type.replace(/_/g, ' ')}</span>
                                            )}
                                        </td>
                                        <td className="td text-muted-foreground">{company.contacts_count}</td>
                                        <td className="td text-muted-foreground">
                                            {[company.city, company.state].filter(Boolean).join(', ') || '—'}
                                        </td>
                                        <td className="td">
                                            <Link href={`/companies/${company.id}`} className="text-muted-foreground transition-colors hover:text-primary">
                                                <ExternalLink className="h-4 w-4" />
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination from={companies.from} to={companies.to} total={companies.total} links={companies.links} />
                </Card>
            </div>
        </AppLayout>
    );
}
