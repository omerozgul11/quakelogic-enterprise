import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { CrmLayout } from '@/Components/layout/CrmLayout';
import { Button } from '@/Components/ui/Button';
import { Pill } from '@/Components/ui/Pill';
import { ConfirmDialog } from '@/Components/ui/Modal';
import { CrmClientFormModal } from '@/Components/crm/CrmClientFormModal';
import { cn, getInitials, avatarGradient, formatCurrency } from '@/Lib/utils';
import { ArrowLeft, Building2, Globe, Mail, Phone, MapPin, Pencil, Trash2, Plus, Target, FolderKanban, ReceiptText, FileText, Truck, Briefcase, Star, BadgeCheck } from 'lucide-react';

interface ContactRow { id: number; first_name: string; last_name: string; title?: string | null; email?: string | null; phone?: string | null; is_decision_maker: boolean; is_key_contact: boolean }
interface LeadRow { id: number; title: string; value: number; status_label: string; status_color: string }
interface ProjectRow { id: number; name: string; progress: number; status_label: string; status_color: string }
interface InvoiceRow { id: number; number: string; kind: string; total: number; currency: string; status_label: string; status_color: string }

interface Client {
    id: number;
    name: string;
    company_type?: string | null;
    industry?: string | null;
    email?: string | null;
    phone?: string | null;
    website?: string | null;
    address_line1?: string | null;
    city?: string | null;
    state?: string | null;
    cage_code?: string | null;
    notes?: string | null;
    contacts: ContactRow[];
}

interface ProposalRow { id: number; number: string; name: string; value: number; status_label: string; status_color: string }
interface OpportunityRow { id: number; title: string; value: number; status_label: string; status_color: string }
interface ShipmentRow { id: number; ulid: string; tracking: string | null; recipient: string | null; status_label: string; status_color: string }

interface Props {
    client: Client;
    leads: LeadRow[];
    projects: ProjectRow[];
    invoices: InvoiceRow[];
    proposals: ProposalRow[];
    opportunities: OpportunityRow[];
    shipments: ShipmentRow[];
    can: { manage: boolean };
}

