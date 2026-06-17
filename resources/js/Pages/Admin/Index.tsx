import { Head, useForm, router } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/Card';
import { StatCard } from '@/Components/ui/StatCard';
import { Select } from '@/Components/ui/Select';
import { NumberInput } from '@/Components/ui/NumberInput';
import { formatDate, generatePassword } from '@/Lib/utils';
import { Users, Shield, Edit, UserCheck, UserX, X, Plus, Trash2, Wand2, Eye, EyeOff, Copy, Check, Mail } from 'lucide-react';
import { useState } from 'react';

interface MailboxState {
    connected: boolean;
    provider: string | null;
    email: string | null;
    from_name: string | null;
    smtp_host: string | null;
    smtp_port: number | null;
    smtp_encryption: string | null;
    smtp_username: string | null;
}

interface User {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    created_at: string;
    title?: string | null;
    phone?: string | null;
    department?: string | null;
    pipeline_keywords?: string[];
    product_expertise?: string[];
    industry_expertise?: string[];
    geographic_focus?: string[];
    min_opportunity_value?: number | string | null;
    max_opportunity_value?: number | string | null;
    workload_score?: number | null;
    roles: Array<{ id: number; name: string }>;
    mailbox: MailboxState;
}

/** Expertise / opportunity-matching profile fields, shared by create + edit. */
function ProfileFields({ form }: { form: { data: Record<string, unknown>; setData: (key: string, value: unknown) => void } }) {
    const set = (k: string) => (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => form.setData(k, e.target.value);
    const str = (k: string) => (form.data[k] as string | undefined) ?? '';
    return (
        <div className="grid grid-cols-1 gap-4 rounded-xl border border-border bg-secondary/20 p-4 sm:grid-cols-2">
            <p className="sm:col-span-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                Expertise &amp; opportunity matching
            </p>
            <div>
                <label className="label">Job title</label>
                <input type="text" className="input" value={str('title')} onChange={set('title')} placeholder="e.g., Business Development Manager" />
            </div>
            <div>
                <label className="label">Department</label>
                <input type="text" className="input" value={str('department')} onChange={set('department')} placeholder="e.g., Business Development" />
            </div>
            <div className="sm:col-span-2">
                <label className="label">Keywords <span className="font-normal text-muted-foreground">(comma-separated — drives the daily match feed &amp; digest)</span></label>
                <textarea className="input min-h-[56px]" value={str('pipeline_keywords')} onChange={set('pipeline_keywords')} placeholder="Seismic, Shake Table, Nuclear, Structural Health Monitoring" />
            </div>
            <div>
                <label className="label">Product expertise</label>
                <input type="text" className="input" value={str('product_expertise')} onChange={set('product_expertise')} placeholder="Seismographs, SHM Systems" />
            </div>
            <div>
                <label className="label">Industry expertise</label>
                <input type="text" className="input" value={str('industry_expertise')} onChange={set('industry_expertise')} placeholder="Nuclear, Government" />
            </div>
            <div className="sm:col-span-2">
                <label className="label">Geographic focus</label>
                <input type="text" className="input" value={str('geographic_focus')} onChange={set('geographic_focus')} placeholder="United States, International" />
            </div>
            <div>
                <label className="label">Min opportunity value ($)</label>
                <NumberInput value={str('min_opportunity_value')} onChange={set('min_opportunity_value')} className="input" />
            </div>
            <div>
                <label className="label">Max opportunity value ($)</label>
                <NumberInput value={str('max_opportunity_value')} onChange={set('max_opportunity_value')} className="input" />
            </div>
        </div>
    );
}

const SMTP_PRESETS: Record<string, { host: string; port: string; enc: string }> = {
    gmail: { host: 'smtp.gmail.com', port: '587', enc: 'tls' },
    office365: { host: 'smtp.office365.com', port: '587', enc: 'tls' },
};

/**
 * Admin-managed work email for a teammate. Mirrors the self-service form on the
 * Settings page so an admin can connect, test or disconnect a user's SMTP
 * mailbox on their behalf. Keyed by user id so switching users resets the form.
 */
function AdminMailbox({ user }: { user: User }) {
    const mailbox = user.mailbox;
    const form = useForm({
        email: mailbox.email ?? user.email,
        from_name: mailbox.from_name ?? user.name,
        smtp_host: mailbox.smtp_host ?? '',
        smtp_port: mailbox.smtp_port ? String(mailbox.smtp_port) : '587',
        smtp_encryption: mailbox.smtp_encryption ?? 'tls',
        smtp_username: mailbox.smtp_username ?? '',
        smtp_password: '',
    });

    const applyPreset = (k: string) => {
        const p = SMTP_PRESETS[k];
        if (p) form.setData(d => ({ ...d, smtp_host: p.host, smtp_port: p.port, smtp_encryption: p.enc }));
    };
    const save = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(`/admin/users/${user.id}/mailbox`, { preserveScroll: true, onSuccess: () => form.setData('smtp_password', '') });
    };
    const test = () => router.post(`/admin/users/${user.id}/mailbox/test`, {}, { preserveScroll: true });
    const disconnect = () => {
        if (confirm(`Disconnect ${user.name}'s work email?`)) router.delete(`/admin/users/${user.id}/mailbox`, { preserveScroll: true });
    };

    return (
        <Card className="animate-panel origin-top mb-6 ring-1 ring-primary/20">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Mail className="h-5 w-5 text-muted-foreground" /> Work email — {user.name}
                    {mailbox.connected && <span className="rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs font-semibold text-emerald-600">Connected</span>}
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                <p className="text-sm text-muted-foreground">
                    Connect this teammate's outgoing email so their follow-ups and daily digest send from their own address.
                    {mailbox.connected && <> Currently connected as <span className="font-semibold">{mailbox.email}</span>.</>}
                </p>

                <div className="flex flex-wrap gap-2">
                    <span className="self-center text-xs text-muted-foreground">Quick setup:</span>
                    <button type="button" onClick={() => applyPreset('gmail')} className="rounded-full border border-border px-3 py-1 text-xs font-medium transition hover:bg-secondary">Gmail / Workspace</button>
                    <button type="button" onClick={() => applyPreset('office365')} className="rounded-full border border-border px-3 py-1 text-xs font-medium transition hover:bg-secondary">Microsoft 365</button>
                </div>

                <form onSubmit={save} className="space-y-4">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label className="label">From address</label>
                            <input type="email" value={form.data.email} onChange={e => form.setData('email', e.target.value)} className="input" required />
                            {form.errors.email && <p className="mt-1 text-xs text-destructive">{form.errors.email}</p>}
                        </div>
                        <div>
                            <label className="label">From name</label>
                            <input type="text" value={form.data.from_name} onChange={e => form.setData('from_name', e.target.value)} className="input" />
                        </div>
                        <div>
                            <label className="label">SMTP host</label>
                            <input type="text" value={form.data.smtp_host} onChange={e => form.setData('smtp_host', e.target.value)} className="input" placeholder="smtp.gmail.com" required />
                            {form.errors.smtp_host && <p className="mt-1 text-xs text-destructive">{form.errors.smtp_host}</p>}
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="label">Port</label>
                                <NumberInput allowDecimal={false} value={form.data.smtp_port} onChange={e => form.setData('smtp_port', e.target.value)} className="input" required />
                            </div>
                            <div>
                                <label className="label">Encryption</label>
                                <Select
                                    value={form.data.smtp_encryption}
                                    onChange={v => form.setData('smtp_encryption', v)}
                                    options={[
                                        { value: 'tls', label: 'TLS / STARTTLS' },
                                        { value: 'ssl', label: 'SSL' },
                                        { value: 'none', label: 'None' },
                                    ]}
                                    className="w-full"
                                />
                            </div>
                        </div>
                        <div>
                            <label className="label">Username</label>
                            <input type="text" value={form.data.smtp_username} onChange={e => form.setData('smtp_username', e.target.value)} className="input" placeholder={form.data.email} />
                        </div>
                        <div>
                            <label className="label">App password {mailbox.connected ? <span className="font-normal text-muted-foreground">(leave blank to keep current)</span> : '*'}</label>
                            <input type="password" value={form.data.smtp_password} onChange={e => form.setData('smtp_password', e.target.value)} className="input" autoComplete="new-password" placeholder="••••••••••••••••" />
                            {form.errors.smtp_password && <p className="mt-1 text-xs text-destructive">{form.errors.smtp_password}</p>}
                        </div>
                    </div>

                    <p className="text-xs text-muted-foreground">Use an app password (not the account login password). The user can change this later under their own Settings.</p>

                    <div className="flex flex-wrap gap-2">
                        <Button type="submit" disabled={form.processing}>{form.processing ? 'Saving…' : mailbox.connected ? 'Update' : 'Connect'}</Button>
                        {mailbox.connected && <Button type="button" variant="secondary" onClick={test}>Send test email</Button>}
                        {mailbox.connected && <Button type="button" variant="danger" onClick={disconnect}>Disconnect</Button>}
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

interface Props {
    users: { data: User[]; total: number };
    roles: Array<{ id: number; name: string }>;
    auth: { user: { id: number } };
}

const CUSTOM = '__custom__';

export default function AdminIndex({ users, roles }: Props) {
    const [editingUser, setEditingUser] = useState<User | null>(null);
    const [showCreate, setShowCreate] = useState(false);
    const [showPassword, setShowPassword] = useState(false);
    const [copied, setCopied] = useState(false);

    const defaultBase = roles.find(r => r.name === 'Read Only')?.name ?? roles[0]?.name ?? '';

    const profileDefaults = {
        title: '', phone: '', department: '',
        pipeline_keywords: '', product_expertise: '', industry_expertise: '', geographic_focus: '',
        min_opportunity_value: '', max_opportunity_value: '',
    };
    const csv = (a?: string[]) => (a ?? []).join(', ');
    const num = (v?: number | string | null) => (v === null || v === undefined ? '' : String(v));

    const edit = useForm({ name: '', is_active: true, role: '', custom_role: '', base_role: defaultBase, ...profileDefaults });
    const create = useForm({ name: '', email: '', password: '', password_confirmation: '', role: roles[0]?.name ?? '', custom_role: '', base_role: defaultBase, ...profileDefaults });

    const startEdit = (user: User) => {
        setShowCreate(false);
        setEditingUser(user);
        edit.setData({
            name: user.name,
            is_active: user.is_active,
            role: user.roles[0]?.name ?? '',
            custom_role: '',
            base_role: defaultBase,
            title: user.title ?? '',
            phone: user.phone ?? '',
            department: user.department ?? '',
            pipeline_keywords: csv(user.pipeline_keywords),
            product_expertise: csv(user.product_expertise),
            industry_expertise: csv(user.industry_expertise),
            geographic_focus: csv(user.geographic_focus),
            min_opportunity_value: num(user.min_opportunity_value),
            max_opportunity_value: num(user.max_opportunity_value),
        });
    };

    const handleSave = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingUser) return;
        edit.patch(`/admin/users/${editingUser.id}`, {
            onSuccess: () => { setEditingUser(null); edit.reset(); },
        });
    };

    const handleCreate = (e: React.FormEvent) => {
        e.preventDefault();
        create.post('/admin/users', {
            onSuccess: () => { setShowCreate(false); create.reset(); setShowPassword(false); },
        });
    };

    const generateForUser = () => {
        const pw = generatePassword(16);
        create.setData(d => ({ ...d, password: pw, password_confirmation: pw }));
        setShowPassword(true);
    };

    const copyPassword = async () => {
        if (!create.data.password) return;
        try {
            await navigator.clipboard.writeText(create.data.password);
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        } catch {
            /* clipboard unavailable — the admin can still read the revealed value */
        }
    };

    const handleDelete = (user: User) => {
        if (confirm(`Delete ${user.name} (${user.email})? This cannot be undone.`)) {
            router.delete(`/admin/users/${user.id}`, { preserveScroll: true });
        }
    };

    const activeCount = users.data.filter(u => u.is_active).length;
    const disabledCount = users.data.filter(u => !u.is_active).length;

    return (
        <AppLayout>
            <Head title="Admin — User Management" />
            <div className="p-6">
                <PageHeader
                    icon={Shield}
                    title="Admin Panel"
                    description={`${users.total} ${users.total === 1 ? 'user' : 'users'} in your organization`}
                    actions={
                        <Button icon={Plus} onClick={() => { setEditingUser(null); setShowCreate(v => !v); }}>
                            Add User
                        </Button>
                    }
                />

                <div className="stagger mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <StatCard title="Total Users" value={users.total} icon={Users} tone="indigo" />
                    <StatCard title="Active" value={activeCount} icon={UserCheck} tone="emerald" />
                    <StatCard title="Disabled" value={disabledCount} icon={UserX} tone="rose" />
                </div>

                {/* Create user */}
                {showCreate && (
                    <form onSubmit={handleCreate}>
                        <Card className="animate-panel origin-top mb-6 ring-1 ring-primary/30">
                            <CardHeader>
                                <CardTitle>Add a new user</CardTitle>
                                <button type="button" onClick={() => setShowCreate(false)} className="text-muted-foreground transition-colors hover:text-foreground">
                                    <X className="h-4 w-4" />
                                </button>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <label className="label">Full name</label>
                                        <input type="text" value={create.data.name} onChange={e => create.setData('name', e.target.value)} className="input" required />
                                        {create.errors.name && <p className="mt-1 text-sm text-destructive">{create.errors.name}</p>}
                                    </div>
                                    <div>
                                        <label className="label">Email</label>
                                        <input type="email" value={create.data.email} onChange={e => create.setData('email', e.target.value)} className="input" required />
                                        {create.errors.email && <p className="mt-1 text-sm text-destructive">{create.errors.email}</p>}
                                    </div>
                                    <div>
                                        <div className="flex items-center justify-between">
                                            <label className="label">Password</label>
                                            <button type="button" onClick={generateForUser} className="mb-1.5 inline-flex items-center gap-1 text-xs font-semibold text-primary transition-colors hover:text-primary/80">
                                                <Wand2 className="h-3.5 w-3.5" /> Generate
                                            </button>
                                        </div>
                                        <div className="relative">
                                            <input
                                                type={showPassword ? 'text' : 'password'}
                                                value={create.data.password}
                                                onChange={e => create.setData('password', e.target.value)}
                                                className="input pr-16 font-mono"
                                                required
                                                autoComplete="new-password"
                                            />
                                            <div className="absolute inset-y-0 right-2 flex items-center gap-1.5">
                                                <button type="button" onClick={() => setShowPassword(v => !v)} title={showPassword ? 'Hide' : 'Show'} className="text-muted-foreground transition-colors hover:text-foreground">
                                                    {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                                </button>
                                                <button type="button" onClick={copyPassword} disabled={!create.data.password} title="Copy" className="text-muted-foreground transition-colors hover:text-primary disabled:opacity-40">
                                                    {copied ? <Check className="h-4 w-4 text-emerald-500" /> : <Copy className="h-4 w-4" />}
                                                </button>
                                            </div>
                                        </div>
                                        {create.errors.password && <p className="mt-1 text-sm text-destructive">{create.errors.password}</p>}
                                    </div>
                                    <div>
                                        <label className="label">Confirm password</label>
                                        <input type={showPassword ? 'text' : 'password'} value={create.data.password_confirmation} onChange={e => create.setData('password_confirmation', e.target.value)} className="input font-mono" required autoComplete="new-password" />
                                    </div>
                                    <div>
                                        <label className="label">Role</label>
                                        <Select
                                            value={create.data.role}
                                            onChange={v => create.setData('role', v)}
                                            placeholder="Select a role…"
                                            options={[...roles.map(r => ({ value: r.name, label: r.name })), { value: CUSTOM, label: '➕ Create custom role…' }]}
                                            className="w-full"
                                        />
                                        {create.errors.role && <p className="mt-1 text-sm text-destructive">{create.errors.role}</p>}
                                    </div>
                                </div>

                                {create.data.role === CUSTOM && (
                                    <div className="grid grid-cols-1 gap-4 rounded-xl border border-primary/30 bg-primary/[0.03] p-4 sm:grid-cols-2">
                                        <div>
                                            <label className="label">Custom role name</label>
                                            <input type="text" value={create.data.custom_role} onChange={e => create.setData('custom_role', e.target.value)} className="input" placeholder="e.g., Regional Director" />
                                            {create.errors.custom_role && <p className="mt-1 text-sm text-destructive">{create.errors.custom_role}</p>}
                                        </div>
                                        <div>
                                            <label className="label">Copy permissions from</label>
                                            <Select
                                                value={create.data.base_role}
                                                onChange={v => create.setData('base_role', v)}
                                                options={roles.map(r => ({ value: r.name, label: r.name }))}
                                                className="w-full"
                                            />
                                            <p className="mt-1 text-xs text-muted-foreground">The new role starts with this role’s access. You can reuse it for other users afterwards.</p>
                                        </div>
                                    </div>
                                )}

                                <ProfileFields form={create} />

                                <p className="text-xs text-muted-foreground">The user can change their own name, email and password later under Settings.</p>
                                <div className="flex gap-2">
                                    <Button type="submit" disabled={create.processing}>{create.processing ? 'Creating…' : 'Create user'}</Button>
                                    <Button type="button" variant="secondary" onClick={() => setShowCreate(false)}>Cancel</Button>
                                </div>
                            </CardContent>
                        </Card>
                    </form>
                )}

                {/* Edit user */}
                {editingUser && (
                    <form onSubmit={handleSave}>
                        <Card className="animate-panel origin-top mb-6 ring-1 ring-primary/30">
                            <CardHeader>
                                <CardTitle>Edit user: {editingUser.email}</CardTitle>
                                <button type="button" onClick={() => setEditingUser(null)} className="text-muted-foreground transition-colors hover:text-foreground">
                                    <X className="h-4 w-4" />
                                </button>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div>
                                        <label className="label">Name</label>
                                        <input type="text" value={edit.data.name} onChange={e => edit.setData('name', e.target.value)} className="input" required />
                                    </div>
                                    <div>
                                        <label className="label">Role</label>
                                        <Select
                                            value={edit.data.role}
                                            onChange={v => edit.setData('role', v)}
                                            options={[...roles.map(r => ({ value: r.name, label: r.name })), { value: CUSTOM, label: '➕ Create custom role…' }]}
                                            className="w-full"
                                        />
                                        {edit.errors.role && <p className="mt-1 text-sm text-destructive">{edit.errors.role}</p>}
                                    </div>
                                    <div>
                                        <label className="label">Active</label>
                                        <Select
                                            value={edit.data.is_active ? '1' : '0'}
                                            onChange={v => edit.setData('is_active', v === '1')}
                                            options={[
                                                { value: '1', label: 'Active' },
                                                { value: '0', label: 'Disabled' },
                                            ]}
                                            className="w-full"
                                        />
                                    </div>
                                </div>

                                {edit.data.role === CUSTOM && (
                                    <div className="grid grid-cols-1 gap-4 rounded-xl border border-primary/30 bg-primary/[0.03] p-4 sm:grid-cols-2">
                                        <div>
                                            <label className="label">Custom role name</label>
                                            <input type="text" value={edit.data.custom_role} onChange={e => edit.setData('custom_role', e.target.value)} className="input" placeholder="e.g., Regional Director" />
                                            {edit.errors.custom_role && <p className="mt-1 text-sm text-destructive">{edit.errors.custom_role}</p>}
                                        </div>
                                        <div>
                                            <label className="label">Copy permissions from</label>
                                            <Select
                                                value={edit.data.base_role}
                                                onChange={v => edit.setData('base_role', v)}
                                                options={roles.map(r => ({ value: r.name, label: r.name }))}
                                                className="w-full"
                                            />
                                            <p className="mt-1 text-xs text-muted-foreground">The new role starts with this role’s access.</p>
                                        </div>
                                    </div>
                                )}

                                <ProfileFields form={edit} />

                                <div className="flex gap-2">
                                    <Button type="submit" disabled={edit.processing}>Save</Button>
                                    <Button type="button" variant="secondary" onClick={() => setEditingUser(null)}>Cancel</Button>
                                </div>
                            </CardContent>
                        </Card>
                    </form>
                )}

                {editingUser && <AdminMailbox key={editingUser.id} user={editingUser} />}

                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b border-border bg-secondary/40">
                                <tr>
                                    <th className="th">Name</th>
                                    <th className="th">Email</th>
                                    <th className="th">Role</th>
                                    <th className="th">Status</th>
                                    <th className="th">Joined</th>
                                    <th className="th" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {users.data.map(user => (
                                    <tr key={user.id} className="row-link">
                                        <td className="td font-medium text-foreground">
                                            {user.name}
                                            {(user.title || user.department) && (
                                                <div className="text-xs font-normal text-muted-foreground">
                                                    {[user.title, user.department].filter(Boolean).join(' · ')}
                                                </div>
                                            )}
                                        </td>
                                        <td className="td text-muted-foreground">
                                            <span className="inline-flex items-center gap-1.5">
                                                {user.email}
                                                {user.mailbox?.connected && (
                                                    <Mail className="h-3.5 w-3.5 text-emerald-500" aria-label="Work email connected" />
                                                )}
                                            </span>
                                        </td>
                                        <td className="td">
                                            <div className="flex flex-wrap gap-1">
                                                {user.roles.map(r => (
                                                    <span key={r.id} className="chip">{r.name}</span>
                                                ))}
                                            </div>
                                        </td>
                                        <td className="td">
                                            <span className={`inline-flex items-center gap-1.5 text-xs font-medium ${user.is_active ? 'text-emerald-600' : 'text-destructive'}`}>
                                                <span className={`h-2 w-2 rounded-full ${user.is_active ? 'bg-emerald-500' : 'bg-destructive'}`} />
                                                {user.is_active ? 'Active' : 'Disabled'}
                                            </span>
                                        </td>
                                        <td className="td text-muted-foreground">{formatDate(user.created_at)}</td>
                                        <td className="td">
                                            <div className="flex items-center gap-2">
                                                <button onClick={() => startEdit(user)} title="Edit" className="text-muted-foreground transition-colors hover:text-primary">
                                                    <Edit className="h-4 w-4" />
                                                </button>
                                                <button onClick={() => handleDelete(user)} title="Delete" className="text-muted-foreground transition-colors hover:text-destructive">
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>
        </AppLayout>
    );
}
