import { Head, Link } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/Card';
import { cn, getInitials, avatarGradient } from '@/Lib/utils';
import { Company } from '@/Types';
import { ArrowLeft, Mail, Phone, Globe, Building2, Users } from 'lucide-react';

interface Props {
    company: Company & {
        contacts: Array<{ id: number; first_name: string; last_name: string; title: string | null; email: string | null }>;
    };
}

export default function CompanyShow({ company }: Props) {
    return (
        <AppLayout>
            <Head title={company.name} />
            <div className="mx-auto max-w-4xl p-6">
                <PageHeader
                    icon={Building2}
                    title={company.name}
                    description={company.type ? company.type.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) : undefined}
                    actions={
                        <Button href="/companies" variant="secondary" icon={ArrowLeft}>
                            Back
                        </Button>
                    }
                />

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle>Details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {company.website && (
                                <div className="flex items-center gap-2 text-sm">
                                    <Globe className="h-4 w-4 shrink-0 text-muted-foreground" />
                                    <a href={company.website} target="_blank" rel="noopener noreferrer" className="truncate text-primary hover:underline">{company.website}</a>
                                </div>
                            )}
                            {company.phone && (
                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Phone className="h-4 w-4 shrink-0 text-muted-foreground" />{company.phone}
                                </div>
                            )}
                            {company.email && (
                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Mail className="h-4 w-4 shrink-0 text-muted-foreground" />{company.email}
                                </div>
                            )}
                            {company.description && <p className="pt-2 text-sm text-muted-foreground">{company.description}</p>}
                        </CardContent>
                    </Card>

                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Contacts ({company.contacts.length})</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {company.contacts.length === 0 ? (
                                <div className="flex items-center gap-2 py-6 text-sm text-muted-foreground">
                                    <Users className="h-4 w-4" /> No contacts linked to this company.
                                </div>
                            ) : (
                                <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    {company.contacts.map(c => {
                                        const name = `${c.first_name} ${c.last_name}`;
                                        return (
                                            <Link key={c.id} href={`/contacts/${c.id}`} className="card-hover flex items-start gap-3 rounded-xl border border-border p-3">
                                                <div className={cn('flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br text-xs font-bold text-white', avatarGradient(name))}>
                                                    {getInitials(name)}
                                                </div>
                                                <div className="min-w-0">
                                                    <p className="text-sm font-medium text-foreground">{name}</p>
                                                    {c.title && <p className="text-xs text-muted-foreground">{c.title}</p>}
                                                    {c.email && <p className="truncate text-xs text-primary">{c.email}</p>}
                                                </div>
                                            </Link>
                                        );
                                    })}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
