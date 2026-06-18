import { Head, Link, router } from '@inertiajs/react';
import { ManufacturingLayout } from '@/Components/layout/ManufacturingLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Pill } from '@/Components/ui/Pill';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Pagination } from '@/Components/ui/Pagination';
import { SearchInput } from '@/Components/ui/SearchInput';
import { PaginatedResponse } from '@/Types';
import { ListTree, Plus, ExternalLink } from 'lucide-react';

interface BomRow {
    id: number; name: string; version: string; product: string;
    status_label: string; status_color: string; items_count: number; is_default: boolean;
}

interface Props {
    boms: PaginatedResponse<BomRow>;
    filters: Record<string, string>;
    can: { manage: boolean };
}

export default function BomsIndex({ boms, filters, can }: Props) {
    const handleSearch = (v: string) => router.get('/manufacturing/boms', { ...filters, search: v || undefined }, { preserveState: true, preserveScroll: true, replace: true });

    return (
        <ManufacturingLayout>
            <Head title="BOMs · Manufacturing" />
            <div className="p-4 sm:p-6">
                <PageHeader
                    icon={ListTree}
                    title="Bills of Materials"
                    description={`${boms.total} ${boms.total === 1 ? 'BOM' : 'BOMs'}`}
                    actions={can.manage && <Button href="/manufacturing/boms/create" icon={Plus}>New BOM</Button>}
                />

                <Card className="mb-4 p-4">
                    <SearchInput className="w-full sm:max-w-sm" initial={filters.search ?? ''} onSearch={handleSearch} placeholder="Search BOM or product…" />
                </Card>

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b border-border bg-secondary/40">
                                <tr>
                                    <th className="th">BOM</th>
                                    <th className="th">Output product</th>
                                    <th className="th">Status</th>
                                    <th className="th text-right">Components</th>
                                    <th className="th" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {boms.data.length === 0 ? (
                                    <tr><td colSpan={5}>
                                        <EmptyState icon={ListTree} title="No BOMs yet"
                                            description="Define a bill of materials to drive work orders."
                                            action={can.manage && <Button href="/manufacturing/boms/create" icon={Plus}>New BOM</Button>} />
                                    </td></tr>
                                ) : boms.data.map(b => (
                                    <tr key={b.id} className="row-link">
                                        <td className="td">
                                            <Link href={`/manufacturing/boms/${b.id}`} className="font-medium text-foreground hover:text-primary">{b.name}</Link>
                                            <span className="ml-2 font-mono text-xs text-muted-foreground">{b.version}</span>
                                            {b.is_default && <Pill className="ml-2" color="blue" label="Default" />}
                                        </td>
                                        <td className="td text-muted-foreground">{b.product}</td>
                                        <td className="td"><Pill color={b.status_color} label={b.status_label} /></td>
                                        <td className="td text-right text-muted-foreground">{b.items_count}</td>
                                        <td className="td">
                                            <div className="flex justify-end">
                                                <Link href={`/manufacturing/boms/${b.id}`} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-secondary hover:text-primary" title="View"><ExternalLink className="h-4 w-4" /></Link>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination from={boms.from} to={boms.to} total={boms.total} links={boms.links} />
                </Card>
            </div>
        </ManufacturingLayout>
    );
}
