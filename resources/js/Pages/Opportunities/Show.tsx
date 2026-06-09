import { Head, Link, router } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/Card';
import { formatCurrency, formatDate } from '@/Lib/utils';
import { Opportunity, CapturePlan } from '@/Types';
import { ArrowLeft, Edit, Target, Building, ExternalLink, Plus } from 'lucide-react';

interface Props {
    opportunity: Opportunity & {
        capture_plan?: CapturePlan;
        assignments?: Array<{ id: number; user: { id: number; name: string } }>;
        competitors?: Array<{ id: number; company: { id: number; name: string } }>;
    };
    can: { edit: boolean; delete: boolean; createCapture: boolean };
}

function InfoRow({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="flex items-center justify-between gap-4 py-3 border-b border-border last:border-0">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="text-sm font-semibold text-foreground text-right">{value ?? '—'}</dd>
        </div>
    );
}

export default function OpportunityShow({ opportunity, can }: Props) {
    const handleDelete = () => {
        if (confirm('Delete this opportunity? This cannot be undone.')) {
            router.delete(`/opportunities/${opportunity.id}`);
        }
    };

    return (
        <AppLayout>
            <Head title={opportunity.title} />
            <div className="p-6 max-w-6xl mx-auto">
                <PageHeader
                    icon={Target}
                    title={opportunity.title}
                    description={opportunity.solicitation_number ? `Solicitation: ${opportunity.solicitation_number}` : undefined}
                    actions={
                        <>
                            <Button variant="secondary" icon={ArrowLeft} href="/opportunities">
                                Back
                            </Button>
                            {can.edit && (
                                <Button variant="secondary" icon={Edit} href={`/opportunities/${opportunity.id}/edit`}>
                                    Edit
                                </Button>
                            )}
                            {!opportunity.capture_plan && can.createCapture && (
                                <Button icon={Plus} href={`/capture/create?opportunity_id=${opportunity.id}`}>
                                    Create Capture Plan
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Main Details */}
                    <div className="lg:col-span-2 space-y-6">
                        <Card className="animate-rise">
                            <CardHeader>
                                <CardTitle>Opportunity Details</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <dl>
                                    <InfoRow label="Agency" value={opportunity.agency_name} />
                                    <InfoRow label="Estimated Value" value={formatCurrency(opportunity.estimated_value)} />
                                    <InfoRow label="Due Date" value={opportunity.due_date ? formatDate(opportunity.due_date) : null} />
                                    <InfoRow label="Posted Date" value={opportunity.posted_date ? formatDate(opportunity.posted_date) : null} />
                                    <InfoRow label="NAICS Code" value={opportunity.naics_code} />
                                    <InfoRow label="Set-Aside" value={opportunity.set_aside_type} />
                                    <InfoRow label="Place of Performance" value={opportunity.place_of_performance} />
                                    <InfoRow label="Source" value={
                                        <span className="chip">
                                            {opportunity.source?.replace(/_/g, ' ').toUpperCase()}
                                        </span>
                                    } />
                                    {opportunity.source_url && (
                                        <InfoRow label="Source Link" value={
                                            <a href={opportunity.source_url} target="_blank" rel="noopener noreferrer"
                                                className="inline-flex items-center gap-1 text-primary hover:underline">
                                                View on {opportunity.source?.replace(/_/g, ' ')} <ExternalLink className="h-3 w-3" />
                                            </a>
                                        } />
                                    )}
                                </dl>
                            </CardContent>
                        </Card>

                        {opportunity.description && (
                            <Card className="animate-rise">
                                <CardHeader>
                                    <CardTitle>Description</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm text-muted-foreground whitespace-pre-wrap leading-relaxed">
                                        {opportunity.description}
                                    </p>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-4">
                        {/* Status */}
                        <Card className="animate-rise">
                            <CardHeader>
                                <CardTitle className="text-sm">Status</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <StatusBadge status={opportunity.status} />
                            </CardContent>
                        </Card>

                        {/* Capture Plan */}
                        <Card className="animate-rise">
                            <CardHeader>
                                <CardTitle className="text-sm">Capture Plan</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {opportunity.capture_plan ? (
                                    <div>
                                        <StatusBadge status={
                                            typeof opportunity.capture_plan.stage === 'string'
                                                ? opportunity.capture_plan.stage
                                                : (opportunity.capture_plan.stage as any)?.value ?? 'discovery'
                                        } />
                                        <Link href={`/capture/${opportunity.capture_plan.id}`}
                                            className="mt-3 block text-sm font-medium text-primary hover:underline">
                                            View capture plan →
                                        </Link>
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground">No capture plan yet.</p>
                                )}
                            </CardContent>
                        </Card>

                        {/* Team */}
                        {opportunity.assignments && opportunity.assignments.length > 0 && (
                            <Card className="animate-rise">
                                <CardHeader>
                                    <CardTitle className="text-sm">Assigned Team</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-2">
                                        {opportunity.assignments.map(a => (
                                            <div key={a.id} className="flex items-center gap-2">
                                                <div className="bg-brand-gradient flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold text-white">
                                                    {a.user.name[0]}
                                                </div>
                                                <span className="text-sm text-foreground">{a.user.name}</span>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Competitors */}
                        {opportunity.competitors && opportunity.competitors.length > 0 && (
                            <Card className="animate-rise">
                                <CardHeader>
                                    <CardTitle className="text-sm">Known Competitors</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-2">
                                        {opportunity.competitors.map(c => (
                                            <div key={c.id} className="flex items-center gap-2 text-sm text-foreground">
                                                <Building className="h-3 w-3 text-muted-foreground" />
                                                {c.company.name}
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {can.delete && (
                            <Button variant="danger" className="w-full" onClick={handleDelete}>
                                Delete Opportunity
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
