import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { Button } from '@/Components/ui/Button';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/Card';
import { StatusBadge } from '@/Components/ui/StatusBadge';
import { ConfirmDialog } from '@/Components/ui/Modal';
import { formatDate } from '@/Lib/utils';
import {
    ArrowLeft, Bell, Check, Reply, X, RotateCcw, Calendar, Clock, Send,
    FileText, Building2, User, Mail, Phone, Trash2,
} from 'lucide-react';

interface FollowUp {
    id: number;
    type: string;
    subject: string;
    status: string;
    message: string | null;
    scheduled_date: string;
    sent_at: string | null;
    responded_at: string | null;
    created_at: string;
    assigned_to: { id: number; name: string } | null;
    proposal: { id: number; proposal_number: string; project_name: string } | null;
    opportunity: { id: number; title: string } | null;
    contact: {
        id: number; first_name: string; last_name: string; title: string | null;
        email: string | null; phone: string | null;
        company: { id: number; name: string } | null;
    } | null;
}

interface Props {
    followUp: FollowUp;
    can: { update: boolean };
}

function fmtDateTime(v: string | null): string {
    if (!v) return '—';
    return formatDate(v);
}

export default function FollowUpShow({ followUp, can }: Props) {
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const setStatus = (status: string) => {
        router.patch(`/follow-ups/${followUp.id}`, { status }, { preserveScroll: true });
    };

    const confirmDelete = () => {
        setProcessing(true);
        router.delete(`/follow-ups/${followUp.id}`, { onFinish: () => setProcessing(false) });
    };

    const s = followUp.status;

    return (
        <AppLayout>
            <Head title={followUp.subject} />
            <div className="mx-auto max-w-4xl p-6">
                <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <Button href="/follow-ups" variant="secondary" icon={ArrowLeft}>Back to Follow-Ups</Button>
                    {can.update && <Button variant="danger" icon={Trash2} onClick={() => setDeleteOpen(true)}>Delete</Button>}
                </div>

                <Card className="mb-6 p-6">
                    <div className="flex items-start gap-4">
                        <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                            <Bell className="h-5 w-5 text-primary" />
                        </div>
                        <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="text-xl font-bold text-foreground">{followUp.subject}</h1>
                                <StatusBadge status={followUp.status} />
                                <span className="chip capitalize">{followUp.type}</span>
                            </div>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Scheduled for {formatDate(followUp.scheduled_date)}
                                {followUp.assigned_to && <> · Assigned to {followUp.assigned_to.name}</>}
                            </p>
                        </div>
                    </div>

                    {can.update && (
                        <div className="mt-5 flex flex-wrap gap-2 border-t border-border pt-4">
                            {s !== 'sent' && (
                                <Button variant="success" size="sm" icon={Send} onClick={() => setStatus('sent')}>Mark as Sent</Button>
                            )}
                            {s !== 'responded' && (
                                <Button variant="secondary" size="sm" icon={Reply} onClick={() => setStatus('responded')}>Mark as Responded</Button>
                            )}
                            {s !== 'scheduled' && (
                                <Button variant="secondary" size="sm" icon={RotateCcw} onClick={() => setStatus('scheduled')}>Re-open</Button>
                            )}
                            {s !== 'cancelled' && (
                                <Button variant="ghost" size="sm" icon={X} onClick={() => setStatus('cancelled')}>Cancel</Button>
                            )}
                        </div>
                    )}
                </Card>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        {followUp.message && (
                            <Card>
                                <CardHeader><CardTitle>Message</CardTitle></CardHeader>
                                <CardContent>
                                    <p className="whitespace-pre-line text-sm text-foreground">{followUp.message}</p>
                                </CardContent>
                            </Card>
                        )}

                        <Card>
                            <CardHeader><CardTitle>Timeline</CardTitle></CardHeader>
                            <CardContent className="space-y-3">
                                <div className="flex items-center gap-3 text-sm">
                                    <Calendar className="h-4 w-4 text-muted-foreground" />
                                    <span className="text-muted-foreground">Scheduled</span>
                                    <span className="ml-auto font-medium text-foreground">{formatDate(followUp.scheduled_date)}</span>
                                </div>
                                <div className="flex items-center gap-3 text-sm">
                                    <Send className="h-4 w-4 text-muted-foreground" />
                                    <span className="text-muted-foreground">Sent</span>
                                    <span className="ml-auto font-medium text-foreground">{fmtDateTime(followUp.sent_at)}</span>
                                </div>
                                <div className="flex items-center gap-3 text-sm">
                                    <Check className="h-4 w-4 text-muted-foreground" />
                                    <span className="text-muted-foreground">Responded</span>
                                    <span className="ml-auto font-medium text-foreground">{fmtDateTime(followUp.responded_at)}</span>
                                </div>
                                <div className="flex items-center gap-3 text-sm">
                                    <Clock className="h-4 w-4 text-muted-foreground" />
                                    <span className="text-muted-foreground">Created</span>
                                    <span className="ml-auto font-medium text-foreground">{fmtDateTime(followUp.created_at)}</span>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-6">
                        <Card>
                            <CardHeader><CardTitle>Linked To</CardTitle></CardHeader>
                            <CardContent className="space-y-3">
                                {followUp.proposal && (
                                    <Link href={`/proposals/${followUp.proposal.id}`} className="flex items-start gap-3 rounded-lg p-2 transition hover:bg-secondary/40">
                                        <FileText className="mt-0.5 h-4 w-4 text-muted-foreground" />
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium text-foreground">{followUp.proposal.project_name}</p>
                                            <p className="font-mono text-xs text-primary">{followUp.proposal.proposal_number}</p>
                                        </div>
                                    </Link>
                                )}
                                {followUp.opportunity && (
                                    <Link href={`/opportunities/${followUp.opportunity.id}`} className="flex items-start gap-3 rounded-lg p-2 transition hover:bg-secondary/40">
                                        <Building2 className="mt-0.5 h-4 w-4 text-muted-foreground" />
                                        <p className="truncate text-sm font-medium text-foreground">{followUp.opportunity.title}</p>
                                    </Link>
                                )}
                                {followUp.contact && (
                                    <Link href={`/contacts/${followUp.contact.id}`} className="flex items-start gap-3 rounded-lg p-2 transition hover:bg-secondary/40">
                                        <User className="mt-0.5 h-4 w-4 text-muted-foreground" />
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium text-foreground">
                                                {followUp.contact.first_name} {followUp.contact.last_name}
                                            </p>
                                            {followUp.contact.title && <p className="truncate text-xs text-muted-foreground">{followUp.contact.title}</p>}
                                            {followUp.contact.company && <p className="truncate text-xs text-muted-foreground">{followUp.contact.company.name}</p>}
                                        </div>
                                    </Link>
                                )}
                                {!followUp.proposal && !followUp.opportunity && !followUp.contact && (
                                    <p className="px-2 py-1 text-sm text-muted-foreground">Not linked to any record.</p>
                                )}
                            </CardContent>
                        </Card>

                        {followUp.contact && (followUp.contact.email || followUp.contact.phone) && (
                            <Card>
                                <CardHeader><CardTitle>Reach Out</CardTitle></CardHeader>
                                <CardContent className="space-y-2">
                                    {followUp.contact.email && (
                                        <a href={`mailto:${followUp.contact.email}`} className="flex items-center gap-2 text-sm text-primary hover:underline">
                                            <Mail className="h-4 w-4 text-muted-foreground" />{followUp.contact.email}
                                        </a>
                                    )}
                                    {followUp.contact.phone && (
                                        <a href={`tel:${followUp.contact.phone}`} className="flex items-center gap-2 text-sm text-foreground hover:text-primary">
                                            <Phone className="h-4 w-4 text-muted-foreground" />{followUp.contact.phone}
                                        </a>
                                    )}
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>

            <ConfirmDialog
                open={deleteOpen}
                onClose={() => setDeleteOpen(false)}
                onConfirm={confirmDelete}
                processing={processing}
                title="Delete follow-up?"
                message={<>This will delete the follow-up “<span className="font-medium text-foreground">{followUp.subject}</span>”.</>}
            />
        </AppLayout>
    );
}
