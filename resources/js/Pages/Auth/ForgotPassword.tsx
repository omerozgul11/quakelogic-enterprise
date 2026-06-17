import { Head, Link, useForm } from '@inertiajs/react';
import { Mail, ArrowRight, ShieldCheck } from 'lucide-react';
import { Logo } from '@/Components/ui/Logo';

interface Props {
    status?: string;
}

export default function ForgotPassword({ status }: Props) {
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/forgot-password');
    };

    return (
        <>
            <Head title="Forgot Password" />
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
                            Back to winning <span className="text-gradient">government contracts</span> in no time.
                        </h2>
                        <p className="mt-4 text-lg text-slate-300">
                            Reset your password and pick up right where you left off.
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

                        <h1 className="text-2xl font-bold tracking-tight text-foreground">Reset your password</h1>
                        <p className="mt-1.5 text-sm text-muted-foreground">Enter your email and we'll send you a reset link.</p>

                        {status && (
                            <div className="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">
                                {status}
                            </div>
                        )}

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
                                        autoFocus
                                    />
                                </div>
                                {errors.email && <p className="mt-1.5 text-sm text-destructive">{errors.email}</p>}
                            </div>

                            <button
                                type="submit"
                                disabled={processing}
                                className="bg-brand-gradient group flex w-full items-center justify-center gap-2 rounded-xl py-2.5 px-4 text-sm font-semibold text-white shadow-glow transition-all hover:opacity-95 active:scale-[0.99] disabled:opacity-60"
                            >
                                {processing ? 'Sending…' : 'Send reset link'}
                                {!processing && <ArrowRight className="h-4 w-4 transition-transform group-hover:translate-x-0.5" />}
                            </button>
                        </form>

                        <p className="mt-7 text-center text-sm text-muted-foreground">
                            <Link href="/login" className="font-medium text-primary hover:underline">Back to sign in</Link>
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}
