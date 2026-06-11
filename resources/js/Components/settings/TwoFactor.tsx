import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { ShieldCheck, ShieldOff, KeyRound, Loader2, RefreshCw, Copy, Check } from 'lucide-react';

interface Props {
    enabled: boolean;   // a 2FA secret has been generated
    confirmed: boolean; // the user verified a code, so 2FA is active
}

/**
 * Optional time-based one-time-password (TOTP) two-factor authentication, backed
 * by Fortify's endpoints. Flow: Enable → scan QR / enter code to confirm → save
 * recovery codes. Users can disable it again at any time.
 */
export function TwoFactor({ enabled, confirmed }: Props) {
    const [qrSvg, setQrSvg] = useState<string | null>(null);
    const [secret, setSecret] = useState<string | null>(null);
    const [recovery, setRecovery] = useState<string[] | null>(null);
    const [code, setCode] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [copied, setCopied] = useState(false);

    const setupMode = enabled && !confirmed;

    // While in setup mode, load the QR code + secret to display.
    useEffect(() => {
        if (!setupMode) { setQrSvg(null); setSecret(null); return; }
        let active = true;
        Promise.all([
            fetch('/user/two-factor-qr-code', { headers: { Accept: 'application/json' } }).then(r => r.json()),
            fetch('/user/two-factor-secret-key', { headers: { Accept: 'application/json' } }).then(r => r.json()),
        ]).then(([q, s]) => {
            if (!active) return;
            setQrSvg(q.svg ?? null);
            setSecret(s.secretKey ?? null);
        }).catch(() => active && setError('Could not load the setup code. Please try again.'));
        return () => { active = false; };
    }, [setupMode]);

    const enable = () => {
        setBusy(true); setError(null);
        router.post('/user/two-factor-authentication', {}, {
            preserveScroll: true,
            onFinish: () => setBusy(false),
        });
    };

    const confirmCode = () => {
        setBusy(true); setError(null);
        router.post('/user/confirmed-two-factor-authentication', { code }, {
            preserveScroll: true,
            onFinish: () => setBusy(false),
            onSuccess: () => { setCode(''); revealRecovery(); },
            onError: (e) => setError((e as Record<string, string>).code ?? 'That code was invalid. Try again.'),
        });
    };

    const disable = () => {
        if (!window.confirm('Disable two-factor authentication? Your account will rely on password only.')) return;
        setBusy(true);
        router.delete('/user/two-factor-authentication', {
            preserveScroll: true,
            onFinish: () => setBusy(false),
            onSuccess: () => { setRecovery(null); setQrSvg(null); setSecret(null); },
        });
    };

    const revealRecovery = async () => {
        try {
            const codes = await fetch('/user/two-factor-recovery-codes', { headers: { Accept: 'application/json' } }).then(r => r.json());
            setRecovery(Array.isArray(codes) ? codes : null);
        } catch { setError('Could not load recovery codes.'); }
    };

    const regenerate = () => {
        router.post('/user/two-factor-recovery-codes', {}, { preserveScroll: true, onSuccess: () => revealRecovery() });
    };

    const copyRecovery = async () => {
        if (!recovery) return;
        try {
            await navigator.clipboard.writeText(recovery.join('\n'));
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        } catch { /* clipboard unavailable */ }
    };

    return (
        <Card className="mb-6">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <ShieldCheck className="h-5 w-5 text-muted-foreground" /> Two-Factor Authentication
                    {confirmed && <span className="rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs font-semibold text-emerald-600">On</span>}
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                {error && <p className="rounded-lg bg-destructive/10 px-3 py-2 text-sm text-destructive">{error}</p>}

                {/* Off */}
                {!enabled && (
                    <>
                        <p className="text-sm text-muted-foreground">
                            Add an extra layer of security. When enabled, you'll enter a one-time code from an
                            authenticator app (Google Authenticator, Authy, 1Password, …) each time you sign in.
                            This is optional.
                        </p>
                        <Button onClick={enable} disabled={busy} icon={busy ? Loader2 : ShieldCheck}>
                            {busy ? 'Enabling…' : 'Enable two-factor authentication'}
                        </Button>
                    </>
                )}

                {/* Setup: scan + confirm */}
                {setupMode && (
                    <div className="space-y-4">
                        <p className="text-sm text-muted-foreground">
                            Scan this QR code with your authenticator app, then enter the 6-digit code it shows to finish.
                        </p>
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                            <div className="shrink-0 rounded-xl border border-border bg-white p-3" dangerouslySetInnerHTML={{ __html: qrSvg ?? '' }} />
                            <div className="space-y-2">
                                {secret && (
                                    <p className="text-xs text-muted-foreground">
                                        Or enter this key manually:<br />
                                        <code className="mt-1 inline-block rounded bg-secondary px-2 py-1 font-mono text-foreground">{secret}</code>
                                    </p>
                                )}
                                <div>
                                    <label className="label">Verification code</label>
                                    <div className="flex gap-2">
                                        <input
                                            value={code}
                                            onChange={e => setCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
                                            inputMode="numeric"
                                            autoComplete="one-time-code"
                                            placeholder="123456"
                                            className="input w-36 font-mono tracking-widest"
                                        />
                                        <Button onClick={confirmCode} disabled={busy || code.length < 6}>{busy ? 'Confirming…' : 'Confirm'}</Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="button" onClick={disable} className="text-xs font-medium text-muted-foreground hover:text-destructive">
                            Cancel setup
                        </button>
                    </div>
                )}

                {/* Active */}
                {confirmed && (
                    <div className="space-y-4">
                        <p className="flex items-center gap-2 text-sm text-foreground">
                            <ShieldCheck className="h-4 w-4 text-emerald-600" />
                            Two-factor authentication is active on your account.
                        </p>

                        <div className="rounded-xl border border-border p-4">
                            <div className="flex items-center justify-between gap-2">
                                <p className="flex items-center gap-1.5 text-sm font-medium text-foreground"><KeyRound className="h-4 w-4 text-muted-foreground" /> Recovery codes</p>
                                <div className="flex items-center gap-2">
                                    {recovery && (
                                        <button type="button" onClick={copyRecovery} className="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-primary">
                                            {copied ? <Check className="h-3.5 w-3.5 text-emerald-500" /> : <Copy className="h-3.5 w-3.5" />} Copy
                                        </button>
                                    )}
                                    <button type="button" onClick={regenerate} className="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-primary">
                                        <RefreshCw className="h-3.5 w-3.5" /> Regenerate
                                    </button>
                                </div>
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">Store these somewhere safe — each can be used once if you lose your authenticator.</p>
                            {recovery ? (
                                <div className="mt-3 grid grid-cols-2 gap-1.5">
                                    {recovery.map(c => <code key={c} className="rounded bg-secondary px-2 py-1 text-center font-mono text-xs text-foreground">{c}</code>)}
                                </div>
                            ) : (
                                <Button onClick={revealRecovery} variant="secondary" size="sm" className="mt-3">Show recovery codes</Button>
                            )}
                        </div>

                        <Button onClick={disable} disabled={busy} variant="danger" icon={ShieldOff}>
                            {busy ? 'Disabling…' : 'Disable two-factor authentication'}
                        </Button>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
