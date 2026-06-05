import { Head, router, useForm } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { formatDate } from '@/Lib/utils';
import { Users, Shield, Edit } from 'lucide-react';
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
    users: {
        data: User[];
        total: number;
    };
    roles: Array<{ id: number; name: string }>;
}

export default function AdminIndex({ users, roles }: Props) {
    const [editingUser, setEditingUser] = useState<User | null>(null);
    const { data, setData, patch, processing, reset } = useForm({
        name: '',
        is_active: true,
        role: '',
    });

    const startEdit = (user: User) => {
        setEditingUser(user);
        setData({
            name: user.name,
            is_active: user.is_active,
            role: user.roles[0]?.name ?? '',
        });
    };

    const handleSave = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingUser) return;
        patch(`/admin/users/${editingUser.id}`, {
            onSuccess: () => { setEditingUser(null); reset(); },
        });
    };

    return (
        <AppLayout>
            <Head title="Admin — User Management" />
            <div className="p-6">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 flex items-center gap-2">
                            <Shield className="h-6 w-6 text-red-500" />
                            Admin Panel
                        </h1>
                        <p className="text-gray-500 mt-1">{users.total} users</p>
                    </div>
                </div>

                {editingUser && (
                    <form onSubmit={handleSave} className="bg-white rounded-xl border border-blue-200 p-6 mb-6 space-y-4">
                        <h2 className="text-base font-semibold text-gray-900">Edit User: {editingUser.email}</h2>
                        <div className="grid grid-cols-3 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Name</label>
                                <input type="text" value={data.name} onChange={e => setData('name', e.target.value)}
                                    className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" required />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Role</label>
                                <select value={data.role} onChange={e => setData('role', e.target.value)}
                                    className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    {roles.map(r => <option key={r.id} value={r.name}>{r.name}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Active</label>
                                <select value={data.is_active ? '1' : '0'} onChange={e => setData('is_active', e.target.value === '1')}
                                    className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="1">Active</option>
                                    <option value="0">Disabled</option>
                                </select>
                            </div>
                        </div>
                        <div className="flex gap-2">
                            <button type="submit" disabled={processing}
                                className="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">Save</button>
                            <button type="button" onClick={() => setEditingUser(null)}
                                className="px-4 py-2 text-sm border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                        </div>
                    </form>
                )}

                <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <table className="w-full">
                        <thead>
                            <tr className="border-b border-gray-200 bg-gray-50">
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Name</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Email</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Role</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Status</th>
                                <th className="text-left text-xs font-medium text-gray-500 uppercase px-4 py-3">Joined</th>
                                <th className="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {users.data.map(user => (
                                <tr key={user.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3 text-sm font-medium text-gray-900">{user.name}</td>
                                    <td className="px-4 py-3 text-sm text-gray-600">{user.email}</td>
                                    <td className="px-4 py-3">
                                        {user.roles.map(r => (
                                            <span key={r.id} className="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded-full">
                                                {r.name}
                                            </span>
                                        ))}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`text-xs px-2 py-1 rounded-full ${user.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                                            {user.is_active ? 'Active' : 'Disabled'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-sm text-gray-500">{formatDate(user.created_at)}</td>
                                    <td className="px-4 py-3">
                                        <button onClick={() => startEdit(user)} className="text-gray-400 hover:text-gray-600">
                                            <Edit className="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
