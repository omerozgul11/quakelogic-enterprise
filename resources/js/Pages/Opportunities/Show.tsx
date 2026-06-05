import { Head, Link, router } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { formatCurrency, formatDate, formatDateTime } from '@/Lib/utils';
import { Opportunity, CapturePlan } from '@/Types';
import { ArrowLeft, Edit, Target, Calendar, DollarSign, Building, ExternalLink, Plus } from 'lucide-react';

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
        <div className="flex flex-col gap-1 py-3 border-b border-gray-100 last:border-0">
            <dt className="text-xs font-medium text-gray-500 uppercase tracking-wide">{label}</dt>
            <dd className="text-sm text-gray-900">{value ?? '—'}</dd>
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
                <div className="flex items-center gap-4 mb-6">
                    <Link href="/opportunities" className="text-gray-400 hover:text-gray-600">
                        <ArrowLeft className="h-5 w-5" />
                    </Link>
                    <div className="flex-1">
                        <div className="flex items-center gap-3">
                            <h1 className="text-xl font-bold text-gray-900 leading-tight">{opportunity.title}</h1>
                            <StatusBadge status={opportunity.status} />
                        </div>
                        {opportunity.solicitation_number && (
                            <p className="text-sm text-gray-500 mt-0.5">Solicitation: {opportunity.solicitation_number}</p>
                        )}
                    </div>
                    <div className="flex gap-2">
                        {can.edit && (
                            <Link href={`/opportunities/${opportunity.id}/edit`}
                                className="flex items-center gap-2 px-3 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">
                                <Edit className="h-4 w-4" /> Edit
                            </Link>
                        )}
                        {!opportunity.capture_plan && can.createCapture && (
                            <Link href={`/capture/create?opportunity_id=${opportunity.id}`}
                                className="flex items-center gap-2 px-3 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                <Plus className="h-4 w-4" /> Create Capture Plan
                            </Link>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Main Details */}
                    <div className="lg:col-span-2 space-y-6">
                        <div className="bg-white rounded-xl border border-gray-200 p-6">
                            <h2 className="text-base font-semibold text-gray-900 mb-4">Opportunity Details</h2>
                            <dl>
                                <InfoRow label="Agency" value={opportunity.agency_name} />
                                <InfoRow label="Estimated Value" value={formatCurrency(opportunity.estimated_value)} />
                                <InfoRow label="Due Date" value={opportunity.due_date ? formatDate(opportunity.due_date) : null} />
                                <InfoRow label="Posted Date" value={opportunity.posted_date ? formatDate(opportunity.posted_date) : null} />
                                <InfoRow label="NAICS Code" value={opportunity.naics_code} />
                                <InfoRow label="Set-Aside" value={opportunity.set_aside_type} />
                                <InfoRow label="Place of Performance" value={opportunity.place_of_performance} />
                                <InfoRow label="Source" value={
                                    <span className="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded-full">
                                        {opportunity.source?.replace(/_/g, ' ').toUpperCase()}
                                    </span>
                                } />
                                {opportunity.source_url && (
                                    <InfoRow label="Source Link" value={
                                        <a href={opportunity.source_url} target="_blank" rel="noopener noreferrer"
                                            className="flex items-center gap-1 text-blue-600 hover:underline">
                                            View on {opportunity.source?.replace(/_/g, ' ')} <ExternalLink className="h-3 w-3" />
                                        </a>
                                    } />
                                )}
                            </dl>
                        </div>

                        {opportunity.description && (
                            <div className="bg-white rounded-xl border border-gray-200 p-6">
                                <h2 className="text-base font-semibold text-gray-900 mb-3">Description</h2>
                                <p className="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">
                                    {opportunity.description}
                                </p>
                            </div>
                        )}
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-4">
                        {/* Capture Plan */}
                        <div className="bg-white rounded-xl border border-gray-200 p-5">
                            <h3 className="text-sm font-semibold text-gray-900 mb-3">Capture Plan</h3>
                            {opportunity.capture_plan ? (
                                <div>
                                    <StatusBadge status={
                                        typeof opportunity.capture_plan.stage === 'string'
                                            ? opportunity.capture_plan.stage
                                            : (opportunity.capture_plan.stage as any)?.value ?? 'discovery'
                                    } />
                                    <Link href={`/capture/${opportunity.capture_plan.id}`}
                                        className="mt-3 block text-sm text-blue-600 hover:underline">
                                        View capture plan →
                                    </Link>
                                </div>
                            ) : (
                                <p className="text-sm text-gray-500">No capture plan yet.</p>
                            )}
                        </div>

                        {/* Team */}
                        {opportunity.assignments && opportunity.assignments.length > 0 && (
                            <div className="bg-white rounded-xl border border-gray-200 p-5">
                                <h3 className="text-sm font-semibold text-gray-900 mb-3">Assigned Team</h3>
                                <div className="space-y-2">
                                    {opportunity.assignments.map(a => (
                                        <div key={a.id} className="flex items-center gap-2">
                                            <div className="h-7 w-7 rounded-full bg-blue-100 text-blue-700 text-xs font-bold flex items-center justify-center">
                                                {a.user.name[0]}
                                            </div>
                                            <span className="text-sm text-gray-700">{a.user.name}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* Competitors */}
                        {opportunity.competitors && opportunity.competitors.length > 0 && (
                            <div className="bg-white rounded-xl border border-gray-200 p-5">
                                <h3 className="text-sm font-semibold text-gray-900 mb-3">Known Competitors</h3>
                                <div className="space-y-2">
                                    {opportunity.competitors.map(c => (
                                        <div key={c.id} className="text-sm text-gray-700 flex items-center gap-2">
                                            <Building className="h-3 w-3 text-gray-400" />
                                            {c.company.name}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {can.delete && (
                            <button onClick={handleDelete}
                                className="w-full text-sm text-red-600 border border-red-200 rounded-lg px-4 py-2 hover:bg-red-50">
                                Delete Opportunity
                            </button>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
