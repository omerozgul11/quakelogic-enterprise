import { Head, Link } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/Card';
import { cn, getInitials, avatarGradient } from '@/Lib/utils';
import { Contact } from '@/Types';
import { ArrowLeft, Mail, Phone, Linkedin, Star, UserRound } from 'lucide-react';

interface Props {
    contact: Contact & {
        agency: { id: number; name: string } | null;
        company: { id: number; name: string } | null;
        activities: Array<{ id: number; type: string; summary: string; created_at: string; user: { name: string } | null }>;
    };
}

export default function ContactShow({ contact }: Props) {
    const name = `${contact.first_name} ${contact.last_name}`;

    return (
        <AppLayout>
            <Head title={name} />
            <div className="mx-auto max-w-4xl p-6">
                <PageHeader
                    icon={UserRound}
                    title={name}
                    description={contact.title ?? undefined}
                    actions={
                        <Button href="/contacts" variant="secondary" icon={ArrowLeft}>
                            Back
                        </Button>
                    }
                />

                <div className="mb-6 flex items-center gap-4">
                    <div className={cn('flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-gradient-to-br text-xl font-bold text-white', avatarGradient(name))}>
                        {getInitials(name)}
                    </div>
                    {contact.is_decision_maker && (
                        <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-800">
                            <Star className="h-3 w-3" /> Decision Maker
                        </span>
                    )}
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Contact Info</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                {contact.email && (
                                    <a href={`mailto:${contact.email}`} className="flex items-center gap-2 text-sm text-primary hover:underline">
                                        <Mail className="h-4 w-4 text-muted-foreground" />{contact.email}
                                    </a>
                                )}
                                {contact.phone && (
                                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                        <Phone className="h-4 w-4 text-muted-foreground" />{contact.phone}
                                    </div>
                                )}
                                {contact.linkedin_url && (
                                    <a href={contact.linkedin_url} target="_blank" rel="noopener noreferrer"
                                        className="flex items-center gap-2 text-sm text-primary hover:underline">
                                        <Linkedin className="h-4 w-4 text-muted-foreground" />LinkedIn Profile
                                    </a>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Organization</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {contact.agency && (
                                    <Link href={`/agencies/${contact.agency.id}`} className="text-sm text-primary hover:underline">
                                        {contact.agency.name}
                                    </Link>
                                )}
                                {contact.company && (
                                    <Link href={`/companies/${contact.company.id}`} className="text-sm text-primary hover:underline">
                                        {contact.company.name}
                                    </Link>
                                )}
                                {!contact.agency && !contact.company && <p className="text-sm text-muted-foreground">Independent</p>}
                            </CardContent>
                        </Card>

                        {contact.notes && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Notes</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm text-muted-foreground">{contact.notes}</p>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Activity History</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {contact.activities.length === 0 ? (
                                <p className="py-8 text-center text-sm text-muted-foreground">No activities recorded yet.</p>
                            ) : (
                                <div className="stagger space-y-4">
                                    {contact.activities.map(a => (
                                        <div key={a.id} className="animate-fade-in flex gap-3">
                                            <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-secondary">
                                                <span className="text-xs font-semibold text-muted-foreground">{a.type[0].toUpperCase()}</span>
                                            </div>
                                            <div>
                                                <p className="text-sm text-foreground">{a.summary}</p>
                                                <p className="text-xs text-muted-foreground">{a.type} · {a.user?.name ?? 'System'} · {a.created_at}</p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
