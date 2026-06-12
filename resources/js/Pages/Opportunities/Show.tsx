import { Head, Link, router } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/Card';
import { formatCurrency, formatDate, sourceLabel } from '@/Lib/utils';
import { Opportunity } from '@/Types';
import { ArrowLeft, Edit, Target, Building, ExternalLink, Rocket, Users, Mail, Phone, FileText, Eye, Download } from 'lucide-react';
import { FilePreviewModal, PreviewFile } from '@/Components/ui/FilePreviewModal';
import { useState } from 'react';

interface OppContact { id: number; name: string; title: string | null; email: string | null; phone: string | null }

interface SamDocument { index: number; name: string; preview_url: string; download_url: string }

interface Props {
    opportunity: Opportunity & {
        company?: { id: number; name: string } | null;
        assignments?: Array<{ id: number; user: { id: number; name: string } }>;
        competitors?: Array<{ id: number; company: { id: number; name: string } }>;
        proposals?: Array<{ id: number; proposal_number: string; status: string }>;
    };
    contacts: OppContact[];
    samDocuments: SamDocument[];
    can: { edit: boolean; delete: boolean; pursue: boolean };
}

function InfoRow({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="flex items-center justify-between gap-4 py-3 border-b border-border last:border-0">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="text-sm font-semibold text-foreground text-right">{value ?? '—'}</dd>
        </div>
    );
}

export default function OpportunityShow({ opportunity, contacts, samDocuments, can }: Props) {
    const application = opportunity.proposals?.[0];
    const [preview, setPreview] = useState<PreviewFile | null>(null);

    const handleDelete = () => {
        if (confirm('Delete this opportunity? This cannot be undone.')) {
            router.delete(`/opportunities/${opportunity.id}`);
        }
    };

    const pursue = () => router.post(`/opportunities/${opportunity.id}/pursue`);

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
                            {application ? (
                                <Button icon={Rocket} href={`/proposals/${application.id}`}>
                                    View Application
                                </Button>
                            ) : can.pursue && (
                                <Button icon={Rocket} onClick={pursue}>
                                    Pursue / Start Application
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
                                    <InfoRow label="Company / Agency" value={opportunity.company?.name ?? opportunity.agency_name} />
                                    <InfoRow label="Estimated Value" value={formatCurrency(opportunity.estimated_value)} />
                                    <InfoRow label="Due Date" value={opportunity.due_date ? formatDate(opportunity.due_date) : null} />
                                    <InfoRow label="Posted Date" value={opportunity.posted_date ? formatDate(opportunity.posted_date) : null} />
                                    <InfoRow label="NAICS Code" value={opportunity.naics_code} />
                                    <InfoRow label="Set-Aside" value={opportunity.set_aside_type} />
                                    <InfoRow label="Place of Performance" value={opportunity.place_of_performance} />
                                    <InfoRow label="Source" value={<span className="chip">{sourceLabel(opportunity.source)}</span>} />
                                    {opportunity.source_url && (
                                        <InfoRow label="Source Link" value={
                                            <a href={opportunity.source_url} target="_blank" rel="noopener noreferrer"
                                                className="inline-flex items-center gap-1 text-primary hover:underline">
                                                View on {sourceLabel(opportunity.source)} <ExternalLink className="h-3 w-3" />
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

                        {/* Solicitation documents pulled live from the SAM.gov record */}
                        <Card className="animate-rise">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <FileText className="h-4 w-4 text-muted-foreground" /> Solicitation Documents
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {samDocuments.length === 0 ? (
                                    <div className="text-sm text-muted-foreground">
                                        <p>This SAM.gov notice has no downloadable attachments — the details are in the description, or behind the notice's portal link.</p>
                                        {opportunity.source_url && (
                                            <a href={opportunity.source_url} target="_blank" rel="noopener noreferrer" className="mt-1.5 inline-flex items-center gap-1 font-medium text-primary hover:underline">
                                                View the full notice on SAM.gov <ExternalLink className="h-3 w-3" />
                                            </a>
                                        )}
                                    </div>
                                ) : (
                                    <div className="space-y-2">
                                        {samDocuments.map(d => (
                                            <div key={d.index} className="flex items-center gap-2 rounded-lg border border-border p-2.5 transition-colors hover:bg-secondary/50">
                                                <FileText className="h-4 w-4 shrink-0 text-muted-foreground" />
                                                <span className="min-w-0 flex-1 truncate text-sm text-foreground" title={d.name}>{d.name}</span>
                                                <button
                                                    type="button"
                                                    onClick={() => setPreview({ name: d.name, mimeType: 'application/pdf', previewUrl: d.preview_url, downloadUrl: d.download_url })}
                                                    title="Preview"
                                                    className="text-muted-foreground transition-colors hover:text-primary"
                                                >
                                                    <Eye className="h-4 w-4" />
                                                </button>
                                                <a href={d.download_url} title="Download" className="text-muted-foreground transition-colors hover:text-primary">
                                                    <Download className="h-4 w-4" />
                                                </a>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-4">
                        {/* Contacts — shown above the status bubbles */}
                        <Card className="animate-rise">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-sm">
                                    <Users className="h-4 w-4 text-muted-foreground" /> Contacts
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {contacts.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">No contacts yet. Pursue this opportunity and upload a document to extract them.</p>
                                ) : (
                                    <div className="space-y-3">
                                        {contacts.map(c => (
                                            <div key={c.id} className="border-b border-border pb-3 last:border-0 last:pb-0">
                                                <p className="text-sm font-medium text-foreground">{c.name}</p>
                                                {c.title && <p className="text-xs text-muted-foreground">{c.title}</p>}
                                                {c.email && (
                                                    <a href={`mailto:${c.email}`} className="mt-1 flex items-center gap-1.5 text-xs text-primary hover:underline">
                                                        <Mail className="h-3 w-3" /> {c.email}
                                                    </a>
                                                )}
                                                {c.phone && (
                                                    <p className="mt-0.5 flex items-center gap-1.5 text-xs text-muted-foreground">
                                                        <Phone className="h-3 w-3" /> {c.phone}
                                                    </p>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Status */}
                        <Card className="animate-rise">
                            <CardHeader>
                                <CardTitle className="text-sm">Status</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <StatusBadge status={opportunity.status} />
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
            <FilePreviewModal file={preview} onClose={() => setPreview(null)} />
        </AppLayout>
    );
}
