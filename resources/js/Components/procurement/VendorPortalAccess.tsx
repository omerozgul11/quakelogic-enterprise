import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { KeyRound, ShieldCheck, ShieldOff } from 'lucide-react';

interface Contact {
    id: number;
    email: string | null;
    portal_enabled: boolean;
    portal_last_login_at?: string | null;
}

interface Props {
    supplierId: number;
    contact: Contact;
}

/**
 * Staff control to grant/revoke a supplier contact's vendor-portal login and
 * set their password. Posts to the supplier contact's portal endpoint.
 */
export function VendorPortalAccess({ supplierId, contact }: Props) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ enabled: boolean; password: string }>({ enabled: true, password: '' });
    const url = `/procurement/suppliers/${supplierId}/contacts/${contact.id}/portal`;

    const enable = () => {
        form.transform(() => ({ enabled: true, password: form.data.password }));
        form.post(url, { preserveScroll: true, onSuccess: () => { setOpen(false); form.reset('password'); } });
    };
    const disable = () => {
        form.transform(() => ({ enabled: false, password: '' }));
        form.post(url, { preserveScroll: true });
    };

    if (!contact.email) {
        return <p className="mt-1 text-xs text-muted-foreground/70">Add an email to enable portal access.</p>;
    }

    return (
        <div className="mt-1.5">
            {contact.portal_enabled ? (
                <div className="flex flex-wrap items-center gap-2 text-xs">
                    <span className="inline-flex items-center gap-1 font-medium text-emerald-600"><ShieldCheck className="h-3.5 w-3.5" /> Portal enabled</span>
                    {contact.portal_last_login_at && <span className="text-muted-foreground">· last in {new Date(contact.portal_last_login_at).toLocaleDateString()}</span>}
                    <button type="button" onClick={() => setOpen(o => !o)} className="text-primary hover:underline">Reset password</button>
                    <button type="button" onClick={disable} disabled={form.processing} className="inline-flex items-center gap-1 text-muted-foreground hover:text-destructive"><ShieldOff className="h-3.5 w-3.5" /> Disable</button>
                </div>
            ) : (
                <button type="button" onClick={() => setOpen(o => !o)} className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"><KeyRound className="h-3.5 w-3.5" /> Enable portal access</button>
            )}

            {open && (
                <div className="mt-2 flex flex-wrap items-center gap-2">
                    <input
                        type="password"
                        className="input h-8 max-w-[220px] text-xs"
                        placeholder="New portal password (min 8)"
                        value={form.data.password}
                        onChange={e => form.setData('password', e.target.value)}
                    />
                    <button type="button" onClick={enable} disabled={form.processing || form.data.password.length < 8}
                        className="rounded-md bg-primary px-2.5 py-1 text-xs font-medium text-primary-foreground disabled:opacity-50">
                        {contact.portal_enabled ? 'Update' : 'Enable'}
                    </button>
                </div>
            )}
            {form.errors.password && <p className="mt-1 text-xs text-destructive">{form.errors.password}</p>}
        </div>
    );
}
