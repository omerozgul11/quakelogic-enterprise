import { Head, useForm } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/Card';
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
                <PageHeader
                    icon={Settings}
                    title="Settings"
                    description="Manage your profile and account security"
                />

                {/* Profile */}
                <form onSubmit={handleProfileSubmit}>
                    <Card className="mb-6">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <User className="h-5 w-5 text-muted-foreground" /> Profile
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <label className="label">Full Name</label>
                                <input type="text" value={profileForm.data.name} onChange={e => profileForm.setData('name', e.target.value)}
                                    className="input" required />
                                {profileForm.errors.name && <p className="mt-1 text-xs text-destructive">{profileForm.errors.name}</p>}
                            </div>
                            <div>
                                <label className="label">Email</label>
                                <input type="email" value={profileForm.data.email} onChange={e => profileForm.setData('email', e.target.value)}
                                    className="input" required />
                                {profileForm.errors.email && <p className="mt-1 text-xs text-destructive">{profileForm.errors.email}</p>}
                            </div>
                            {user.commission_rate_override != null && (
                                <div>
                                    <label className="label">Commission Rate Override</label>
                                    <p className="text-sm text-muted-foreground">{user.commission_rate_override}% (set by administrator)</p>
                                </div>
                            )}
                            <div className="flex items-center justify-end gap-3">
                                {profileForm.recentlySuccessful && (
                                    <p className="text-sm font-medium text-emerald-600">Profile updated successfully.</p>
                                )}
                                <Button type="submit" disabled={profileForm.processing}>
                                    {profileForm.processing ? 'Saving...' : 'Save Profile'}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </form>

                {/* Password */}
                <form onSubmit={handlePasswordSubmit}>
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Lock className="h-5 w-5 text-muted-foreground" /> Change Password
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <label className="label">Current Password</label>
                                <input type="password" value={passwordForm.data.current_password}
                                    onChange={e => passwordForm.setData('current_password', e.target.value)}
                                    className="input" required />
                                {passwordForm.errors.current_password && <p className="mt-1 text-xs text-destructive">{passwordForm.errors.current_password}</p>}
                            </div>
                            <div>
                                <label className="label">New Password</label>
                                <input type="password" value={passwordForm.data.password}
                                    onChange={e => passwordForm.setData('password', e.target.value)}
                                    className="input" required />
                                {passwordForm.errors.password && <p className="mt-1 text-xs text-destructive">{passwordForm.errors.password}</p>}
                            </div>
                            <div>
                                <label className="label">Confirm New Password</label>
                                <input type="password" value={passwordForm.data.password_confirmation}
                                    onChange={e => passwordForm.setData('password_confirmation', e.target.value)}
                                    className="input" required />
                            </div>
                            <div className="flex items-center justify-end gap-3">
                                {passwordForm.recentlySuccessful && (
                                    <p className="text-sm font-medium text-emerald-600">Password changed successfully.</p>
                                )}
                                <Button type="submit" disabled={passwordForm.processing}>
                                    {passwordForm.processing ? 'Changing...' : 'Change Password'}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </AppLayout>
    );
}