export default function ClientShow({ client, leads, projects, invoices, proposals, opportunities, shipments, can }: Props) {
    const [editOpen, setEditOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [processing, setProcessing] = useState(false);

    const confirmDelete = () => {
        setProcessing(true);
        router.delete(`/crm/clients/${client.id}`, { onFinish: () => setProcessing(false) });
    };

    const details: Array<{ icon: React.ComponentType<{ className?: string }>; value: string | null | undefined; href?: string }> = [
        { icon: Globe, value: client.website, href: client.website ?? undefined },
        { icon: Mail, value: client.email, href: client.email ? `mailto:${client.email}` : undefined },
        { icon: Phone, value: client.phone, href: client.phone ? `tel:${client.phone}` : undefined },
        { icon: MapPin, value: [client.address_line1, client.city, client.state].filter(Boolean).join(', ') || null },
    ];

    return (
        <CrmLayout>
            <Head title={`${client.name} · CRM`} />
            <div className="mx-auto max-w-6xl px-4 py-6 sm:px-6">
                <Link href="/crm/clients" className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" /> Clients
                </Link>

                <div className="card-surface mb-6 p-6">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div className="flex items-center gap-4">
                            <div className={cn('flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br text-lg font-bold text-white', avatarGradient(client.name))}>
                                {getInitials(client.name)}
                            </div>
                            <div>
                                <h1 className="text-2xl font-bold tracking-tight text-foreground">{client.name}</h1>
                                <div className="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                    {client.company_type && <span className="chip capitalize">{client.company_type.replace(/_/g, ' ')}</span>}
                                    {client.industry && <span>{client.industry}</span>}
                                    {client.cage_code && <span className="font-mono text-xs">CAGE {client.cage_code}</span>}
                                </div>
                            </div>
                        </div>
                        {can.manage && (
                            <div className="flex items-center gap-2">
                                <Button variant="secondary" icon={Pencil} onClick={() => setEditOpen(true)}>Edit</Button>
                                <Button variant="danger" icon={Trash2} onClick={() => setDeleting(true)}>Delete</Button>
                            </div>
                        )}
                    </div>

                    <div className="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        {details.filter(d => d.value).map((d, i) => {
                            const Icon = d.icon;
                            return (
                                <div key={i} className="flex items-center gap-2.5 text-sm">
                                    <Icon className="h-4 w-4 shrink-0 text-muted-foreground" />
                                    {d.href ? (
                                        <a href={d.href} target={d.icon === Globe ? '_blank' : undefined} rel="noreferrer" className="truncate text-primary hover:underline">{d.value}</a>
                                    ) : (
                                        <span className="truncate text-foreground">{d.value}</span>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                    {client.notes && <p className="mt-4 whitespace-pre-line border-t border-border pt-4 text-sm text-muted-foreground">{client.notes}</p>}
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Contacts */}
                    <section className="card-surface p-5 lg:col-span-2">
                        <div className="mb-3 flex items-center justify-between">
                            <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70"><Building2 className="h-4 w-4" /> Contacts ({client.contacts.length})</h2>
                            <Link href="/crm/contacts" className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"><Plus className="h-3.5 w-3.5" /> Manage</Link>
                        </div>
                        {client.contacts.length === 0 ? (
                            <p className="py-4 text-sm text-muted-foreground">No contacts for this client yet.</p>
                        ) : (
                            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                {client.contacts.map(c => (
                                    <div key={c.id} className="flex items-center gap-3 rounded-lg border border-border px-3 py-2">
                                        <span className={cn('flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br text-[11px] font-bold text-white', avatarGradient(`${c.first_name} ${c.last_name}`))}>
                                            {getInitials(`${c.first_name} ${c.last_name}`)}
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="flex items-center gap-1.5 truncate text-sm font-medium text-foreground">
                                                {c.first_name} {c.last_name}
                                                {c.is_decision_maker && <BadgeCheck className="h-3.5 w-3.5 text-primary" />}
                                                {c.is_key_contact && <Star className="h-3.5 w-3.5 text-amber-500" />}
                                            </span>
                                            <span className="block truncate text-xs text-muted-foreground">{c.title || c.email || '—'}</span>
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </section>

                    <RelatedList title="Leads" icon={Target} empty="No leads." items={leads.map(l => ({
                        id: l.id, href: '/crm/leads', primary: l.title, secondary: formatCurrency(l.value), color: l.status_color, label: l.status_label,
                    }))} />
                    <RelatedList title="Projects" icon={FolderKanban} empty="No projects." items={projects.map(p => ({
                        id: p.id, href: `/projects/${p.id}`, primary: p.name, secondary: `${p.progress}%`, color: p.status_color, label: p.status_label,
                    }))} />
                    <RelatedList title="Invoices & estimates" icon={ReceiptText} empty="No billing yet." className="lg:col-span-2" items={invoices.map(i => ({
                        id: i.id, href: `/crm/invoices/${i.id}`, primary: i.number, secondary: formatCurrency(i.total, i.currency), color: i.status_color, label: i.status_label,
                    }))} />

                    {/* Cross-platform: this client's records in Proposals & Shipments */}
                    <RelatedList title="Proposals" icon={FileText} empty="No proposals linked to this client yet." items={proposals.map(p => ({
                        id: p.id, href: `/proposals/${p.id}`, primary: `${p.number} · ${p.name}`, secondary: formatCurrency(p.value), color: p.status_color, label: p.status_label,
                    }))} />
                    <RelatedList title="Opportunities" icon={Briefcase} empty="No opportunities linked." items={opportunities.map(o => ({
                        id: o.id, href: `/opportunities/${o.id}`, primary: o.title, secondary: formatCurrency(o.value), color: o.status_color, label: o.status_label,
                    }))} />
                    <RelatedList title="Shipments" icon={Truck} empty="No shipments for this client's proposals." className="lg:col-span-2" items={shipments.map(s => ({
                        id: s.id, href: `/shipments/mailings/${s.ulid}`, primary: s.recipient || s.tracking || '—', secondary: s.tracking || '', color: s.status_color, label: s.status_label,
                    }))} />
                </div>
            </div>

            {editOpen && <CrmClientFormModal open onClose={() => setEditOpen(false)} client={client} />}
            <ConfirmDialog
                open={deleting}
                onClose={() => setDeleting(false)}
                onConfirm={confirmDelete}
                processing={processing}
                title="Delete client?"
                message={<>This removes <span className="font-medium text-foreground">{client.name}</span> from your CRM.</>}
            />
        </CrmLayout>
    );
}

interface RelatedItem { id: number; href: string; primary: string; secondary: string; color: string; label: string }

function RelatedList({ title, icon: Icon, items, empty, className }: { title: string; icon: React.ComponentType<{ className?: string }>; items: RelatedItem[]; empty: string; className?: string }) {
    return (
        <section className={cn('card-surface p-5', className)}>
            <h2 className="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70"><Icon className="h-4 w-4" /> {title}</h2>
            {items.length === 0 ? (
                <p className="py-4 text-sm text-muted-foreground">{empty}</p>
            ) : (
                <div className="space-y-1.5">
                    {items.map(it => (
                        <Link key={it.id} href={it.href} className="flex items-center gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-secondary">
                            <span className="min-w-0 flex-1 truncate text-sm font-medium text-foreground">{it.primary}</span>
                            <span className="text-xs text-muted-foreground">{it.secondary}</span>
                            <Pill color={it.color} label={it.label} />
                        </Link>
                    ))}
                </div>
            )}
        </section>
    );
}
