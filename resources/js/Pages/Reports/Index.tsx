import { Head, Link } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/Card';
import { formatCurrency } from '@/Lib/utils';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Legend } from 'recharts';
import { ChartGradients, ChartTooltip, AXIS_TICK, GRID_STROKE } from '@/Components/ui/ChartKit';
import { ExportMenu } from '@/Components/ui/ExportMenu';
import { BarChart2, Users, ChevronRight, Landmark } from 'lucide-react';

interface Props {
    proposalTrend: Array<{ year: number; month: number; total: number; awarded: number; proposal_value: number; award_value: number }>;
    commissionTrend: Array<{ period_month: string; total_commissions: number; count: number }>;
    topOpportunities: Array<{ id: number; title: string; agency_name: string | null; estimated_value: number }>;
}

const MONTH_NAMES = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

export default function ReportsIndex({ proposalTrend, commissionTrend, topOpportunities }: Props) {
    const chartData = proposalTrend.slice(0, 12).reverse().map(d => ({
        name: `${MONTH_NAMES[d.month - 1]} ${d.year}`,
        Submitted: d.total,
        Awarded: d.awarded,
    }));

    const commissionData = commissionTrend.slice(0, 6).reverse().map(d => ({
        name: d.period_month,
        Commission: Number(d.total_commissions),
    }));

    return (
        <AppLayout>
            <Head title="Reports" />
            <div className="p-6">
                <PageHeader
                    icon={BarChart2}
                    title="Reports & Analytics"
                    description="Proposal activity, commissions, and the biggest contracts in your pipeline."
                    actions={
                        <>
                            <ExportMenu urlTemplate="/reports/download/{format}" />
                            <Link
                                href="/reports/users"
                                className="bg-brand-gradient inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-opacity hover:opacity-95"
                            >
                                <Users className="h-4 w-4" /> Team Performance
                            </Link>
                        </>
                    }
                />

                <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
                    {/* Proposal Activity */}
                    <Card>
                        <CardHeader><CardTitle>Proposal Activity — Last 12 Months</CardTitle></CardHeader>
                        <CardContent>
                            {chartData.length === 0 ? (
                                <p className="py-10 text-center text-sm text-muted-foreground">No data available yet.</p>
                            ) : (
                                <ResponsiveContainer width="100%" height={260}>
                                    <BarChart data={chartData} barGap={6}>
                                        <ChartGradients />
                                        <CartesianGrid strokeDasharray="3 3" stroke={GRID_STROKE} vertical={false} />
                                        <XAxis dataKey="name" tick={AXIS_TICK} tickLine={false} axisLine={false} />
                                        <YAxis tick={AXIS_TICK} tickLine={false} axisLine={false} allowDecimals={false} width={28} />
                                        <Tooltip cursor={{ fill: 'hsl(var(--muted-foreground))', opacity: 0.08 }} content={<ChartTooltip />} />
                                        <Legend iconType="circle" wrapperStyle={{ fontSize: 12 }} />
                                        <Bar dataKey="Submitted" fill="url(#cg-0)" radius={[6, 6, 0, 0]} maxBarSize={26} />
                                        <Bar dataKey="Awarded" fill="url(#cg-2)" radius={[6, 6, 0, 0]} maxBarSize={26} />
                                    </BarChart>
                                </ResponsiveContainer>
                            )}
                        </CardContent>
                    </Card>

                    {/* Commission Trend */}
                    <Card>
                        <CardHeader><CardTitle>Commission Trend — Last 6 Months</CardTitle></CardHeader>
                        <CardContent>
                            {commissionData.length === 0 ? (
                                <p className="py-10 text-center text-sm text-muted-foreground">No commission data available yet.</p>
                            ) : (
                                <ResponsiveContainer width="100%" height={260}>
                                    <BarChart data={commissionData}>
                                        <ChartGradients />
                                        <CartesianGrid strokeDasharray="3 3" stroke={GRID_STROKE} vertical={false} />
                                        <XAxis dataKey="name" tick={AXIS_TICK} tickLine={false} axisLine={false} />
                                        <YAxis tick={AXIS_TICK} tickLine={false} axisLine={false} tickFormatter={v => `$${(v / 1000).toFixed(0)}K`} width={44} />
                                        <Tooltip cursor={{ fill: 'hsl(var(--muted-foreground))', opacity: 0.08 }} content={<ChartTooltip currency />} />
                                        <Bar dataKey="Commission" fill="url(#cg-6)" radius={[6, 6, 0, 0]} maxBarSize={36} />
                                    </BarChart>
                                </ResponsiveContainer>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Top contracts */}
                <Card className="mt-6 overflow-hidden">
                    <div className="flex items-center justify-between border-b border-border px-5 py-4">
                        <div>
                            <h2 className="font-semibold text-foreground">Top Contracts by Value</h2>
                            <p className="mt-0.5 text-xs text-muted-foreground">The highest-value opportunities in your pipeline — live SAM.gov data. Click one to open it.</p>
                        </div>
                    </div>
                    {topOpportunities.length === 0 ? (
                        <p className="py-10 text-center text-sm text-muted-foreground">No contracts with a value yet.</p>
                    ) : (
                        <div className="divide-y divide-border">
                            {topOpportunities.map((opp, i) => (
                                <Link
                                    key={opp.id}
                                    href={`/opportunities/${opp.id}`}
                                    className="group flex items-center gap-4 px-5 py-3.5 transition-colors hover:bg-secondary/50"
                                >
                                    <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">
                                        {i + 1}
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium text-foreground group-hover:text-primary">{opp.title}</p>
                                        {opp.agency_name && (
                                            <p className="mt-0.5 flex items-center gap-1.5 truncate text-xs text-muted-foreground">
                                                <Landmark className="h-3 w-3 shrink-0" /> {opp.agency_name}
                                            </p>
                                        )}
                                    </div>
                                    <span className="shrink-0 text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                                        {formatCurrency(opp.estimated_value)}
                                    </span>
                                    <ChevronRight className="h-4 w-4 shrink-0 text-muted-foreground/40 transition-transform group-hover:translate-x-0.5 group-hover:text-primary" />
                                </Link>
                            ))}
                        </div>
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}
