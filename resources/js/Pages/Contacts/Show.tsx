import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { Button } from '@/Components/ui/Button';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/Card';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { ConfirmDialog } from '@/Components/ui/Modal';
import { ContactFormModal } from '@/Components/crm/ContactFormModal';
import { cn, getInitials, avatarGradient, formatDate } from '@/Lib/utils';
import { Contact } from '@/Types';
import {
    ArrowLeft, Mail, Phone, Smartphone, Linkedin, Star, KeyRound, Building2,
    Briefcase, Copy, Check, Bell, ExternalLink, MapPin, Pencil, Trash2,
} from 'lucide-react';

interface FollowUpRow {
    id: number;
    subject: string;
    status: string;
    type: string;
    scheduled_date: string;
    proposal: { id: number; proposal_number: string; project_name: string } | null;
}

interface Props {
    contact: Contact & {
        agency: { id: number; name: string } | null;
        company: { id: number; name: string; phone?: string | null; email?: string | null; website?: string | null } | null;
        follow_ups: FollowUpRow[];
    };
    companies: Array<{ id: number; name: string }>;
    can: { manage: boolean };
}

function CopyButton({ value }: { value: string }) {
    const [copied, setCopied] = useState(false);
    return (
        <button
            type="button"
            onClick={() => {
                navigator.clipboard?.writeText(value).then(() => {
                    setCopied(true);
                    setTimeout(() => setCopied(false), 1500);
                });
            }}
            className="rounded-md p-1 text-muted-foreground/60 opacity-0 transition hover:bg-secondary hover:text-foreground group-hover:opacity-100"
            title="Copy"
        >
            {copied ? <Check className="h-3.5 w-3.5 text-emerald-500" /> : <Copy className="h-3.5 w-3.5" />}
        </button>
    );
}

function DetailRow({ icon: Icon, label, children }: { icon: typeof Mail; label: string; children: React.ReactNode }) {
    return (
        <div className="group flex items-start gap-3 rounded-lg px-2 py-2 transition hover:bg-secondary/40">
            <Icon className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
            <div className="min-w-0 flex-1">
                <p className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">{label}</p>
                <div className="mt-0.5 flex items-center gap-1 text-sm text-foreground">{children}</div>
            </div>
        </div>
    );
}

