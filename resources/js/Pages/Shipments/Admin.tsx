import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { ShieldCheck, Search, Lock } from 'lucide-react';
import { ShipmentsLayout } from '@/Components/layout/ShipmentsLayout';
import { cn, getInitials, avatarGradient } from '@/Lib/utils';

interface UserRow {
    id: number;
    name: string;
    email: string;
    role: string;
    is_admin: boolean;
    is_active: boolean;
    has_access: boolean;
}

function Toggle({ on, disabled, onChange }: { on: boolean; disabled?: boolean; onChange: (v: boolean) => void }) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={on}
            disabled={disabled}
            onClick={() => !disabled && onChange(!on)}
            className={cn(
                'relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors',
                on ? 'bg-brand-gradient' : 'bg-input',
                disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer',
            )}
        >
            <span className={cn('inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform', on ? 'translate-x-[22px]' : 'translate-x-0.5')} />
        </button>
    );
}

export default function ShipmentsAdmin({ users }: { users: UserRow[] }) {
    const [q, setQ] = useState('');
    const [pending, setPending] = useState<number | null>(null);

    const filtered = users.filter(u =>
        !q || u.name.toLowerCase().includes(q.toLowerCase()) || u.email.toLowerCase().includes(q.toLowerCase()),
    );
    const withAccess = users.filter(u => u.has_access).length;

    const setAccess = (user: UserRow, grant: boolean) => {
        if (user.is_admin) return;
        setPending(user.id);
        router.post(`/shipments/admin/users/${user.id}`, { grant }, {
            preserveScroll: true,
            onFinish: () => setPending(null),
        });
    };

    return (
        <ShipmentsLayout>
            <Head title="Shipments access" />
            <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6">
                <div className="mb-6 flex items-center gap-3">
                    <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-gradient text-white"><ShieldCheck className="h-5 w-5" /></span>
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-foreground">Shipments access</h1>
                        <p className="mt-0.5 text-sm text-muted-foreground">
                            Control who can use Shipments. This changes only their Shipments access — never their Proposals access.
                        </p>
                    </div>
                </div>

                <div className="mb-4 flex items-center justify-between gap-3">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <input
                            value={q}
                            onChange={e => setQ(e.target.value)}
                            placeholder="Search people…"
                            className="input input-with-icon"
                        />
                    </div>
                    <p className="shrink-0 text-sm text-muted-foreground"><span className="font-semibold text-foreground">{withAccess}</span> with access</p>
                </div>

                <div className="overflow-hidden rounded-xl border border-border bg-card shadow-soft">
                    {filtered.map(u => (
                        <div key={u.id} className="flex items-center gap-4 border-b border-border px-4 py-3 last:border-0">
                            <span className={cn('flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br text-xs font-bold text-white', avatarGradient(u.name))}>
                                {getInitials(u.name)}
                            </span>
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-medium text-foreground">
                                    {u.name}
                                    {!u.is_active && <span className="ml-2 rounded-full bg-secondary px-1.5 py-0.5 text-[10px] font-semibold text-muted-foreground">Inactive</span>}
                                </p>
                                <p className="truncate text-xs text-muted-foreground">{u.email} · {u.role}</p>
                            </div>
                            {u.is_admin ? (
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary" title="Admins always have access">
                                    <Lock className="h-3 w-3" /> Admin
                                </span>
                            ) : (
                                <span className={cn('text-xs font-medium', pending === u.id ? 'text-muted-foreground' : u.has_access ? 'text-emerald-600' : 'text-muted-foreground')}>
                                    {u.has_access ? 'Has access' : 'No access'}
                                </span>
                            )}
                            <Toggle on={u.has_access} disabled={u.is_admin || pending === u.id} onChange={v => setAccess(u, v)} />
                        </div>
                    ))}
                    {filtered.length === 0 && (
                        <div className="px-6 py-12 text-center text-sm text-muted-foreground">No people match “{q}”.</div>
                    )}
                </div>
            </div>
        </ShipmentsLayout>
    );
}
