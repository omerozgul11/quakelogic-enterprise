import { useForm, Head } from '@inertiajs/react';
import { Mail, Lock, ArrowRight } from 'lucide-react';
import { LogoMark } from '@/Components/ui/Logo';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/login');
    };

    return (
        <>
            <Head title="Sign In" />
            <div className="relative flex min-h-screen items-center justify-center overflow-hidden px-4 py-10">
                {/* Ambient, lively backdrop */}
                <div className="animate-float pointer-events-none absolute -top-24 left-[15%] h-80 w-80 rounded-full bg-orange-400/30 blur-3xl" />
                <div className="animate-float pointer-events-none absolute -bottom-20 right-[12%] h-96 w-96 rounded-full bg-amber-400/25 blur-3xl" style={{ animationDelay: '1.6s' }} />
                <div className="animate-float pointer-events-none absolute bottom-1/3 left-1/2 h-72 w-72 rounded-full bg-rose-400/15 blur-3xl" style={{ animationDelay: '0.8s' }} />

                <div className="relative w-full max-w-[26rem] animate-scale-in">
                    {/* Brand */}
                    <div className="mb-7 flex flex-col items-center text-center">
                        <div className="shadow-glow mb-4 rounded-[16px]">
                            <LogoMark size={56} />
                        </div>
                        <h1 className="text-[28px] font-bold tracking-tight text-foreground">Welcome back</h1>
                        <p className="mt-1.5 text-[15px] text-muted-foreground">
                            Sign in to <span className="font-semibold text-foreground">QuakeLogic</span> Enterprise
                        </p>
                    </div>

                    {/* Card */}
                    <div className="card-surface glass p-7 sm:p-8">
                        <form onSubmit={submit} className="space-y-5">
                            <div>
                                <label htmlFor="email" className="label">Email address</label>
                                <div className="relative">
                                    <Mail className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                    <input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={e => setData('email', e.target.value)}
                                        className="input input-with-icon"
                                        placeholder="you@quakelogic.net"
                                        required
                                        autoFocus
                                    />
                                </div>
                                {errors.email && <p className="mt-1.5 text-sm text-destructive">{errors.email}</p>}
                            </div>

                            <div>
                                <div className="mb-1.5 flex items-center justify-between">
                                    <label htmlFor="password" className="label mb-0">Password</label>
                                    <a href="/forgot-password" className="text-sm font-semibold text-primary hover:underline">Forgot?</a>
                                </div>
                                <div className="relative">
                                    <Lock className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                    <input
                                        id="password"
                                        type="password"
                                        value={data.password}
                                        onChange={e => setData('password', e.target.value)}
                                        className="input input-with-icon"
                                        placeholder="••••••••"
                                        required
                                    />
                                </div>
                                {errors.password && <p className="mt-1.5 text-sm text-destructive">{errors.password}</p>}
                            </div>

                            <label className="flex items-center gap-2 text-sm text-muted-foreground">
                                <input
                                    type="checkbox"
                                    checked={data.remember}
                                    onChange={e => setData('remember', e.target.checked)}
                                    className="h-4 w-4 rounded-md border-input text-primary focus:ring-primary/30"
                                />
                                Keep me signed in
                            </label>

                            <button
                                type="submit"
                                disabled={processing}
                                className="bg-brand-gradient shadow-glow group flex w-full items-center justify-center gap-2 rounded-full py-3 px-4 text-sm font-semibold text-white transition-all duration-300 [transition-timing-function:cubic-bezier(0.34,1.4,0.5,1)] hover:-translate-y-0.5 hover:brightness-105 active:translate-y-0 active:scale-[0.99] disabled:opacity-60"
                            >
                                {processing ? 'Signing in…' : 'Sign in'}
                                {!processing && <ArrowRight className="h-4 w-4 transition-transform group-hover:translate-x-0.5" />}
                            </button>
                        </form>
                    </div>

                    <p className="mt-6 text-center text-xs text-muted-foreground">
                        © {new Date().getFullYear()} QuakeLogic Enterprise. All rights reserved.
                    </p>
                </div>
            </div>
        </>
    );
}
