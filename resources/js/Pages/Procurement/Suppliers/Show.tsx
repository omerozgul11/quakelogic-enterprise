import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ProcurementLayout } from '@/Components/layout/ProcurementLayout';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Pill } from '@/Components/ui/Pill';
import { ConfirmDialog } from '@/Components/ui/Modal';
import { SupplierFormModal, EditableSupplier } from '@/Components/procurement/SupplierFormModal';
import { SupplierContactModal, EditableSupplierContact } from '@/Components/procurement/SupplierContactModal';
import { formatCurrency, cn, getInitials, avatarGradient } from '@/Lib/utils';
import { ArrowLeft, Factory, Pencil, Trash2, Plus, Mail, Phone, Globe, MapPin, Star, BadgeCheck, ShoppingCart } from 'lucide-react';

interface Contact extends EditableSupplierContact { id: number; name: string }
interface Supplier extends EditableSupplier {
    id: number; code: string; name: string; status_label: string; status_color: string; contacts: Contact[];
}
interface OrderRow { id: number; number: string; status_label: string; status_color: string; total: number; currency: string; order_date: string | null }

interface Props {
    supplier: Supplier;
    orders: OrderRow[];
    spend: number;
    statuses: { value: string; label: string }[];
    can: { manage: boolean };
}

export default function SupplierShow({ supplier, orders, spend, statuses, can }: Props) {
    const [editOpen, setEditOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [contactOpen, setContactOpen] = useState(false);
    const [editContact, setEditContact] = useState<Contact | null>(null);
    const [delContact, setDelContact] = useState<Contact | null>(null);

    const confirmDelete = () => {
        setProcessing(true);
        router.delete(`/procurement/suppliers/${supplier.id}`, { onFinish: () => setProcessing(false) });
    };
    const openAddContact = () => { setEditContact(null); setContactOpen(true); };
    const confirmDelContact = () => {
        if (!delContact) return;
        router.delete(`/procurement/suppliers/${supplier.id}/contacts/${delContact.id}`, { preserveScroll: true, onFinish: () => setDelContact(null) });
    };

    const details = [
        { icon: Mail, value: supplier.email, href: supplier.email ? `mailto:${supplier.email}` : undefined },
        { icon: Phone, value: supplier.phone, href: supplier.phone ? `tel:${supplier.phone}` : undefined },
        { icon: Globe, value: supplier.website, href: supplier.website ?? undefined },
        { icon: MapPin, value: [supplier.address_line1, supplier.city, supplier.state].filter(Boolean).join(', ') || null },
    ].filter(d => d.value);

    return (
        <ProcurementLayout>
            <Head title={`${supplier.name} · Procurement`} />
            <div className="mx-auto max-w-6xl px-4 py-6 sm:px-6">
                <Link href="/procurement/suppliers" className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" /> Suppliers
                </Link>

                <div className="card-surface mb-6 p-6">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div className="flex items-center gap-4">
                            <div className={cn('flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br text-lg font-bold text-white', avatarGradient(supplier.name))}>
                                {getInitials(supplier.name)}
                            </div>
                            <div>
                                <h1 className="text-2xl font-bold tracking-tight text-foreground">{supplier.name}</h1>
                                <div className="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                    <span className="font-mono text-xs">{supplier.code}</span>
                                    <Pill color={supplier.status_color} label={supplier.status_label} />
                                    {supplier.category && <span>{supplier.category}</span>}
                                    {supplier.payment_terms && <span className="chip">{supplier.payment_terms}</span>}
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

                    <div className="mt-5 grid grid-cols-2 gap-4 border-t border-border pt-4 sm:grid-cols-4">
                        <div><p className="text-xs text-muted-foreground">Total spend</p><p className="mt-0.5 text-lg font-bold text-foreground">{formatCurrency(spend)}</p></div>
                        <div><p className="text-xs text-muted-foreground">Purchase orders</p><p className="mt-0.5 text-lg font-bold text-foreground">{orders.length}</p></div>
                        <div><p className="text-xs text-muted-foreground">Lead time</p><p className="mt-0.5 text-lg font-bold text-foreground">{supplier.lead_time_days != null ? `${supplier.lead_time_days}d` : '—'}</p></div>
                        <div><p className="text-xs text-muted-foreground">Rating</p><p className="mt-0.5 flex items-center gap-1 text-lg font-bold text-foreground">{supplier.rating ?? '—'}{supplier.rating ? <Star className="h-4 w-4 text-amber-500" /> : null}</p></div>
                    </div>

                    {details.length > 0 && (
                        <div className="mt-4 grid grid-cols-1 gap-3 border-t border-border pt-4 sm:grid-cols-2">
                            {details.map((d, i) => { const Icon = d.icon; return (
                                <div key={i} className="flex items-center gap-2.5 text-sm">
                                    <Icon className="h-4 w-4 shrink-0 text-muted-foreground" />
                                    {d.href ? <a href={d.href} className="truncate text-primary hover:underline">{d.value}</a> : <span className="truncate text-foreground">{d.value}</span>}
                                </div>
                            ); })}
                        </div>
                    )}
                    {supplier.notes && <p className="mt-4 whitespace-pre-line border-t border-border pt-4 text-sm text-muted-foreground">{supplier.notes}</p>}
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <Card className="p-5">
                        <div className="mb-3 flex items-center justify-between">
                            <h2 className="text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Contacts</h2>
                            {can.manage && <button onClick={openAddContact} className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"><Plus className="h-3.5 w-3.5" /> Add</button>}
                        </div>
                        {supplier.contacts.length === 0 ? (
                            <p className="py-4 text-sm text-muted-foreground">No contacts yet.</p>
                        ) : (
                            <div className="space-y-2">
                                {supplier.contacts.map(c => (
                                    <div key={c.id} className="flex items-center gap-3 rounded-lg border border-border px-3 py-2">
                                        <span className="min-w-0 flex-1">
                                            <span className="flex items-center gap-1.5 truncate text-sm font-medium text-foreground">
                                                {c.name} {c.is_primary && <BadgeCheck className="h-3.5 w-3.5 text-primary" />}
                                            </span>
                                            <span className="block truncate text-xs text-muted-foreground">{c.title || c.email || c.phone || '—'}</span>
                                        </span>
                                        {can.manage && (
                                            <span className="flex items-center gap-1">
                                                <button onClick={() => { setEditContact(c); setContactOpen(true); }} className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground"><Pencil className="h-3.5 w-3.5" /></button>
                                                <button onClick={() => setDelContact(c)} className="rounded-md p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"><Trash2 className="h-3.5 w-3.5" /></button>
                                            </span>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </Card>

                    <Card className="p-5">
                        <h2 className="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70"><ShoppingCart className="h-4 w-4" /> Purchase orders</h2>
                        {orders.length === 0 ? (
                            <p className="py-4 text-sm text-muted-foreground">No purchase orders yet.</p>
                        ) : (
                            <div className="space-y-1.5">
                                {orders.map(po => (
                                    <Link key={po.id} href={`/procurement/purchase-orders/${po.id}`} className="flex items-center gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-secondary">
                                        <span className="min-w-0 flex-1 truncate text-sm font-medium text-foreground">{po.number}</span>
                                        <span className="text-sm text-muted-foreground">{formatCurrency(po.total, po.currency)}</span>
                                        <Pill color={po.status_color} label={po.status_label} />
                                    </Link>
                                ))}
                            </div>
                        )}
                    </Card>
                </div>
            </div>

            {editOpen && <SupplierFormModal open onClose={() => setEditOpen(false)} supplier={supplier} statuses={statuses} />}
            {contactOpen && <SupplierContactModal key={editContact?.id ?? 'new'} open onClose={() => setContactOpen(false)} supplierId={supplier.id} contact={editContact} />}
            <ConfirmDialog open={deleting} onClose={() => setDeleting(false)} onConfirm={confirmDelete} processing={processing}
                title="Delete supplier?" message={<>This soft-deletes <span className="font-medium text-foreground">{supplier.name}</span>.</>} />
            <ConfirmDialog open={!!delContact} onClose={() => setDelContact(null)} onConfirm={confirmDelContact} confirmLabel="Remove"
                title="Remove contact?" message={delContact ? <>Remove <span className="font-medium text-foreground">{delContact.name}</span>?</> : ''} />
        </ProcurementLayout>
    );
}
