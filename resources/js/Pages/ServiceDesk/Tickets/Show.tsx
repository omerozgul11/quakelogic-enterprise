import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { ServiceDeskLayout } from '@/Components/layout/ServiceDeskLayout';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Pill } from '@/Components/ui/Pill';
import { Select } from '@/Components/ui/Select';
import { ConfirmDialog } from '@/Components/ui/Modal';
import { cn, getInitials, avatarGradient } from '@/Lib/utils';
import { ArrowLeft, LifeBuoy, Trash2, Lock, Cpu, AlertTriangle, Send } from 'lucide-react';

interface Comment { id: number; body: string; is_internal: boolean; author: string | null; created_at: string | null }
interface Ticket {
    id: number; number: string; subject: string; description: string | null;
    type: string; type_label: string; type_color: string;
    status: string; status_label: string; status_color: string;
    priority: string; priority_label: string; priority_color: string;
    channel: string | null; serial_number: string | null; rma_disposition: string | null;
    overdue: boolean; due_at: string | null; opened_at: string | null; resolved_at: string | null; resolution: string | null;
    assignee_id: number | null; assignee: string | null; company: string | null; contact: string | null;
    asset: { id: number; asset_tag: string; name: string } | null; product: { id: number; sku: string } | null;
}

interface Props {
    ticket: Ticket;
    comments: Comment[];
    statuses: { value: string; label: string }[];
    priorities: { value: string; label: string }[];
    users: { id: number; name: string }[];
    can: { manage: boolean; comment: boolean };
}

