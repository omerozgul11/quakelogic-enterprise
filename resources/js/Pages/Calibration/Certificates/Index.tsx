import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { CalibrationLayout } from '@/Components/layout/CalibrationLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Pill } from '@/Components/ui/Pill';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Pagination } from '@/Components/ui/Pagination';
import { SearchInput } from '@/Components/ui/SearchInput';
import { Select } from '@/Components/ui/Select';
import { RecordModal } from '@/Components/calibration/RecordModal';
import { PaginatedResponse } from '@/Types';
import { BadgeCheck, Plus, ExternalLink, ShieldCheck } from 'lucide-react';

interface CertRow {
    id: number; certificate_number: string; subject: string;
    result: string; result_label: string; result_color: string;
    nist_traceable: boolean; calibrated_at: string | null; due_at: string | null; overdue: boolean;
}
interface FormData {
    assets: { id: number; asset_tag: string; name: string; serial_number: string | null }[];
    products: { id: number; sku: string; name: string }[];
    users: { id: number; name: string }[];
}

interface Props {
    certificates: PaginatedResponse<CertRow>;
    filters: Record<string, string>;
    results: { value: string; label: string }[];
    form: FormData;
    can: { manage: boolean };
}

export default function CertificatesIndex({ certificates, filters, results, form, can }: Props) {
    const [recordOpen, setRecordOpen] = useState(false);
    const apply = (patch: Record<string, string | undefined>) => router.get('/calibration/certificates', { ...filters, ...patch }, { preserveState: true, preserveScroll: true, replace: true });

    return (
        <CalibrationLayout>
            <Head title="Certificates · Calibration" />
            <div className="p-4 sm:p-6">
                <PageHeader
                    icon={BadgeCheck}
                    title="Calibration Certificates"
                    description={`${certificates.total} ${certificates.total === 1 ? 'certificate' : 'certificates'}`}
                    actions={can.manage && <Button onClick={() => setRecordOpen(true)} icon={Plus}>Record Calibration</Button>}
                />

                <Card className="mb-4 flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                    <SearchInput className="w-full sm:max-w-sm" initial={filters.search ?? ''} onSearch={v => apply({ search: v || undefined })} placeholder="Search cert #, serial, asset…" />
                    <div className="flex gap-2">
                        <Select value={filters.result ?? ''} onChange={v => apply({ result: v || undefined })} placeholder="All results" options={results} />
                        <Select value={filters.due ?? ''} onChange={v => apply({ due: v || undefined })} placeholder="Any due" options={[{ value: 'overdue', label: 'Overdue' }, { value: 'soon', label: 'Due in 30 days' }]} />
                    </div>
                </Card>

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b border-border bg-secondary/40">
                                <tr>
                                    <th className="th">Certificate</th>
                                    <th className="th">Instrument</th>
                                    <th className="th">Result</th>
                                    <th className="th hidden md:table-cell">Calibrated</th>
                                    <th className="th">Due</th>
                                    <th className="th" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {certificates.data.length === 0 ? (
                                    <tr><td colSpan={6}>
                                        <EmptyState icon={BadgeCheck} title="No certificates found"
                                            description="Record a NIST-traceable calibration to start tracking due dates."
                                            action={can.manage && <Button onClick={() => setRecordOpen(true)} icon={Plus}>Record Calibration</Button>} />
                                    </td></tr>
                                ) : certificates.data.map(c => (
                                    <tr key={c.id} className="row-link">
                                        <td className="td">
                                            <Link href={`/calibration/certificates/${c.id}`} className="inline-flex items-center gap-1.5 font-mono font-medium text-foreground hover:text-primary">
                                                {c.certificate_number}
                                                {c.nist_traceable && <ShieldCheck className="h-3.5 w-3.5 text-emerald-500" />}
                                            </Link>
                                        </td>
                                        <td className="td text-foreground">{c.subject}</td>
                                        <td className="td"><Pill color={c.result_color} label={c.result_label} /></td>
                                        <td className="td hidden text-muted-foreground md:table-cell">{c.calibrated_at ?? '—'}</td>
                                        <td className="td"><span className={c.overdue ? 'font-semibold text-red-600' : 'text-foreground'}>{c.due_at ?? '—'}</span></td>
                                        <td className="td"><div className="flex justify-end"><Link href={`/calibration/certificates/${c.id}`} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-secondary hover:text-primary" title="View"><ExternalLink className="h-4 w-4" /></Link></div></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination from={certificates.from} to={certificates.to} total={certificates.total} links={certificates.links} />
                </Card>
            </div>

            {recordOpen && <RecordModal open onClose={() => setRecordOpen(false)} form={form} results={results} />}
        </CalibrationLayout>
    );
}
