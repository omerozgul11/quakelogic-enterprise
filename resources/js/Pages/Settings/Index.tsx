import { Head, useForm, router } from '@inertiajs/react';
import { AppLayout } from '@/Components/layout/AppLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/Card';
import { Select } from '@/Components/ui/Select';
import { TwoFactor } from '@/Components/settings/TwoFactor';
import { Settings, Lock, User, Palette, LayoutDashboard, Bell, Mail, Check } from 'lucide-react';

interface Preferences {
    display: { theme: 'system' | 'light' | 'dark'; density: 'comfortable' | 'compact' };
    dashboard: { default_view: 'personal' | 'executive' };
    channels: { new_proposal: boolean; new_opportunity: boolean; desktop: boolean; sound: boolean };
}

interface Props {
    user: { id: number; name: string; email: string; commission_rate_override: number | null };
    preferences: Preferences;
    twoFactor: { enabled: boolean; confirmed: boolean };
    mailbox: {
        connected: boolean;
        provider: string | null;
        email: string | null;
        from_name: string | null;
        smtp_host: string | null;
        smtp_port: number | null;
        smtp_encryption: string | null;
        smtp_username: string | null;
    };
}

const SMTP_PRESETS: Record<string, { host: string; port: string; enc: string }> = {
    gmail: { host: 'smtp.gmail.com', port: '587', enc: 'tls' },
    office365: { host: 'smtp.office365.com', port: '587', enc: 'tls' },
};

function applyTheme(theme: string) {
    try {
        const el = document.documentElement;
        if (theme === 'system') {
            localStorage.removeItem('theme');
            el.classList.toggle('dark', window.matchMedia('(prefers-color-scheme: dark)').matches);
        } else {
            localStorage.setItem('theme', theme);
            el.classList.toggle('dark', theme === 'dark');
        }
    } catch { /* ignore */ }
}

function Toggle({ checked, onChange, label, hint }: { checked: boolean; onChange: (v: boolean) => void; label: string; hint?: string }) {
    return (
        <button type="button" onClick={() => onChange(!checked)} className="flex w-full items-center justify-between gap-3 rounded-lg px-1 py-2 text-left">
            <span>
                <span className="block text-sm font-medium text-foreground">{label}</span>
                {hint && <span className="block text-xs text-muted-foreground">{hint}</span>}
            </span>
            <span className={`relative h-6 w-11 shrink-0 rounded-full transition-colors ${checked ? 'bg-primary' : 'bg-secondary'}`}>
                <span className={`absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-all ${checked ? 'left-[22px]' : 'left-0.5'}`} />
            </span>
        </button>
    );
}

