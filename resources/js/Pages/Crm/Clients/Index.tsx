import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { CrmLayout } from '@/Components/layout/CrmLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Pagination } from '@/Components/ui/Pagination';
import { ConfirmDialog } from '@/Components/ui/Modal';
import { SearchInput } from '@/Components/ui/SearchInput';
import { CrmClientFormModal, EditableClient } from '@/Components/crm/CrmClientFormModal';
import { cn, getInitials, avatarGradient } from '@/Lib/utils';
import { PaginatedResponse } from '@/Types';
import { Building2, ExternalLink, Plus, Pencil, Trash2 } from 'lucide-react';

type ClientRow = EditableClient & { name: string; company_type?: string | null; city?: string | null; state?: string | null; contacts_count: number };

interface Props {
    clients: PaginatedResponse<ClientRow>;
    filters: Record<string, string>;
    can: { manage: boolean };
}

export default function ClientsIndex({ clients, filters, can }: Props) {
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<ClientRow | null>(null);
    const [deleting, setDeleting] = useState<ClientRow | null>(null);
    const [processing, setProcessing] = useState(false);

    const openAdd = () => { setEditing(null); setFormOpen(true); };
    const openEdit = (c: ClientRow) => { setEditing(c); setFormOpen(true); };
    const confirmDelete = () => {
        if (!deleting) return;
        setProcessing(true);
        router.delete(`/crm/clients/${deleting.id}`, {
            preserveScroll: true,
            onFinish: () => { setProcessing(false); setDeleting(null); },
        });
    };

    const handleSearch = (value: string) => {
        router.get('/crm/clients', { ...filters, search: value || undefined }, { preserveState: true, preserveScroll: true, replace: true });
    };

    return (
        <CrmLayout>
            <Head title="Clients · CRM" />
            <div className="p-4 sm:p-6">
                <PageHeader
                    icon={Building2}
                    title="Clients"
                    description={`${clients.total} ${clients.total === 1 ? 'client' : 'clients'}`}
                    actions={can.manage && <Button onClick={openAdd} icon={Plus}>Add Client</Button>}
                />

                <Card className="mb-4 p-4">
                    <SearchInput
                        className="w-full sm:max-w-sm"
                        initial={filters.search ?? ''}
                        onSearch={handleSearch}
                        placeholder="Search clients…"
                    />
                </Card>

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b border-border bg-secondary/40">
                                <tr>
                                    <th className="th">Client</th>
                                    <th className="th">Type</th>
                                    <th className="th hidden sm:table-cell">Contacts</th>
                                    <th className="th hidden md:table-cell">Location</th>
                                    <th className="th" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {clients.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={5}>
                                            <EmptyState
                                                icon={Building2}
                                                title="No clients found"
                                                description="Add a client to start tracking contacts, leads, projects and invoices."
                                                action={can.manage && <Button onClick={openAdd} icon={Plus}>Add Client</Button>}
                                            />
                                        </td>
                                    </tr>
                                ) : clients.data.map(client => (
                                    <tr key={client.id} className="row-link">
                                        <td className="td">
                                            <div className="flex items-center gap-3">
                                                <div className={cn('flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br text-xs font-bold text-white', avatarGradient(client.name))}>
                                                    {getInitials(client.name)}
                                                </div>
                                                <Link href={`/crm/clients/${client.id}`} className="font-medium text-foreground hover:text-primary">
                                                    {client.name}
                                                </Link>
                                            </div>
                                        </td>
                                        <td className="td">
                                            {client.company_type && <span className="chip capitalize">{client.company_type.replace(/_/g, ' ')}</span>}
                                        </td>
                                        <td className="td hidden text-muted-foreground sm:table-cell">{client.contacts_count}</td>
                                        <td className="td hidden text-muted-foreground md:table-cell">
                                            {[client.city, client.state].filter(Boolean).join(', ') || '—'}
                                        </td>
                                        <td className="td">
                                            <div className="flex items-center justify-end gap-1">
                                                <Link href={`/crm/clients/${client.id}`} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-secondary hover:text-primary" title="View">
                                                    <ExternalLink className="h-4 w-4" />
                                                </Link>
                                                {can.manage && (
                                                    <>
                                                        <button onClick={() => openEdit(client)} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground" title="Edit">
                                                            <Pencil className="h-4 w-4" />
                                                        </button>
                                                        <button onClick={() => setDeleting(client)} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive" title="Delete">
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
                    <Pagination from={clients.from} to={clients.to} total={clients.total} links={clients.links} />
                </Card>
            </div>

            {formOpen && <CrmClientFormModal key={editing?.id ?? 'new'} open onClose={() => setFormOpen(false)} client={editing} />}
            <ConfirmDialog
                open={!!deleting}
                onClose={() => setDeleting(null)}
                onConfirm={confirmDelete}
                processing={processing}
                title="Delete client?"
                message={deleting ? <>This removes <span className="font-medium text-foreground">{deleting.name}</span>. Linked contacts are kept.</> : ''}
            />
        </CrmLayout>
    );
}
