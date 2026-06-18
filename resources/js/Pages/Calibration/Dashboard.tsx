import { Head, Link } from '@inertiajs/react';
import { CalibrationLayout } from '@/Components/layout/CalibrationLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { StatCard } from '@/Components/ui/StatCard';
import { Card } from '@/Components/ui/Card';
import { Pill } from '@/Components/ui/Pill';
import { EmptyState } from '@/Components/ui/EmptyState';
import { BadgeCheck, AlertTriangle, CalendarClock, CheckCheck } from 'lucide-react';

interface Stats { total: number; overdue: number; due_soon: number; this_year: number }
interface UpcomingRow { id: number; certificate_number: string; subject: string; due_at: string | null; overdue: boolean; result_color: string; result_label: string }

interface Props {
    stats: Stats;
    upcoming: UpcomingRow[];
}

export default function CalibrationDashboard({ stats, upcoming }: Props) {
    return (
        <CalibrationLayout>
            <Head title="Calibration" />
            <div className="p-4 sm:p-6">
                <PageHeader icon={BadgeCheck} title="Calibration" description="NIST-traceable certificates & due dates" />

                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <StatCard title="Certificates" value={stats.total} icon={BadgeCheck} tone="indigo" href="/calibration/certificates" />
                    <StatCard title="Overdue" value={stats.overdue} icon={AlertTriangle} tone={stats.overdue > 0 ? 'rose' : 'emerald'} href="/calibration/certificates?due=overdue" />
                    <StatCard title="Due in 30 days" value={stats.due_soon} icon={CalendarClock} tone={stats.due_soon > 0 ? 'amber' : 'sky'} href="/calibration/certificates?due=soon" />
                    <StatCard title="This year" value={stats.this_year} icon={CheckCheck} tone="teal" />
                </div>

                <Card className="mt-6 p-5">
                    <h2 className="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70"><CalendarClock className="h-4 w-4" /> Calibrations by due date</h2>
                    {upcoming.length === 0 ? (
                        <EmptyState icon={BadgeCheck} title="No calibrations recorded" description="Record a calibration certificate to start tracking due dates." />
                    ) : (
                        <div className="space-y-1.5">
                            {upcoming.map(c => (
                                <Link key={c.id} href={`/calibration/certificates/${c.id}`} className="flex items-center gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-secondary">
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-sm font-medium text-foreground">{c.subject}</span>
                                        <span className="block truncate font-mono text-xs text-muted-foreground">{c.certificate_number}</span>
                                    </span>
                                    <Pill color={c.result_color} label={c.result_label} />
                                    {c.due_at && <span className={c.overdue ? 'text-xs font-semibold text-red-600' : 'text-xs text-muted-foreground'}>{c.overdue ? 'Overdue ' : 'Due '}{c.due_at}</span>}
                                </Link>
                            ))}
                        </div>
                    )}
                </Card>
            </div>
        </CalibrationLayout>
    );
}
