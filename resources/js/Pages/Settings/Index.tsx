import { Head, useForm } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { Settings, Lock, User } from 'lucide-react';

interface Props {
    user: {
        id: number;
        name: string;
        email: string;
        commission_rate_override: number | null;
    };
}

export default function SettingsIndex({ user }: Props) {
    const profileForm = useForm({ name: user.name, email: user.email });
    const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' });

    const handleProfileSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        profileForm.patch('/settings/profile');
    };

    const handlePasswordSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        passwordForm.post('/settings/password', {
            onSuccess: () => passwordForm.reset(),
        });
    };

    return (
        <AppLayout>
            <Head title="Settings" />
            <div className="p-6 max-w-3xl mx-auto">
                <div className="flex items-center gap-2 mb-6">
                    <Settings className="h-6 w-6 text-gray-500" />
                    <h1 className="text-2xl font-bold text-gray-900">Settings</h1>
                </div>

                {/* Profile */}
                <form onSubmit={handleProfileSubmit} className="bg-white rounded-xl border border-gray-200 p-6 mb-6">
                    <h2 className="text-base font-semibold text-gray-900 mb-4 flex items-center gap-2">
                        <User className="h-5 w-5 text-gray-400" /> Profile
                    </h2>
                    <div className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                            <input type="text" value={profileForm.data.name} onChange={e => profileForm.setData('name', e.target.value)}
                                className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" required />
                            {profileForm.errors.name && <p className="text-red-600 text-xs mt-1">{profileForm.errors.name}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" value={profileForm.data.email} onChange={e => profileForm.setData('email', e.target.value)}
                                className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" required />
                            {profileForm.errors.email && <p className="text-red-600 text-xs mt-1">{profileForm.errors.email}</p>}
                        </div>
                        {user.commission_rate_override != null && (
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Commission Rate Override</label>
                                <p className="text-sm text-gray-600">{user.commission_rate_override}% (set by administrator)</p>
                            </div>
                        )}
                        <div className="flex justify-end">
                            <button type="submit" disabled={profileForm.processing}
                                className="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
                                {profileForm.processing ? 'Saving...' : 'Save Profile'}
                            </button>
                        </div>
                    </div>
                    {profileForm.recentlySuccessful && (
                        <p className="text-green-600 text-sm mt-3">Profile updated successfully.</p>
                    )}
                </form>

                {/* Password */}
                <form onSubmit={handlePasswordSubmit} className="bg-white rounded-xl border border-gray-200 p-6">
                    <h2 className="text-base font-semibold text-gray-900 mb-4 flex items-center gap-2">
                        <Lock className="h-5 w-5 text-gray-400" /> Change Password
                    </h2>
                    <div className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                            <input type="password" value={passwordForm.data.current_password}
                                onChange={e => passwordForm.setData('current_password', e.target.value)}
                                className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" required />
                            {passwordForm.errors.current_password && <p className="text-red-600 text-xs mt-1">{passwordForm.errors.current_password}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                            <input type="password" value={passwordForm.data.password}
                                onChange={e => passwordForm.setData('password', e.target.value)}
                                className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" required />
                            {passwordForm.errors.password && <p className="text-red-600 text-xs mt-1">{passwordForm.errors.password}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                            <input type="password" value={passwordForm.data.password_confirmation}
                                onChange={e => passwordForm.setData('password_confirmation', e.target.value)}
                                className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" required />
                        </div>
                        <div className="flex justify-end">
                            <button type="submit" disabled={passwordForm.processing}
                                className="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
                                {passwordForm.processing ? 'Changing...' : 'Change Password'}
                            </button>
                        </div>
                    </div>
                    {passwordForm.recentlySuccessful && (
                        <p className="text-green-600 text-sm mt-3">Password changed successfully.</p>
                    )}
                </form>
            </div>
        </AppLayout>
    );
}
