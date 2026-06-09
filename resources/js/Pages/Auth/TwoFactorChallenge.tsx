import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { KeyRound, ShieldCheck, ArrowRight } from 'lucide-react';
import { Logo } from '@/Components/ui/Logo';

export default function TwoFactorChallenge() {
    const [useRecovery, setUseRecovery] = useState(false);
    const { data, setData, post, processing, errors } = useForm({
        code: '',
        recovery_code: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/two-factor-challenge');
    };

    return (
        <>
            <Head title="Two-Factor Authentication" />
            <div className="relative grid min-h-screen lg:grid-cols-2">
                {/* Left brand panel */}
                <div className="bg-sidebar relative hidden overflow-hidden lg:flex lg:flex-col lg:justify-between lg:p-12">
                    <div className="animate-float absolute -left-24 top-10 h-72 w-72 rounded-full bg-indigo-600/30 blur-3xl" />
                    <div className="animate-float absolute -right-16 bottom-10 h-80 w-80 rounded-full bg-violet-600/20 blur-3xl" style={{ animationDelay: '1.5s' }} />

                    <div className="relative animate-fade-in">
                        <Logo dark />
                    </div>

                    <div className="relative max-w-md animate-rise">
                        <h2 className="text-4xl font-extrabold leading-tight tracking-tight text-white">
                            One more step to keep your account <span className="text-gradient">secure</span>.
                        </h2>
                        <p className="mt-4 text-lg text-slate-300">
                            Two-factor authentication adds an extra layer of protection to your pipeline.
                        </p>
                        <div className="mt-8 flex flex-wrap gap-x-6 gap-y-3 text-sm text-slate-300">
                            {['Opportunity intelligence', 'Capture workflow', 'Proposal management', 'Commission tracking'].map(f => (
                                <span key={f} className="inline-flex items-center gap-2">
                                    <ShieldCheck className="h-4 w-4 text-indigo-400" />
                                    {f}
                                </span>
                            ))}
                        </div>
                    </div>

                    <p className="relative text-xs text-slate-500">© {new Date().getFullYear()} QuakeLogic Enterprise. All rights reserved.</p>
                </div>

                {/* Right form panel */}
                <div className="flex items-center justify-center bg-background p-6 sm:p-12">
                    <div className="w-full max-w-md animate-scale-in">
                        <div className="mb-8 lg:hidden">
                            <Logo />
                        </div>

                        <h1 className="text-2xl font-bold tracking-tight text-foreground">Two-factor authentication</h1>
                        <p className="mt-1.5 text-sm text-muted-foreground">
                            {useRecovery
                                ? 'Enter one of your recovery codes.'
                                : 'Enter the 6-digit code from your authenticator app.'}
                        </p>

                        <form onSubmit={handleSubmit} className="mt-8 space-y-5">
                            {useRecovery ? (
                                <div>
                                    <label htmlFor="recovery_code" className="mb-1.5 block text-sm font-medium text-foreground">Recovery code</label>
                                    <div className="relative">
                                        <KeyRound className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                        <input
                                            id="recovery_code"
                                            type="text"
                                            value={data.recovery_code}
                                            onChange={e => setData('recovery_code', e.target.value)}
                                            className="w-full rounded-xl border border-input bg-card py-2.5 pl-10 pr-3 text-sm font-mono text-foreground transition-all focus:border-primary/40 focus:outline-none focus:ring-4 focus:ring-primary/10"
                                            autoFocus
                                            autoComplete="one-time-code"
                                        />
                                    </div>
                                    {errors.recovery_code && <p className="mt-1.5 text-sm text-destructive">{errors.recovery_code}</p>}
                                </div>
                            ) : (
                                <div>
                                    <label htmlFor="code" className="mb-1.5 block text-sm font-medium text-foreground">Authentication code</label>
                                    <div className="relative">
                                        <KeyRound className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                        <input
                                            id="code"
                                            type="text"
                                            inputMode="numeric"
                                            pattern="[0-9]*"
                                            maxLength={6}
                                            value={data.code}
                                            onChange={e => setData('code', e.target.value)}
                                            className="w-full rounded-xl border border-input bg-card py-2.5 pl-10 pr-3 text-center text-xl font-mono tracking-widest text-foreground transition-all focus:border-primary/40 focus:outline-none focus:ring-4 focus:ring-primary/10"
                                            autoFocus
                                            autoComplete="one-time-code"
                                        />
                                    </div>
                                    {errors.code && <p className="mt-1.5 text-sm text-destructive">{errors.code}</p>}
                                </div>
                            )}

                            <button
                                type="submit"
                                disabled={processing}
                                className="bg-brand-gradient group flex w-full items-center justify-center gap-2 rounded-xl py-2.5 px-4 text-sm font-semibold text-white shadow-glow transition-all hover:opacity-95 active:scale-[0.99] disabled:opacity-60"
                            >
                                {processing ? 'Verifying…' : 'Verify'}
                                {!processing && <ArrowRight className="h-4 w-4 transition-transform group-hover:translate-x-0.5" />}
                            </button>
                        </form>

                        <p className="mt-7 text-center text-sm text-muted-foreground">
                            <button type="button" onClick={() => setUseRecovery(!useRecovery)} className="font-medium text-primary hover:underline">
                                {useRecovery ? 'Use authentication code instead' : 'Use a recovery code instead'}
                            </button>
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}
