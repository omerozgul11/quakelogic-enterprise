import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { CalibrationLayout } from '@/Components/layout/CalibrationLayout';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Pill } from '@/Components/ui/Pill';
import { ConfirmDialog } from '@/Components/ui/Modal';
import { ArrowLeft, BadgeCheck, Trash2, ShieldCheck, Cpu, Package, AlertTriangle } from 'lucide-react';

interface Certificate {
    id: number; certificate_number: string;
    result: string; result_label: string; result_color: string;
    nist_traceable: boolean; method: string | null; standard_used: string | null; technician: string | null; serial_number: string | null;
    calibrated_at: string | null; due_at: string | null; overdue: boolean; interval_months: number | null;
    measurements: Record<string, unknown> | null; notes: string | null;
    asset: { id: number; asset_tag: string; name: string } | null;
    product: { id: number; sku: string; name: string } | null;
    performer: string | null;
}

interface Props {
    certificate: Certificate;
    can: { manage: boolean };
}

export default function CertificateShow({ certificate: c, can }: Props) {
    const [deleting, setDeleting] = useState(false);
    const [processing, setProcessing] = useState(false);

    const confirmDelete = () => {
        setProcessing(true);
        router.delete(`/calibration/certificates/${c.id}`, { onFinish: () => setProcessing(false) });
    };

    const details: Array<{ label: string; value: React.ReactNode }> = [
        { label: 'Result', value: <Pill color={c.result_color} label={c.result_label} /> },
        { label: 'Calibrated', value: c.calibrated_at ?? '—' },
        { label: 'Next due', value: c.due_at ? <span className={c.overdue ? 'font-semibold text-red-600' : 'text-foreground'}>{c.due_at}{c.overdue ? ' (overdue)' : ''}</span> : '—' },
        { label: 'Interval', value: c.interval_months ? `${c.interval_months} months` : '—' },
        { label: 'Serial number', value: c.serial_number || '—' },
        { label: 'Technician', value: c.technician || c.performer || '—' },
        { label: 'Method', value: c.method || '—' },
        { label: 'Reference standard', value: c.standard_used || '—' },
    ];

    return (
        <CalibrationLayout>
            <Head title={`${c.certificate_number} · Calibration`} />
            <div className="mx-auto max-w-4xl px-4 py-6 sm:px-6">
                <Link href="/calibration/certificates" className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" /> Certificates
                </Link>

                <div className="card-surface mb-6 p-6">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div className="flex items-center gap-4">
                            <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-500 text-white"><BadgeCheck className="h-7 w-7" /></div>
                            <div>
                                <h1 className="font-mono text-2xl font-bold tracking-tight text-foreground">{c.certificate_number}</h1>
                                <div className="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                    <Pill color={c.result_color} label={c.result_label} />
                                    {c.nist_traceable && <span className="inline-flex items-center gap-1 text-xs text-emerald-600"><ShieldCheck className="h-3.5 w-3.5" /> NIST-traceable</span>}
                                    {c.overdue && <span className="inline-flex items-center gap-1 text-xs font-semibold text-red-600"><AlertTriangle className="h-3.5 w-3.5" /> Overdue</span>}
                                </div>
                                {c.asset && <p className="mt-1 inline-flex items-center gap-1.5 text-sm"><Cpu className="h-3.5 w-3.5 text-muted-foreground" /> <Link href={`/assets/registry/${c.asset.id}`} className="text-primary hover:underline">{c.asset.asset_tag} · {c.asset.name}</Link></p>}
                                {!c.asset && c.product && <p className="mt-1 inline-flex items-center gap-1.5 text-sm"><Package className="h-3.5 w-3.5 text-muted-foreground" /> <Link href={`/inventory/products/${c.product.id}`} className="text-primary hover:underline">{c.product.sku} · {c.product.name}</Link></p>}
                            </div>
                        </div>
                        {can.manage && <Button variant="danger" size="sm" icon={Trash2} onClick={() => setDeleting(true)}>Delete</Button>}
                    </div>
                </div>

                <Card className="p-5">
                    <h2 className="mb-3 text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Certificate details</h2>
                    <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm sm:grid-cols-4">
                        {details.map(d => (
                            <div key={d.label}><dt className="text-xs text-muted-foreground">{d.label}</dt><dd className="text-foreground">{d.value}</dd></div>
                        ))}
                    </dl>
                    {c.notes && <p className="mt-4 whitespace-pre-line border-t border-border pt-4 text-sm text-muted-foreground">{c.notes}</p>}
                </Card>
            </div>

            <ConfirmDialog open={deleting} onClose={() => setDeleting(false)} onConfirm={confirmDelete} processing={processing}
                title="Delete certificate?" message={<>This soft-deletes <span className="font-mono font-medium text-foreground">{c.certificate_number}</span>.</>} />
        </CalibrationLayout>
    );
}
