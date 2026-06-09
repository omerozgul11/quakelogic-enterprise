import { Head, useForm, router } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/Card';
import { StatCard } from '@/Components/ui/StatCard';
import { formatDate } from '@/Lib/utils';
import { Users, Shield, Edit, UserCheck, UserX, X, Plus, Trash2 } from 'lucide-react';
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

export default function AdminIndex({ users, roles }: Props) {
    const [editingUser, setEditingUser] = useState<User | null>(null);
    const [showCreate, setShowCreate] = useState(false);

    const edit = useForm({ name: '', is_active: true, role: '' });
    const create = useForm({ name: '', email: '', password: '', password_confirmation: '', role: roles[0]?.name ?? '' });

    const startEdit = (user: User) => {
        setShowCreate(false);
        setEditingUser(user);
        edit.setData({ name: user.name, is_active: user.is_active, role: user.roles[0]?.name ?? '' });
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
            onSuccess: () => { setShowCreate(false); create.reset(); },
        });
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
                                        <label className="label">Password</label>
                                        <input type="password" value={create.data.password} onChange={e => create.setData('password', e.target.value)} className="input" required autoComplete="new-password" />
                                        {create.errors.password && <p className="mt-1 text-sm text-destructive">{create.errors.password}</p>}
                                    </div>
                                    <div>
                                        <label className="label">Confirm password</label>
                                        <input type="password" value={create.data.password_confirmation} onChange={e => create.setData('password_confirmation', e.target.value)} className="input" required autoComplete="new-password" />
                                    </div>
                                    <div>
                                        <label className="label">Role</label>
                                        <select value={create.data.role} onChange={e => create.setData('role', e.target.value)} className="select" required>
                                            <option value="" disabled>Select a role…</option>
                                            {roles.map(r => <option key={r.id} value={r.name}>{r.name}</option>)}
                                        </select>
                                        {create.errors.role && <p className="mt-1 text-sm text-destructive">{create.errors.role}</p>}
                                    </div>
                                </div>
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
                                        <select value={edit.data.role} onChange={e => edit.setData('role', e.target.value)} className="select">
                                            {roles.map(r => <option key={r.id} value={r.name}>{r.name}</option>)}
                                        </select>
                                    </div>
                                    <div>
                                        <label className="label">Active</label>
                                        <select value={edit.data.is_active ? '1' : '0'} onChange={e => edit.setData('is_active', e.target.value === '1')} className="select">
                                            <option value="1">Active</option>
                                            <option value="0">Disabled</option>
                                        </select>
                                    </div>
                                </div>
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
