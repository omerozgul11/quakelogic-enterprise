import { Head, Link } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/Card';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { Agency } from '@/Types';
import { ArrowLeft, Mail, Phone, Globe, Building, ExternalLink } from 'lucide-react';

interface Props {
    agency: Agency & {
        contacts: Array<{ id: number; first_name: string; last_name: string; title: string | null; email: string | null; phone: string | null; is_decision_maker: boolean }>;
        opportunities: Array<{ id: number; title: string; status: string; due_date: string | null }>;
    };
}

export default function AgencyShow({ agency }: Props) {
    return (
        <AppLayout>
            <Head title={agency.name} />
            <div className="mx-auto max-w-5xl p-6">
                <PageHeader
                    icon={Building}
                    title={agency.name}
                    description={agency.acronym ?? undefined}
                    actions={
                        <Button href="/agencies" variant="secondary" icon={ArrowLeft}>
                            Back
                        </Button>
                    }
                />

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Contact Info</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <dl className="space-y-2.5 text-sm">
                                    {agency.website && (
                                        <div className="flex items-center gap-2 text-muted-foreground">
                                            <Globe className="h-4 w-4 text-muted-foreground" />
                                            <a href={agency.website} target="_blank" rel="noopener noreferrer" className="truncate text-primary hover:underline">{agency.website}</a>
                                        </div>
                                    )}
                                    {agency.phone && <div className="flex items-center gap-2 text-muted-foreground"><Phone className="h-4 w-4" />{agency.phone}</div>}
                                    {agency.email && <div className="flex items-center gap-2 text-muted-foreground"><Mail className="h-4 w-4" />{agency.email}</div>}
                                    {(agency.city || agency.state) && (
                                        <div className="text-muted-foreground">{[agency.city, agency.state, agency.zip_code].filter(Boolean).join(', ')}</div>
                                    )}
                                </dl>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Contacts ({agency.contacts.length})</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {agency.contacts.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">No contacts yet.</p>
                                ) : (
                                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                        {agency.contacts.map(c => (
                                            <div key={c.id} className="flex items-start gap-3 rounded-xl border border-border p-3">
                                                <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-secondary text-xs font-bold text-muted-foreground">
                                                    {c.first_name[0]}{c.last_name[0]}
                                                </div>
                                                <div className="min-w-0">
                                                    <p className="flex items-center gap-2 text-sm font-medium text-foreground">
                                                        {c.first_name} {c.last_name}
                                                        {c.is_decision_maker && <span className="chip">Decision Maker</span>}
                                                    </p>
                                                    {c.title && <p className="truncate text-xs text-muted-foreground">{c.title}</p>}
                                                    {c.email && <p className="truncate text-xs text-primary">{c.email}</p>}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Opportunities ({agency.opportunities.length})</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {agency.opportunities.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">No opportunities linked.</p>
                                ) : (
                                    <div className="space-y-2">
                                        {agency.opportunities.map(o => (
                                            <Link key={o.id} href={`/opportunities/${o.id}`}
                                                className="group flex items-center justify-between gap-2 rounded-xl border border-border p-3 transition-colors hover:bg-secondary/50">
                                                <span className="flex-1 truncate text-sm text-foreground group-hover:text-primary">{o.title}</span>
                                                <StatusBadge status={o.status} />
                                                <ExternalLink className="h-4 w-4 shrink-0 text-muted-foreground transition-colors group-hover:text-primary" />
                                            </Link>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