function when(iso: string | null): string {
    if (!iso) return '';
    return new Date(iso).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

export default function TicketShow({ ticket, comments, statuses, priorities, users, can }: Props) {
    const [deleting, setDeleting] = useState(false);
    const composer = useForm({ body: '', is_internal: false });

    const post = (verb: string, payload: Record<string, string>) => router.post(`/tickets/queue/${ticket.id}/${verb}`, payload, { preserveScroll: true });
    const submitComment = (e: FormEvent) => {
        e.preventDefault();
        composer.post(`/tickets/queue/${ticket.id}/comments`, { preserveScroll: true, onSuccess: () => composer.reset() });
    };

    const details: Array<{ label: string; value: React.ReactNode }> = [
        { label: 'Type', value: <Pill color={ticket.type_color} label={ticket.type_label} /> },
        { label: 'Channel', value: ticket.channel ? <span className="capitalize">{ticket.channel}</span> : '—' },
        { label: 'Client', value: ticket.company || '—' },
        { label: 'Contact', value: ticket.contact || '—' },
        { label: 'Asset', value: ticket.asset ? <Link href={`/assets/registry/${ticket.asset.id}`} className="inline-flex items-center gap-1 text-primary hover:underline"><Cpu className="h-3.5 w-3.5" />{ticket.asset.asset_tag}</Link> : '—' },
        { label: 'Opened', value: when(ticket.opened_at) || '—' },
        { label: 'SLA due', value: ticket.due_at ? <span className={ticket.overdue ? 'font-semibold text-red-600' : 'text-foreground'}>{when(ticket.due_at)}</span> : '—' },
    ];
    if (ticket.type === 'rma') {
        details.push({ label: 'Returned', value: ticket.product?.sku ?? '—' });
        details.push({ label: 'Serial', value: ticket.serial_number || '—' });
        details.push({ label: 'Disposition', value: ticket.rma_disposition ? <span className="capitalize">{ticket.rma_disposition}</span> : '—' });
    }

    return (
        <ServiceDeskLayout>
            <Head title={`${ticket.number} · Service Desk`} />
            <div className="mx-auto max-w-5xl px-4 py-6 sm:px-6">
                <Link href="/tickets/queue" className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" /> Tickets
                </Link>

                <div className="card-surface mb-6 p-6">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div className="flex items-start gap-4">
                            <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-brand-gradient text-white"><LifeBuoy className="h-6 w-6" /></div>
                            <div>
                                <h1 className="text-xl font-bold tracking-tight text-foreground">{ticket.subject}</h1>
                                <div className="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                    <span className="font-mono text-xs">{ticket.number}</span>
                                    <Pill color={ticket.status_color} label={ticket.status_label} />
                                    <Pill color={ticket.priority_color} label={ticket.priority_label} />
                                    {ticket.overdue && <span className="inline-flex items-center gap-1 text-xs font-semibold text-red-600"><AlertTriangle className="h-3.5 w-3.5" /> Overdue</span>}
                                </div>
                            </div>
                        </div>
                        {can.manage && <Button variant="danger" size="sm" icon={Trash2} onClick={() => setDeleting(true)}>Delete</Button>}
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="space-y-4 lg:col-span-2">
                        {ticket.description && (
                            <Card className="p-5">
                                <p className="whitespace-pre-line text-sm text-foreground">{ticket.description}</p>
                            </Card>
                        )}

                        <div className="space-y-3">
                            {comments.length === 0 ? (
                                <p className="px-1 text-sm text-muted-foreground">No replies yet.</p>
                            ) : comments.map(c => (
                                <div key={c.id} className={cn('rounded-xl border p-4', c.is_internal ? 'border-amber-200 bg-amber-50/60 dark:border-amber-900/50 dark:bg-amber-950/20' : 'border-border bg-card')}>
                                    <div className="mb-1.5 flex items-center gap-2">
                                        <span className={cn('flex h-7 w-7 items-center justify-center rounded-full bg-gradient-to-br text-[10px] font-bold text-white', avatarGradient(c.author))}>{getInitials(c.author)}</span>
                                        <span className="text-sm font-medium text-foreground">{c.author}</span>
                                        {c.is_internal && <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase text-amber-700 dark:bg-amber-900/40 dark:text-amber-300"><Lock className="h-3 w-3" /> Internal</span>}
                                        <span className="ml-auto text-xs text-muted-foreground">{when(c.created_at)}</span>
                                    </div>
                                    <p className="whitespace-pre-line text-sm text-foreground">{c.body}</p>
                                </div>
                            ))}
                        </div>

                        {can.comment && (
                            <Card className="p-4">
                                <form onSubmit={submitComment} className="space-y-3">
                                    <textarea className="input min-h-[88px]" placeholder="Write a reply…" value={composer.data.body} onChange={e => composer.setData('body', e.target.value)} />
                                    {composer.errors.body && <p className="text-xs text-destructive">{composer.errors.body}</p>}
                                    <div className="flex items-center justify-between">
                                        <label className="flex items-center gap-2 text-sm text-foreground">
                                            <input type="checkbox" checked={composer.data.is_internal} onChange={e => composer.setData('is_internal', e.target.checked)} /> Internal note
                                        </label>
                                        <Button type="submit" icon={Send} disabled={composer.processing || !composer.data.body.trim()}>{composer.processing ? 'Sending…' : 'Reply'}</Button>
                                    </div>
                                </form>
                            </Card>
                        )}
                    </div>

                    <div className="space-y-4">
                        {can.manage && (
                            <Card className="space-y-3 p-5">
                                <div>
                                    <label className="label">Status</label>
                                    <Select className="w-full" value={ticket.status} onChange={v => v !== ticket.status && post('status', { status: v })} options={statuses} />
                                </div>
                                <div>
                                    <label className="label">Priority</label>
                                    <Select className="w-full" value={ticket.priority} onChange={v => v !== ticket.priority && post('priority', { priority: v })} options={priorities} />
                                </div>
                                <div>
                                    <label className="label">Assignee</label>
                                    <Select className="w-full" value={ticket.assignee_id ? String(ticket.assignee_id) : ''} placeholder="— Unassigned —" onChange={v => post('assign', { assigned_to: v })} options={users.map(u => ({ value: String(u.id), label: u.name }))} />
                                </div>
                            </Card>
                        )}

                        <Card className="p-5">
                            <h2 className="mb-3 text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Details</h2>
                            <dl className="space-y-2.5 text-sm">
                                {details.map(d => (
                                    <div key={d.label} className="flex items-center justify-between gap-3">
                                        <dt className="text-xs text-muted-foreground">{d.label}</dt>
                                        <dd className="text-right text-foreground">{d.value}</dd>
                                    </div>
                                ))}
                            </dl>
                        </Card>

                        {ticket.resolution && (
                            <Card className="p-5">
                                <h2 className="mb-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Resolution</h2>
                                <p className="whitespace-pre-line text-sm text-muted-foreground">{ticket.resolution}</p>
                            </Card>
                        )}
                    </div>
                </div>
            </div>

            <ConfirmDialog open={deleting} onClose={() => setDeleting(false)} onConfirm={() => router.delete(`/tickets/queue/${ticket.id}`)}
                title="Delete ticket?" message={<>This soft-deletes <span className="font-mono font-medium text-foreground">{ticket.number}</span>.</>} />
        </ServiceDeskLayout>
    );
}
