import { Head, Link, router } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
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
                <PageHeader
                    icon={Building}
                    title="Agencies"
                    description={`${agencies.total} ${agencies.total === 1 ? 'agency' : 'agencies'} in CRM`}
                    actions={
                        can.create && (
                            <Button href="/agencies/create" icon={Plus}>
                                Add Agency
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
                                placeholder="Search agencies…"
                                defaultValue={filters.search ?? ''}
                                onKeyDown={e => e.key === 'Enter' && handleFilter((e.target as HTMLInputElement).value)}
                                className="input input-with-icon"
                            />
                        </div>
                        {filters.search && (
                            <button onClick={() => router.get('/agencies')} className="inline-flex items-center gap-1 text-sm font-medium text-destructive hover:underline">
                                <X className="h-4 w-4" /> Clear
                            </button>
                        )}
                    </div>
                </Card>

                {agencies.data.length === 0 ? (
                    <Card>
                        <EmptyState
                            icon={Building}
                            title="No agencies found"
                            description="Try adjusting your search, or add a new agency to your CRM."
                            action={can.create && <Button href="/agencies/create" icon={Plus}>Add Agency</Button>}
                        />
                    </Card>
                ) : (
                    <div className="stagger grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {agencies.data.map(agency => (
                            <Link key={agency.id} href={`/agencies/${agency.id}`} className="card-surface card-hover group block p-5">
                                <div className="flex items-start gap-3">
                                    <div className="bg-brand-gradient flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-sm font-bold text-white shadow-sm">
                                        {agency.acronym ?? agency.name[0]}
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-semibold text-foreground group-hover:text-primary">{agency.name}</p>
                                        {agency.acronym && <p className="text-sm text-muted-foreground">{agency.acronym}</p>}
                                        <div className="mt-2 flex gap-4 text-xs text-muted-foreground">
                                            <span>{agency.contacts_count} contacts</span>
                                            <span>{agency.opportunities_count} opportunities</span>
                                        </div>
                                    </div>
                                    <ExternalLink className="h-4 w-4 shrink-0 text-muted-foreground transition-colors group-hover:text-primary" />
                                </div>
                                {(agency.city || agency.state) && (
                                    <p className="mt-3 text-xs text-muted-foreground">
                                        {[agency.city, agency.state].filter(Boolean).join(', ')}
                                    </p>
                                )}
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