export default function SettingsIndex({ user, preferences, twoFactor, mailbox }: Props) {
    const profileForm = useForm({ name: user.name, email: user.email });
    const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' });

    const mailboxForm = useForm({
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
        if (p) mailboxForm.setData(d => ({ ...d, smtp_host: p.host, smtp_port: p.port, smtp_encryption: p.enc }));
    };
    const saveMailbox = (e: React.FormEvent) => {
        e.preventDefault();
        mailboxForm.post('/settings/mailbox', { preserveScroll: true, onSuccess: () => mailboxForm.setData('smtp_password', '') });
    };
    const testMailbox = () => router.post('/settings/mailbox/test', {}, { preserveScroll: true });
    const disconnectMailbox = () => { if (confirm('Disconnect your work email?')) router.delete('/settings/mailbox', { preserveScroll: true }); };
    const prefs = useForm<Preferences>(preferences);

    const handleProfileSubmit = (e: React.FormEvent) => { e.preventDefault(); profileForm.patch('/settings/profile'); };
    const handlePasswordSubmit = (e: React.FormEvent) => { e.preventDefault(); passwordForm.put('/settings/password', { onSuccess: () => passwordForm.reset() }); };
    const savePrefs = (e: React.FormEvent) => { e.preventDefault(); prefs.put('/settings/preferences'); };

    const setChannel = (key: keyof Preferences['channels'], v: boolean) => {
        prefs.setData('channels', { ...prefs.data.channels, [key]: v });
        if (key === 'desktop' && v && 'Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
    };

    return (
        <AppLayout>
            <Head title="Settings" />
            <div className="mx-auto max-w-3xl p-6">
                <PageHeader icon={Settings} title="Settings" description="Manage your profile, preferences, and notifications" />

                {/* Profile */}
                <form onSubmit={handleProfileSubmit}>
                    <Card className="mb-6">
                        <CardHeader><CardTitle className="flex items-center gap-2"><User className="h-5 w-5 text-muted-foreground" /> Profile</CardTitle></CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <label className="label">Full Name</label>
                                <input type="text" value={profileForm.data.name} onChange={e => profileForm.setData('name', e.target.value)} className="input" required />
                                {profileForm.errors.name && <p className="mt-1 text-xs text-destructive">{profileForm.errors.name}</p>}
                            </div>
                            <div>
                                <label className="label">Email</label>
                                <input type="email" value={profileForm.data.email} onChange={e => profileForm.setData('email', e.target.value)} className="input" required />
                                {profileForm.errors.email && <p className="mt-1 text-xs text-destructive">{profileForm.errors.email}</p>}
                            </div>
                            {user.commission_rate_override != null && (
                                <div>
                                    <label className="label">Commission Rate Override</label>
                                    <p className="text-sm text-muted-foreground">{user.commission_rate_override}% (set by administrator)</p>
                                </div>
                            )}
                            <div className="flex items-center justify-end gap-3">
                                {profileForm.recentlySuccessful && <p className="text-sm font-medium text-emerald-600">Saved.</p>}
                                <Button type="submit" disabled={profileForm.processing}>{profileForm.processing ? 'Saving...' : 'Save Profile'}</Button>
                            </div>
                        </CardContent>
                    </Card>
                </form>

                {/* Preferences */}
                <form onSubmit={savePrefs}>
                    <Card className="mb-6">
                        <CardHeader><CardTitle className="flex items-center gap-2"><Palette className="h-5 w-5 text-muted-foreground" /> Display &amp; Dashboard</CardTitle></CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div>
                                    <label className="label">Theme</label>
                                    <Select className="w-full" value={prefs.data.display.theme}
                                        onChange={v => { const t = v as Preferences['display']['theme']; prefs.setData('display', { ...prefs.data.display, theme: t }); applyTheme(t); }}
                                        options={[
                                            { value: 'system', label: 'System' },
                                            { value: 'light', label: 'Light' },
                                            { value: 'dark', label: 'Dark' },
                                        ]}
                                    />
                                </div>
                                <div>
                                    <label className="label">Density</label>
                                    <Select className="w-full" value={prefs.data.display.density}
                                        onChange={v => prefs.setData('display', { ...prefs.data.display, density: v as Preferences['display']['density'] })}
                                        options={[
                                            { value: 'comfortable', label: 'Comfortable' },
                                            { value: 'compact', label: 'Compact' },
                                        ]}
                                    />
                                </div>
                                <div>
                                    <label className="label flex items-center gap-1"><LayoutDashboard className="h-3.5 w-3.5" /> Default dashboard</label>
                                    <Select className="w-full" value={prefs.data.dashboard.default_view}
                                        onChange={v => prefs.setData('dashboard', { ...prefs.data.dashboard, default_view: v as Preferences['dashboard']['default_view'] })}
                                        options={[
                                            { value: 'personal', label: 'Personal' },
                                            { value: 'executive', label: 'Executive' },
                                        ]}
                                    />
                                </div>
                            </div>
                            <div className="flex items-center justify-end gap-3">
                                {prefs.recentlySuccessful && <p className="text-sm font-medium text-emerald-600">Saved.</p>}
                                <Button type="submit" disabled={prefs.processing}>{prefs.processing ? 'Saving...' : 'Save Preferences'}</Button>
                            </div>
                        </CardContent>
                    </Card>
                </form>

                {/* Notification channels */}
                <Card className="mb-6">
                    <CardHeader><CardTitle className="flex items-center gap-2"><Bell className="h-5 w-5 text-muted-foreground" /> Notification Channels</CardTitle></CardHeader>
                    <CardContent className="divide-y divide-border">
                        <Toggle label="New proposals" hint="Get alerted when a proposal is created" checked={prefs.data.channels.new_proposal} onChange={v => setChannel('new_proposal', v)} />
                        <Toggle label="New opportunities" hint="Get alerted when an opportunity is added" checked={prefs.data.channels.new_opportunity} onChange={v => setChannel('new_opportunity', v)} />
                        <Toggle label="Desktop notifications" hint="Show a desktop popup when you're away (Windows & Mac)" checked={prefs.data.channels.desktop} onChange={v => setChannel('desktop', v)} />
                        <Toggle label="Notification sound" hint="Play a sound for new notifications" checked={prefs.data.channels.sound} onChange={v => setChannel('sound', v)} />
                        <div className="flex items-center justify-end gap-3 pt-3">
                            <Button type="button" onClick={savePrefs} disabled={prefs.processing}>{prefs.processing ? 'Saving...' : 'Save Channels'}</Button>
                        </div>
                    </CardContent>
                </Card>

                {/* Password */}
                <form onSubmit={handlePasswordSubmit}>
                    <Card>
                        <CardHeader><CardTitle className="flex items-center gap-2"><Lock className="h-5 w-5 text-muted-foreground" /> Change Password</CardTitle></CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <label className="label">Current Password</label>
                                <input type="password" value={passwordForm.data.current_password} onChange={e => passwordForm.setData('current_password', e.target.value)} className="input" required />
                                {passwordForm.errors.current_password && <p className="mt-1 text-xs text-destructive">{passwordForm.errors.current_password}</p>}
                            </div>
                            <div>
                                <label className="label">New Password</label>
                                <input type="password" value={passwordForm.data.password} onChange={e => passwordForm.setData('password', e.target.value)} className="input" required />
                                {passwordForm.errors.password && <p className="mt-1 text-xs text-destructive">{passwordForm.errors.password}</p>}
                            </div>
                            <div>
                                <label className="label">Confirm New Password</label>
                                <input type="password" value={passwordForm.data.password_confirmation} onChange={e => passwordForm.setData('password_confirmation', e.target.value)} className="input" required />
                            </div>
                            <div className="flex items-center justify-end gap-3">
                                {passwordForm.recentlySuccessful && <p className="text-sm font-medium text-emerald-600">Password changed.</p>}
                                <Button type="submit" disabled={passwordForm.processing}>{passwordForm.processing ? 'Changing...' : 'Change Password'}</Button>
                            </div>
                        </CardContent>
                    </Card>
                </form>

                {/* Two-factor authentication (optional) */}
                <div className="mt-6">
                    <TwoFactor enabled={twoFactor.enabled} confirmed={twoFactor.confirmed} />
                </div>

                {/* Work email connection */}
                <Card className="mt-6">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Mail className="h-5 w-5 text-muted-foreground" /> Work Email
                            {mailbox.connected && <span className="rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs font-semibold text-emerald-600">Connected</span>}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {mailbox.connected && (
                            <p className="mb-4 flex items-center gap-2 text-sm text-foreground">
                                <Check className="h-4 w-4 text-emerald-600" />
                                Connected as <span className="font-semibold">{mailbox.email}</span> — follow-ups &amp; your daily digest send from this address.
                            </p>
                        )}
                        <p className="mb-4 text-sm text-muted-foreground">
                            Connect your own work email so proposal follow-ups and your daily opportunity digest send from your
                            address (replies come back to you). Most providers need an <span className="font-medium text-foreground">app password</span>,
                            not your normal login password.
                        </p>

                        <div className="mb-4 flex flex-wrap items-center gap-2">
                            <span className="self-center text-xs font-medium text-muted-foreground">Quick setup:</span>
                            <button type="button" onClick={() => applyPreset('gmail')} className="rounded-full border border-border px-3 py-1 text-xs font-medium transition hover:bg-secondary">Gmail / Workspace</button>
                            <button type="button" onClick={() => applyPreset('office365')} className="rounded-full border border-border px-3 py-1 text-xs font-medium transition hover:bg-secondary">Microsoft 365</button>
                        </div>

                        <form onSubmit={saveMailbox} className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="label">From email *</label>
                                    <input type="email" value={mailboxForm.data.email} onChange={e => mailboxForm.setData('email', e.target.value)} className="input" required />
                                    {mailboxForm.errors.email && <p className="mt-1 text-xs text-destructive">{mailboxForm.errors.email}</p>}
                                </div>
                                <div>
                                    <label className="label">From name</label>
                                    <input type="text" value={mailboxForm.data.from_name} onChange={e => mailboxForm.setData('from_name', e.target.value)} className="input" />
                                </div>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-3">
                                <div className="sm:col-span-2">
                                    <label className="label">SMTP host *</label>
                                    <input type="text" value={mailboxForm.data.smtp_host} onChange={e => mailboxForm.setData('smtp_host', e.target.value)} className="input" placeholder="smtp.gmail.com" required />
                                    {mailboxForm.errors.smtp_host && <p className="mt-1 text-xs text-destructive">{mailboxForm.errors.smtp_host}</p>}
                                </div>
                                <div>
                                    <label className="label">Port *</label>
                                    <input type="number" value={mailboxForm.data.smtp_port} onChange={e => mailboxForm.setData('smtp_port', e.target.value)} className="input" required />
                                </div>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="label">Encryption</label>
                                    <Select
                                        value={mailboxForm.data.smtp_encryption}
                                        onChange={v => mailboxForm.setData('smtp_encryption', v)}
                                        options={[{ value: 'tls', label: 'TLS / STARTTLS (587)' }, { value: 'ssl', label: 'SSL (465)' }, { value: 'none', label: 'None' }]}
                                        className="w-full"
                                    />
                                </div>
                                <div>
                                    <label className="label">Username <span className="font-normal text-muted-foreground">(defaults to your email)</span></label>
                                    <input type="text" value={mailboxForm.data.smtp_username} onChange={e => mailboxForm.setData('smtp_username', e.target.value)} className="input" placeholder={mailboxForm.data.email} />
                                </div>
                            </div>
                            <div>
                                <label className="label">App password {mailbox.connected ? <span className="font-normal text-muted-foreground">(leave blank to keep current)</span> : '*'}</label>
                                <input type="password" value={mailboxForm.data.smtp_password} onChange={e => mailboxForm.setData('smtp_password', e.target.value)} className="input" autoComplete="new-password" placeholder="••••••••••••••••" />
                                {mailboxForm.errors.smtp_password && <p className="mt-1 text-xs text-destructive">{mailboxForm.errors.smtp_password}</p>}
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <Button type="submit" disabled={mailboxForm.processing}>{mailboxForm.processing ? 'Saving…' : mailbox.connected ? 'Update' : 'Connect'}</Button>
                                {mailbox.connected && <Button type="button" variant="secondary" onClick={testMailbox}>Send test email</Button>}
                                {mailbox.connected && <Button type="button" variant="danger" onClick={disconnectMailbox}>Disconnect</Button>}
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
