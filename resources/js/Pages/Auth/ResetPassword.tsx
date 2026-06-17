import { Head, useForm } from '@inertiajs/react';
import { Mail, Lock, ArrowRight, ShieldCheck } from 'lucide-react';
import { Logo } from '@/Components/ui/Logo';

interface Props {
    token: string;
    email: string;
}

export default function ResetPassword({ token, email }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/reset-password');
    };

    return (
        <>
            <Head title="Reset Password" />
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
                            Secure your account, then get back to <span className="text-gradient">capturing opportunities</span>.
                        </h2>
                        <p className="mt-4 text-lg text-slate-300">
                            Choose a strong new password to keep your pipeline protected.
                        </p>
                        <div className="mt-8 flex flex-wrap gap-x-6 gap-y-3 text-sm text-slate-300">
                            {['Opportunity intelligence', 'Proposal management', 'Commission tracking'].map(f => (
                                <span key={f} className="inline-flex items-center gap-2">
                                    <ShieldCheck className="h-4 w-4 text-indigo-400" />
                                    {f}
                                </span>
                            ))}
                        </div>
                    </div>

                    <p className="relative text-xs text-slate-500">© {new Date().getFullYear()} QuakeLogic Proposals. All rights reserved.</p>
                </div>

                {/* Right form panel */}
                <div className="flex items-center justify-center bg-background p-6 sm:p-12">
                    <div className="w-full max-w-md animate-scale-in">
                        <div className="mb-8 lg:hidden">
                            <Logo />
                        </div>

                        <h1 className="text-2xl font-bold tracking-tight text-foreground">Set a new password</h1>
                        <p className="mt-1.5 text-sm text-muted-foreground">Enter a new password for your account.</p>

                        <form onSubmit={handleSubmit} className="mt-8 space-y-5">
                            <div>
                                <label htmlFor="email" className="mb-1.5 block text-sm font-medium text-foreground">Email address</label>
                                <div className="relative">
                                    <Mail className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                    <input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={e => setData('email', e.target.value)}
                                        className="w-full rounded-xl border border-input bg-card py-2.5 pl-10 pr-3 text-sm text-foreground transition-all focus:border-primary/40 focus:outline-none focus:ring-4 focus:ring-primary/10"
                                        placeholder="you@quakelogic.net"
                                        required
                                    />
                                </div>
                                {errors.email && <p className="mt-1.5 text-sm text-destructive">{errors.email}</p>}
                            </div>

                            <div>
                                <label htmlFor="password" className="mb-1.5 block text-sm font-medium text-foreground">New password</label>
                                <div className="relative">
                                    <Lock className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                    <input
                                        id="password"
                                        type="password"
                                        value={data.password}
                                        onChange={e => setData('password', e.target.value)}
                                        className="w-full rounded-xl border border-input bg-card py-2.5 pl-10 pr-3 text-sm text-foreground transition-all focus:border-primary/40 focus:outline-none focus:ring-4 focus:ring-primary/10"
                                        placeholder="••••••••"
                                        required
                                        autoFocus
                                    />
                                </div>
                                {errors.password && <p className="mt-1.5 text-sm text-destructive">{errors.password}</p>}
                            </div>

                            <div>
                                <label htmlFor="password_confirmation" className="mb-1.5 block text-sm font-medium text-foreground">Confirm password</label>
                                <div className="relative">
                                    <Lock className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                    <input
                                        id="password_confirmation"
                                        type="password"
                                        value={data.password_confirmation}
                                        onChange={e => setData('password_confirmation', e.target.value)}
                                        className="w-full rounded-xl border border-input bg-card py-2.5 pl-10 pr-3 text-sm text-foreground transition-all focus:border-primary/40 focus:outline-none focus:ring-4 focus:ring-primary/10"
                                        placeholder="••••••••"
                                        required
                                    />
                                </div>
                            </div>

                            <button
                                type="submit"
                                disabled={processing}
                                className="bg-brand-gradient group flex w-full items-center justify-center gap-2 rounded-xl py-2.5 px-4 text-sm font-semibold text-white shadow-glow transition-all hover:opacity-95 active:scale-[0.99] disabled:opacity-60"
                            >
                                {processing ? 'Resetting…' : 'Reset password'}
                                {!processing && <ArrowRight className="h-4 w-4 transition-transform group-hover:translate-x-0.5" />}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </>
    );
}