export default function ContactShow({ contact, companies, can }: Props) {
    const name = `${contact.first_name} ${contact.last_name}`.trim();
    const org = contact.company ?? contact.agency ?? null;
    const followUps = contact.follow_ups ?? [];
    const hasContactInfo = contact.email || contact.phone || contact.mobile || contact.linkedin_url;

    const [editOpen, setEditOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const confirmDelete = () => {
        setProcessing(true);
        router.delete(`/contacts/${contact.id}`, { onFinish: () => setProcessing(false) });
    };

    return (
        <AppLayout>
            <Head title={name} />
            <div className="mx-auto max-w-5xl p-6">
                <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <Button href="/contacts" variant="secondary" icon={ArrowLeft}>Back to Contacts</Button>
                    <div className="flex flex-wrap gap-2">
                        {contact.email && <Button href={`mailto:${contact.email}`} variant="secondary" icon={Mail}>Email</Button>}
                        {contact.phone && <Button href={`tel:${contact.phone}`} variant="secondary" icon={Phone}>Call</Button>}
                        {can.manage && <Button variant="secondary" icon={Pencil} onClick={() => setEditOpen(true)}>Edit</Button>}
                        {can.manage && <Button variant="danger" icon={Trash2} onClick={() => setDeleteOpen(true)}>Delete</Button>}
                    </div>
                </div>

                {/* Business card */}
                <Card className="mb-6 overflow-hidden p-0">
                    <div className="h-24 bg-gradient-to-r from-primary/90 via-primary to-orange-500" />
                    <div className="px-6 pb-6">
                        <div className="-mt-10 flex flex-col gap-4 sm:flex-row sm:items-end">
                            <div className={cn(
                                'flex h-20 w-20 shrink-0 items-center justify-center rounded-2xl border-4 border-card bg-gradient-to-br text-2xl font-bold text-white shadow-lift',
                                avatarGradient(name),
                            )}>
                                {getInitials(name)}
                            </div>
                            <div className="min-w-0 flex-1 pb-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <h1 className="truncate text-2xl font-bold text-foreground">{name}</h1>
                                    {contact.is_decision_maker && (
                                        <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">
                                            <Star className="h-3 w-3" /> Decision Maker
                                        </span>
                                    )}
                                    {contact.is_key_contact && (
                                        <span className="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">
                                            <KeyRound className="h-3 w-3" /> Key Contact
                                        </span>
                                    )}
                                </div>
                                <p className="mt-0.5 text-sm text-muted-foreground">
                                    {contact.title || 'Contact'}
                                    {org && (
                                        <>
                                            {' · '}
                                            {contact.company ? (
                                                <Link href={`/companies/${contact.company.id}`} className="font-medium text-foreground hover:text-primary">
                                                    {org.name}
                                                </Link>
                                            ) : (
                                                <span className="font-medium text-foreground">{org.name}</span>
                                            )}
                                        </>
                                    )}
                                </p>
                            </div>
                        </div>
                    </div>
                </Card>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Left: contact details */}
                    <div className="space-y-6 lg:col-span-1">
                        <Card>
                            <CardHeader><CardTitle>Contact Details</CardTitle></CardHeader>
                            <CardContent className="space-y-0.5">
                                {!hasContactInfo && (
                                    <p className="px-2 py-2 text-sm text-muted-foreground">No contact details on file.</p>
                                )}
                                {contact.email && (
                                    <DetailRow icon={Mail} label="Email">
                                        <a href={`mailto:${contact.email}`} className="truncate text-primary hover:underline">{contact.email}</a>
                                        <CopyButton value={contact.email} />
                                    </DetailRow>
                                )}
                                {contact.phone && (
                                    <DetailRow icon={Phone} label="Phone">
                                        <a href={`tel:${contact.phone}`} className="hover:text-primary">{contact.phone}</a>
                                        <CopyButton value={contact.phone} />
                                    </DetailRow>
                                )}
                                {contact.mobile && (
                                    <DetailRow icon={Smartphone} label="Mobile">
                                        <a href={`tel:${contact.mobile}`} className="hover:text-primary">{contact.mobile}</a>
                                        <CopyButton value={contact.mobile} />
                                    </DetailRow>
                                )}
                                {contact.department && (
                                    <DetailRow icon={Briefcase} label="Department">
                                        <span>{contact.department}</span>
                                    </DetailRow>
                                )}
                                {contact.linkedin_url && (
                                    <DetailRow icon={Linkedin} label="LinkedIn">
                                        <a href={contact.linkedin_url} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-1 text-primary hover:underline">
                                            View profile <ExternalLink className="h-3 w-3" />
                                        </a>
                                    </DetailRow>
                                )}
                            </CardContent>
                        </Card>

                        {org && (
                            <Card>
                                <CardHeader><CardTitle>Organization</CardTitle></CardHeader>
                                <CardContent className="space-y-0.5">
                                    <DetailRow icon={Building2} label={contact.company ? 'Company' : 'Agency'}>
                                        {contact.company ? (
                                            <Link href={`/companies/${contact.company.id}`} className="text-primary hover:underline">{org.name}</Link>
                                        ) : contact.agency ? (
                                            <Link href={`/agencies/${contact.agency.id}`} className="text-primary hover:underline">{org.name}</Link>
                                        ) : (
                                            <span>{org.name}</span>
                                        )}
                                    </DetailRow>
                                    {contact.company?.phone && (
                                        <DetailRow icon={Phone} label="Org Phone">
                                            <a href={`tel:${contact.company.phone}`} className="hover:text-primary">{contact.company.phone}</a>
                                        </DetailRow>
                                    )}
                                    {contact.company?.website && (
                                        <DetailRow icon={MapPin} label="Website">
                                            <a href={contact.company.website} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-1 truncate text-primary hover:underline">
                                                {contact.company.website.replace(/^https?:\/\//, '')} <ExternalLink className="h-3 w-3 shrink-0" />
                                            </a>
                                        </DetailRow>
                                    )}
                                </CardContent>
                            </Card>
                        )}

                        {contact.notes && (
                            <Card>
                                <CardHeader><CardTitle>Notes</CardTitle></CardHeader>
                                <CardContent>
                                    <p className="whitespace-pre-line text-sm text-muted-foreground">{contact.notes}</p>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    {/* Right: follow-ups / activity */}
                    <Card className="lg:col-span-2">
                        <CardHeader><CardTitle>Follow-Ups & Activity</CardTitle></CardHeader>
                        <CardContent>
                            {followUps.length === 0 ? (
                                <div className="py-10 text-center">
                                    <Bell className="mx-auto h-8 w-8 text-muted-foreground/40" />
                                    <p className="mt-2 text-sm text-muted-foreground">No follow-ups recorded for this contact yet.</p>
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    {followUps.map(f => (
                                        <Link
                                            key={f.id}
                                            href={`/follow-ups/${f.id}`}
                                            className="flex items-start gap-3 rounded-xl border border-border bg-card p-3 transition hover:border-primary/30 hover:shadow-sm"
                                        >
                                            <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-secondary">
                                                <Bell className="h-4 w-4 text-muted-foreground" />
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-center justify-between gap-2">
                                                    <p className="truncate text-sm font-medium text-foreground">{f.subject}</p>
                                                    <StatusBadge status={f.status} />
                                                </div>
                                                <p className="mt-0.5 text-xs text-muted-foreground">
                                                    <span className="capitalize">{f.type}</span> · {formatDate(f.scheduled_date)}
                                                    {f.proposal && <> · <span className="font-mono">{f.proposal.proposal_number}</span></>}
                                                </p>
                                            </div>
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>

            {editOpen && (
                <ContactFormModal open onClose={() => setEditOpen(false)} contact={contact} companies={companies} />
            )}
            <ConfirmDialog
                open={deleteOpen}
                onClose={() => setDeleteOpen(false)}
                onConfirm={confirmDelete}
                processing={processing}
                title="Delete contact?"
                message={<>This will remove <span className="font-medium text-foreground">{name}</span> from your contacts.</>}
            />
        </AppLayout>
    );
}
