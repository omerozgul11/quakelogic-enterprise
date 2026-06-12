import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Pagination } from '@/Components/ui/Pagination';
import { ConfirmDialog } from '@/Components/ui/Modal';
import { Select } from '@/Components/ui/Select';
import { CompanyFormModal, EditableCompany } from '@/Components/crm/CompanyFormModal';
import { cn, getInitials, avatarGradient } from '@/Lib/utils';
import { Company, PaginatedResponse } from '@/Types';
import { Building2, ExternalLink, Search, X, Plus, Pencil, Trash2 } from 'lucide-react';

type CompanyRow = Company & EditableCompany & { contacts_count: number };

interface Props {
    companies: PaginatedResponse<CompanyRow>;
    filters: Record<string, string>;
    can: { create: boolean; manage: boolean };
}

const TYPES = ['competitor', 'partner', 'vendor', 'subcontractor', 'teaming_partner'];

export default function CompaniesIndex({ companies, filters, can }: Props) {
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<CompanyRow | null>(null);
    const [deleting, setDeleting] = useState<CompanyRow | null>(null);
    const [processing, setProcessing] = useState(false);

    const openAdd = () => { setEditing(null); setFormOpen(true); };
    const openEdit = (c: CompanyRow) => { setEditing(c); setFormOpen(true); };
    const confirmDelete = () => {
        if (!deleting) return;
        setProcessing(true);
        router.delete(`/companies/${deleting.id}`, {
            preserveScroll: true,
            onFinish: () => { setProcessing(false); setDeleting(null); },
        });
    };

    const handleFilter = (key: string, value: string) => {
        router.get('/companies', { ...filters, [key]: value || undefined }, { preserveState: true });
    };

    return (
        <AppLayout>
            <Head title="Companies" />
            <div className="p-4 sm:p-6">
                <PageHeader
                    icon={Building2}
                    title="Companies"
                    description={`${companies.total} ${companies.total === 1 ? 'company' : 'companies'} in your CRM`}
                    actions={
                        can.create && (
                            <Button onClick={openAdd} icon={Plus}>
                                Add Company
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
                                placeholder="Search companies…"
                                defaultValue={filters.search ?? ''}
                                onKeyDown={e => e.key === 'Enter' && handleFilter('search', (e.target as HTMLInputElement).value)}
                                className="input input-with-icon"
                            />
                        </div>
                        <Select
                            value={filters.type ?? ''}
                            onChange={v => handleFilter('type', v)}
                            options={TYPES.map(t => ({ value: t, label: t.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) }))}
                            placeholder="All Types"
                            className="w-full sm:w-44"
                        />
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
                                    <th className="th hidden sm:table-cell">Contacts</th>
                                    <th className="th hidden md:table-cell">Location</th>
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
                                                action={can.create && <Button onClick={openAdd} icon={Plus}>Add Company</Button>}
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
                                            {company.company_type && (
                                                <span className="chip capitalize">{company.company_type.replace(/_/g, ' ')}</span>
                                            )}
                                        </td>
                                        <td className="td hidden text-muted-foreground sm:table-cell">{company.contacts_count}</td>
                                        <td className="td hidden text-muted-foreground md:table-cell">
                                            {[company.city, company.state].filter(Boolean).join(', ') || '—'}
                                        </td>
                                        <td className="td">
                                            <div className="flex items-center justify-end gap-1">
                                                <Link href={`/companies/${company.id}`} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-secondary hover:text-primary" title="View">
                                                    <ExternalLink className="h-4 w-4" />
                                                </Link>
                                                {can.manage && (
                                                    <>
                                                        <button onClick={() => openEdit(company)} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground" title="Edit">
                                                            <Pencil className="h-4 w-4" />
                                                        </button>
                                                        <button onClick={() => setDeleting(company)} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive" title="Delete">
                                                            <Trash2 className="h-4 w-4" />
                                                        </button>
                                                    </>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination from={companies.from} to={companies.to} total={companies.total} links={companies.links} />
                </Card>
            </div>

            {formOpen && (
                <CompanyFormModal key={editing?.id ?? 'new'} open onClose={() => setFormOpen(false)} company={editing} />
            )}
            <ConfirmDialog
                open={!!deleting}
                onClose={() => setDeleting(null)}
                onConfirm={confirmDelete}
                processing={processing}
                title="Delete company?"
                message={deleting ? <>This will remove <span className="font-medium text-foreground">{deleting.name}</span> from your CRM. Linked contacts are kept.</> : ''}
            />
        </AppLayout>
    );
}
