import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { AssetLayout } from '@/Components/layout/AssetLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Pill } from '@/Components/ui/Pill';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Pagination } from '@/Components/ui/Pagination';
import { SearchInput } from '@/Components/ui/SearchInput';
import { Select } from '@/Components/ui/Select';
import { AssetFormModal } from '@/Components/assets/AssetFormModal';
import { CommissionModal } from '@/Components/assets/CommissionModal';
import { formatCurrency } from '@/Lib/utils';
import { PaginatedResponse } from '@/Types';
import { Cpu, Plus, PackagePlus, ExternalLink } from 'lucide-react';

interface AssetRow {
    id: number; asset_tag: string; name: string; serial_number: string | null;
    status: string; status_label: string; status_color: string;
    category: string | null; location: string | null; assignee: string | null; company: string | null;
    current_value: number | null; currency: string;
}
interface FormData {
    products: { id: number; sku: string; name: string }[];
    warehouses: { id: number; name: string; code: string }[];
    companies: { id: number; name: string }[];
    users: { id: number; name: string }[];
}

interface Props {
    assets: PaginatedResponse<AssetRow>;
    filters: Record<string, string>;
    statuses: { value: string; label: string }[];
    form: FormData;
    can: { manage: boolean };
    next_tag: string;
}

export default function AssetsIndex({ assets, filters, statuses, form, can, next_tag }: Props) {
    const [addOpen, setAddOpen] = useState(false);
    const [commissionOpen, setCommissionOpen] = useState(false);

    const apply = (patch: Record<string, string | undefined>) => router.get('/assets/registry', { ...filters, ...patch }, { preserveState: true, preserveScroll: true, replace: true });

    return (
        <AssetLayout>
            <Head title="Assets" />
            <div className="p-4 sm:p-6">
                <PageHeader
                    icon={Cpu}
                    title="Assets"
                    description={`${assets.total} ${assets.total === 1 ? 'asset' : 'assets'}`}
                    actions={can.manage && (
                        <div className="flex gap-2">
                            <Button variant="secondary" onClick={() => setCommissionOpen(true)} icon={PackagePlus}>Commission</Button>
                            <Button onClick={() => setAddOpen(true)} icon={Plus}>Add Asset</Button>
                        </div>
                    )}
                />

                <Card className="mb-4 flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                    <SearchInput className="w-full sm:max-w-sm" initial={filters.search ?? ''} onSearch={v => apply({ search: v || undefined })} placeholder="Search tag, name, serial…" />
                    <Select value={filters.status ?? ''} onChange={v => apply({ status: v || undefined })} placeholder="All status" options={statuses} />
                </Card>

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b border-border bg-secondary/40">
                                <tr>
                                    <th className="th">Asset</th>
                                    <th className="th">Status</th>
                                    <th className="th hidden sm:table-cell">Location</th>
                                    <th className="th hidden md:table-cell">Assigned</th>
                                    <th className="th text-right">Value</th>
                                    <th className="th" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {assets.data.length === 0 ? (
                                    <tr><td colSpan={6}>
                                        <EmptyState icon={Cpu} title="No assets found"
                                            description="Register an asset, or commission one out of inventory stock."
                                            action={can.manage && <Button onClick={() => setAddOpen(true)} icon={Plus}>Add Asset</Button>} />
                                    </td></tr>
                                ) : assets.data.map(a => (
                                    <tr key={a.id} className="row-link">
                                        <td className="td">
                                            <Link href={`/assets/registry/${a.id}`} className="block">
                                                <span className="font-medium text-foreground hover:text-primary">{a.name}</span>
                                                <span className="block font-mono text-xs text-muted-foreground">{a.asset_tag}{a.serial_number ? ` · SN ${a.serial_number}` : ''}</span>
                                            </Link>
                                        </td>
                                        <td className="td"><Pill color={a.status_color} label={a.status_label} /></td>
                                        <td className="td hidden text-muted-foreground sm:table-cell">{a.location ?? '—'}</td>
                                        <td className="td hidden text-muted-foreground md:table-cell">{a.assignee ?? a.company ?? '—'}</td>
                                        <td className="td text-right text-foreground">{a.current_value !== null ? formatCurrency(a.current_value, a.currency) : '—'}</td>
                                        <td className="td"><div className="flex justify-end"><Link href={`/assets/registry/${a.id}`} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-secondary hover:text-primary" title="View"><ExternalLink className="h-4 w-4" /></Link></div></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination from={assets.from} to={assets.to} total={assets.total} links={assets.links} />
                </Card>
            </div>

            {addOpen && <AssetFormModal open onClose={() => setAddOpen(false)} statuses={statuses} form={form} defaultTag={next_tag} />}
            {commissionOpen && <CommissionModal open onClose={() => setCommissionOpen(false)} statuses={statuses} form={form} />}
        </AssetLayout>
    );
}
