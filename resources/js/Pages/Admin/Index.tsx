import { Head, useForm, router } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/Card';
import { StatCard } from '@/Components/ui/StatCard';
import { Select } from '@/Components/ui/Select';
import { formatDate, generatePassword } from '@/Lib/utils';
import { Users, Shield, Edit, UserCheck, UserX, X, Plus, Trash2, Wand2, Eye, EyeOff, Copy, Check } from 'lucide-react';
import { useState } from 'react';

interface User {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    created_at: string;
    roles: Array<{ id: number; name: string }>;
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

    const edit = useForm({ name: '', is_active: true, role: '', custom_role: '', base_role: defaultBase });
    const create = useForm({ name: '', email: '', password: '', password_confirmation: '', role: roles[0]?.name ?? '', custom_role: '', base_role: defaultBase });

    const startEdit = (user: User) => {
        setShowCreate(false);
        setEditingUser(user);
        edit.setData({ name: user.name, is_active: user.is_active, role: user.roles[0]?.name ?? '', custom_role: '', base_role: defaultBase });
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
                        <Card className="mb-6 ring-1 ring-primary/30">
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
                        <Card className="mb-6 ring-1 ring-primary/30">
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

                                <div className="flex gap-2">
                                    <Button type="submit" disabled={edit.processing}>Save</Button>
                                    <Button type="button" variant="secondary" onClick={() => setEditingUser(null)}>Cancel</Button>
                                </div>
                            </CardContent>
                        </Card>
                    </form>
                )}

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
                                        <td className="td font-medium text-foreground">{user.name}</td>
                                        <td className="td text-muted-foreground">{user.email}</td>
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
